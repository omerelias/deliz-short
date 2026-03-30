<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//chackout fields
add_filter( 'woocommerce_checkout_fields', 'oc_theme_woo_add_checkout_fields', 200 );
function oc_theme_woo_add_checkout_fields( $fields ){
	// hide fields from billing form
	$ar_hidden_billing_fields = array(
		'billing_country',
		'billing_company',
		'billing_postcode',
	);

	// additional fields for shipping
	$ar_shipping_fields = array(
		'shipping_floor' 		=> __( 'Floor', 	 'woocommerce' ),
		'shipping_apartment'	=> __( 'Appartment', 'woocommerce' ),		
	);

	// additional fields for billing
	$ar_billing_fields = array(
		'billing_floor' 		=> __( 'Floor', 	 'woocommerce' ),
		'billing_apartment'		=> __( 'Appartment', 'woocommerce' ),	
	);

	$chosen_methods 	 = WC()->session->get( 'chosen_shipping_methods' );
  	$chosen_shipping 	 = $chosen_methods[0];
  	$local_pickup_chosen = ($chosen_shipping && strstr($chosen_shipping, 'local_pickup'));

	$i 			= 0;
	$priority 	= 70;
	foreach ( $ar_billing_fields as $field_key => $field_val ){
		$is_odd = $i % 2 == 0;
		$class 	= ( $is_odd ) ? 'form-row-first' : 'form-row-last';
		$args_field = array(
			'required' 	=> 1,
			'label' 	=> $field_val,
			'class' 	=> array( $class ),
			'priority'  => $priority
		);
		// add new fields
		$fields['billing'][$field_key] = $args_field;
		$i++;
		$priority = $priority + 10;		
	}

	$i 			= 0;
	$priority 	= 70;
	foreach ( $ar_shipping_fields as $field_key => $field_val ){
		$is_odd = $i % 2 == 0;
		$class 	= ( $is_odd ) ? 'form-row-first' : 'form-row-last';
		$args_field = array(
			'required' 	=> 1,
			'label' 	=> $field_val,
			'class' 	=> array( $class ),
			'priority'  => $priority
		);

		// $args = array_merge( $ar_default_args, $args_field );
		// add new fields
		$fields['shipping'][$field_key] = $args_field;
		$i++;
		$priority = $priority + 10;
	}

	// formatted( $ar_hidden_billing_fields, 'ar_hidden_billing_fields BEFORE !' );

	// formatted( $ar_hidden_billing_fields, 'ar_hidden_billing_fields' );
	// hide some fields as Country, postalcode , e.t.c.
	foreach ( $ar_hidden_billing_fields as $field_name => $field_val ){
		$fields['billing'][ $field_val ]['class'][] = 'field-hidden';
	}

	// Change fields classes and labels
	$fields['billing']['billing_phone']['class'][] 		= 'form-row-first';	
	$fields['billing']['billing_address_1']['class'][] 	= 'form-row-first';	
	$fields['billing']['billing_email']['class'][] 		= 'form-row-last';
	$fields['billing']['billing_address_2']['class'][] 	= 'form-row-last';
	// $fields['billing']['billing_address_2']['label'] 	= __( 'Floor', 	 'woocommerce' );
    $fields['billing']['billing_city']['label'] = __( 'עיר', 'woocommerce' );
	$fields['billing']['billing_address_1']['label'] = __( 'רחוב ומספר בית', 'woocommerce' );
	$fields['billing']['billing_address_2']['label'] = __( "מספר דירה", 'woocommerce' );
	$fields['billing']['billing_floor']['label'] = __( 'קומה', 'woocommerce' );
	$fields['billing']['billing_apartment']['label'] = __( 'מספר דירה', 'woocommerce' );

    $fields['billing']['billing_city']['priority'] = '1';
    $fields['billing']['billing_address_1']['priority'] = '2';
    $fields['billing']['billing_address_2']['priority'] = '3';
    $fields['billing']['billing_floor']['priority'] = '4';
    $fields['billing']['billing_apartment']['priority'] = '5';

	unset( $fields['billing']['billing_address_1']['placeholder'] );
	unset( $fields['billing']['billing_address_2']['placeholder'] );
	unset( $fields['billing']['billing_floor']['placeholder'] );
	$fields['billing']['billing_floor']['required'] = 0;
	$fields['billing']['billing_apartment']['required'] = 0;
	$fields['shipping']['shipping_floor']['required'] = 0;
	$fields['shipping']['shipping_apartment']['required'] = 0;		

    if ( isset( $_POST['ship_to_different_address'] ) || $local_pickup_chosen ){
  		$fields['billing']['billing_address_1']['required'] 	= 0;
  		$fields['billing']['billing_city']['required'] 			= 0;
		//$fields['billing']['billing_floor']['required'] = 0;
		//$fields['billing']['billing_apartment']['required'] = 0;
  	}
	return $fields;
}

