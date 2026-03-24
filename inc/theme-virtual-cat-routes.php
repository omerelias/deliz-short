<?php
/**
 * Rewrite rules, הפניות ו־template_redirect לנתיבי ‎/cat/…‎.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// מאפשר לקרוא get_query_var('mp_cat') ו-get_query_var('mp_product')
add_filter(
  'query_vars',
  function ( $vars ) {
    $vars[] = 'mp_cat';
    $vars[] = 'mp_product';

    return $vars;
  }
);

add_action(
  'init',
  function () {
    $front_id = (int) get_option( 'page_on_front' );

    if ( $front_id ) {
      add_rewrite_rule(
        '^cat/([^/]+)/?$',
        'index.php?page_id=' . $front_id . '&mp_cat=$matches[1]',
        'top'
      );
      add_rewrite_rule(
        '^cat/([^/]+)/product/([^/]+)/?$',
        'index.php?page_id=' . $front_id . '&mp_cat=$matches[1]&mp_product=$matches[2]',
        'top'
      );
    } else {
      add_rewrite_rule(
        '^cat/([^/]+)/?$',
        'index.php?mp_cat=$matches[1]',
        'top'
      );
      add_rewrite_rule(
        '^cat/([^/]+)/product/([^/]+)/?$',
        'index.php?mp_cat=$matches[1]&mp_product=$matches[2]',
        'top'
      );
    }
  },
  20
);

/**
 * מזהה מוצר publish לפי slug בנתיב ‎/cat/…/product/{slug}/‎.
 */
function ed_get_published_product_id_by_mp_slug( $slug ) {
  if ( ! is_string( $slug ) || $slug === '' ) {
    return 0;
  }

  $slug = rawurldecode( $slug );
  $slug = trim( $slug );
  if ( $slug === '' ) {
    return 0;
  }

  $post = get_page_by_path( $slug, OBJECT, 'product' );
  if ( $post instanceof WP_Post && $post->post_type === 'product' && $post->post_status === 'publish' ) {
    return (int) $post->ID;
  }

  $san = sanitize_title( $slug );
  if ( $san !== '' && $san !== $slug ) {
    $post = get_page_by_path( $san, OBJECT, 'product' );
    if ( $post instanceof WP_Post && $post->post_type === 'product' && $post->post_status === 'publish' ) {
      return (int) $post->ID;
    }
  }

  return 0;
}

// ‎/cat/{קטגוריה}/product/{מוצר}/‎ → 301 לעמוד המוצר המלא
add_action(
  'template_redirect',
  function () {
    $product_slug = get_query_var( 'mp_product', '' );
    if ( $product_slug === '' || $product_slug === null ) {
      return;
    }

    $product_id = ed_get_published_product_id_by_mp_slug( (string) $product_slug );
    if ( $product_id ) {
      wp_safe_redirect( get_permalink( $product_id ), 301 );
      exit;
    }

    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();

    $template_404 = get_query_template( '404' );
    if ( $template_404 ) {
      include $template_404;
    } else {
      wp_die( esc_html__( 'Page not found.', 'deliz-short' ), '', array( 'response' => 404 ) );
    }
    exit;
  },
  0
);

// ‎/cat/{slug}/‎ בלי product_cat תואם → 404 (חריג: ‎rebuy‎)
add_action(
  'template_redirect',
  function () {
    if ( ! get_query_var( 'mp_cat' ) || get_query_var( 'mp_product' ) ) {
      return;
    }

    $slug = ed_get_cat_slug_from_request();
    if ( $slug === '' ) {
      return;
    }

    if ( $slug === 'rebuy' ) {
      return;
    }

    $term = get_term_by( 'slug', $slug, 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
      return;
    }

    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();

    $template_404 = get_query_template( '404' );
    if ( $template_404 ) {
      include $template_404;
    } else {
      wp_die( esc_html__( 'Page not found.', 'deliz-short' ), '', array( 'response' => 404 ) );
    }
    exit;
  },
  5
);

add_action(
  'template_redirect',
  function () {
    if ( ! get_query_var( 'mp_cat' ) ) {
      return;
    }

    if ( get_query_var( 'mp_product' ) && is_404() ) {
      return;
    }

    if ( is_404() ) {
      global $wp_query;
      $wp_query->is_404 = false;
      status_header( 200 );
    }
  },
  10
);

add_filter(
  'redirect_canonical',
  function ( $redirect_url, $requested_url ) {
    if ( get_query_var( 'mp_cat' ) || get_query_var( 'mp_product' ) ) {
      return false;
    }

    $path = parse_url( $requested_url, PHP_URL_PATH ) ?: '';
    if ( preg_match( '#^/cat/[^/]+(/product/[^/]+)?/?$#', $path ) ) {
      return false;
    }

    return $redirect_url;
  },
  10,
  2
);
