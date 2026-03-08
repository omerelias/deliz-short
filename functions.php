<?php
/**
 * deliz-short functions
 */

if ( ! defined('ABSPATH') ) exit;

define('DELIZ_SHORT_VERSION', time());
require 'helpers.php';
require 'functions-front.php';
include_once (get_stylesheet_directory() . '/oc-plugins-compat/oc-plugins-compat-functions.php');

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
});


//add_action('admin_init', function () {
//    $order_id = 1887;
//    $order=wc_get_order($order_id);
//    echo "ocws_shipping_info_date: "; var_dump(get_post_meta($order_id, 'ocws_shipping_info_date', true));
//
////    var_dump($order->get_shipping_first_name());
//    die;
//    echo '<pre style="direction:ltr">';
//    echo "ORDER: {$order_id}\n\n";
//
//    echo "_shipping_first_name: "; var_dump(get_post_meta($order_id, '_shipping_first_name', true));
//    echo "_shipping_last_name: ";  var_dump(get_post_meta($order_id, '_shipping_last_name', true));
//
//    echo "_billing_first_name: ";  var_dump(get_post_meta($order_id, '_billing_first_name', true));
//    echo "_billing_last_name: ";   var_dump(get_post_meta($order_id, '_billing_last_name', true));
//
//    echo "\nPossible custom keys:\n";
//    foreach (get_post_meta($order_id) as $k => $v) {
//        if (stripos($k, 'recipient') !== false || stripos($k, 'last') !== false || stripos($k, 'first') !== false) {
//            echo $k . " => ";
//            var_dump($v);
//        }
//    }
//
//    echo '</pre>';
//    die;
//});