########
// save  custom fields to woo session
add_action( 'woocommerce_checkout_process', 'oc_save_custom_checkout_fields' );
function oc_save_custom_checkout_fields(){
	$ar_addiional_fields = array(
		'billing_floor',
		'billing_apartment',
		'shipping_floor',
		'shipping_apartment',
	);

	$checkout_data = WC()->session->get( 'checkout_data' );
	foreach ( $ar_addiional_fields as $additional_field ){
    	$field_value 		= isset($_POST[$additional_field]) ? sanitize_text_field($_POST[$additional_field]) : '';
    	if ( $field_value ){
			$checkout_data[ $additional_field ] = $field_value;	
    	}
	}
	WC()->session->set( 'checkout_data', $checkout_data );
}

#############################################

// get custom fields value !
// doent work!
add_filter( 'woocommerce_checkout_get_value', 'oc_change_checkout_field', 100, 2 );
function oc_change_checkout_field( $field_value, $field_name ){
	if ( isset( $_POST['post_data'] ) ) {
		parse_str( $_POST['post_data'], $post_data );
	} else {
		$post_data = $_POST; // fallback for final checkout (non-ajax)
	}

	$ar_new_fields = array(
		'billing_floor',
		'billing_apartment',
		'shipping_floor',
		'shipping_apartment',
	);

	if ( in_array( $field_name , $ar_new_fields ) ){
		$checkout_data = WC()->session->get( 'checkout_data' );
		if ( $checkout_data ){
			$field_value = $checkout_data[ $field_name ];
		}
	}
	return $field_value;
}

add_action('woocommerce_before_shop_loop_item_title', function () {
  global $product;

  if ( ! $product instanceof WC_Product ) return;

  // מציג רק כשהמוצר לא במלאי
  if ( ! $product->is_in_stock() ) {
    echo '<span class="badge-oos">' . esc_html__( 'זמנית אזל המלאי', 'deliz-short' ) . '</span>';
  }
}, 10);

// פותח wrapper לפני התמונה
add_action('woocommerce_before_shop_loop_item_title', function () {
  echo '<div class="loop-thumb-wrap">';
}, 9);

// סוגר wrapper אחרי התמונה (ה-thumbnail מודפס ב-10)
add_action('woocommerce_before_shop_loop_item_title', function () {
  echo '</div>';
}, 11);

add_action('woocommerce_shop_loop_item_title', function () {
  echo '<div class="loop-bottom-wrap">';
}, 9);

// סוגר wrapper אחרי התמונה (ה-thumbnail מודפס ב-10)
add_action('woocommerce_after_shop_loop_item', function () {
  echo '</div>';
}, 15);

