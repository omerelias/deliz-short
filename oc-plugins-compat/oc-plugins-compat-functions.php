<?php

/*

* include OC plugins compatibility functions (by Milla Shub)

* paste the following line of code to functions.php of your theme

* */

/* require_once 'oc-plugins-compat/oc-plugins-compat-functions.php'; */



add_action( 'init', 'oc_compat_script_enqueuer' );



function oc_compat_script_enqueuer() {



    wp_register_script( 'oc-compat-js', get_stylesheet_directory_uri().'/oc-plugins-compat/assets/js/oc-compat.js', array('jquery') );

    wp_localize_script( 'oc-compat-js', 'oc_compat', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));



    wp_enqueue_script( 'oc-compat-js' );



    wp_enqueue_style( 'oc-compat-css', get_stylesheet_directory_uri() .'/oc-plugins-compat/assets/css/oc-compat.css', array() );



}



//add_filter( 'woocommerce_update_order_review_fragments', 'oc_compat_add_checkout_fragments' );



function oc_compat_add_checkout_fragments( $fragments ) {

    ob_start();

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

        'billing_apartment'

    );

    ?>

    <div class="woocommerce-billing-fields__field-wrapper woocommerce-billing-fields-part-2 billing-fields-shipping-data-1 <?php if ( $local_pickup_chosen == 1  ){ echo 'hidden'; } ?>">

        <?php

        $fields = WC()->checkout()->get_checkout_fields( 'billing' );

        foreach ( $fields as $key => $field ) {

            if ( in_array( $key, $ar_billing_fields_first ) ){

                woocommerce_form_field( $key, $field, WC()->checkout()->get_value( $key ) );

            }

        }

        ?>



    </div>

    <?php

    $billing_fields_2 = ob_get_clean();

    $fragments['.woocommerce-billing-fields-part-2'] = $billing_fields_2;



    ob_start();

    ?>

    <div class="other-recipient-fields">

        <?php do_action('ocws_send_to_other_person_fields'); ?>

    </div>

    <?php

    $other_recipient_fields = ob_get_clean();

    $fragments['.other-recipient-fields'] = $other_recipient_fields;

    return $fragments;

}



// minicart

if (! function_exists('header_mini_cart')) {

    function header_mini_cart(){

        // check if woocommerce is active

        if ( !OC_MAIN_THEME_WOO_IS_ACTIVE ){

            return;

        }



        $icon_svg 	= get_theme_mod( 'header_cart_icon' );

        if ( $icon_svg ){

            // if ( file_exists( $icon_svg ) ){

            // 	$icon_cart 	= file_get_contents( $icon_svg );

            // }

            // $icon_cart =  oc_get_svg_image_if_exist( $icon_svg );

            $icon_cart = oc_get_svg_async( $icon_svg );

        } else {

            $icon_cart 	= false;

        }

        // get default icon from theme

        if ( !$icon_cart ){

            $icon_cart 	= child_theme_get_svg_icon( 'icon-cart.svg' );

        }



        $count_method 	= get_theme_mod( 'minicart_count_method' );



        if ( $count_method == 'rows' ){

            // count items in cart ( rows )

            $cart_content_count = count( WC()->cart->get_cart() );

        } else {

            $cart_content_count = WC()->cart->get_cart_contents_count();

        }

        $circle_class = $cart_content_count == 0 || empty( $cart_content_count ) ? 'hidden' : 'hidden';



        // $url = wc_get_checkout_url();

        ?>

        <div class="site-header-minicart">

            <button class="mini-cart-icon btn-empty" aria-label="<?php _e( 'Minicart link' ); ?>" type="button">

                <span class="child-theme minicart-circle <?php echo $circle_class; ?>"><?php echo $cart_content_count; ?></span>

                <?php echo $icon_cart; ?>

                <?php do_action('ocws_deli_header_mini_cart') ?>

            </button>

        </div>

        <?php

        // move to panel

        //woocommerce_mini_cart();

    }

}





?>