<?php
/**
 * Free-shipping progress for floating cart (OC Advanced / Local pickup / WC Free Shipping).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// דיבוג: ב-wp-config.php לפני require wp-settings: define( 'DELIZ_FREE_SHIP_BAR_DEBUG', true );
// קובץ לוג: wp-content/deliz-free-ship-bar-debug.log (בנוסף ל-error_log אם מוגדר).
if ( ! defined( 'DELIZ_FREE_SHIP_BAR_DEBUG' ) ) {
	define( 'DELIZ_FREE_SHIP_BAR_DEBUG', false );
}

/**
 * @return bool
 */
function deliz_short_free_ship_bar_debug_is_enabled() {
	$on = defined( 'DELIZ_FREE_SHIP_BAR_DEBUG' ) && DELIZ_FREE_SHIP_BAR_DEBUG;
	return (bool) apply_filters( 'deliz_short_free_ship_bar_debug', $on );
}

/**
 * @param string $step תיאור השלב.
 * @param mixed  ...$vars משתנים ל-var_dump.
 */
function deliz_short_free_ship_bar_debug( $step, ...$vars ) {
	if ( ! deliz_short_free_ship_bar_debug_is_enabled() ) {
		return;
	}
	$blocks = array();
	foreach ( $vars as $i => $v ) {
		ob_start();
		var_dump( $v );
		$blocks[] = 'arg_' . $i . ' => ' . ob_get_clean();
	}
	$text = '[deliz_free_ship_bar] ' . $step . "\n" . implode( "\n", $blocks );
	error_log( $text );
	if ( defined( 'WP_CONTENT_DIR' ) && is_string( WP_CONTENT_DIR ) ) {
		$log_file = rtrim( WP_CONTENT_DIR, '/\\' ) . '/deliz-free-ship-bar-debug.log';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug file
		@file_put_contents( $log_file, gmdate( 'Y-m-d H:i:s \U\T\C ' ) . preg_replace( '/\s+/', ' ', $text ) . "\n", FILE_APPEND | LOCK_EX );
	}
	// הערת HTML — מוצגת בתוך #ed-float-cart (במקור דף או בתוך מחרוזת ה-fragment ב-AJAX).
	echo "\n<!-- deliz_free_ship_bar ";
	echo esc_html( $step );
	echo " | ";
	echo esc_html( str_replace( array( "\r", "\n", '--' ), array( ' ', ' ', '–' ), $text ) );
	echo " -->\n";
}

/**
 * Cart subtotal basis aligned with OC Advanced Shipping free-shipping check.
 *
 * @param WC_Cart $cart Cart.
 * @return float
 */
function deliz_short_cart_total_for_free_shipping_progress( $cart ) {
	if ( ! $cart ) {
		return 0.0;
	}
	$total = (float) $cart->get_displayed_subtotal();
	if ( $cart->display_prices_including_tax() ) {
		$total -= (float) $cart->get_discount_tax();
	}
	$total -= (float) $cart->get_discount_total();
	return round( $total, wc_get_price_decimals() );
}

/**
 * @param string $method Rate id e.g. oc_woo_advanced_shipping_method:3.
 * @return bool
 */
function deliz_short_is_oc_advanced_shipping_chosen( $method ) {
	return $method === '' || strpos( $method, 'oc_woo_advanced_shipping_method' ) === 0;
}

/**
 * @param string $method Rate id.
 * @return bool
 */
function deliz_short_is_oc_local_pickup_chosen( $method ) {
	return strpos( $method, 'oc_woo_local_pickup_method' ) === 0;
}

/**
 * @param string $json_option Serialized price_depending JSON.
 * @return float|null Lowest cart value that yields zero shipping cost.
 */
