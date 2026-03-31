<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('acf/init', function () {
  if ( ! function_exists('acf_add_options_page') ) return;

  acf_add_options_page([
    'page_title'  => __( 'הגדרות אתר', 'deliz-short' ),
    'menu_title'  => __( 'הגדרות אתר', 'deliz-short' ),
    'menu_slug'   => 'site-settings',
    'capability'  => 'manage_options',
    'redirect'    => false,
    'position'    => 59,
    'icon_url'    => 'dashicons-admin-generic',
    'update_button' => __( 'שמור', 'deliz-short' ),
    'updated_message' => __( 'ההגדרות נשמרו', 'deliz-short' ),
  ]);
});

if ( function_exists('acf_add_options_page') ) {
  acf_add_options_page([
    'page_title' => __( 'סליידר ראשי', 'deliz-short' ),
    'menu_title' => __( 'סליידר ראשי', 'deliz-short' ),
    'menu_slug'  => 'main-slider',
    'capability' => 'manage_options',
    'redirect'   => false,
    'position'   => 59,
    'icon_url'   => 'dashicons-images-alt2',
  ]);
}



/**
 * Generate theme options CSS file from ACF Options.
 * Outputs: /wp-content/uploads/theme-options.css
 */

add_action('acf/save_post', function ($post_id) {
  // Only when saving ACF Options page
  if ($post_id !== 'options') return;

  $file = trailingslashit(get_stylesheet_directory()) . 'assets/css/theme-options.css';

  // Pull values from options (דוגמאות)
  $primary   = get_field('main_color', 'option');   // main_color
  $primary_hover   = get_field('main_color_hover', 'option');   // main_color_hover
  $main_text_color   = get_field('main_text_color', 'option');   // main_color_text
  $main_text_color_hover   = get_field('main_text_color_hover', 'option');   // main_color_text_hover
  $secondary = get_field('second_color', 'option'); // second_color
  //$second_color_hover = get_field('second_color_hover', 'option'); // second_color_hover
  $floating_cart_bg = get_field('floating_cart_bg', 'option');  // צבע רקע כפתור סל צף
  $floating_cart_text_color    = get_field('floating_cart_text_color', 'option');   // צבע טקסט כפתור סל צף
  $radius    = get_field('radius', 'option');   // רדיוס פינות
  //top-header
  $top_header_bg   = get_field('top_header_bg', 'option'); //צבע רקע
  $top_header_txt_color   = get_field('top_header_txt_color', 'option'); //צבע טקסט
  //main header
  $main_header_bg   = get_field('main_header_bg', 'option'); //צבע רקע
  $main_header_txt_color   = get_field('main_header_txt_color', 'option'); //צבע רקע
  $menu_link_color   = get_field('menu_link_color', 'option'); //צבע לינק תפריט דסקטופ
  $menu_link_color_active   = get_field('menu_link_color_active', 'option'); //צבע לינק תפריט דסקטופ פעיל
  //mobile menu
  $mobile_menu_bg   = get_field('mobile_menu_bg', 'option'); //צבע רקע
  $mobile_menu_text_color   = get_field('mobile_menu_text_color', 'option'); //צבע לינק
  $mobile_menu_bg__active   = get_field('mobile_menu_bg__active', 'option'); //צבע רקע פעיל
  $mobile_menu_text_color_active   = get_field('mobile_menu_text_color_active', 'option'); //צבע טקסט פעיל
  //Footer top
  $ft_bg_color   = get_field('ft_bg_color', 'option'); //צבע רקע
  $ft_txt_color   = get_field('ft_txt_color', 'option'); //צבע רקע
  //main footer
  $fb_bg_color   = get_field('fb_bg_color', 'option'); //צבע רקע
  $fb_txt_color   = get_field('fb_txt_color', 'option'); //צבע רקע

  // Normalize
  $primary   = is_string($primary) ? trim($primary) : '';
  $secondary = is_string($secondary) ? trim($secondary) : '';
  $floating_cart_bg = is_string($floating_cart_bg) ? trim($floating_cart_bg) : $floating_cart_bg;
  $floating_cart_text_color    = is_string($floating_cart_text_color) ? trim($floating_cart_text_color) : $floating_cart_text_color;
  $radius    = is_string($radius) ? trim($radius) : $radius;

  // Helpers
  $to_px = function($v){
    if ($v === '' || $v === null) return '';
    if (is_numeric($v)) return $v . 'px';
    return preg_match('/(px|rem|em|%)$/', $v) ? $v : $v; // אם מגיע כבר עם יחידה
  };

  $css  = "/* Auto-generated. Do not edit directly. */\n";
  $css .= ":root{\n";
  if ($primary)   $css .= "  --color-primary: " . $primary . ";\n";
  if ($primary_hover)   $css .= "  --color-primary-hover: " . $primary_hover . ";\n";
  if ($main_text_color)   $css .= "  --main-text-color: " . $main_text_color . ";\n";
  if ($main_text_color_hover)   $css .= "  --main-text-color-hover: " . $main_text_color_hover . ";\n";  
  if ($secondary) $css .= "  --color-secondary: " . $secondary . ";\n";
  //if ($second_color_hover)   $css .= "  --color-secondary-hover: " . $second_color_hover . ";\n";
  if ($floating_cart_bg) $css .= "  --floating-cart-bg: " . $floating_cart_bg . ";\n";
  if ($floating_cart_text_color) $css .= "  --floating-cart-text-color: " . $floating_cart_text_color . ";\n";
  if ($radius) $css .= "  --radius: " . $to_px($radius) . ";\n";
  if ($top_header_bg)   $css .= "  --top-header-bg: " . $top_header_bg . ";\n";
  if ($top_header_txt_color)   $css .= "  --top-header-txt_color: " . $top_header_txt_color . ";\n";
  if ($main_header_bg)   $css .= "  --main-header-bg: " . $main_header_bg . ";\n";
  if ($main_header_txt_color)   $css .= "  --main-header-txt-color: " . $main_header_txt_color . ";\n";
  if ($menu_link_color)   $css .= "  --menu-link-color: " . $menu_link_color . ";\n";
  if ($menu_link_color_active)   $css .= "  --menu-link-color-active: " . $menu_link_color_active . ";\n";
  if ($mobile_menu_bg)   $css .= "  --mobile-menu-bg: " . $mobile_menu_bg . ";\n";
  if ($mobile_menu_text_color)   $css .= "  --mobile-menu-text-color: " . $mobile_menu_text_color . ";\n";
  if ($mobile_menu_bg__active)   $css .= "  --mobile-menu-bg-active: " . $mobile_menu_bg__active . ";\n";
  if ($mobile_menu_text_color_active)   $css .= "  --mobile-menu-text-color-active: " . $mobile_menu_text_color_active . ";\n";
  if ($ft_bg_color)   $css .= "  --ft-bg-color: " . $ft_bg_color . ";\n";
  if ($ft_txt_color)   $css .= "  --ft-txt-color: " . $ft_txt_color . ";\n";
  if ($fb_bg_color)   $css .= "  --fb-bg-color: " . $fb_bg_color . ";\n";
  if ($fb_txt_color)   $css .= "  --fb-txt-color: " . $fb_txt_color . ";\n";
  $css .= "}\n";

  // דוגמה לשימוש במשתנים בלי inline:
  $css .= "body{font-size:var(--font-size-base,16px)}\n";
  $css .= ".top-header{background-color:var(--top-header-bg);color:var(--top-header-txt_color)}\n";
  $css .= ".top-header a{color:var(--top-header-txt_color)}\n";
  $css .= ".site-header{background-color:var(--main-header-bg);color:var(--main-header-txt-color)}\n";
  $css .= ".site-header a{color:var(--main-header-txt-color)}\n";

  // עיצוב סליידר ראשי
  if ( have_rows('slider_settings', 'option') ) {
  $i = 0;

  while ( have_rows('slider_settings', 'option') ) {
    the_row();
    $i++;

    $content_bg = sanitize_hex_color( get_sub_field('content_bg') ) ?: '';
    $txt_color  = sanitize_hex_color( get_sub_field('text_on_image_color') ) ?: '';
    $btn_bg     = sanitize_hex_color( get_sub_field('btn_bg') ) ?: '';
    $btn_txt    = sanitize_hex_color( get_sub_field('btn_txt') ) ?: '';

    // ✅ פר סלייד
    $sel = '.ed-main-slider .ed-slide[data-ed-slide="'.$i.'"]';

    $css .= $sel . "{\n";
    if ($content_bg) $css .= "  --ed-content-bg: {$content_bg};\n";
    if ($txt_color)  $css .= "  --ed-text-color: {$txt_color};\n";
    if ($btn_bg)     $css .= "  --ed-btn-bg: {$btn_bg};\n";
    if ($btn_txt)    $css .= "  --ed-btn-txt: {$btn_txt};\n";
    $css .= "}\n";
  }
}


  // Ensure folder exists + write file
  wp_mkdir_p( dirname($file) );
  file_put_contents($file, $css);

}, 20);

function ed_add_items_images_size_body_class($classes) {
    if (!function_exists('get_field')) {
        return $classes;
    }

    $items_images_size = get_field('items_images_size', 'option');

    if ($items_images_size === 'square') {
        $classes[] = 'square_images';
    } elseif ($items_images_size === 'rectangular') {
        $classes[] = 'rectangular_images';
    }

    return $classes;
}