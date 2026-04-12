<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//slick
add_action('wp_enqueue_scripts', function () {

  wp_enqueue_script('jquery');

  wp_enqueue_style(
    'slick',
    get_stylesheet_directory_uri() . '/assets/slick/slick.css',
    [],
    '1.8.1'
  );

  wp_enqueue_style(
    'slick-theme',
    get_stylesheet_directory_uri() . '/assets/slick/slick-theme.css',
    ['slick'],
    '1.8.1'
  );

  wp_enqueue_script(
    'slick',
    get_stylesheet_directory_uri() . '/assets/slick/slick.min.js',
    ['jquery'],
    '1.8.1',
    true
  );

}, 20);

add_action('wp_enqueue_scripts', function () {
  // Theme stylesheet (style.css)
  wp_enqueue_style('deliz-short-style', get_stylesheet_uri(), [], DELIZ_SHORT_VERSION);

  // Optional extra CSS/JS
  $css = get_template_directory_uri() . '/assets/css/main.css';
  $js  = get_template_directory_uri() . '/assets/js/main.js';

  wp_enqueue_style('deliz-short-main', $css, [], time());
  wp_enqueue_style(
    'deliz-short-free-ship-bar',
    get_template_directory_uri() . '/assets/css/free-shipping-bar.css',
    array('deliz-short-main'),
    DELIZ_SHORT_VERSION
  );
  wp_enqueue_script('deliz-short-main', $js, [], DELIZ_SHORT_VERSION, true);

  // Ensure wc_ajax_url + coupon nonce exist for float cart coupon apply (even on pages that don't enqueue WC cart scripts)
  if (function_exists('WC')) {
    $wc_ajax_url = null;
    if (class_exists('WC_AJAX') && is_callable(['WC_AJAX', 'get_endpoint'])) {
      $wc_ajax_url = WC_AJAX::get_endpoint('%%endpoint%%');
    } else {
      // Fallback format used by WooCommerce for wc-ajax endpoints
      $wc_ajax_url = home_url('/?wc-ajax=%%endpoint%%');
    }

    $params = [
       'wc_ajax_url'           => $wc_ajax_url,
      'apply_coupon_nonce'    => wp_create_nonce('apply-coupon'),
      'remove_coupon_nonce'   => wp_create_nonce('remove-coupon'),
    ];

    $inline = '(function(){'
      . 'window.ED_COUPON_PARAMS=window.ED_COUPON_PARAMS||' . wp_json_encode($params) . ';'
      . 'window.wc_cart_params=window.wc_cart_params||window.ED_COUPON_PARAMS;'
      . 'if(window.wc_cart_params&&!window.wc_cart_params.remove_coupon_nonce&&window.ED_COUPON_PARAMS){'
      . 'window.wc_cart_params.remove_coupon_nonce=window.ED_COUPON_PARAMS.remove_coupon_nonce;}'
      . 'window.wc_checkout_params=window.wc_checkout_params||window.wc_cart_params;'
      . 'if(window.wc_checkout_params&&!window.wc_checkout_params.remove_coupon_nonce&&window.ED_COUPON_PARAMS){'
      . 'window.wc_checkout_params.remove_coupon_nonce=window.ED_COUPON_PARAMS.remove_coupon_nonce;}'
      . '})();';

    wp_add_inline_script('deliz-short-main', $inline, 'before');
  }

  // Product Popup functionality is loaded from includes/product-popup/class-product-popup.php
});

add_action('wp_enqueue_scripts', function () {
  $rel  = 'assets/css/theme-options.css';
  $path = trailingslashit(get_stylesheet_directory()) . $rel;
  $url  = trailingslashit(get_stylesheet_directory_uri()) . $rel;

  $ver = file_exists($path) ? filemtime($path) : null;

  wp_enqueue_style('theme-options', $url, [], $ver);
}, 20);

// OCWS: gate + open choose-shipping when clicking floating-cart checkout (also a dependency for checkout-upsells).
// Must run before checkout-upsells (priority 10) so the handle is registered first.
add_action(
  'wp_enqueue_scripts',
  function () {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
      return;
    }
    wp_register_script(
      'deliz-ocws-checkout-gate',
      get_template_directory_uri() . '/assets/js/deliz-ocws-checkout-gate.js',
      array( 'jquery' ),
      defined( 'DELIZ_SHORT_VERSION' ) ? DELIZ_SHORT_VERSION : '1.0.0',
      true
    );
    wp_enqueue_script( 'deliz-ocws-checkout-gate' );
    wp_localize_script(
      'deliz-ocws-checkout-gate',
      'delizOcwsCheckoutGate',
      array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'ocwsActive' => class_exists( 'OCWS_Popup', false ),
      )
    );
  },
  5
);

