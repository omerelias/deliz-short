<?php
/**
 * עזרי נתיב וירטואלי ‎/cat/{slug}/‎ (משותף ל־routes + SEO).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * מחזיר את slug הקטגוריה מ־mp_cat או מנתיב ה-URL.
 */
function ed_get_cat_slug_from_request() {
  $slug = (string) get_query_var( 'mp_cat' );
  if ( $slug ) {
    return sanitize_title( $slug );
  }

  $path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '';
  if ( preg_match( '#^/cat/([^/]+)/?$#', $path, $m ) ) {
    return sanitize_title( $m[1] );
  }

  return '';
}

/**
 * ‎/cat/rebuy/‎ — נתיב קנייה חוזרת (לא product_cat).
 */
function ed_is_mp_cat_rebuy_route() {
  if ( get_query_var( 'mp_product' ) ) {
    return false;
  }

  return ( ed_get_cat_slug_from_request() === 'rebuy' );
}
