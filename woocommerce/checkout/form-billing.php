<?php
/**
 * Checkout billing information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-billing.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 * @global WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

$ar_billing_fields_first = array(
	'billing_address_1', 
	'billing_city', 
	'billing_postcode', 
	'billing_country',
	'billing_company',
	'billing_address_1',
	'billing_address_2',
	'billing_floor',
	'billing_apartment', 
);

$chosen_methods 	= WC()->session->get( 'chosen_shipping_methods' );
$chosen_shipping 	= $chosen_methods[0];
$local_pickup_chosen = ($chosen_shipping && strstr($chosen_shipping, 'local_pickup'));


$is_shipping_to_other_address = 0;
if ( isset( $_COOKIE['oc_shipping_to_other_address'] ) && $_COOKIE['oc_shipping_to_other_address'] != 0 ){
	$is_shipping_to_other_address = $_COOKIE['oc_shipping_to_other_address'];
}
?>
<?php if ( ! is_user_logged_in() ) : ?>
	<div class="checkout-login">
		<span><?php _e( 'Was here previous?', 'oc-main-theme' ) ?> </span>
		<a class="my-account-link login-panel" href="#" rel="nofollow">
			<strong><?php _e( 'Click for sign in', 'oc-main-theme' );?></strong>
		</a>
	</div>
<?php endif; ?>

<div class="woocommerce-billing-fields">
	<?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ) : ?>

		<h2 class="col-title"><?php esc_html_e( 'Billing &amp; Shipping', 'oc-main-theme' ); ?></h2>

	<?php else : ?>

		<h2 class="col-title"><?php esc_html_e( 'Billing details', 'oc-main-theme' ); ?></h2>

	<?php endif; ?>

	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="woocommerce-billing-fields__field-wrapper">
		<?php
		$fields = $checkout->get_checkout_fields( 'billing' );

		foreach ( $fields as $key => $field ) {
			if ( !in_array( $key, $ar_billing_fields_first ) ){
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
		}
		?>
	</div>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<div class="ship-method">

	<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
		<div class="woocommerce-account-fields">
			<?php if ( ! $checkout->is_registration_required() ) : ?>

				<p class="form-row form-row-wide create-account">
					<span class="open-account-text"><?php _e( 'Open account for quick order next time', 'oc-main-theme' ); ?></span>
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></span>
					</label>
				</p>

			<?php endif; ?>

			<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

			<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>

				<div class="create-account">
					<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
					<?php endforeach; ?>
					<div class="clear"></div>
				</div>

			<?php endif; ?>

			<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
		</div>
	<?php endif;?>
<?php /*if ( ! is_user_logged_in() ) : ?>
	<?php //if ( $checkout->is_registration_enabled() ){ 
	?>
		<div class="open-registration-panel">	
			<a href="#" rel="nofollow" class="open-registration btn-empty"><?php _e( 'Open account for quick order next time', 'oc-main-theme' ) ?></a>
		</div>
	<?php //} ?>
<?php endif; */?>

	<!-- only for displaying  -->
	<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
		<?php wc_cart_totals_shipping_html(); ?>
	<?php endif; ?>
</div>

<div class="diff-address-shipping <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>">	
	<h2 id="ship-to-different-address">
		<span><?php esc_html_e( 'Ship to a different address?', 'oc-main-theme' ); ?></span>
	</h2>
	<h3>
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input id="ship-to-different-address-checkbox" style="display:none" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="ship_to_different_address" value="1" <?php checked( '1', $is_shipping_to_other_address ) ?> />
			<span class="custom-checkbox shipping-to-different-address" tabindex="0"></span>
			<span><?php _e( 'Ship to someone other', 'oc-main-theme' ) ?></span> 
			<input type="hidden" class="hidden shipping-to-other-address" id="shipping-to-other-address" name="oc_theme_ship_to_different_address" value="<?php echo $is_shipping_to_other_address ?>"  />	
		</label>
	</h3>
</div>

<div class="woocommerce-billing-fields__field-wrapper billing-fields-shipping-data <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>">
	<?php
	$fields = $checkout->get_checkout_fields( 'billing' );
	foreach ( $fields as $key => $field ) {
		if ( in_array( $key, $ar_billing_fields_first ) ){
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
	}
	?>
</div>
