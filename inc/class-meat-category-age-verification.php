<?php
/**
 * Age verification checkbox on checkout when the cart contains products
 * from product categories marked as requiring 18+ confirmation.
 *
 * @package meat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Meat_Category_Age_Verification
 */
class Meat_Category_Age_Verification { 

	const TERM_META_KEY = '_meat_requires_age_verification';

	const CHECKOUT_FIELD = 'oc_alcohol_age_verification';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'product_cat_add_form_fields', array( $this, 'add_category_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_field' ), 10, 2 );
		add_action( 'created_product_cat', array( $this, 'save_category_field' ), 10, 2 );
		add_action( 'edited_product_cat', array( $this, 'save_category_field' ), 10, 2 );

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_checkout_checkbox' ), 9 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_checkbox' ) );
	}

	/**
	 * @param WP_Term $term Term being edited.
	 */
	public function edit_category_field( $term ) {
		$checked = get_term_meta( $term->term_id, self::TERM_META_KEY, true ) === 'yes';
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="meat_requires_age_verification"><?php esc_html_e( 'אימות גיל (18+)', 'woocommerce' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="meat_requires_age_verification" id="meat_requires_age_verification" value="1" <?php checked( $checked ); ?> />
					<?php esc_html_e( 'מוצרים בקטגוריה זו דורשים אישור מעל גיל 18 בצ\'קאאוט.', 'woocommerce' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	public function add_category_field() {
		?>
		<div class="form-field">
			<label for="meat_requires_age_verification"><?php esc_html_e( 'אימות גיל (18+)', 'woocommerce' ); ?></label>
			<label>
				<input type="checkbox" name="meat_requires_age_verification" id="meat_requires_age_verification" value="1" />
				<?php esc_html_e( 'מוצרים בקטגוריה זו דורשים אישור מעל גיל 18 בצ\'קאאוט.', 'woocommerce' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * @param int $term_id Term ID.
	 */
	public function save_category_field( $term_id ) {
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		if ( isset( $_POST['action'] ) && 'editedtag' === $_POST['action'] ) {
			check_admin_referer( 'update-tag_' . $term_id );
		} elseif ( isset( $_POST['action'] ) && 'add-tag' === $_POST['action'] ) {
			check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
		} else {
			return;
		}
		if ( isset( $_POST['meat_requires_age_verification'] ) && '1' === $_POST['meat_requires_age_verification'] ) {
			update_term_meta( $term_id, self::TERM_META_KEY, 'yes' );
		} else {
			delete_term_meta( $term_id, self::TERM_META_KEY );
		}
	}

	/**
	 * Whether any cart line is in a category (or its ancestors) that requires age verification.
	 */
	public function cart_requires_age_verification() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$term_ids   = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				continue;
			}

			foreach ( $term_ids as $tid ) {
				$chain = array_merge( array( (int) $tid ), get_ancestors( (int) $tid, 'product_cat' ) );
				foreach ( $chain as $check_id ) {
					if ( get_term_meta( $check_id, self::TERM_META_KEY, true ) === 'yes' ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public function render_checkout_checkbox() {
		if ( ! $this->cart_requires_age_verification() ) {
			return;
		}

		woocommerce_form_field(
			self::CHECKOUT_FIELD,
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row alcohol-age-verification' ),
				'label'    => __( 'אני מאשר/ת כי אני מעל גיל 18 ומורשה לרכוש מוצרי אלכוהול.', 'woocommerce' ),
				'required' => true,
			)
		);
	}

	public function validate_checkout_checkbox() {
		if ( ! $this->cart_requires_age_verification() ) {
			return;
		}

		if ( empty( $_POST[ self::CHECKOUT_FIELD ] ) ) {
			wc_add_notice( __( 'עליך לאשר כי אתה מעל גיל 18 ומורשה לרכוש מוצרי אלכוהול כדי להמשיך בתשלום.', 'woocommerce' ), 'error' );
		}
	}
}