function deliz_short_ocws_lowest_zero_shipping_threshold_from_json( $json_option ) {
	if ( empty( $json_option ) || ! is_string( $json_option ) ) {
		return null;
	}
	$schema = json_decode( $json_option, true );
	if ( ! is_array( $schema ) || empty( $schema['active'] ) || empty( $schema['rules'] ) || ! is_array( $schema['rules'] ) ) {
		return null;
	}
	$best = null;
	foreach ( $schema['rules'] as $rule ) {
		if ( ! isset( $rule['cart_value'], $rule['shipping_price'] ) ) {
			continue;
		}
		if ( floatval( $rule['shipping_price'] ) > 0.00001 ) {
			continue;
		}
		$cv = floatval( $rule['cart_value'] );
		if ( $cv <= 0 ) {
			continue;
		}
		if ( null === $best || $cv < $best ) {
			$best = $cv;
		}
	}
	return $best;
}

/**
 * @param mixed $loc Value from get_location_code_by Post data / polygon.
 * @return bool
 */
function deliz_short_ocws_is_valid_bar_location_code( $loc ) {
	if ( null === $loc || false === $loc || '' === $loc ) {
		return false;
	}
	if ( 0 === $loc || '0' === $loc ) {
		return false;
	}
	return true;
}

/**
 * Resolve OCWS delivery location code (city / polygon) from session + customer.
 *
 * @return string|int|null
 */
function deliz_short_ocws_resolve_location_code_for_bar() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		deliz_short_free_ship_bar_debug( 'resolve_location: RETURN null — no WC/session' );
		return null;
	}
	if ( function_exists( 'ocws_use_google_cities_and_polygons' ) && ocws_use_google_cities_and_polygons() ) {
		$popup      = WC()->session->get( 'popup_location_code' );
		$city_code  = WC()->session->get( 'chosen_city_code', '' );
		$coords_raw = WC()->session->get( 'chosen_address_coords', '' );
		if ( '' === (string) $coords_raw && function_exists( 'WC' ) && WC()->checkout() ) {
			$coords_raw = (string) WC()->checkout()->get_value( 'billing_address_coords' );
		}
		deliz_short_free_ship_bar_debug(
			'resolve_location: polygon mode session',
			array(
				'popup_location_code'   => $popup,
				'chosen_city_code'      => $city_code,
				'chosen_address_coords' => $coords_raw,
			)
		);
		if ( ! empty( $popup ) ) {
			return $popup;
		}
		// אותה לוגיקה כמו OC_Woo_Shipping_Polygon::get_location_code_by_post_data — ChIJ… לא תמיד בטבלת הערים, אבל קואורדינטות → find_matching_polygon.
		if ( class_exists( 'OC_Woo_Shipping_Polygon' ) ) {
			$post_data = array(
				'billing_city_code'      => $city_code,
				'billing_address_coords' => $coords_raw,
			);
			$loc = null;
			if ( is_multisite() && is_callable( array( 'OC_Woo_Shipping_Polygon', 'get_location_code_by_post_data_network' ) ) ) {
				$loc = OC_Woo_Shipping_Polygon::get_location_code_by_post_data_network( $post_data );
			} elseif ( is_callable( array( 'OC_Woo_Shipping_Polygon', 'get_location_code_by_post_data' ) ) ) {
				$loc = OC_Woo_Shipping_Polygon::get_location_code_by_post_data( $post_data );
			}
			if ( deliz_short_ocws_is_valid_bar_location_code( $loc ) ) {
				deliz_short_free_ship_bar_debug( 'resolve_location: RETURN from get_location_code_by_post_data*', $loc );
				return $loc;
			}
		}
		deliz_short_free_ship_bar_debug( 'resolve_location: RETURN null — polygon mode, no code resolved' );
		return null;
	}
	$city = WC()->session->get( 'chosen_shipping_city', '' );
	if ( ! $city && WC()->customer ) {
		$city = WC()->customer->get_billing_city();
	}
	deliz_short_free_ship_bar_debug(
		'resolve_location: city mode',
		array(
			'chosen_shipping_city' => WC()->session->get( 'chosen_shipping_city', '' ),
			'billing_city_fallback' => WC()->customer ? WC()->customer->get_billing_city() : '',
			'resolved'              => $city,
		)
	);
	return $city ? $city : null;
}

