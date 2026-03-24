<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('rest_api_init', function () {
  register_rest_route('ed/v1', '/products', [
    'methods'  => WP_REST_Server::READABLE, // GET
    'callback' => 'ed_rest_get_products_html',
    'permission_callback' => '__return_true',
    'args' => [
      'term'     => ['required' => true],
      'per_page' => ['default' => 12],
      'paged'    => ['default' => 1],
    ],
  ]);
});

function ed_rest_get_products_html(\WP_REST_Request $req) {
  $slug     = sanitize_title($req->get_param('term'));
  $per_page = max(1, min(48, (int) $req->get_param('per_page')));
  $paged    = max(1, (int) $req->get_param('paged'));

  $term = get_term_by('slug', $slug, 'product_cat');
  if (!$term || is_wp_error($term)) {
    return new \WP_REST_Response(['html' => '<p>קטגוריה לא נמצאה</p>'], 404);
  }

  $shortcode = sprintf(
    '[products category="%s" limit="%d" paginate="false" columns="2"]',
    esc_attr($slug),
    (int)$per_page
  );

  $response_data = [
    'term' => ['slug' => $slug, 'name' => $term->name],
    'html' => do_shortcode($shortcode),
  ];

  // ✅ Include cart fragments in AJAX response for sync
  if (function_exists('WC') && WC()->cart) {
    $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
    if (!empty($fragments)) {
      $response_data['fragments'] = $fragments;
      // Also include fragment hash for validation
      $response_data['fragment_hash'] = function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '';
    }
  }
  
  return new \WP_REST_Response($response_data, 200);
}

//קניה חוזרת
add_action('rest_api_init', function () {
  register_rest_route('ed/v1', '/rebuy-view', [
    'methods'  => WP_REST_Server::READABLE,
    'callback' => function () {
      if (!is_user_logged_in()) {
        return new WP_REST_Response(['html' => '<p>יש להתחבר כדי לצפות בהיסטוריית רכישה.</p>'], 401);
      }

      ob_start();

      // ✅ include של הקובץ
      $file = WP_CONTENT_DIR . '/themes/deliz-short/template-parts/product-history.php';
      if (file_exists($file)) {
        include $file;
      } else {
        echo '<p>קובץ תצוגה לא נמצא: ed-rebuy-view.php</p>';
      }

      $html = ob_get_clean();
      return new WP_REST_Response(['html' => $html], 200);
    },
    'permission_callback' => '__return_true', // נבדוק login בתוך callback
  ]);
});


function ed_rest_rebuy(\WP_REST_Request $req) {
  $mode     = $req->get_param('mode') === 'last' ? 'last' : 'all';
  $per_page = max(1, min(48, (int)$req->get_param('per_page')));

  $user_id = get_current_user_id();
  $cache_key = 'ed_rebuy_' . $user_id . '_' . $mode . '_' . $per_page;
  
  // ✅ Always include fresh cart fragments (don't cache them)
  $fragments = [];
  if (function_exists('WC') && WC()->cart) {
    $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
  }
  
  $cached = get_transient($cache_key);
  if ($cached && is_array($cached)) {
    $cached['fragments'] = $fragments;
    $cached['fragment_hash'] = function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '';
    return new \WP_REST_Response($cached, 200);
  }

  // סטטוסים לבחירה (שנה אם צריך רק completed)
  $orders = wc_get_orders([
    'customer_id' => $user_id,
    'status'      => ['completed'], // אפשר להוסיף processing לפי צורך
    'orderby'     => 'date',
    'order'       => 'DESC',
    'limit'       => ($mode === 'all') ? 50 : 1, // "all" - סורק עד 50 הזמנות אחרונות
    'return'      => 'objects',
  ]);

  if (empty($orders)) {
    $payload = [
      'title' => ($mode === 'last') ? 'שחזור הזמנה קודמת' : 'מוצרים שקניתי',
      'html'  => '<p>לא נמצאו רכישות קודמות.</p>',
      'count' => 0,
      'fragments' => $fragments,
      'fragment_hash' => function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '',
    ];
    // Cache without fragments (they're added fresh on each request)
    $cache_payload = $payload;
    unset($cache_payload['fragments'], $cache_payload['fragment_hash']);
    set_transient($cache_key, $cache_payload, 60);
    return new \WP_REST_Response($payload, 200);
  }

  $ids = [];

  if ($mode === 'last') {
    $last = $orders[0];
    foreach ($last->get_items('line_item') as $item) {
      $pid = (int) $item->get_product_id();
      if ($pid) $ids[] = $pid;
    }
    $ids = array_values(array_unique($ids));
  } else {
    // unique, בסדר "מההזמנה האחרונה" (הזמנות ממוין DESC)
    $seen = [];
    foreach ($orders as $order) {
      foreach ($order->get_items('line_item') as $item) {
        $pid = (int) $item->get_product_id();
        if (!$pid || isset($seen[$pid])) continue;
        $seen[$pid] = true;
        $ids[] = $pid;
      }
    }
  }

  if (empty($ids)) {
    $payload = [
      'title' => ($mode === 'last') ? 'שחזור הזמנה קודמת' : 'מוצרים שקניתי',
      'html'  => '<p>לא נמצאו מוצרים להצגה.</p>',
      'count' => 0,
      'fragments' => $fragments,
      'fragment_hash' => function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '',
    ];
    // Cache without fragments (they're added fresh on each request)
    $cache_payload = $payload;
    unset($cache_payload['fragments'], $cache_payload['fragment_hash']);
    set_transient($cache_key, $cache_payload, 60);
    return new \WP_REST_Response($payload, 200);
  }

  // הגבלה לתצוגה
  $ids = array_slice($ids, 0, $per_page);

  $shortcode = sprintf(
    '[products ids="%s" orderby="post__in" columns="2" paginate="false"]',
    esc_attr(implode(',', $ids))
  );

  $payload = [
    'title' => ($mode === 'last') ? 'שחזור הזמנה קודמת' : 'מוצרים שקניתי',
    'html'  => do_shortcode($shortcode),
    'count' => count($ids),
    'fragments' => $fragments,
    'fragment_hash' => function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '',
  ];

  // Cache without fragments (they're added fresh on each request)
  $cache_payload = $payload;
  unset($cache_payload['fragments'], $cache_payload['fragment_hash']);
  set_transient($cache_key, $cache_payload, 60);
  
  return new \WP_REST_Response($payload, 200);
}

