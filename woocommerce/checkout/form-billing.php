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

$checkout_style = apply_filters('ocws_checkout_page_style', 'regular');

$ar_billing_fields_first = array(
	'billing_google_autocomplete',
	'billing_address_1',
	'billing_city',
	'billing_postcode',
	'billing_country',
	'billing_company',
	'billing_address_1',
	'billing_address_2',
	'billing_street',
	'billing_house_num',
	'billing_enter_code',
	'billing_floor',
	'billing_apartment',
);

do_action( 'ocws_maybe_fix_shipping_method' );

$chosen_methods 	= WC()->session->get( 'chosen_shipping_methods' );
$chosen_shipping 	= $chosen_methods[0];
$local_pickup_chosen = ($chosen_shipping && strstr($chosen_shipping, 'local_pickup'));


$is_shipping_to_other_address = 0;
if ( isset( $_COOKIE['oc_shipping_to_other_address'] ) && $_COOKIE['oc_shipping_to_other_address'] != 0 ){
	$is_shipping_to_other_address = $_COOKIE['oc_shipping_to_other_address'];
}
?>

<div class="checkout-block checkout-block--billing is-open" data-block="billing" aria-expanded="true">
	<div class="checkout-block__header">
		<h3 class="checkout-block__title"><?php esc_html_e( 'פרטי המזמין', 'deliz-short' ); ?></h3>
		<button type="button" class="checkout-block__edit"><?php esc_html_e( 'עריכה', 'deliz-short' ); ?></button>
		<span class="checkout-block__icon">▼</span>
	</div>
	<div class="checkout-block__summary">
		<?php
		$billing_name = $checkout->get_value('billing_first_name') . ' ' . $checkout->get_value('billing_last_name');
		$billing_phone = $checkout->get_value('billing_phone');
		$billing_email = $checkout->get_value('billing_email');
		$billing_city_code = $checkout->get_value('billing_city');
		$billing_street = $checkout->get_value('billing_street');
		$billing_house_num = $checkout->get_value('billing_house_num');
		$billing_address_1 = $checkout->get_value('billing_address_1');
		
		// Convert city code to Hebrew name if needed
		$billing_city = $billing_city_code;
		if (function_exists('ocws_get_city_title') && $billing_city_code) {
			if (is_numeric($billing_city_code) || (function_exists('ocws_is_hash') && ocws_is_hash($billing_city_code))) {
				$city_name = ocws_get_city_title($billing_city_code);
				if ($city_name) {
					$billing_city = $city_name;
				}
			}
		}
		
		// Build address - use street and house_num if available, otherwise address_1
		// Remove "Rishon LeZion" or any English city names from address_1
		$address_parts = array();
		if ($billing_street) {
			$address_parts[] = $billing_street;
		}
		if ($billing_house_num) {
			$address_parts[] = $billing_house_num;
		}
		if ($billing_address_1 && !$billing_street) {
			// Remove common English city names from address_1
			$cleaned_address = $billing_address_1;
			$english_cities = array('Rishon LeZion', 'Rishon LeZiyyon', 'Tel Aviv', 'Jerusalem', 'Haifa');
			foreach ($english_cities as $city) {
				$cleaned_address = str_ireplace($city, '', $cleaned_address);
			}
			$cleaned_address = trim($cleaned_address);
			if ($cleaned_address) {
				$address_parts[] = $cleaned_address;
			}
		}
		if ($billing_city) {
			$address_parts[] = $billing_city;
		}
		$billing_address = implode(' ', $address_parts);
		
		if ($billing_name || $billing_phone || $billing_email || $billing_address) {
			if ($billing_name) echo '<strong>' . esc_html(trim($billing_name)) . '</strong><br>';
			if ($billing_phone) echo esc_html($billing_phone) . '<br>';
			if ($billing_email) echo esc_html($billing_email) . '<br>';
			if ($billing_address) {
				echo esc_html(trim($billing_address));
			}
		} else {
			echo esc_html__('לא הוזנו פרטים', 'deliz-short');
		}
		?>
	</div>
	<div class="checkout-block__content">
		<div class="woocommerce-billing-fields">
	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="checkout-login">
			<span><?php _e( 'Was here previous?', 'deliz-short' ) ?> </span>
			<a class="my-account-link login-panel" href="#" rel="nofollow">
				<strong><?php _e( 'Click for sign in', 'deliz-short' );?></strong>
			</a>
		</div>
	<?php endif; ?>
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<?php
	/*if (isset(WC()->session) && ocws_use_google_cities_and_polygons()) {
		$checkout_session_data = WC()->session->get('checkout_data', array());
		$city_code = WC()->checkout->get_value( 'billing_city_code' );
		$coords = WC()->checkout->get_value( 'billing_address_coords' );
		if (empty($city_code) || empty($coords)) {
			if (!empty($checkout_session_data)) {
				$checkout_session_data['billing_google_autocomplete'] = '';
				$checkout_session_data['billing_city'] = '';
				$checkout_session_data['billing_address_1'] = '';
				$checkout_session_data['billing_address_2'] = '';
				$checkout_session_data['billing_floor'] = '';
				$checkout_session_data['billing_apartment'] = '';
				$checkout_session_data['billing_enter_code'] = '';
				$checkout_session_data['billing_street'] = '';
				$checkout_session_data['billing_house_num'] = '';
				$checkout_session_data['billing_postcode'] = '';
				WC()->session->set('checkout_data', $checkout_session_data);
				WC()->session->save_data();
			}
		}
	}*/
	?>

	<div class="woocommerce-billing-fields__field-wrapper woocommerce-billing-fields-part-1">
		<?php
		$fields = $checkout->get_checkout_fields( 'billing' );

		foreach ( $fields as $key => $field ) {
			if ( !in_array( $key, $ar_billing_fields_first ) ){
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
		}
		?>
	</div>

	<?php //do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
		</div>
	</div>
</div>
