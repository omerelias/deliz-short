<?php
/**
 * Checkout shipping information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-shipping.php.
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

 
$chosen_methods 	= WC()->session->get( 'chosen_shipping_methods' );
$chosen_shipping 	= $chosen_methods[0];
$local_pickup_chosen = ($chosen_shipping && strstr($chosen_shipping, 'local_pickup'));

?>
<div class="checkout-block checkout-block--shipping is-closed <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>" data-block="shipping" aria-expanded="false">
	<div class="checkout-block__header">
		<h3 class="checkout-block__title"><?php esc_html_e( 'משלוח', 'deliz-short' ); ?></h3>
		<button type="button" class="checkout-block__edit"><?php esc_html_e( 'עריכה', 'deliz-short' ); ?></button>
		<span class="checkout-block__icon">▼</span>
	</div>
	<div class="checkout-block__summary">
		<?php
		// Get shipping method
		$chosen_methods = WC()->session->get('chosen_shipping_methods');
		$chosen_shipping = $chosen_methods[0] ?? '';
		$packages = WC()->shipping()->get_packages();
		$shipping_label = __('משלוח', 'woocommerce');
		
		foreach ($packages as $i => $package) {
			$chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
			if ($chosen_method != '' && isset($package['rates'][$chosen_method])) {
				$shipping_label = $package['rates'][$chosen_method]->get_label();
				break;
			}
		}
		
		// Get address - check if shipping to different address
		$is_shipping_to_other = isset($_COOKIE['oc_shipping_to_other_address']) && $_COOKIE['oc_shipping_to_other_address'] != 0;
		
		if ($is_shipping_to_other) {
			// Shipping address
			$address_city_code = $checkout->get_value('shipping_city');
			$address_street = $checkout->get_value('shipping_street');
			$address_house_num = $checkout->get_value('shipping_house_num');
			$address_address_1 = $checkout->get_value('shipping_address_1');
		} else {
			// Billing address
			$address_city_code = $checkout->get_value('billing_city');
			$address_street = $checkout->get_value('billing_street');
			$address_house_num = $checkout->get_value('billing_house_num');
			$address_address_1 = $checkout->get_value('billing_address_1');
		}
		
		// Convert city code to city name if needed (for oc-woo-shipping plugin)
		$address_city = $address_city_code;
		if (function_exists('ocws_get_city_title') && $address_city_code) {
			// Check if it's a code (numeric or hash)
			if (is_numeric($address_city_code) || (function_exists('ocws_is_hash') && ocws_is_hash($address_city_code))) {
				$city_name = ocws_get_city_title($address_city_code);
				if ($city_name) {
					$address_city = $city_name;
				}
			} else {
				// Try to get from select option text if it's a select field
				// This will be handled by JS for dynamic updates
				$address_city = $address_city_code;
			}
		}
		
		// Clean address_1 - remove English city names
		$cleaned_address_1 = $address_address_1;
		if ($cleaned_address_1) {
			$english_cities = array('Rishon LeZion', 'Rishon LeZiyyon', 'Tel Aviv', 'Jerusalem', 'Haifa');
			foreach ($english_cities as $city) {
				$cleaned_address_1 = str_ireplace($city, '', $cleaned_address_1);
			}
			$cleaned_address_1 = trim($cleaned_address_1);
		}
		
		// Build address string
		$address_parts = array();
		if ($address_street) $address_parts[] = $address_street;
		if ($address_house_num) $address_parts[] = $address_house_num;
		if ($cleaned_address_1 && !$address_street) $address_parts[] = $cleaned_address_1; // Fallback if no street
		if ($address_city) $address_parts[] = $address_city;
		
		$address_string = !empty($address_parts) ? implode(' ', $address_parts) : '';
		
		// Check if shipping or pickup method
		$is_pickup = false;
		$is_shipping_method = false;
		if ($chosen_shipping) {
			if (strpos($chosen_shipping, 'local_pickup') !== false || strpos($chosen_shipping, 'oc_woo_local_pickup_method') !== false) {
				$is_pickup = true;
			} elseif (strpos($chosen_shipping, 'oc_woo_advanced_shipping_method') !== false) {
				$is_shipping_method = true;
			}
		}
		
		// Get shipping/pickup date and time info
		$date_time_info = '';
		if ($is_pickup && class_exists('OCWS_LP_Pickup_Info')) {
			$pickup_info = OCWS_LP_Pickup_Info::get_pickup_info();
			if (!empty($pickup_info['date'])) {
				$weekday = '';
				if (function_exists('ocws_get_day_of_week')) {
					$weekday = ocws_get_day_of_week($pickup_info['date']);
				}
				$date_time_info = '<br><strong>' . __('סניף', 'ocws') . ':</strong> ' . esc_html($pickup_info['aff_name'] ?? '');
				$date_time_info .= '<br><strong>' . __('תאריך', 'ocws') . ':</strong> ' . ($weekday ? esc_html($weekday . ', ') : '') . esc_html($pickup_info['date']);
				if (!empty($pickup_info['slot_start'])) {
					$show_dates_only = get_option('ocws_lp_common_show_dates_only', '') == 1;
					if (!$show_dates_only) {
						$show_slot_start_only = get_option('ocws_lp_common_show_slot_start_only', '') == 1;
						if ($show_slot_start_only) {
							$date_time_info .= '<br><strong>' . __('שעה', 'ocws') . ':</strong> ' . esc_html($pickup_info['slot_start']);
						} else {
							$date_time_info .= '<br><strong>' . __('שעה', 'ocws') . ':</strong> ' . esc_html($pickup_info['slot_start']) . ' - ' . esc_html($pickup_info['slot_end'] ?? '');
						}
					}
				}
			}
		} elseif ($is_shipping_method && class_exists('OC_Woo_Shipping_Info')) {
			$shipping_info = OC_Woo_Shipping_Info::get_shipping_info();
			if (!empty($shipping_info['date'])) {
				$weekday = '';
				if (function_exists('ocws_get_day_of_week')) {
					$weekday = ocws_get_day_of_week($shipping_info['date']);
				}
				$date_time_info = '<br><strong>' . __('תאריך', 'ocws') . ':</strong> ' . ($weekday ? esc_html($weekday . ', ') : '') . esc_html($shipping_info['date']);
				if (!empty($shipping_info['slot_start'])) {
					$show_dates_only = get_option('ocws_common_show_dates_only', '') == 1;
					if (!$show_dates_only) {
						$date_time_info .= '<br><strong>' . __('שעה', 'ocws') . ':</strong> ' . esc_html($shipping_info['slot_start']) . ' - ' . esc_html($shipping_info['slot_end'] ?? '');
					}
				}
			}
		}
		
		// Display
		if ($shipping_label && $shipping_label != __('משלוח', 'woocommerce')) {
			echo '<strong>' . esc_html($shipping_label) . '</strong>';
		}
		if ($address_string) {
			if ($shipping_label && $shipping_label != __('משלוח', 'woocommerce')) {
				echo '<br>';
			}
			echo esc_html($address_string);
		}
		if ($date_time_info) {
			echo $date_time_info;
		}
		if (!$shipping_label && !$address_string && !$date_time_info) {
			echo esc_html__('לא נבחר משלוח', 'deliz-short');
		}
		?>
	</div>
	<div class="checkout-block__content">
		<!-- Shipping Methods -->
		<div class="ship-method">
			<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
				<div class="woocommerce-account-fields">
					<?php if ( ! $checkout->is_registration_required() ) : ?>
						<p class="form-row form-row-wide create-account">
							<span class="open-account-text"><?php _e( 'Open account for quick order next time', 'deliz-short' ); ?></span>
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
			
			<!-- Shipping methods display -->
			<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
				<?php wc_cart_totals_shipping_html(); ?>
			<?php endif; ?>
		</div>
		
		<!-- Ship to different address checkbox -->
		<?php 
		$checkout_style = apply_filters('ocws_checkout_page_style', 'regular');
		$is_shipping_to_other_address = 0;
		if ( isset( $_COOKIE['oc_shipping_to_other_address'] ) && $_COOKIE['oc_shipping_to_other_address'] != 0 ){
			$is_shipping_to_other_address = $_COOKIE['oc_shipping_to_other_address'];
		}
		?>
		<div class="diff-address-shipping <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>">
			<h2 id="ship-to-different-address">
				<span><?php esc_html_e( 'Ship to a different address?', 'deliz-short' ); ?></span>
			</h2>
			<h3>
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input id="ship-to-different-address-checkbox" style="display:none" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="ship_to_different_address" value="1" <?php checked( '1', $is_shipping_to_other_address ) ?> />
					<span class="custom-checkbox shipping-to-different-address"></span>
					<span><?php _e( 'Ship to someone other', 'deliz-short' ) ?></span>
					<input type="hidden" class="hidden shipping-to-other-address" id="shipping-to-other-address" name="oc_theme_ship_to_different_address" value="<?php echo $is_shipping_to_other_address ?>"  />
				</label>
			</h3>
		</div>
		
		<?php if ($checkout_style == 'deli') { ?>
			<?php do_action('ocws_delivery_data_deli_style'); ?>
			<div class="other-recipient-fields">
				<?php do_action('ocws_send_to_other_person_fields'); ?>
			</div>
		<?php } ?>
		
		<?php if ($checkout_style == 'regular') { ?>
			<div class="other-recipient-fields">
				<?php do_action('ocws_send_to_other_person_fields'); ?>
			</div>
		<?php } ?>
		
		<!-- Billing address fields for shipping (when not shipping to different address) -->
		<?php 
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
		?>
		<div class="woocommerce-billing-fields__field-wrapper woocommerce-billing-fields-part-2 billing-fields-shipping-data-1 <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>">
			<?php
			$fields = $checkout->get_checkout_fields( 'billing' );
			foreach ( $fields as $key => $field ) {
				if ( in_array( $key, $ar_billing_fields_first ) ){
					woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
				}
			}
			?>
		</div>
		
		<!-- Shipping address fields -->
		<div class="woocommerce-shipping-fields">
	<?php if ( true === WC()->cart->needs_shipping_address() ) : ?>
<?php /*
		<h3 id="ship-to-different-address">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input id="ship-to-different-address-checkbox" style="display:none" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" /> <span><?php esc_html_e( 'Ship to a different address?', 'woocommerce' ); ?></span>
			</label>
		</h3>
*/ ?>
		<div class="shipping_address">

			<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

			<div class="woocommerce-shipping-fields__field-wrapper">
				<?php
				$fields = $checkout->get_checkout_fields( 'shipping' );

				foreach ( $fields as $key => $field ) {
					woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
				}
				?>
			</div>

			<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>

		</div>

	<?php endif; ?>
		</div>
		
		<!-- Shipping/Pickup date and time fields -->
		<?php 
		// Render shipping additional fields (for oc_woo_advanced_shipping_method)
		// This includes #oc-woo-shipping-additional with slot-list-container
		do_action( 'woocommerce_after_checkout_billing_form', $checkout ); 
		?>
	</div>
</div>
<div class="checkout-block checkout-block--notes is-closed" data-block="notes" aria-expanded="false">
	<div class="checkout-block__header">
		<h3 class="checkout-block__title"><?php esc_html_e( 'הערות למשלוח', 'deliz-short' ); ?></h3>
		<button type="button" class="checkout-block__edit"><?php esc_html_e( 'עריכה', 'deliz-short' ); ?></button>
		<span class="checkout-block__icon">▼</span>
	</div>
	<div class="checkout-block__summary">
		<?php
		$order_notes = $checkout->get_value('order_comments');
		echo $order_notes ? esc_html(wp_trim_words($order_notes, 10)) : esc_html__('אין הערות', 'deliz-short');
		?>
	</div>
	<div class="checkout-block__content">
		<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>

		<div class="woocommerce-additional-fields__field-wrapper">
			<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
		</div>
	</div>
</div>