/**
 * Free shipping threshold for OC home delivery (group option or price-depending 0₪ tier).
 *
 * @return float|null
 */
function deliz_short_free_shipping_threshold_oc_advanced() {
	if ( ! class_exists( 'OC_Woo_Shipping_Group_Data_Store' ) || ! class_exists( 'OC_Woo_Shipping_Group_Option' ) ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN null — missing OC classes' );
		return null;
	}
	$location_code = deliz_short_ocws_resolve_location_code_for_bar();
	if ( null === $location_code || '' === $location_code ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN null — empty location_code' );
		return null;
	}
	$data_store = new OC_Woo_Shipping_Group_Data_Store();
	$group_id   = $data_store->get_group_by_location( $location_code );
	deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: group_id for location', array( 'location' => $location_code, 'group_id' => $group_id ) );
	if ( null === $group_id ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN null — no group' );
		return null;
	}
	$data = OC_Woo_Shipping_Group_Option::get_option( $group_id, 'min_total_for_free_shipping', false );
	$t    = isset( $data['option_value'] ) ? floatval( $data['option_value'] ) : 0.0;
	if ( $t > 0 ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN min_total_for_free_shipping', $t );
		return $t;
	}
	$loc_pd = OC_Woo_Shipping_Group_Option::get_location_option( $location_code, $group_id, 'price_depending', '' );
	$from   = deliz_short_ocws_lowest_zero_shipping_threshold_from_json( $loc_pd['option_value'] ?? '' );
	if ( null !== $from && $from > 0 ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN location price_depending', $from );
		return $from;
	}
	$grp_pd = OC_Woo_Shipping_Group_Option::get_option( $group_id, 'price_depending', '' );
	$from   = deliz_short_ocws_lowest_zero_shipping_threshold_from_json( $grp_pd['option_value'] ?? '' );
	if ( null !== $from && $from > 0 ) {
		deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN group price_depending', $from );
		return $from;
	}
	deliz_short_free_ship_bar_debug( 'threshold_oc_advanced: RETURN null — no threshold' );
	return null;
}

/**
 * Pickup: threshold from affiliate option min_total_for_free_pickup (when configured).
 *
 * @return float|null
 */
function deliz_short_free_shipping_threshold_oc_pickup() {
	if ( ! class_exists( 'OCWS_LP_Affiliate_Option' ) || ! WC()->session ) {
		deliz_short_free_ship_bar_debug( 'threshold_pickup: RETURN null — no class or session' );
		return null;
	}
	$aff_id = (int) WC()->session->get( 'chosen_pickup_aff', 0 );
	deliz_short_free_ship_bar_debug( 'threshold_pickup: chosen_pickup_aff', $aff_id );
	if ( $aff_id <= 0 ) {
		deliz_short_free_ship_bar_debug( 'threshold_pickup: RETURN null — aff_id 0' );
		return null;
	}
	$opt = OCWS_LP_Affiliate_Option::get_option( $aff_id, 'min_total_for_free_pickup', false );
	$t   = isset( $opt->option_value ) ? floatval( $opt->option_value ) : 0.0;
	deliz_short_free_ship_bar_debug( 'threshold_pickup: min_total_for_free_pickup raw', $t );
	return $t > 0 ? $t : null;
}

/**
 * WooCommerce core Free Shipping method instance min_amount.
 *
 * @param string $method e.g. free_shipping:12.
 * @return float|null
 */