// additional fields to register form
add_action( 'woocommerce_register_form_start', 'oc_woo_additional_register_fields_start' );
function oc_woo_additional_register_fields_start(){
	$additional_fields = true;
	if ( $additional_fields ){
		woocommerce_form_field(
			'user_first_name',
			array(
				'type'        => 'text',
				'required'    => true, // just adds an "*"
				'label'       => __( 'First name', 'woocommerce' ),
				'class' 	  => array( 'woocommerce-form-row' )
				// 'label'       => __( 'שם פרטי', 'woocommerce' )
			),
			( isset( $_POST[ 'user_first_name' ] ) ? $_POST[ 'user_first_name' ] : '' )
		);

		woocommerce_form_field(
			'user_last_name',
			array(
				'type'        => 'text',
				'required'    => true, // just adds an "*"
				'label'       => __( 'Last name', 'woocommerce' ),
				'class' 	  => array( 'woocommerce-form-row' ),
				// 'label'       => __( 'שם משפחה', 'woocommerce' )
			),
			( isset( $_POST[ 'user_last_name' ] ) ? $_POST[ 'user_last_name' ] : '' )
		);
	}
}

// additional fields
add_action( 'woocommerce_register_form', 'oc_woo_additional_register_fields_end' );
function oc_woo_additional_register_fields_end(){
?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_password_check"><?php esc_html_e( 'Submit password', 'woocommerce'); ?>&nbsp;<span class="required">*</span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_check" id="reg_password_check" autocomplete="new-password-check" />
	</p>
<?php
}

add_filter( 'woocommerce_registration_errors', 'oc_theme_woo_validate_register_form' , 10, 3 );
function oc_theme_woo_validate_register_form( $validation_errors, $username, $email ) {
    
    if ( isset( $_POST['user_first_name'] ) && empty( $_POST['user_first_name'] ) ) {
        $validation_errors->add( 'user_first_name_error', __( '<strong>Error</strong>: First name is required!', 'woocommerce' ) );
    }

    if ( isset( $_POST['user_last_name'] ) && empty( $_POST['user_last_name'] ) ) {
        $validation_errors->add( 'user_last_name_error', __( '<strong>Error</strong>: Last name is required!', 'woocommerce' ) );
    }

    return $validation_errors;
}

// save additional fields on user register
add_action( 'user_register', 'oc_user_save_account_data' );
function oc_user_save_account_data( $user_id ) {
    if ( isset( $_POST['user_first_name'] ) ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['user_first_name'] ) );
        update_user_meta( $user_id, 'billing_first_name', sanitize_text_field( $_POST['user_first_name'] ) );
    }

    if ( isset( $_POST['user_last_name'] ) ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['user_last_name'] ) );
        update_user_meta( $user_id, 'billing_last_name', sanitize_text_field( $_POST['user_last_name'] ) );
    }
}

// Remove "Downloads" from My Account menu (WooCommerce)
add_filter('woocommerce_account_menu_items', function ($items) {
    unset($items['downloads']); // removes "הורדות"
    return $items;
}, 99 );

// Last price popup html  
add_action('wp_footer', 'oc_last_price_popup');
function oc_last_price_popup() {
    ?>
		<!-- Modal -->
		<div class="modal fade" id="lastPricePop" tabindex="-1" aria-labelledby="lastPricePopLabel" aria-hidden="true">
      <div class="modal-ovelay"></div>
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="lastPricePop"><?php the_field('weight_pop_title', 'option'); ?></h5>
						<button class="close">
              <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" role="presentation" viewBox="0 0 16 14">
                <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
              </svg>
            </button>
					</div>
					<div class="modal-body">
						<?php the_field('weight_pop_content', 'option'); ?>
					</div>
				</div>
			</div>
		</div>
	<?php
}

add_action('wp', function () {      
  remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
  //remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
  // Split order review: table in popup, payment on page (see form-checkout order block)
  remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
  add_action('woocommerce_checkout_payment', 'woocommerce_checkout_payment', 10);
});

// Change place order button text when only one payment method is available
add_filter('woocommerce_order_button_text', function ($text) {
    if (!function_exists('WC')) {
        return $text;
    }

    $gateways = WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : array();
    if (empty($gateways) || count($gateways) !== 1) {
        return $text;
    }

    $gateway = reset($gateways);
    $id      = $gateway->id;
    $title   = strip_tags($gateway->get_title());

    // Cash on delivery / מזומן
    if ($id === 'cod' || mb_stripos($title, 'מזומן') !== false) {
        return __('תשלום במזומן', 'deliz-short');
    }

    // Credit card
    if (mb_stripos($title, 'אשראי') !== false || mb_stripos($title, 'credit') !== false || mb_stripos($title, 'card') !== false) {
        return __('תשלום באשראי', 'deliz-short');
    }

    // Fallback: use generic but still action-like text
    return sprintf(__('תשלום באמצעות %s', 'deliz-short'), $title ? $title : __('השיטה שנבחרה', 'deliz-short'));
});

