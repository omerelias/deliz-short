<?php

// Load Product Popup functionality
require_once get_template_directory() . '/includes/product-popup/class-product-popup.php';

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
  wp_enqueue_script('deliz-short-main', $js, [], DELIZ_SHORT_VERSION, true);

  // Product Popup functionality is loaded from includes/product-popup/class-product-popup.php
});

add_action('wp_enqueue_scripts', function () {
  $rel  = 'assets/css/theme-options.css';
  $path = trailingslashit(get_stylesheet_directory()) . $rel;
  $url  = trailingslashit(get_stylesheet_directory_uri()) . $rel;

  $ver = file_exists($path) ? filemtime($path) : null;

  wp_enqueue_style('theme-options', $url, [], $ver);
}, 20);


add_action('acf/init', function () {
  if ( ! function_exists('acf_add_options_page') ) return;

  acf_add_options_page([
    'page_title'  => 'הגדרות אתר',
    'menu_title'  => 'הגדרות אתר',
    'menu_slug'   => 'site-settings',
    'capability'  => 'manage_options',
    'redirect'    => false,
    'position'    => 59,
    'icon_url'    => 'dashicons-admin-generic',
    'update_button' => 'שמור',
    'updated_message' => 'ההגדרות נשמרו',
  ]);
});

if ( function_exists('acf_add_options_page') ) {
  acf_add_options_page([
    'page_title' => 'סליידר ראשי',
    'menu_title' => 'סליידר ראשי',
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

//main slider
add_shortcode('ed_main_slider', function () {
  if ( ! have_rows('slider_settings', 'option') ) return '';

  // נחשב כמה באנרים כדי להחליט אם slick
  $rows = [];
  while ( have_rows('slider_settings', 'option') ) {
    the_row();
    $rows[] = [
      'desktop_image'     => get_sub_field('desktop_image'),
      'mobile_image'      => get_sub_field('mobile_image'),
      'text_on_image'     => (string) get_sub_field('text_on_image'),
      'btn_on_image'      => get_sub_field('btn_on_image'),
      'content_placement' => (string) get_sub_field('content_placement'),
    ];
  }

  $count = count($rows);
  if ( $count === 0 ) return '';

  ob_start(); ?>
  <div class="ed-main-slider <?php echo $count > 1 ? 'js-ed-main-slider' : 'is-static'; ?>">
    
    <?php $i = 0; foreach ($rows as $row):
        $i++;
      $placement = in_array($row['content_placement'], ['right','center','left'], true) ? $row['content_placement'] : 'center';

      $d = is_array($row['desktop_image']) ? $row['desktop_image'] : null;
      $m = is_array($row['mobile_image'])  ? $row['mobile_image']  : null;

      $desktop_url = $d['url'] ?? '';
      $mobile_url  = $m['url'] ?? '';
      $alt         = $d['alt'] ?? ($m['alt'] ?? '');

      if ( ! $desktop_url ) continue;

      $text = trim($row['text_on_image']);

      $btn  = is_array($row['btn_on_image']) ? $row['btn_on_image'] : null;
      $btn_url    = $btn['url'] ?? '';
      $btn_title  = $btn['title'] ?? '';
      $btn_target = $btn['target'] ?? '';

      echo '<div class="ed-slide ed-place--'.esc_attr($placement).'" data-ed-slide="'.(int)$i.'">';
      ?>
        <picture class="ed-slide__media">
          <?php if ($mobile_url): ?>
            <source media="(max-width: 767px)" srcset="<?php echo esc_url($mobile_url); ?>">
          <?php endif; ?>
          <img class="ed-slide__img" src="<?php echo esc_url($desktop_url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy">
        </picture>

        <?php if ($text || ($btn_url && $btn_title)): ?>
          <div class="ed-slide__content">
            <?php if ($text): ?>
              <div class="ed-slide__text"><?php echo esc_html($text); ?></div>
            <?php endif; ?>

            <?php if ($btn_url && $btn_title): ?>
              <a class="ed-slide__btn"
                 href="<?php echo esc_url($btn_url); ?>"
                 <?php echo $btn_target ? ' target="'.esc_attr($btn_target).'" rel="noopener noreferrer"' : ''; ?>>
                 <?php echo esc_html($btn_title); ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
});

//menu shortcode
add_shortcode('ed_menu_sidebar', function ($atts) {
  $atts = shortcode_atts([
    'menu'  => 'תפריט קטגוריות',
    'class' => 'ed-mp-sidebar',
  ], $atts, 'ed_menu_sidebar');

  $menu_obj = wp_get_nav_menu_object($atts['menu']);
  if (!$menu_obj) return '';

  $items = wp_get_nav_menu_items($menu_obj->term_id);
  if (empty($items)) return '';

  $cats = [];
  foreach ($items as $it) {
    if (($it->object ?? '') !== 'product_cat' || empty($it->object_id)) continue;
    $term = get_term((int)$it->object_id, 'product_cat');
    if (!$term || is_wp_error($term)) continue;

    $icon_url = function_exists('get_field') ? (string) get_field('menu_item_icon', (int)$it->ID) : '';
    $cats[] = [
      'title'    => $it->title,
      'slug'     => $term->slug,
      'icon_url' => $icon_url,
    ];
  }

  if (is_user_logged_in()) {
    array_unshift($cats, [
      'title'    => 'קנייה חוזרת',
      'slug'     => 'rebuy',
      'icon_url' => 'https://deliz-short.mywebsite.co.il/wp-content/uploads/2026/01/%D7%91%D7%A9%D7%A8-%D7%91%D7%A7%D7%A8.jpg', // אם תרצה אייקון קבוע תגיד לי
    ]);
  }

  if (!$cats) return '';

  // base URL = current page
  $page_url = get_permalink();
  $default_slug = $cats[0]['slug'];

  // enqueue shared JS once
  wp_register_script('ed-menu-products', false, [], null, true);
  wp_enqueue_script('ed-menu-products');

$config = [
  'endpoint'     => rest_url('ed/v1/products'),
  'rebuyEndpoint' => rest_url('ed/v1/rebuy'),
  'restNonce'     => wp_create_nonce('wp_rest'),
  'pageUrl'      => $page_url,
  'defaultSlug'  => $default_slug,
  'perPage'      => 12,
  'productsSelector' => '[data-ed-products-box="1"]',
  'titleSelector'    => '[data-ed-products-title="1"]',
  'menuSelector'     => '[data-ed-term]',
  'catBase' => home_url('/cat/'),
  'searchEndpoint'       => rest_url('ed/v1/product-search'),
  'searchInputSelector'  => '#search_q',
  'searchWrapSelector'   => '.search',
  'cartFragmentsEndpoint' => rest_url('ed/v1/cart-fragments'),
];

  wp_add_inline_script('ed-menu-products', 'window.ED_MENU_PRODUCTS=' . wp_json_encode($config) . ';', 'before');
  wp_add_inline_script('ed-menu-products', ed_menu_products_js_shared(), 'after');

  ob_start(); ?>
    <div class="<?php echo esc_attr($atts['class']); ?>" data-ed-menu-sidebar="1">
      <ul class="ed-mp__menu">
        <?php
          $menu_icons_show = get_field('menu_icons_show', 'option');
          $menu_icons_round = get_field('menu_icons_round', 'option');
        ?>
        <?php foreach ($cats as $c):
          $href = home_url('/cat/' . $c['slug'] . '/');
          ?>
          <li class="ed-mp__item">
            <a class="ed-mp__link"
               href="<?php echo esc_url($href); ?>"
               data-ed-term="<?php echo esc_attr($c['slug']); ?>">
              <?php if($menu_icons_show): ?>
                <?php if (!empty($c['icon_url'])): ?>                
                  <img class="ed-mp__icon icon-style-<?php echo $menu_icons_round; ?>" src="<?php echo esc_url($c['icon_url']); ?>" alt="" loading="lazy" />
                <?php endif; ?>
              <?php endif; ?>
              <span class="ed-mp__text"><?php echo esc_html($c['title']); ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php
  return ob_get_clean();
});

//items area
add_shortcode('ed_products_box', function ($atts) {
  $atts = shortcode_atts([
    'class' => 'ed-mp-products-wrap',
    'title_tag' => 'h2',
    'per_page' => 12,
    'columns' => 2,
  ], $atts, 'ed_products_box');

  $tag = tag_escape($atts['title_tag']);

  $title_html = '';
  $products_html = '';

  // ✅ אם נכנסו ישירות ל /cat/{slug}/ אז mp_cat קיים מה-rewrite
  $slug = get_query_var('mp_cat');
  if ($slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term && !is_wp_error($term)) {
      $title_html = esc_html($term->name);
      $products_html = do_shortcode(sprintf(
        '[products category="%s" limit="%d" paginate="false" columns="%d"]',
        esc_attr($slug),
        (int)$atts['per_page'],
        (int)$atts['columns']
      ));
    }
  }

  return '
  <div class="'. esc_attr($atts['class']) .'">
    <'.$tag.' class="ed-mp__title" data-ed-products-title="1">'.$title_html.'</'.$tag.'>
    <div class="ed-mp__products" data-ed-products-box="1" aria-live="polite">'.$products_html.'</div>
  </div>';
});

//טאבים של קניה אחרונה
add_action('rest_api_init', function () {
  register_rest_route('ed/v1', '/rebuy', [
    'methods'  => WP_REST_Server::READABLE, // GET
    'callback' => 'ed_rest_rebuy',
    'permission_callback' => function () {
      return is_user_logged_in();
    },
    'args' => [
      'mode'     => ['default' => 'all'], // all | last
      'per_page' => ['default' => 12],
    ],
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


function ed_menu_products_js_shared() {
  return <<<JS
(function () {
  const cfg = window.ED_MENU_PRODUCTS;
  if (!cfg) return;

  const box   = document.querySelector(cfg.productsSelector);
  const title = document.querySelector(cfg.titleSelector);
  if (!box) return;

  const links = () => Array.from(document.querySelectorAll(cfg.menuSelector));
  let controller = null;
  let current = null;
  let lastTerm = null;
  let beforeSearch = null;

  function getTermFromUrl() {
    const u = new URL(location.href);

    // 1) Pretty URL: /cat/{slug}/
    const p = u.pathname.replace(/\\/+$/, '');
    const parts = p.split('/').filter(Boolean);
    const i = parts.indexOf('cat');
    if (i !== -1 && parts[i + 1]) return parts[i + 1];

    // 2) Fallback old query param
    return u.searchParams.get('mp_cat') || '';
  }

  function setActive(term) {
    links().forEach(a => a.classList.toggle('is-active', a.dataset.edTerm === term));
  }

  function setTitle(text) {
    if (!title) return;
    title.textContent = text || '';
  }

  function debounce(fn, wait = 300) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }


  async function loadTerm(term, {push=false} = {}) {
    lastTerm = term;
    term = term || cfg.defaultSlug;
    if (!term || term === current) return;

    current = term;
    setActive(term);

    if (controller) controller.abort();
    controller = new AbortController();

    box.classList.add('is-loading');

    // טייטל זמני מהלינק בתפריט (כדי שלא יהיה ריק עד שהשרת חוזר)
    const link = links().find(a => a.dataset.edTerm === term);
    if (link) setTitle(link.textContent.trim());

    // ✅ מצב "קנייה חוזרת"
    if (term === 'rebuy') {
      await loadRebuy({ push });
      return;
    }

    const url = new URL(cfg.endpoint);
    url.searchParams.set('term', term);
    url.searchParams.set('per_page', cfg.perPage);

    try {
      const res = await fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error('Request failed');
      const data = await res.json();

      // html של מוצרים
      box.innerHTML = data.html || '';
      box.classList.remove('is-loading');

      // שם קטגוריה מדויק מהשרת
      if (data.term && data.term.name) setTitle(data.term.name);

      // ✅ Update cart fragments if provided (for AJAX navigation sync)
      if (data.fragments && typeof data.fragments === 'object') {
        updateCartFragments(data.fragments);
      }

      // עדכון URL: /cat/{slug}/
      if (push) {
        const base = (cfg.catBase || '/cat/').replace(/\\/+$/, '/') ; // מבטיח / בסוף
        const newUrl = new URL(base + term + '/', window.location.origin);
        history.pushState({term}, '', newUrl.toString());
      }
    } catch (e) {
      if (e.name === 'AbortError') return;
      box.classList.remove('is-loading');
      box.innerHTML = '<p>שגיאה בטעינה. נסה שוב.</p>';
    }
  }

  async function loadRebuy({push=false} = {}) {
  if (!cfg.rebuyEndpoint) return;

  current = 'rebuy';
  setActive('rebuy');
  setTitle('קנייה חוזרת');

  box.classList.add('is-loading');

  const makeTabs = () => {
    return `
      <div class="ed-rb">
        <div class="ed-rb__tabs" role="tablist">
          <button class="ed-rb__tab is-active" type="button" data-rb-tab="all">מוצרים שקניתי</button>
          <button class="ed-rb__tab" type="button" data-rb-tab="last">שחזור הזמנה קודמת</button>
        </div>
        <div class="ed-rb__panel" data-rb-panel="1"></div>
      </div>
    `;
  };

  box.innerHTML = makeTabs();
  const panel = box.querySelector('[data-rb-panel="1"]');

  async function fetchMode(mode) {
    const url = new URL(cfg.rebuyEndpoint);
    url.searchParams.set('mode', mode);
    url.searchParams.set('per_page', cfg.perPage);

    const res = await fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': cfg.restNonce
      }
    });

    if (!res.ok) throw new Error('rebuy failed: ' + res.status);
    return await res.json();
  }

  try {
    // טוענים ברירת מחדל "all"
    const data = await fetchMode('all');
    panel.innerHTML = data.html || '';
    box.classList.remove('is-loading');

    // ✅ Update cart fragments if provided
    if (data.fragments && typeof data.fragments === 'object') {
      updateCartFragments(data.fragments);
    }

    if (push) {
      const base = (cfg.catBase || '/cat/').replace(/\/+$/, '/');
      history.pushState({term: 'rebuy'}, '', new URL(base + 'rebuy' + '/', window.location.origin).toString());
    }

    // קליקים על טאבים
    box.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-rb-tab]');
      if (!btn) return;

      const mode = btn.dataset.rbTab;
      box.querySelectorAll('.ed-rb__tab').forEach(b => b.classList.toggle('is-active', b === btn));

      panel.innerHTML = '';
      box.classList.add('is-loading');

      const d = await fetchMode(mode);
      panel.innerHTML = d.html || '';
      box.classList.remove('is-loading');
      
      // ✅ Update cart fragments if provided
      if (d.fragments && typeof d.fragments === 'object') {
        updateCartFragments(d.fragments);
      }
    }, { once: true });

  } catch (e) {
    box.classList.remove('is-loading');
    box.innerHTML = '<p>שגיאה בטעינת קנייה חוזרת.</p>';
  }
}


  function getSearchWrap() {
    return document.querySelector(cfg.searchWrapSelector || '.search');
  }

  function getSearchInput() {
    return document.querySelector(cfg.searchInputSelector || '#search_q');
  }

  function ensureSearchUI() {
    const wrap = getSearchWrap();
    if (!wrap) return;

    // כפתור זכוכית
    if (!wrap.querySelector('.ed-search-btn')) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'ed-search-btn';
      b.setAttribute('aria-label', 'חפש');
      b.innerHTML = '🔍';
      wrap.appendChild(b);
    }

    // כפתור X
    if (!wrap.querySelector('.ed-search-clear')) {
      const c = document.createElement('button');
      c.type = 'button';
      c.className = 'ed-search-clear';
      c.setAttribute('aria-label', 'נקה חיפוש');
      c.style.display = 'none';
      c.innerHTML = '✕';
      wrap.appendChild(c);
    }
  }

  function showClear(show) {
    const wrap = getSearchWrap();
    if (!wrap) return;
    const c = wrap.querySelector('.ed-search-clear');
    if (c) c.style.display = show ? 'inline-flex' : 'none';
  }

  function stripQueryFromUrl() {
    const u = new URL(location.href);
    u.searchParams.delete('q');
    history.pushState({term: lastTerm || current || cfg.defaultSlug}, '', u.toString());
  }

  async function loadSearch(q, {push=false} = {}) {
    const input = getSearchInput();
    const query = String(q || (input ? input.value : '') || '').trim();
    if (!query) return;

    q = (q || '').trim();
    if (!q) return;

    // שמירת מצב לפני חיפוש פעם אחת
    if (!beforeSearch) {
      beforeSearch = {
        html: box.innerHTML,
        title: title ? title.textContent : '',
        term: current || lastTerm || cfg.defaultSlug,
        url: location.href
      };
    }

    // UI
    setActive('');               // אין active קטגוריה בזמן חיפוש
    setTitle(`תוצאות חיפוש עבור - \${query}`);  // הכותרת לפי דרישתך
    showClear(true);

    if (controller) controller.abort();
    controller = new AbortController();

    box.classList.add('is-loading');

    const url = new URL(cfg.searchEndpoint);
    url.searchParams.set('q', query);
    url.searchParams.set('per_page', cfg.perPage);

    try {
      const res = await fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error('Search failed');
      const data = await res.json();

      box.innerHTML = data.html || '';
      box.classList.remove('is-loading');

      // ✅ Update cart fragments if provided
      if (data.fragments && typeof data.fragments === 'object') {
        updateCartFragments(data.fragments);
      }

    } catch (e) {
      if (e.name === 'AbortError') return;
      box.classList.remove('is-loading');
      box.innerHTML = '<p>שגיאה בחיפוש. נסה שוב.</p>';
    }
  }

  function clearSearch() {
    const input = getSearchInput();
    if (input) input.value = '';

    // החזר מצב קודם
    if (beforeSearch) {
      box.innerHTML = beforeSearch.html || '';
      setTitle(beforeSearch.title || '');

      // תחזיר active קטגוריה
      const backTerm = beforeSearch.term || cfg.defaultSlug;
      current = null; // כדי לא לחסום loadTerm בגלל "term === current"
      loadTerm(backTerm, {push: true}); // גם יעדכן URL ל /cat/{slug}/
      beforeSearch = null;
    } else {
      // fallback: פשוט חזור לקטגוריה אחרונה
      current = null;
      loadTerm(lastTerm || cfg.defaultSlug, {push: true});
    }

    showClear(false);
    //stripQueryFromUrl();
  }

  function bindSearch() {
    ensureSearchUI();

    const wrap = getSearchWrap();
    const input = getSearchInput();
    if (!wrap || !input) return;

    const btn = wrap.querySelector('.ed-search-btn');
    const clearBtn = wrap.querySelector('.ed-search-clear');

    const run = () => {
      const query = (input.value || '').trim();
      if (!query) return;
      loadSearch(query, {push: false});
    };

  const MIN_LEN = 2;

  const runLive = debounce(() => {
    const query = (input.value || '').trim();
    if (!query) { if (beforeSearch) clearSearch(); return; }
    if (query.length < MIN_LEN) return;
    loadSearch(query, { push: false });
  }, 300);

  input.addEventListener('input', (e) => {
    e.stopPropagation();
    e.stopImmediatePropagation();
    runLive();
  }, true);

    btn && btn.addEventListener('click', run);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); run(); }
      if (e.key === 'Escape') { e.preventDefault(); clearSearch(); }
    });

    clearBtn && clearBtn.addEventListener('click', clearSearch);
  }

  // להפעיל אחרי שהדף נטען
  document.addEventListener('DOMContentLoaded', bindSearch);

  // ✅ Helper function to update cart fragments
  function updateCartFragments(fragments) {
    if (!fragments || typeof fragments !== 'object') return;
    
    Object.keys(fragments).forEach(selector => {
      const element = document.querySelector(selector);
      if (element && fragments[selector]) {
        element.outerHTML = fragments[selector];
        
        // Re-initialize any event listeners if needed
        // (WooCommerce fragments should handle this, but we ensure it)
        if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
          jQuery(document.body).trigger('wc_fragment_refresh');
        }
      }
    });
  }

  // ✅ Refresh cart fragments on demand (for BFCache and manual refresh)
  async function refreshCartFragments() {
    if (!cfg.cartFragmentsEndpoint) return;
    
    try {
      const url = new URL(cfg.cartFragmentsEndpoint);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        if (data.fragments) {
          updateCartFragments(data.fragments);
        }
      }
    } catch (e) {
      console.warn('Failed to refresh cart fragments:', e);
    }
  }

  // ✅ Handle BFCache (Back/Forward Cache) - refresh cart on page restore
  window.addEventListener('pageshow', function(event) {
    // event.persisted is true when page is restored from BFCache
    if (event.persisted) {
      // Refresh cart fragments to ensure they're up-to-date
      refreshCartFragments();
      
      // Also reload the current category to ensure everything is fresh
      const term = getTermFromUrl();
      if (term) {
        current = null; // Reset to allow reload
        loadTerm(term, {push: false});
      }
    }
  });

  // ✅ Also refresh fragments on visibility change (tab switch)
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      // Tab became visible - refresh fragments in case cart changed in another tab
      refreshCartFragments();
    }
  });

  // קליקים על תפריט מכל מקום בעמוד
  document.addEventListener('click', (e) => {
    const a = e.target.closest(cfg.menuSelector);
    if (!a) return;
    e.preventDefault();
    loadTerm(a.dataset.edTerm, {push: true});
  });

  // Back/Forward
  window.addEventListener('popstate', () => {
    const term = getTermFromUrl();
    loadTerm(term);
    // Refresh fragments on navigation
    refreshCartFragments();
  });

  // טעינה ראשונית: מה-URL או הראשון בתפריט
  loadTerm(getTermFromUrl() || cfg.defaultSlug, {push:false});
})();
JS;
}