function deliz_short_free_shipping_threshold_wc_instance( $method ) {
	if ( ! preg_match( '/^free_shipping:(\d+)$/', $method, $m ) ) {
		deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: RETURN null — method not matched', $method );
		return null;
	}
	$instance_id = absint( $m[1] );
	$opts        = get_option( 'woocommerce_free_shipping_' . $instance_id . '_settings', array() );
	deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: instance settings', array( 'instance_id' => $instance_id, 'opts' => $opts ) );
	if ( ! is_array( $opts ) ) {
		return null;
	}
	if ( empty( $opts['requires'] ) || 'coupon' === $opts['requires'] ) {
		deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: RETURN null — requires coupon or empty' );
		return null;
	}
	if ( ! in_array( $opts['requires'], array( 'min_amount', 'either', 'both' ), true ) ) {
		deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: RETURN null — requires not min_amount', $opts['requires'] ?? '' );
		return null;
	}
	if ( empty( $opts['min_amount'] ) ) {
		deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: RETURN null — no min_amount' );
		return null;
	}
	$amt = floatval( wc_format_decimal( $opts['min_amount'] ) );
	deliz_short_free_ship_bar_debug( 'threshold_wc_free_shipping: RETURN amount', $amt );
	return $amt;
}

/**
 * Data for floating cart free-shipping bar template.
 *
 * @param WC_Cart|null $cart Cart.
 * @return array{show:bool,percent:int,reached:bool,remaining:float,label_html:string,threshold:float,current:float}
 */