add_action('wp_enqueue_scripts', function() {
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    
    // Checkout SMS flow JS
    wp_enqueue_script(
        'checkout-sms-flow',
        get_template_directory_uri() . '/assets/js/checkout-sms-flow.js',
        array('jquery'),
        DELIZ_SHORT_VERSION,
        true
    );
    
    // Localize script with user login status
    $sms_auth = class_exists('OC_SMS_Auth') ? OC_SMS_Auth::get_instance() : null;
    $settings = $sms_auth ? $sms_auth->get_settings() : array();

    $delivery_extra = function_exists( 'deliz_short_checkout_sms_delivery_extra_for_localize' )
      ? deliz_short_checkout_sms_delivery_extra_for_localize()
      : array(
        'show_delivery_extra' => false,
        'delivery_address_line' => '',
        'shipping_intro_html' => '',
      );

    wp_localize_script('checkout-sms-flow', 'oc_sms_auth', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oc_sms_auth'),
        'is_logged_in' => is_user_logged_in() ? 1 : 0,
        'code_expiry' => isset($settings['code_expiry']) ? $settings['code_expiry'] : 180,
        'show_delivery_extra' => ! empty( $delivery_extra['show_delivery_extra'] ),
        'shipping_intro_html' => isset( $delivery_extra['shipping_intro_html'] ) ? $delivery_extra['shipping_intro_html'] : '',
        'i18n' => array(
            'invalid_phone' => __('מספר טלפון לא תקין', 'deliz-short'),
            'code_sent' => __('קוד נשלח בהצלחה', 'deliz-short'),
            'error_sending' => __('שגיאה בשליחת הקוד', 'deliz-short'),
            'code_resent' => __('קוד נשלח מחדש', 'deliz-short'),
            'error_verifying' => __('שגיאה באימות הקוד', 'deliz-short'),
            'error_resending' => __('שגיאה בשליחה חוזרת של הקוד', 'deliz-short'),
        )
    ));
    
    // Checkout SMS popup CSS
    wp_enqueue_style(
        'checkout-sms-popup',
        get_template_directory_uri() . '/assets/css/checkout-sms-popup.css',
        array(),
        DELIZ_SHORT_VERSION
    );
}, 30);

// Enqueue checkout blocks styles and scripts
add_action('wp_enqueue_scripts', function() {
    if (!is_checkout()) {
        return;
    }
    
    wp_enqueue_style(
        'checkout-blocks',
        get_template_directory_uri() . '/assets/css/checkout-blocks.css',
        array(),
        DELIZ_SHORT_VERSION
    );
    
    wp_enqueue_script(
        'checkout-blocks',
        get_template_directory_uri() . '/assets/js/checkout-blocks.js',
        array('jquery'),
        DELIZ_SHORT_VERSION,
        true
    );
}, 25);

remove_action('woocommerce_after_shop_loop_item','woocommerce_template_loop_add_to_cart');

// Shortcode: [ed_basket_bar]
add_shortcode('ed_basket_bar', function () {
  if ( ! function_exists('WC') ) return '';
  ob_start();
  get_template_part('template-parts/floating-basket-bar');
  return ob_get_clean();
});

// Update automatically via Woo fragments (add/remove/qty changes)
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
  if ( ! function_exists('WC') ) return $fragments;

  ob_start();
  get_template_part('template-parts/floating-basket-bar');
  $fragments['#ed-basket-bar'] = ob_get_clean();

  return $fragments;
});

// JS: toggle body class basket-open
add_action('wp_enqueue_scripts', function () {
  if ( is_admin() ) return;

  $js = <<<JS
(function(){
  function toggleBasket(){
    document.body.classList.toggle('basket-open');
    var btn = document.getElementById('ed-basket-toggle');
    if (btn) btn.setAttribute('aria-expanded', document.body.classList.contains('basket-open') ? 'true' : 'false');
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('#ed-basket-toggle');
    if (!btn) return;
    e.preventDefault();
    toggleBasket();
  });

  // Optional: if cart fragments replace the button, nothing breaks because we use delegation above.
})();
JS;

  wp_add_inline_script('jquery', $js, 'after'); // נטען יחד עם jQuery שקיים בכל אתר Woo לרוב
});