// Set order_button_text per gateway so data-order_button_text is correct for JS (multiple payment methods)
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (empty($gateways)) {
        return $gateways;
    }
    foreach ($gateways as $id => $gateway) {
        $title = strip_tags($gateway->get_title());
        if ($id === 'cod' || mb_stripos($title, 'מזומן') !== false) {
            $gateway->order_button_text = __('ביצוע הזמנה', 'deliz-short');
        } elseif (mb_stripos($title, 'אשראי') !== false || mb_stripos($title, 'credit') !== false || mb_stripos($title, 'card') !== false) {
            $gateway->order_button_text = __('עבור לתשלום באשראי', 'deliz-short');
        } else {
            // cheque, etc.
            $gateway->order_button_text = __('ביצוע הזמנה', 'deliz-short');
        }
    }
    return $gateways;
});

add_action( 'woocommerce_review_order_after_cart_contents', 'woocommerce_checkout_coupon_form_custom' );
function woocommerce_checkout_coupon_form_custom() {
    echo '<tr class="coupon-form"><td colspan="2">';
    oc_woo_coupon_form_copy_for_checkout();    
    echo '</tr></td>';
}

// custom form | copy of reaL FORM
function oc_woo_coupon_form_copy_for_checkout(){
	if(in_array('pw-woocommerce-gift-cards/pw-gift-cards.php', apply_filters('active_plugins', get_option('active_plugins')))){
		$place = __( 'קוד קופון / שובר מתנה', 'deliz-short' );
		$btn = __( 'החל', 'deliz-short' );
	}else{
		$place = __( 'קוד קופון', 'deliz-short' );
		$btn = __( 'החלה', 'deliz-short' );
	} 
?>
	<div class="coupon-form copy-form" role="presentation">		
        <div class="checkout-coupon-form-inner">		
    		<input type="text" name="coupon_code_copy" class="input-text" placeholder="<?php echo esc_attr( $place ); ?>" id="coupon_code_copy" value="">
    		<button type="button" class="button apply-coupon-copy" name="apply_coupon" value="<?php echo esc_attr( $btn ); ?>"><?php echo esc_html( $btn ); ?></button>
        </div>
	</div>
	<?php //points mark ?>
	<?php if(in_array('yith-woocommerce-points-and-rewards-premium/init.php', apply_filters('active_plugins', get_option('active_plugins')))): ?>
		<div class="open-points" style="display:none;"><a href="javascript:void(0);"><?php echo __( "Click to use points", "deliz-short" ); ?></a></div>
	<?php endif; ?>	 
<?php
}

// Make billing postcode / P.O. Box optional (theme-level override).
add_filter( 'woocommerce_billing_fields', function( $fields ) {
    if ( isset( $fields['billing_postcode'] ) ) {
        $fields['billing_postcode']['required'] = false;
    }
    return $fields;
}, 20 );

/**
 * לאחר הזמנה: WooCommerce ו-oc-woo-shipping מנקים סשן/עגלה. מאמתים מיד אחרי הניקוי את נתוני המשלוח מההזמנה,
 * ושומרים גיבוי ב-cookie לטעינות הבאות (סל ריק עלול לאפס שוב את שיטת המשלוח).
 */
function deliz_short_oc_ship_cookie_params() {
	$path   = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
	$domain = ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '';

	return array( $path, $domain, time() + ( 60 * 60 * 24 * 90 ) );
}

/**
 * @return array<string, mixed>|null
 */