function deliz_short_get_free_shipping_bar_data( $cart = null ) {
	deliz_short_free_ship_bar_debug( 'get_bar_data: ENTER' );

	$empty = array(
		'show'       => false,
		'percent'    => 0,
		'reached'    => false,
		'remaining'  => 0.0,
		'label_html' => '',
		'threshold'  => 0.0,
		'current'    => 0.0,
	);
	if ( ! function_exists( 'WC' ) ) {
		deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN empty — WC missing' );
		return $empty;
	}
	if ( ! $cart || ! $cart instanceof WC_Cart ) {
		deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN empty — invalid cart', $cart );
		return $empty;
	}
	if ( $cart->is_empty() ) {
		deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN empty — cart is_empty' );
		return $empty;
	}

	foreach ( $cart->get_applied_coupons() as $code ) {
		try {
			$coupon = new WC_Coupon( $code );
			if ( $coupon->get_free_shipping() ) {
				$cur = deliz_short_cart_total_for_free_shipping_progress( $cart );
				deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN coupon free shipping', array( 'code' => $code, 'current' => $cur ) );
				return array(
					'show'       => true,
					'percent'    => 100,
					'reached'    => true,
					'remaining'  => 0.0,
					'label_html' => esc_html__( 'מגיע לך משלוח חינם!', 'deliz-short' ),
					'threshold'  => $cur,
					'current'    => $cur,
				);
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	$current = deliz_short_cart_total_for_free_shipping_progress( $cart );
	$method  = '';
	if ( WC()->session ) {
		$chosen = WC()->session->get( 'chosen_shipping_methods', array() );
		$method = isset( $chosen[0] ) ? (string) $chosen[0] : '';
	}
	deliz_short_free_ship_bar_debug(
		'get_bar_data: after coupons',
		array(
			'current_subtotal_basis' => $current,
			'chosen_shipping_methods' => WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array(),
			'method_primary'          => $method,
		)
	);

	$threshold = null;
	$branch    = '';
	if ( deliz_short_is_oc_local_pickup_chosen( $method ) ) {
		$branch    = 'pickup';
		$threshold = deliz_short_free_shipping_threshold_oc_pickup();
	} elseif ( strpos( $method, 'free_shipping:' ) === 0 ) {
		$branch    = 'wc_free_shipping';
		$threshold = deliz_short_free_shipping_threshold_wc_instance( $method );
	} elseif ( deliz_short_is_oc_advanced_shipping_chosen( $method ) ) {
		$branch    = 'oc_advanced';
		$threshold = deliz_short_free_shipping_threshold_oc_advanced();
	} else {
		deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN empty — shipping method not pickup/advanced/free_shipping', $method );
		return $empty;
	}
	deliz_short_free_ship_bar_debug( 'get_bar_data: branch + threshold before legacy', array( 'branch' => $branch, 'threshold' => $threshold ) );

	if ( ( null === $threshold || $threshold <= 0 ) && '' === $method ) {
		$legacy = get_option( 'woocommerce_free_shipping_1_settings' );
		deliz_short_free_ship_bar_debug( 'get_bar_data: legacy free_shipping_1 attempt', $legacy );
		if ( is_array( $legacy ) && isset( $legacy['min_amount'] ) ) {
			$threshold = floatval( $legacy['min_amount'] );
		}
	}

	if ( null === $threshold || $threshold <= 0 ) {
		deliz_short_free_ship_bar_debug( 'get_bar_data: RETURN empty — threshold still null or <= 0', $threshold );
		return $empty;
	}

	$reached   = $current >= $threshold - 0.00001;
	$percent   = $reached ? 100 : (int) min( 100, max( 0, round( ( $current / $threshold ) * 100 ) ) );
	$remaining = $reached ? 0.0 : max( 0.0, $threshold - $current );
	if ( $reached ) {
		$label = esc_html__( 'מגיע לך משלוח חינם!', 'deliz-short' );
	} else {
		$price_plain = html_entity_decode( wp_strip_all_tags( wc_price( $remaining ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$price_plain = preg_replace( '/\s+/u', ' ', trim( $price_plain ) );
		$label         = esc_html( sprintf( __( 'עוד %s למשלוח חינם', 'deliz-short' ), $price_plain ) );
	}

	deliz_short_free_ship_bar_debug(
		'get_bar_data: RETURN success',
		array(
			'percent'   => $percent,
			'reached'   => $reached,
			'threshold' => $threshold,
			'current'   => $current,
			'remaining' => $remaining,
		)
	);

	return array(
		'show'       => true,
		'percent'    => $percent,
		'reached'    => $reached,
		'remaining'  => $remaining,
		'label_html' => $label,
		'threshold'  => $threshold,
		'current'    => $current,
	);
}

/**
 * סל צף — החלפה: בהדר (במקום ה-chip לשעבר) רק בר משלוח חינם.
 * בפוטר (במקום הבר לשעבר) — chip דרך deliz_short_float_cart_footer_shipping.
 *
 * בתבנית: do_action( 'deliz_short_float_cart_header_shipping' );
 * ה-HTML של #ocws-delivery-data-chip: Oc_Woo_Shipping_Public::show_chip_in_cart()
 * (plugins/oc-woo-shipping/public/class-oc-woo-shipping-public.php).
 */
function deliz_short_float_cart_render_free_shipping_bar() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$cart = WC()->cart;
	if ( ! function_exists( 'deliz_short_get_free_shipping_bar_data' ) ) {
		return;
	}
	$ed_free_ship_bar = deliz_short_get_free_shipping_bar_data( $cart );
	if ( function_exists( 'deliz_short_free_ship_bar_debug' ) ) {
		deliz_short_free_ship_bar_debug( 'float_cart_header: נתוני בר', $ed_free_ship_bar );
	}
	if ( empty( $ed_free_ship_bar['show'] ) ) {
		if ( function_exists( 'deliz_short_free_ship_bar_debug' ) ) {
			deliz_short_free_ship_bar_debug( 'float_cart_header: בר לא מוצג (show ריק)' );
		}
		return;
	}
	set_query_var( 'deliz_free_ship_bar', $ed_free_ship_bar );
	get_template_part( 'template-parts/free-shipping-bar' );
}

/**
 * @return void
 */
function deliz_short_float_cart_render_ocws_delivery_chip() {
	if ( class_exists( 'Oc_Woo_Shipping_Public' ) && is_callable( array( 'Oc_Woo_Shipping_Public', 'show_chip_in_cart' ) ) ) {
		Oc_Woo_Shipping_Public::show_chip_in_cart();
	}
}

//add_action( 'deliz_short_float_cart_header_shipping', 'deliz_short_float_cart_render_free_shipping_bar', 10 );
add_action( 'deliz_short_float_cart_footer_shipping', 'deliz_short_float_cart_render_ocws_delivery_chip', 10 );
