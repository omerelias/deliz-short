<?php

/**

 * deliz-short functions

 */



if ( ! defined('ABSPATH') ) exit;



define('DELIZ_SHORT_VERSION', time());

require 'helpers.php';

require 'functions-front.php';require_once __DIR__ . '/inc/theme-updater.php';

include_once (get_stylesheet_directory() . '/oc-plugins-compat/oc-plugins-compat-functions.php');

require_once get_stylesheet_directory() . '/site-options/init.php';

// SMS LOGIN

require_once( 'inc/oc-sms-auth/class-oc-sms-auth.php' );



// Checkout Upsells

require_once( 'includes/lib/checkout-upsells/class-checkout-upsells.php' );



// Promotions

require_once( 'includes/lib/promotions/class-promotions.php' );

//

add_action('after_setup_theme', function () {

    // Translations

  load_theme_textdomain('deliz-short', get_template_directory() . '/languages');



  // Basic supports

  add_theme_support('title-tag');

  add_theme_support('post-thumbnails');

  add_theme_support('automatic-feed-links');

  add_theme_support('html5', [

    'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'

  ]);



  // Menus

  register_nav_menus([

    'primary' => __('Primary Menu', 'deliz-short'),

    'footer'  => __('Footer Menu', 'deliz-short'),

  ]);



  // Optional: custom logo

  add_theme_support('custom-logo', [

    'height' => 120,

    'width'  => 320,

    'flex-height' => true,

    'flex-width'  => true,

  ]);



  // גודל אייקון תפריט (מוצג 50x50 – 100 לרטינה)

  add_image_size('ed_menu_icon', 100, 100, true);

});



function sea2door_order_formatted_billing_address( $order ) {

    ob_start();

    $billing_fields = apply_filters(
        'woocommerce_admin_billing_fields', array(
            'first_name' => array(
                'label' => __( 'First name', 'woocommerce' ),
                'show'  => false,
            ),
            'last_name'  => array(
                'label' => __( 'Last name', 'woocommerce' ),
                'show'  => false,
            ),
            'company'    => array(
                'label' => __( 'Company', 'woocommerce' ),
                'show'  => false,
            ),
            'address_1'  => array(
                'label' => __( 'Address line 1', 'woocommerce' ),
                'show'  => false,
            ),
            'address_2'  => array(
                'label' => __( 'Address line 2', 'woocommerce' ),
                'show'  => false,
            ),
            'city'       => array(
                'label' => __( 'City', 'woocommerce' ),
                'show'  => true,
            ),
            'postcode'   => array(
                'label' => __( 'Postcode / ZIP', 'woocommerce' ),
                'show'  => false,
            ),
            'country'    => array(
                'label'   => __( 'Country', 'woocommerce' ),
                'show'    => false,
                'class'   => 'js_field-country select short',
                'type'    => 'select',
                'options' => array( '' => __( 'Select a country&hellip;', 'woocommerce' ) ) + WC()->countries->get_allowed_countries(),
            ),
            'state'      => array(
                'label' => __( 'State / County', 'woocommerce' ),
                'class' => 'js_field-state select short',
                'show'  => false,
            ),
            'email'      => array(
                'label' => __( 'Email address', 'woocommerce' ),
            ),
            'phone'      => array(
                'label' => __( 'Phone', 'woocommerce' ),
            ),
        )
    );

    foreach ( $billing_fields as $key => $field ) {
        if ( isset( $field['show'] ) && false === $field['show'] ) {
            continue;
        }

        $field_name = 'billing_' . $key;

        if ( isset( $field['value'] ) ) {
            $field_value = $field['value'];
        } elseif ( is_callable( array( $order, 'get_' . $field_name ) ) ) {
            $field_value = $order->{"get_$field_name"}( 'edit' );
        } else {
            $field_value = $order->get_meta( '_' . $field_name );
        }

        if ( 'billing_city' === $field_name ) {
            $field_value = apply_filters( 'ocws_get_city_title', $field_value );
        }

        if ( 'billing_phone' === $field_name ) {
            $field_value = wc_make_phone_clickable( $field_value );
        } else {
            $field_value = make_clickable( esc_html( $field_value ) );
        }

        if ( $field_value ) {
            echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . wp_kses_post( $field_value ) . '</p>';
        }
    }
    return ob_get_clean();
}