// מאפשר לקרוא get_query_var('mp_cat')
add_filter('query_vars', function ($vars) {
  $vars[] = 'mp_cat';
  return $vars;
});

add_action('init', function () {
  // /cat/{slug}/
  $front_id = (int) get_option('page_on_front');

  if ($front_id) {
    // דף בית סטטי
    add_rewrite_rule(
      '^cat/([^/]+)/?$',
      'index.php?page_id=' . $front_id . '&mp_cat=$matches[1]',
      'top'
    );
  } else {
    // אם הבית הוא "הפוסטים האחרונים" – עדיין נמנע 404 ונשתמש ב-home
    add_rewrite_rule(
      '^cat/([^/]+)/?$',
      'index.php?mp_cat=$matches[1]',
      'top'
    );
  }
}, 20);

// לא מאפשר 404 על /cat/{slug} (גם אם slug לא קיים)
add_action('template_redirect', function () {
  if (!get_query_var('mp_cat')) return;

  if (is_404()) {
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
  }
});

// מונע מ-WordPress להפוך /cat/slug/ ל- / (redirect canonical)
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
  // אם ה-rewrite שלנו זיהה קטגוריה
  if (get_query_var('mp_cat')) {
    return false;
  }

  // גם אם עוד לא הוזן query_var מסיבה כלשהי - בדיקה לפי path
  $path = parse_url($requested_url, PHP_URL_PATH) ?: '';
  if (preg_match('#^/cat/[^/]+/?$#', $path)) {
    return false;
  }

  return $redirect_url;
}, 10, 2);

