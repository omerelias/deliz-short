<?php
/**
 * SEO לנתיבי ‎/cat/{slug}/‎: canonical, title, meta, robots, breadcrumbs (Yoast).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

function ed_get_cat_canonical_url() {
  if ( get_query_var( 'mp_product' ) ) {
    return '';
  }

  $slug = ed_get_cat_slug_from_request();
  if ( ! $slug ) {
    return '';
  }

  $term = get_term_by( 'slug', $slug, 'product_cat' );
  if ( ! $term || is_wp_error( $term ) ) {
    return '';
  }

  return home_url( '/cat/' . $slug . '/' );
}

/**
 * קטגוריית מוצר תקפה לפי ‎mp_cat‎ (לכותרת ומטא).
 *
 * @return WP_Term|null
 */
function ed_get_mp_cat_product_term() {
  if ( get_query_var( 'mp_product' ) ) {
    return null;
  }

  $slug = ed_get_cat_slug_from_request();
  if ( $slug === '' ) {
    return null;
  }

  $term = get_term_by( 'slug', $slug, 'product_cat' );
  if ( ! $term || is_wp_error( $term ) ) {
    return null;
  }

  return $term;
}

function ed_get_mp_cat_seo_document_title( WP_Term $term ) {
  $sep = apply_filters( 'document_title_separator', '-' );

  return $term->name . ' ' . $sep . ' ' . get_bloginfo( 'name', 'display' );
}

function ed_get_mp_cat_seo_description( WP_Term $term ) {
  $raw = term_description( $term->term_id, 'product_cat' );
  $raw = wp_strip_all_tags( (string) $raw );
  $raw = preg_replace( '/\s+/u', ' ', $raw );
  $raw = trim( $raw );
  if ( $raw !== '' ) {
    if ( function_exists( 'mb_substr' ) ) {
      return mb_substr( $raw, 0, 155 );
    }

    return substr( $raw, 0, 155 );
  }

  return sprintf(
    /* translators: 1: category name, 2: site name */
    __( 'מוצרים בקטגוריית %1$s — %2$s', 'deliz-short' ),
    $term->name,
    get_bloginfo( 'name', 'display' )
  );
}

add_filter(
  'document_title_parts',
  function ( $parts ) {
    $term = ed_get_mp_cat_product_term();
    if ( ! $term ) {
      return $parts;
    }
    $parts['title'] = $term->name;

    return $parts;
  },
  20
);

add_filter(
  'wpseo_title',
  function ( $title ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_document_title( $term ) : $title;
  }
);

add_filter(
  'wpseo_metadesc',
  function ( $desc ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_description( $term ) : $desc;
  }
);

add_filter(
  'wpseo_opengraph_title',
  function ( $title ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_document_title( $term ) : $title;
  }
);

add_filter(
  'wpseo_opengraph_desc',
  function ( $desc ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_description( $term ) : $desc;
  }
);

add_filter(
  'rank_math/frontend/title',
  function ( $title ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_document_title( $term ) : $title;
  }
);

add_filter(
  'rank_math/frontend/description',
  function ( $desc ) {
    $term = ed_get_mp_cat_product_term();

    return $term ? ed_get_mp_cat_seo_description( $term ) : $desc;
  }
);

add_filter(
  'wpseo_robots',
  function ( $robots ) {
    return ed_is_mp_cat_rebuy_route() ? 'noindex, follow' : $robots;
  },
  20
);

add_filter(
  'rank_math/robots',
  function ( $robots ) {
    if ( ! ed_is_mp_cat_rebuy_route() ) {
      return $robots;
    }
    if ( is_array( $robots ) ) {
      $robots['index'] = 'noindex';

      return $robots;
    }

    return 'noindex, follow';
  },
  99
);

add_filter(
  'wp_robots',
  function ( array $robots ) {
    if ( ed_is_mp_cat_rebuy_route() ) {
      $robots['noindex'] = true;
    }

    return $robots;
  },
  20
);

add_action(
  'wp_head',
  function () {
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
      return;
    }
    $term = ed_get_mp_cat_product_term();
    if ( ! $term ) {
      return;
    }
    $desc = ed_get_mp_cat_seo_description( $term );
    echo '<meta name="description" content="' . esc_attr( $desc ) . "\" />\n";
  },
  2
);

add_action(
  'wp_head',
  function () {
    $canon = ed_get_cat_canonical_url();
    if ( ! $canon ) {
      return;
    }

    echo '<link rel="canonical" href="' . esc_url( $canon ) . "\" />\n";
  },
  1
);

add_filter(
  'wpseo_canonical',
  function ( $canonical ) {
    $canon = ed_get_cat_canonical_url();

    return $canon ? $canon : $canonical;
  }
);

add_filter(
  'rank_math/frontend/canonical',
  function ( $canonical ) {
    $canon = ed_get_cat_canonical_url();

    return $canon ? $canon : $canonical;
  }
);

add_filter(
  'wpseo_breadcrumb_links',
  function ( $links ) {
    if ( ! get_query_var( 'mp_cat' ) || get_query_var( 'mp_product' ) ) {
      return $links;
    }

    if ( ! is_array( $links ) ) {
      $links = [];
    }

    $slug = ed_get_cat_slug_from_request();
    if ( $slug === '' ) {
      return $links;
    }

    $link_url_exists = static function ( array $links, $target ) {
      $t = untrailingslashit( $target );
      foreach ( $links as $item ) {
        if ( empty( $item['url'] ) ) {
          continue;
        }
        if ( untrailingslashit( $item['url'] ) === $t ) {
          return true;
        }
      }

      return false;
    };

    $ensure_home_crumb = static function () use ( &$links ) {
      if ( ! empty( $links ) ) {
        return;
      }
      $links[] = [
        'url'  => home_url( '/' ),
        'text' => __( 'דף הבית', 'deliz-short' ),
      ];
    };

    if ( $slug === 'rebuy' ) {
      $target = home_url( '/cat/rebuy/' );
      if ( $link_url_exists( $links, $target ) ) {
        return $links;
      }
      $ensure_home_crumb();
      $links[] = [
        'url'  => $target,
        'text' => __( 'קנייה חוזרת', 'deliz-short' ),
      ];

      return $links;
    }

    $term = get_term_by( 'slug', $slug, 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) {
      return $links;
    }

    $url = ed_get_cat_canonical_url();
    if ( ! $url ) {
      return $links;
    }
    if ( $link_url_exists( $links, $url ) ) {
      return $links;
    }

    $ensure_home_crumb();
    $links[] = [
      'url'  => $url,
      'text' => $term->name,
    ];

    return $links;
  },
  20
);

add_filter(
  'wpseo_breadcrumb_single_link',
  function ( $link_output, $link ) {
    if ( ! empty( $link['url'] ) && $link['url'] === home_url( '/' ) ) {
      $text = __( 'דף הבית', 'deliz-short' );
      $link_output = sprintf(
        '<a href="%s">%s</a>',
        esc_url( $link['url'] ),
        esc_html( $text )
      );
    }

    return $link_output;
  },
  10,
  2
);