function deliz_short_build_ship_patch_from_order( WC_Order $order ) {
	$shipping_items = $order->get_items( 'shipping' );
	if ( empty( $shipping_items ) ) {
		return null;
	}

	$chosen = array();
	foreach ( $shipping_items as $item ) {
		if ( ! $item instanceof WC_Order_Item_Shipping ) {
			continue;
		}
		$method_id = $item->get_method_id();
		if ( ! $method_id ) {
			continue;
		}
		$instance_id = $item->get_instance_id();
		$chosen[]      = ( null !== $instance_id && '' !== $instance_id )
			? $method_id . ':' . absint( $instance_id )
			: $method_id;
	}
	$chosen = array_values( array_filter( $chosen ) );
	if ( empty( $chosen ) ) {
		return null;
	}

	$first           = reset( $shipping_items );
	$base_method_id  = $first instanceof WC_Order_Item_Shipping ? $first->get_method_id() : '';
	$patch           = array(
		'chosen_shipping_methods'        => $chosen,
		'sync_chosen_shipping_methods'   => $chosen,
		'checkout_data'                  => array(),
	);
	$cookie_method = '';

	if ( $base_method_id && 0 === strpos( $base_method_id, 'oc_woo_advanced_shipping_method' ) ) {
		$cookie_method = 'oc_woo_advanced_shipping_method';

		$city_code = $order->get_meta( '_billing_city_code' );
		if ( ! $city_code ) {
			$city_code = $order->get_billing_city();
		}
		$city_name = $order->get_meta( '_billing_city_name' );
		$street    = $order->get_meta( '_billing_street' );
		if ( ! $street ) {
			$street = $order->get_meta( 'billing_street' );
		}
		$house = $order->get_meta( '_billing_house_num' );
		if ( ! $house ) {
			$house = $order->get_meta( 'billing_house_num' );
		}
		$coords = $order->get_meta( '_billing_address_coords' );

		if ( $city_code ) {
			$patch['chosen_city_code']     = $city_code;
			$patch['chosen_shipping_city'] = $city_code;
			$patch['checkout_data']['billing_city']       = $city_code;
			$patch['checkout_data']['billing_city_code']  = $order->get_meta( '_billing_city_code' ) ?: $city_code;
			$patch['checkout_data']['shipping_city']      = $city_code;
			$patch['checkout_data']['shipping_city_code'] = $patch['checkout_data']['billing_city_code'];
		}
		if ( $city_name ) {
			$patch['chosen_city_name'] = $city_name;
			$patch['checkout_data']['billing_city_name']  = $city_name;
			$patch['checkout_data']['shipping_city_name'] = $city_name;
		}
		if ( $street ) {
			$patch['chosen_street'] = $street;
			$patch['checkout_data']['billing_street']  = $street;
			$patch['checkout_data']['shipping_street'] = $street;
		}
		if ( $house ) {
			$patch['chosen_house_num'] = $house;
			$patch['checkout_data']['billing_house_num']  = $house;
			$patch['checkout_data']['shipping_house_num'] = $house;
		}
		if ( $coords ) {
			$patch['chosen_address_coords'] = $coords;
			$patch['checkout_data']['billing_address_coords']  = $coords;
			$patch['checkout_data']['shipping_address_coords'] = $coords;
		}
		$ship_date  = $order->get_meta( 'ocws_shipping_info_date' );
		$slot_start = $order->get_meta( 'ocws_shipping_info_slot_start' );
		$slot_end   = $order->get_meta( 'ocws_shipping_info_slot_end' );
		if ( $ship_date ) {
			$patch['checkout_data']['order_expedition_date'] = $ship_date;
		}
		if ( $slot_start ) {
			$patch['checkout_data']['order_expedition_slot_start'] = $slot_start;
		}
		if ( $slot_end ) {
			$patch['checkout_data']['order_expedition_slot_end'] = $slot_end;
		}
	} elseif ( $base_method_id && ( 0 === strpos( $base_method_id, 'oc_woo_local_pickup_method' ) || 0 === strpos( $base_method_id, 'local_pickup' ) ) ) {
		$cookie_method = 'oc_woo_local_pickup_method';

		$aff = $order->get_meta( 'ocws_lp_pickup_aff_id' );
		if ( $aff ) {
			$patch['chosen_pickup_aff'] = absint( $aff );
			$patch['checkout_data']['ocws_lp_pickup_aff_id'] = $aff;
		}
		$p_date  = $order->get_meta( 'ocws_shipping_info_date' );
		$p_start = $order->get_meta( 'ocws_shipping_info_slot_start' );
		$p_end   = $order->get_meta( 'ocws_shipping_info_slot_end' );
		if ( $p_date ) {
			$patch['checkout_data']['ocws_lp_pickup_date'] = $p_date;
		}
		if ( $p_start ) {
			$patch['checkout_data']['ocws_lp_pickup_slot_start'] = $p_start;
		}
		if ( $p_end ) {
			$patch['checkout_data']['ocws_lp_pickup_slot_end'] = $p_end;
		}
	}

	$ocws_payload = array(
		'method'  => '',
		'city'    => '',
		'branch'  => '',
		'polygon' => array(
			'coords'    => '',
			'street'    => '',
			'house_num' => '',
			'city_name' => '',
			'city_code' => '',
		),
	);
	if ( ! empty( $_COOKIE['ocws'] ) ) {
		$prev = json_decode( wp_unslash( $_COOKIE['ocws'] ), true );
		if ( is_array( $prev ) ) {
			$ocws_payload = array_replace_recursive( $ocws_payload, $prev );
		}
	}
	if ( $cookie_method ) {
		$ocws_payload['method'] = $cookie_method;
	}
	if ( 'oc_woo_local_pickup_method' === $cookie_method ) {
		$aff = $order->get_meta( 'ocws_lp_pickup_aff_id' );
		if ( $aff ) {
			$ocws_payload['branch'] = (string) $aff;
		}
	} elseif ( 'oc_woo_advanced_shipping_method' === $cookie_method ) {
		$city_code = $order->get_meta( '_billing_city_code' );
		if ( ! $city_code ) {
			$city_code = $order->get_billing_city();
		}
		if ( $city_code ) {
			$ocws_payload['city'] = (string) $city_code;
		}
		$coords = $order->get_meta( '_billing_address_coords' );
		if ( $coords ) {
			$ocws_payload['polygon']['coords']    = (string) $coords;
			$ocws_payload['polygon']['street']    = (string) ( $order->get_meta( '_billing_street' ) ?: $order->get_meta( 'billing_street' ) );
			$ocws_payload['polygon']['house_num'] = (string) ( $order->get_meta( '_billing_house_num' ) ?: $order->get_meta( 'billing_house_num' ) );
			$ocws_payload['polygon']['city_name'] = (string) $order->get_meta( '_billing_city_name' );
			$ocws_payload['polygon']['city_code'] = (string) ( $order->get_meta( '_billing_city_code' ) ?: $city_code );
		}
	}
	$patch['ocws_cookie'] = $ocws_payload;

	return $patch;
}

