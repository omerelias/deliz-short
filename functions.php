<?php
/**
 * deliz-short functions
 */

if ( ! defined('ABSPATH') ) exit;

define('DELIZ_SHORT_VERSION', '1.0.0');

require 'functions-front.php';

// SMS LOGIN
require_once( 'inc/oc-sms-auth/class-oc-sms-auth.php' );

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
