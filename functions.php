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