/**
 * Canonical for /cat/{slug}/
 */
function ed_get_cat_slug_from_request() {
  $slug = (string) get_query_var('mp_cat');
  if ($slug) return sanitize_title($slug);

  // fallback לפי path (למקרה נדיר ש-query_var לא קיים)
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
  if (preg_match('#^/cat/([^/]+)/?$#', $path, $m)) {
    return sanitize_title($m[1]);
  }

  return '';
}

function ed_get_cat_canonical_url() {
  $slug = ed_get_cat_slug_from_request();
  if (!$slug) return '';

  // canonical רק אם זו באמת קטגוריית מוצר קיימת
  $term = get_term_by('slug', $slug, 'product_cat');
  if (!$term || is_wp_error($term)) return home_url('/');

  return home_url('/cat/' . $slug . '/');
}

// 1) canonical "כללי" (ללא תלות בתוסף SEO) – מוסיף <link rel="canonical">
add_action('wp_head', function () {
  $canon = ed_get_cat_canonical_url();
  if (!$canon) return;

  echo '<link rel="canonical" href="' . esc_url($canon) . "\" />\n";
}, 1);

// 2) Yoast SEO (אם קיים) – מחליף canonical של התוסף כדי שלא יהיו כפולים/שגויים
add_filter('wpseo_canonical', function ($canonical) {
  $canon = ed_get_cat_canonical_url();
  return $canon ? $canon : $canonical;
});

