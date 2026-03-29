<?php
/**
 * Front bootstrap: assets, ACF, REST, shortcodes דף הבית, WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// פופאפ מוצר (REST, AJAX, נכסים) — מחלקה ראשית של תוספת המוצר בחלון
require_once get_template_directory() . '/includes/product-popup/class-product-popup.php';

require_once get_template_directory() . '/inc/class-meat-category-age-verification.php';
Meat_Category_Age_Verification::instance();

// עזרי slug לנתיב ‎/cat/…‎ — משותף ל-routes ול-SEO
require_once get_template_directory() . '/inc/mp-cat-helpers.php';
// rewrite ‎/cat/{slug}/‎ ו־‎/cat/…/product/…‎, template_redirect (301 מוצר, 404 קטגוריה/מוצר), redirect_canonical
require_once get_template_directory() . '/inc/theme-virtual-cat-routes.php';
// canonical, title/meta, robots (כולל rebuy), Yoast/Rank Math, breadcrumbs
require_once get_template_directory() . '/inc/theme-seo.php';

// דפי אפשרויות ACF + כתיבת ‎assets/css/theme-options.css‎ אוטומטית
require_once get_template_directory() . '/inc/theme-acf-options.php';
// טעינת סקריפטים וסטיילים: Slick, main, קופון לסל, SMS צ’קאאוט, בלוקי צ’קאאוט, סל תחתון, inline לסל צף
require_once get_template_directory() . '/inc/theme-enqueue.php';
// REST ‎ed/v1‎: מוצרים לפי קטגוריה, rebuy-view, ‎ed_rest_rebuy‎, חיפוש מוצרים, cart-fragments
require_once get_template_directory() . '/inc/theme-rest-ed-v1.php';
// דף הבית: סליידר, ‎[ed_menu_sidebar]‎, ‎[ed_products_box]‎, JS משותף לתפריט+מוצרים (AJAX)
require_once get_template_directory() . '/inc/theme-ed-homepage-shop.php';
// ממשק חנות: body_class, סל צף, משלוחים, התחברות/overlay, תבנית SMS, ‎[oc_menu]‎
require_once get_template_directory() . '/inc/theme-shop-frontend.php';
// WooCommerce נוסף: שדות צ’קאאוט, הרשמה, קופון, פופאפ משקל, כפתורי תשלום וכו’
require_once get_template_directory() . '/inc/theme-woocommerce-extra.php';
