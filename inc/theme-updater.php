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
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
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
}

new Deliz_Short_Theme_Updater();
