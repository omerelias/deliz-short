<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//מספר מוצאים בשורה דסקטופ
add_filter('body_class', function($classes){

  // ACF Options page field
  $num = get_field('desktop_prod_num', 'option');
  $num_m = get_field('mobile_prod_num', 'option');
  $with_isons = get_field('menu_icons_show', 'option');

  // ניקוי/ולידציה
  $num = is_numeric($num) ? (int) $num : 0;
  $num_m = is_numeric($num_m) ? (int) $num_m : 0;

  if ($num > 0) {
    $classes[] = 'desktop-per-row-' . $num;
  }

  if ($num > 0) {
    $classes[] = 'mobile-per-row-' . $num_m;
  }

  if ($with_isons > 0) {
    $classes[] = 'menu-with-icons';
  }    

  return $classes;
});

//שורט קוד לסל צף
add_shortcode('ed_floating_cart', function () {
  if ( ! function_exists('WC') ) return '';
  ob_start();
  get_template_part('template-parts/floating-mini-cart');
  return ob_get_clean();
});

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
  if ( ! function_exists('WC') ) return $fragments;

  ob_start();
  get_template_part('template-parts/floating-mini-cart');
  $full = ob_get_clean();
  $fragments['#ed-float-cart'] = $full;

  // Header: title + free-shipping bar (deliz_short_float_cart_header_shipping) — keep in sync after partial cart AJAX.
  if ( preg_match( '/<header\s+class="ed-float-cart__header"[^>]*>[\s\S]*?<\/header>/u', $full, $m ) ) {
    $fragments['#ed-float-cart header.ed-float-cart__header'] = $m[0];
  }

  // Dedicated fragment: nested progress bar HTML breaks naive header regex; ensures bar returns after qty AJAX.
  ob_start();
  echo '<div class="ed-float-cart__header-shipping">';
  if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
    do_action( 'deliz_short_float_cart_header_shipping' );
  }
  echo '</div>';
  $fragments['#ed-float-cart .ed-float-cart__header-shipping'] = ob_get_clean();

  // Checkout CTA + ~ running total (non-empty cart only in template).
  if ( preg_match( '/<div\s+class="ed-float-cart__actions"[^>]*>[\s\S]*?<\/div>/u', $full, $m ) ) {
    $fragments['#ed-float-cart .ed-float-cart__actions'] = $m[0];
  }

  // Totals + coupon form + CTA live in the footer; mini-cart JS can update after coupon apply/remove
  // without relying only on wc_fragment_refresh (cart-fragments script may be absent).
  if ( preg_match( '/<footer\s+class="ed-float-cart__footer"[^>]*>[\s\S]*?<\/footer>/u', $full, $m ) ) {
    $fragments['#ed-float-cart footer.ed-float-cart__footer'] = $m[0];
  }

  return $fragments;
});

/**
 * מחיר משלוח ב־#ocws-delivery-data-chip — רינדור אחרון אחרי עדכון הסל (fragments מ-WC / פופאפ / REST),
 * כדי שהמספר יתאים לכמות ולסה״כ אחרי הוספה לסל ושינויי כמות.
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'deliz_short_fragment_ocws_delivery_chip', 50, 1 );
function deliz_short_fragment_ocws_delivery_chip( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}
	if ( ! class_exists( 'Oc_Woo_Shipping_Public', false ) || ! is_callable( array( 'Oc_Woo_Shipping_Public', 'show_chip_in_cart' ) ) ) {
		return $fragments;
	}
	ob_start();
	Oc_Woo_Shipping_Public::show_chip_in_cart();
	$html = ob_get_clean();
	if ( $html !== '' ) {
		$fragments['div#ocws-delivery-data-chip'] = $html;
	}
	return $fragments;
}

// Shipping popup: do not show on page load — only when user adds to cart or clicks delivery chip
add_action('wp_footer', function () {
  if (is_checkout()) return;
  ?>
  <script>
  (function(){
    function hideShippingPopupOnLoad() {
      var popup = document.querySelector('.choose-shipping-popup');
      if (popup && popup.classList.contains('shown')) {
        popup.classList.remove('shown');
        document.body.style.overflow = '';
      }
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function run() {
        document.removeEventListener('DOMContentLoaded', run);
        setTimeout(hideShippingPopupOnLoad, 0);
      });
    } else {
      setTimeout(hideShippingPopupOnLoad, 0);
    }
  })();
  </script>
  <?php
}, 2);

// Product Popup functionality is loaded from includes/product-popup/class-product-popup.php

add_action( 'wp_footer', 'overlay_bg' );
function overlay_bg(){
  echo '<div class="site-overlay"></div>';
}

/**
 * oc-woo-shipping: ממזג billing_floor / billing_apartment / billing_enter_code לקבוצת השדות `ocws`
 * כדי להציגם שוב בתוך #oc-woo-shipping-additional — כפילות מול .woocommerce-billing-fields-part-2 ב-form-shipping.
 * בלי המיזוג השדות נשארים רק בבלוק part-2 (מספיק לצ'קאאוט; הפופאפ משתמש ב-billing ישירות).
 */
add_filter(
	'ocws_merge_into_ocws_group_fields',
	static function () {
		return array();
	},
	999
);

// Checkout / header SMS popup (guests)
add_action(
	'wp_footer',
	function () {
		if ( is_user_logged_in() ) {
			return;
		}
		get_template_part( 'template-parts/checkout-sms-popup' );
	},
	15
);

function print_menu_shortcode($atts, $content = null) {
    extract(shortcode_atts(array( 'name' => null, ), $atts));
    return wp_nav_menu( array( 'menu' => $name, 'echo' => false ) );
}
add_shortcode('oc_menu', 'print_menu_shortcode');

/**
 * AJAX: current ocws_shipping_popup_confirmed for floating-cart checkout gate.
 */
function deliz_short_ajax_ocws_shipping_status() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		wp_send_json_success( array( 'confirmed' => true ) );
		return;
	}
	if ( ! class_exists( 'OCWS_Popup', false ) ) {
		wp_send_json_success( array( 'confirmed' => true ) );
		return;
	}
	wp_send_json_success(
		array(
			'confirmed' => (bool) WC()->session->get( 'ocws_shipping_popup_confirmed' ),
		)
	);
}
add_action( 'wp_ajax_deliz_ocws_shipping_status', 'deliz_short_ajax_ocws_shipping_status' );
add_action( 'wp_ajax_nopriv_deliz_ocws_shipping_status', 'deliz_short_ajax_ocws_shipping_status' );


add_action(
	'woocommerce_before_checkout_form',
	function () {
      $custom_logo_id = get_theme_mod('custom_logo');      
      $site_name = get_bloginfo('name');
      $logo = '<a href="' . esc_url(home_url('/')) . '"><img src="' . esc_url(wp_get_attachment_image_url($custom_logo_id, 'full')) . '" alt="' . esc_attr($site_name) . '"></a>';
      if(get_field('checkout_logo', 'option')){
        $logo = '<a href="' . esc_url(home_url('/')) . '"><img src="' . esc_url(get_field('checkout_logo', 'option')) . '" alt="' . esc_attr($site_name) . '"></a>';
      }

      echo '<div class="checkout_logo">"'.$logo.'"</div>';
	},
	15
);

add_filter('body_class', function ($classes) {
    $show_top_header = function_exists('get_field')
        ? get_field('mobile_show_top_header', 'option')
        : 0;

    if ((int) $show_top_header == 1) {
        $classes[] = 'show-mobile-top-header';
    }

    return $classes;
});