/**
 * @param array<string, mixed> $patch
 */
function deliz_short_apply_ship_patch( array $patch ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	if ( ! empty( $patch['chosen_shipping_methods'] ) && is_array( $patch['chosen_shipping_methods'] ) ) {
		WC()->session->set( 'chosen_shipping_methods', $patch['chosen_shipping_methods'] );
	}
	if ( ! empty( $patch['sync_chosen_shipping_methods'] ) && is_array( $patch['sync_chosen_shipping_methods'] ) ) {
		WC()->session->set( 'sync_chosen_shipping_methods', $patch['sync_chosen_shipping_methods'] );
	}

	$scalars = array(
		'chosen_city_code',
		'chosen_city_name',
		'chosen_street',
		'chosen_house_num',
		'chosen_address_coords',
		'chosen_shipping_city',
		'chosen_pickup_aff',
	);
	foreach ( $scalars as $key ) {
		if ( array_key_exists( $key, $patch ) && null !== $patch[ $key ] && '' !== $patch[ $key ] ) {
			WC()->session->set( $key, $patch[ $key ] );
		}
	}

	if ( ! empty( $patch['checkout_data'] ) && is_array( $patch['checkout_data'] ) ) {
		$cur = WC()->session->get( 'checkout_data', array() );
		if ( ! is_array( $cur ) ) {
			$cur = array();
		}
		foreach ( $patch['checkout_data'] as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$cur[ $k ] = $v;
			}
		}
		WC()->session->set( 'checkout_data', $cur );
	}

	if ( ! empty( $patch['chosen_city_code'] ) && WC()->customer ) {
		WC()->customer->set_billing_city( $patch['chosen_city_code'] );
		WC()->customer->set_shipping_city( $patch['chosen_city_code'] );
	}

	if ( ! empty( $patch['ocws_cookie'] ) && is_array( $patch['ocws_cookie'] ) && ! headers_sent() ) {
		list( $path, $domain, $expire ) = deliz_short_oc_ship_cookie_params();
		$encoded = wp_json_encode( $patch['ocws_cookie'] );
		if ( false !== $encoded ) {
			setcookie( 'ocws', $encoded, $expire, $path, $domain, is_ssl(), false );
			$_COOKIE['ocws'] = $encoded;
		}
	}
}

