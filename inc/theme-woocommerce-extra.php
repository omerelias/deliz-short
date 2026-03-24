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
		$btn = __( 'החלת קופון', 'deliz-short' );
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