// 3) RankMath (אם קיים)
add_filter('rank_math/frontend/canonical', function ($canonical) {
  $canon = ed_get_cat_canonical_url();
  return $canon ? $canon : $canonical;
});

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
  $fragments['#ed-float-cart'] = ob_get_clean();

  return $fragments;
});

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

// Product Popup functionality is loaded from includes/product-popup/class-product-popup.php

// woocommerce login|register form
add_action( 'wp_footer', 'oc_menu_authorization_panel' );
function oc_menu_authorization_panel(){
    if ( is_user_logged_in() ){
        return;
    }
    ?>
    <div class="drawer-panel auth-panel" id="auth-panel">
        <div class="authorization-panel--container">
            <button class="auth__close default-close-btn btn-empty" type="button" aria-label="<?php _e( 'סגירה של פאנל התחברות', 'deliz-short' ) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" role="presentation" viewBox="0 0 16 14">
                    <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
              </svg>
            </button>

            <div class="form-login">
                <?php wc_get_template('myaccount/form-login.php');?>
            </div>
            <div class="my-account-lost-password-form--container">
                <?php
                // Show lost password form by default.
                wc_get_template(
                    'myaccount/form-lost-password.php',
                    array(
                        'form' => 'lost_password',
                    )
                );
                ?>
                <button class="return-to-login-form btn-empty" value="1" type="button"><?php _e( 'Return to login form', 'deliz-short' ) ?></button>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'wp_footer', 'overlay_bg' );
function overlay_bg(){
  echo '<div class="site-overlay"></div>';
}

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


function print_menu_shortcode($atts, $content = null) {
    extract(shortcode_atts(array( 'name' => null, ), $atts));
    return wp_nav_menu( array( 'menu' => $name, 'echo' => false ) );
}
add_shortcode('oc_menu', 'print_menu_shortcode');

// Product Popup functionality is loaded from includes/product-popup/class-product-popup.php