/**
 * @param array<string, mixed> $patch
 */
function deliz_short_write_ship_backup_cookie( array $patch ) {
	if ( headers_sent() ) {
		return;
	}
	list( $path, $domain, $expire ) = deliz_short_oc_ship_cookie_params();
	$wrapper = array(
		'v'     => 1,
		't'     => time(),
		'patch' => $patch,
	);
	$json = wp_json_encode( $wrapper );
	if ( false === $json ) {
		return;
	}
	setcookie( 'deliz_oc_ship_last', $json, $expire, $path, $domain, is_ssl(), false );
	$_COOKIE['deliz_oc_ship_last'] = $json;
}

/**
 * @return array<string, mixed>|null
 */
function deliz_short_read_ship_backup_cookie() {
	if ( empty( $_COOKIE['deliz_oc_ship_last'] ) ) {
		return null;
	}
	$data = json_decode( wp_unslash( $_COOKIE['deliz_oc_ship_last'] ), true );

	return ( is_array( $data ) && ! empty( $data['patch'] ) && is_array( $data['patch'] ) ) ? $data : null;
}

/**
 * @param WC_Order $order
 */
function deliz_short_reapply_shipping_after_order( $order ) {
	if ( ! apply_filters( 'deliz_short_reapply_shipping_after_order', true, $order ) ) {
		return;
	}
	if ( ! $order instanceof WC_Order || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	if ( function_exists( 'ocws_order_shipping_data_to_session' ) ) {
		ocws_order_shipping_data_to_session( $order->get_id() );
	}

	$patch = deliz_short_build_ship_patch_from_order( $order );
	if ( ! $patch ) {
		WC()->session->save_data();
		return;
	}

	deliz_short_apply_ship_patch( $patch );
	deliz_short_write_ship_backup_cookie( $patch );
	WC()->session->save_data();
}

/**
 * @param WC_Cart $cart
 */
function deliz_short_maybe_restore_ship_from_backup_cookie( $cart ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( ! apply_filters( 'deliz_short_restore_shipping_from_backup_cookie', true ) ) {
		return;
	}

	static $did = false;
	if ( $did ) {
		return;
	}

	$methods = WC()->session->get( 'chosen_shipping_methods', array() );
	if ( is_array( $methods ) && array_filter( $methods ) ) {
		return;
	}

	$backup = deliz_short_read_ship_backup_cookie();
	if ( empty( $backup['patch'] ) ) {
		return;
	}

	deliz_short_apply_ship_patch( $backup['patch'] );
	WC()->session->save_data();
	$did = true;
}

add_action(
	'woocommerce_checkout_order_processed',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			deliz_short_reapply_shipping_after_order( $order );
		}
	},
	2500,
	1
);

add_action(
	'woocommerce_thankyou',
	static function ( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			deliz_short_reapply_shipping_after_order( $order );
		}
	},
	5,
	1
);

add_action( 'woocommerce_cart_loaded_from_session', 'deliz_short_maybe_restore_ship_from_backup_cookie', 99999 );