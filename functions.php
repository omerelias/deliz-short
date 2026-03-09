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

  // גודל אייקון תפריט (מוצג 50x50 – 100 לרטינה)
  add_image_size('ed_menu_icon', 100, 100, true);
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

/**
 * זמני – dequeue לרשימת ה־assets הכבדים (לפי PageSpeed).
 * להפעלה: הוסף ?dequeue_slow=1 ל־URL. למחוק אחרי הבדיקה.
 */
//add_action('wp_enqueue_scripts', function () {
//  if ( ! isset($_GET['dequeue_slow']) || ( $_GET['dequeue_slow'] !== '1' && $_GET['dequeue_slow'] !== '' ) ) {
//    return;
//  }
//  $styles_to_dequeue = [
////    'oc-sms-auth',           // sms-auth.css
//    'woocommerce-general',   // woocommerce-rtl.css
//    'slick',                 // slick.css
//    'slick-theme',           // slick-theme.css
//    'checkout-sms-popup',   // checkout-sms-popup.css
//    'theme-options',        // theme-options.css
//    'ed-checkout-upsells',  // checkout-upsells.css
////    'deliz-short-main',      // main.css
//    'deliz-short-product-popup', // product-popup.css
//    'woocommerce-smallscreen',   // woocommerce-smallscreen-rtl.css
//    'ed-promotions',         // promotions.css
//    'wc-blocks-style',       // wc-blocks-rtl.css
////    'deliz-short-style',     // style.css
//    'woocommerce-layout',    // woocommerce-layout-rtl.css
//    'oc-compat-css',         // oc-compat.css
//  ];
//  $scripts_to_dequeue = [
//    'jquery-migrate',        // jquery-migrate.min.js
//    'jquery',                // jquery.min.js
//    'oc-compat-js',          // oc-compat.js
//  ];
//  foreach ( $styles_to_dequeue as $handle ) {
//    wp_dequeue_style($handle);
//  }
//  foreach ( $scripts_to_dequeue as $handle ) {
//    wp_dequeue_script($handle);
//  }
//  // Google Fonts – כל סטייל שמקורו ב־fonts.googleapis.com
//  $wp_styles = wp_styles();
//  foreach ( $wp_styles->registered as $handle => $obj ) {
//    if ( ! empty($obj->src) && strpos($obj->src, 'fonts.googleapis.com') !== false ) {
//      wp_dequeue_style($handle);
//    }
//  }
//}, 9999);

//add_action('wp_enqueue_scripts', function () {
//    $is_list    = isset($_GET['list_assets']) && $_GET['list_assets'] === '1';
//    $is_range   = isset($_GET['dequeue_script_range']) || isset($_GET['dequeue_style_range']);
//    $is_all     = isset($_GET['dequeue_all']) && ( $_GET['dequeue_all'] === '1' || $_GET['dequeue_all'] === '' );
//    $is_scripts = isset($_GET['dequeue_scripts']) && ( $_GET['dequeue_scripts'] === '1' || $_GET['dequeue_scripts'] === '' );
//    $is_styles  = isset($_GET['dequeue_styles']) && ( $_GET['dequeue_styles'] === '1' || $_GET['dequeue_styles'] === '' );
//    if ( ! $is_list && ! $is_range && ! $is_all && ! $is_scripts && ! $is_styles ) {
//        return;
//    }
//    $wp_scripts = wp_scripts();
//    $wp_styles  = wp_styles();
//    $GLOBALS['_ed_asset_queues'] = [
//        'scripts' => array_values($wp_scripts->queue),
//        'styles'  => array_values($wp_styles->queue),
//    ];
//}, 9998);

//add_action('wp_enqueue_scripts', function () {
//    $q = $GLOBALS['_ed_asset_queues'] ?? null;
//    if ( ! $q ) {
//        return;
//    }
//    if ( isset($_GET['dequeue_all']) && ( $_GET['dequeue_all'] === '1' || $_GET['dequeue_all'] === '' ) ) {
//        foreach ( wp_styles()->registered as $handle => $obj ) {
//            wp_dequeue_style($handle);
//        }
//        foreach ( wp_scripts()->registered as $handle => $obj ) {
//            wp_dequeue_script($handle);
//        }
//        return;
//    }
//    if ( isset($_GET['dequeue_scripts']) && ( $_GET['dequeue_scripts'] === '1' || $_GET['dequeue_scripts'] === '' ) ) {
//        foreach ( wp_scripts()->registered as $handle => $obj ) {
//            wp_dequeue_script($handle);
//        }
//    }
//    if ( isset($_GET['dequeue_styles']) && ( $_GET['dequeue_styles'] === '1' || $_GET['dequeue_styles'] === '' ) ) {
//        foreach ( wp_styles()->registered as $handle => $obj ) {
//            wp_dequeue_style($handle);
//        }
//    }
//    if ( isset($_GET['dequeue_script_range']) && preg_match('/^(\d+)[_\-](\d+)$/', sanitize_text_field($_GET['dequeue_script_range']), $m) ) {
//        $from = (int) $m[1];
//        $to   = (int) $m[2];
//        $list = $q['scripts'];
//        for ( $i = $from; $i <= $to && $i < count($list); $i++ ) {
//            wp_dequeue_script($list[ $i ]);
//        }
//    }
//    if ( isset($_GET['dequeue_style_range']) && preg_match('/^(\d+)[_\-](\d+)$/', sanitize_text_field($_GET['dequeue_style_range']), $m) ) {
//        $from = (int) $m[1];
//        $to   = (int) $m[2];
//        $list = $q['styles'];
//        for ( $i = $from; $i <= $to && $i < count($list); $i++ ) {
//            wp_dequeue_style($list[ $i ]);
//        }
//    }
//}, 9999);

//add_action('wp_footer', function () {
//    if ( ! isset($_GET['list_assets']) || $_GET['list_assets'] !== '1' ) {
//        return;
//    }
//    $q = $GLOBALS['_ed_asset_queues'] ?? null;
//    if ( ! $q ) {
//        return;
//    }
//    $n_scripts = count($q['scripts']);
//    $n_styles  = count($q['styles']);
//    $mid_s = $n_scripts > 0 ? (int) floor(($n_scripts - 1) / 2) : 0;
//    $mid_c = $n_styles > 0 ? (int) floor(($n_styles - 1) / 2) : 0;
//    echo '<pre style="position:fixed;bottom:0;left:0;right:0;max-height:55vh;overflow:auto;background:#111;color:#0f0;padding:1em;font-size:12px;z-index:999999;text-align:left;direction:ltr;border:2px solid #0f0;">';
//    echo "=== SCRIPTS (total: $n_scripts) ===\n";
//    echo "Binary: first half = dequeue_script_range=0_{$mid_s}\n\n";
//    $wp_scripts = wp_scripts();
//    foreach ( $q['scripts'] as $i => $handle ) {
//        $obj = $wp_scripts->registered[ $handle ] ?? null;
//        $src = $obj ? $obj->src : '';
//        echo sprintf("%3d: %s\n    %s\n", $i, $handle, $src);
//    }
//    echo "\n=== STYLES (total: $n_styles) ===\n";
//    echo "Binary: first half = dequeue_style_range=0_{$mid_c}\n\n";
//    $wp_styles = wp_styles();
//    foreach ( $q['styles'] as $i => $handle ) {
//        $obj = $wp_styles->registered[ $handle ] ?? null;
//        $src = $obj ? $obj->src : '';
//        echo sprintf("%3d: %s\n    %s\n", $i, $handle, $src);
//    }
//    echo '</pre>';
//}, 9999);