//ajax search
add_action('rest_api_init', function () {
  register_rest_route('ed/v1', '/product-search', [
    'methods'  => 'GET',
    'permission_callback' => '__return_true',
    'args' => [
      'q'        => ['required' => true],
      'per_page' => ['required' => false],
      'columns'  => ['required' => false],
    ],
    'callback' => function (WP_REST_Request $req) {
      $q = trim((string) $req->get_param('q'));
      if ($q === '') return new WP_REST_Response(['html' => '', 'count' => 0], 200);

      $per_page = max(1, (int) $req->get_param('per_page'));
      $columns  = max(1, (int) $req->get_param('columns'));
      if (!$columns) $columns = 2; // ברירת מחדל

      $loop = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        's'              => $q,
        'posts_per_page' => $per_page,
        'no_found_rows'  => true,
        'tax_query'      => [
          [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => ['exclude-from-catalog'],
            'operator' => 'NOT IN',
          ],
        ],
      ]);

      ob_start();

      // ✅ wrapper כמו של ווקומרס shortcodes
      echo '<div class="woocommerce columns-' . (int)$columns . '">';

      if ($loop->have_posts()) {
        wc_get_template('loop/loop-start.php');
        while ($loop->have_posts()) { $loop->the_post(); wc_get_template_part('content', 'product'); }
        wc_get_template('loop/loop-end.php');
      } else {
        echo '<p class="woocommerce-info">לא נמצאו מוצרים.</p>';
      }

      echo '</div>';

      wp_reset_postdata();

      $response_data = [
        'html'  => ob_get_clean(),
        'count' => (int) $loop->post_count,
      ];

      // ✅ Include cart fragments in AJAX response for sync
      if (function_exists('WC') && WC()->cart) {
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
        if (!empty($fragments)) {
          $response_data['fragments'] = $fragments;
          $response_data['fragment_hash'] = function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '';
        }
      }

      return new WP_REST_Response($response_data, 200);
    }
  ]);
});

// ✅ New REST API endpoint for cart fragments
add_action('rest_api_init', function () {
  register_rest_route('ed/v1', '/cart-fragments', [
    'methods'  => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function (WP_REST_Request $req) {
      if (!function_exists('WC') || !WC()->cart) {
        return new \WP_REST_Response(['fragments' => [], 'fragment_hash' => ''], 200);
      }
      $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
      return new \WP_REST_Response([
        'fragments' => $fragments,
        'fragment_hash' => function_exists('wc_get_cart_hash') ? wc_get_cart_hash() : '',
      ], 200);
    }
  ]);
});
