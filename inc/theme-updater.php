<?php
/**
 * GitHub-based updater for the deliz-short theme.
 *
 * Hooks into WordPress' native theme-update flow and announces new releases
 * published on GitHub. When the user visits Dashboard → Updates (or cron
 * runs the twice-daily check), WordPress calls `pre_set_site_transient_update_themes`
 * and this class injects an "update available" row if the latest GitHub
 * release is newer than the installed Version: in style.css.
 *
 * Repo: https://github.com/omerelias/deliz-short  (public)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Deliz_Short_Theme_Updater {

	const GITHUB_USER   = 'omerelias';
	const GITHUB_REPO   = 'deliz-short';
	const CACHE_KEY     = 'deliz_short_gh_release';
	const CACHE_TTL     = 6 * HOUR_IN_SECONDS; // be nice to GitHub's rate limit
	const THEME_SLUG    = 'deliz-short';       // must match the folder name

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'check_for_update' ] );
		add_filter( 'upgrader_source_selection',            [ $this, 'fix_source_dir' ], 10, 4 );
		add_action( 'upgrader_process_complete',            [ $this, 'flush_cache' ], 10, 2 );
		add_action( 'admin_post_deliz_short_force_check',   [ $this, 'handle_force_check' ] );
		add_action( 'admin_notices',                        [ $this, 'render_check_button' ] );
	}

	/**
	 * Inject our update row into the WP update transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->get_remote_release();
		if ( ! $remote ) {
			return $transient;
		}

		$theme          = wp_get_theme( self::THEME_SLUG );
		$installed_ver  = $theme->exists() ? $theme->get( 'Version' ) : '0.0.0';
		$remote_ver     = ltrim( $remote['tag'], 'vV' );

		if ( version_compare( $remote_ver, $installed_ver, '>' ) ) {
			$transient->response[ self::THEME_SLUG ] = [
				'theme'       => self::THEME_SLUG,
				'new_version' => $remote_ver,
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => $remote['zip'],
			];
		} else {
			$transient->no_update[ self::THEME_SLUG ] = [
				'theme'       => self::THEME_SLUG,
				'new_version' => $remote_ver,
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => '',
			];
		}

		return $transient;
	}

	/**
	 * Fetch the latest GitHub release (cached).
	 *
	 * @return array|false ['tag' => string, 'zip' => string]
	 */
	private function get_remote_release() {
		// When WP's native "Check Again" button is clicked (or our custom button),
		// bypass our local cache so the admin gets a truly fresh answer.
		$force = ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$api = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);

		$res = wp_remote_get( $api, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			],
		] );

		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			// short negative cache so a GitHub outage doesn't hammer every admin load
			set_site_transient( self::CACHE_KEY, false, 15 * MINUTE_IN_SECONDS );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, false, 15 * MINUTE_IN_SECONDS );
			return false;
		}

		// Prefer an uploaded .zip asset (clean build) if one exists.
		// Otherwise fall back to GitHub's auto-generated source zipball.
		$zip = $body['zipball_url'];
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] )
					 && substr( $asset['name'], -4 ) === '.zip' ) {
					$zip = $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = [
			'tag' => $body['tag_name'],
			'zip' => $zip,
		];

		set_site_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * GitHub zips extract to something like `omerelias-deliz-short-abc1234/`.
	 * WordPress expects the folder to be `deliz-short/`, otherwise it treats
	 * the upgrade as a brand-new theme and the site loses its active theme.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $args = [] ) {
		global $wp_filesystem;

		if ( ! is_object( $upgrader ) || ! isset( $args['theme'] ) ) {
			return $source;
		}
		if ( $args['theme'] !== self::THEME_SLUG ) {
			return $source;
		}
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . self::THEME_SLUG . '/';
		if ( trailingslashit( $source ) === $corrected ) {
			return $source;
		}

		if ( $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $corrected ) ) ) {
			return $corrected;
		}

		return $source;
	}

	/**
	 * Clear the release cache after a successful update so a re-check picks up
	 * the new state immediately (no stale "update available" row).
	 */
	public function flush_cache( $upgrader, $data ) {
		if ( empty( $data['type'] ) || $data['type'] !== 'theme' ) {
			return;
		}
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Handle the dedicated "Check now" button: clear our cache + the WP
	 * theme-update transient, then bounce back to the updates page where
	 * WP will immediately re-run the check and show the result.
	 */
	public function handle_force_check() {
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'deliz-short' ) );
		}
		check_admin_referer( 'deliz_short_force_check' );

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_themes' );

		wp_safe_redirect( add_query_arg(
			[ 'deliz_short_checked' => '1' ],
			self_admin_url( 'update-core.php?force-check=1' )
		) );
		exit;
	}

	/**
	 * Show a notice with a "Check deliz-short updates now" button on the
	 * updates screen only. Also shows installed vs. latest-seen versions
	 * for quick sanity checks.
	 */
	public function render_check_button() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'update-core' ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) ) {
			return;
		}

		$theme         = wp_get_theme( self::THEME_SLUG );
		$installed_ver = $theme->exists() ? $theme->get( 'Version' ) : '—';
		$cached        = get_site_transient( self::CACHE_KEY );
		$remote_ver    = ( is_array( $cached ) && ! empty( $cached['tag'] ) )
			? ltrim( $cached['tag'], 'vV' )
			: __( 'לא נבדק עדיין', 'deliz-short' );

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=deliz_short_force_check' ),
			'deliz_short_force_check'
		);

		$just_checked = ! empty( $_GET['deliz_short_checked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="notice notice-info" style="padding:14px 18px;">
			<h3 style="margin:0 0 6px;">תבנית deliz-short - בדיקת עדכונים מ-GitHub</h3>
			<p style="margin:4px 0;">
				<strong>גרסה מותקנת:</strong> <?php echo esc_html( $installed_ver ); ?>
				&nbsp;|&nbsp;
				<strong>גרסה אחרונה ב-GitHub (מה-cache):</strong> <?php echo esc_html( $remote_ver ); ?>
			</p>
			<p style="margin:10px 0 0;">
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
					בדוק עדכונים עכשיו
				</a>
				<?php if ( $just_checked ) : ?>
					<span style="color:#1d7c2a;margin-inline-start:10px;">✓ נבדק ונוקה cache. אם יש גרסה חדשה היא תופיע מתחת.</span>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}

new Deliz_Short_Theme_Updater();
