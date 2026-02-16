<?php
/**
 * Promotions System
 * Manages product promotions with discounts and special offers
 */

if (!defined('ABSPATH')) {
  exit;
}

class ED_Promotions {

  const POST_TYPE = 'ed_promotion';
  const META_PREFIX = '_ed_promotion_';

  /**
   * Initialize the promotions system
   */
  public static function init() {
    // Register custom post type
    add_action('init', [__CLASS__, 'register_post_type']);
    
    // Admin hooks
    add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    
    // Admin AJAX handlers
    add_action('wp_ajax_ed_promotion_search_products', [__CLASS__, 'ajax_search_products']);
    add_action('wp_ajax_ed_promotion_search_categories', [__CLASS__, 'ajax_search_categories']);
    add_action('wp_ajax_ed_promotion_save', [__CLASS__, 'ajax_save_promotion']);
    add_action('wp_ajax_ed_promotion_delete', [__CLASS__, 'ajax_delete_promotion']);
    add_action('wp_ajax_ed_promotion_toggle_status', [__CLASS__, 'ajax_toggle_status']);
    
    // Frontend AJAX handlers
    add_action('wp_ajax_ed_get_product_promotion_badge', [__CLASS__, 'ajax_get_product_promotion_badge']);
    add_action('wp_ajax_nopriv_ed_get_product_promotion_badge', [__CLASS__, 'ajax_get_product_promotion_badge']);
    
    // Frontend hooks
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
    
    // Cart integration
    add_filter('woocommerce_cart_item_name', [__CLASS__, 'add_promotion_label_to_cart_item'], 10, 3);
    add_action('woocommerce_after_cart_item_name', [__CLASS__, 'display_promotion_message'], 10, 2);
    
    // Product badge display
    add_action('woocommerce_before_shop_loop_item_title', [__CLASS__, 'display_product_badge'], 5);
    add_action('woocommerce_single_product_summary', [__CLASS__, 'display_product_badge'], 5);
    
    // Calculate promotions
    add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_promotion_discounts']);
  }

  /**
   * Register custom post type for promotions
   */
  public static function register_post_type() {
    $labels = [
      'name' => __('מבצעים', 'deliz-short'),
      'singular_name' => __('מבצע', 'deliz-short'),
      'menu_name' => __('מבצעים', 'deliz-short'),
      'add_new' => __('הוסף מבצע חדש', 'deliz-short'),
      'add_new_item' => __('הוסף מבצע חדש', 'deliz-short'),
      'edit_item' => __('ערוך מבצע', 'deliz-short'),
      'new_item' => __('מבצע חדש', 'deliz-short'),
      'view_item' => __('צפה במבצע', 'deliz-short'),
      'search_items' => __('חפש מבצעים', 'deliz-short'),
      'not_found' => __('לא נמצאו מבצעים', 'deliz-short'),
      'not_found_in_trash' => __('לא נמצאו מבצעים בפח', 'deliz-short'),
    ];

    $args = [
      'labels' => $labels,
      'public' => false,
      'show_ui' => false, // We'll use custom admin page
      'show_in_menu' => false,
      'capability_type' => 'post',
      'hierarchical' => false,
      'supports' => ['title'],
      'has_archive' => false,
      'rewrite' => false,
      'query_var' => false,
    ];

    register_post_type(self::POST_TYPE, $args);
  }

  /**
   * Add admin menu
   */
  public static function add_admin_menu() {
    add_menu_page(
      __('מבצעים', 'deliz-short'),
      __('מבצעים', 'deliz-short'),
      'manage_options',
      'ed-promotions',
      [__CLASS__, 'render_admin_page'],
      'dashicons-tag',
      30
    );
  }

  /**
   * Enqueue admin assets
   */
  public static function enqueue_admin_assets($hook) {
    if (strpos($hook, 'ed-promotions') === false) {
      return;
    }

    wp_enqueue_style(
      'ed-promotions-admin',
      get_template_directory_uri() . '/includes/lib/promotions/assets/css/promotions-admin.css',
      [],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0'
    );

    wp_enqueue_script(
      'ed-promotions-admin',
      get_template_directory_uri() . '/includes/lib/promotions/assets/js/promotions-admin.js',
      ['jquery', 'jquery-ui-datepicker'],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0',
      true
    );

    wp_localize_script('ed-promotions-admin', 'ED_PROMOTIONS', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('ed-promotions-nonce'),
      'i18n' => [
        'confirmDelete' => __('האם אתה בטוח שברצונך למחוק מבצע זה?', 'deliz-short'),
        'saving' => __('שומר...', 'deliz-short'),
        'saved' => __('נשמר בהצלחה', 'deliz-short'),
        'error' => __('אירעה שגיאה', 'deliz-short'),
      ],
    ]);

    // jQuery UI Datepicker
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
  }

  /**
   * Enqueue frontend assets
   */
  public static function enqueue_frontend_assets() {
    if (!function_exists('WC') || !WC()->cart) {
      return;
    }

    wp_enqueue_style(
      'ed-promotions',
      get_template_directory_uri() . '/includes/lib/promotions/assets/css/promotions.css',
      [],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0'
    );

    wp_enqueue_script(
      'ed-promotions',
      get_template_directory_uri() . '/includes/lib/promotions/assets/js/promotions.js',
      ['jquery'],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0',
      true
    );

    wp_localize_script('ed-promotions', 'ED_PROMOTIONS_FRONT', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('ed-promotions-front-nonce'),
    ]);
  }

  /**
   * Render admin page
   */
  public static function render_admin_page() {
    require_once get_template_directory() . '/includes/lib/promotions/admin/admin-page.php';
  }

  /**
   * AJAX: Search products
   */
  public static function ajax_search_products() {
    check_ajax_referer('ed-promotions-nonce', 'nonce');
    
    $search = sanitize_text_field($_GET['term'] ?? '');
    if (empty($search)) {
      wp_send_json_success([]);
      return;
    }

    $args = [
      'post_type' => 'product',
      'post_status' => 'publish',
      'posts_per_page' => 20,
      's' => $search,
      'fields' => 'ids',
    ];

    $product_ids = get_posts($args);
    $results = [];

    foreach ($product_ids as $product_id) {
      $product = wc_get_product($product_id);
      if ($product) {
        $results[] = [
          'id' => $product_id,
          'text' => $product->get_name(),
          'price' => wc_price($product->get_price()),
        ];
      }
    }

    wp_send_json_success($results);
  }

  /**
   * AJAX: Search categories
   */
  public static function ajax_search_categories() {
    check_ajax_referer('ed-promotions-nonce', 'nonce');
    
    $search = sanitize_text_field($_GET['term'] ?? '');
    if (empty($search)) {
      wp_send_json_success([]);
      return;
    }

    $terms = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'number' => 20,
      'search' => $search,
    ]);

    $results = [];
    if (!is_wp_error($terms)) {
      foreach ($terms as $term) {
        $results[] = [
          'id' => $term->term_id,
          'text' => $term->name,
        ];
      }
    }

    wp_send_json_success($results);
  }

  /**
   * AJAX: Save promotion
   */
  public static function ajax_save_promotion() {
    check_ajax_referer('ed-promotions-nonce', 'nonce');
    
    $promotion_id = intval($_POST['promotion_id'] ?? 0);
    $data = $_POST['data'] ?? [];

    // Validate required fields
    if (empty($data['name']) || empty($data['type'])) {
      wp_send_json_error(['message' => __('שם המבצע וסוג המבצע נדרשים', 'deliz-short')]);
      return;
    }

    // Create or update post
    $post_data = [
      'post_title' => sanitize_text_field($data['name']),
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
    ];

    if ($promotion_id > 0) {
      $post_data['ID'] = $promotion_id;
      $promotion_id = wp_update_post($post_data);
    } else {
      $promotion_id = wp_insert_post($post_data);
    }

    if (is_wp_error($promotion_id)) {
      wp_send_json_error(['message' => __('שגיאה בשמירת המבצע', 'deliz-short')]);
      return;
    }

    // Save meta fields
    $meta_fields = [
      'type' => sanitize_text_field($data['type']),
      'target_type' => sanitize_text_field($data['target_type'] ?? ''),
      'target_id' => intval($data['target_id'] ?? 0),
      'discount_percent' => floatval($data['discount_percent'] ?? 0),
      'buy_kg' => floatval($data['buy_kg'] ?? 0),
      'pay_amount' => floatval($data['pay_amount'] ?? 0),
      'start_date' => sanitize_text_field($data['start_date'] ?? ''),
      'end_date' => sanitize_text_field($data['end_date'] ?? ''),
      'has_end_date' => !empty($data['has_end_date']) ? '1' : '0',
      'status' => sanitize_text_field($data['status'] ?? 'active'),
    ];

    foreach ($meta_fields as $key => $value) {
      update_post_meta($promotion_id, self::META_PREFIX . $key, $value);
    }

    // Generate badge text
    $badge_text = self::generate_badge_text($promotion_id);
    update_post_meta($promotion_id, self::META_PREFIX . 'badge_text', $badge_text);

    wp_send_json_success([
      'promotion_id' => $promotion_id,
      'message' => __('המבצע נשמר בהצלחה', 'deliz-short'),
    ]);
  }

  /**
   * AJAX: Delete promotion
   */
  public static function ajax_delete_promotion() {
    check_ajax_referer('ed-promotions-nonce', 'nonce');
    
    $promotion_id = intval($_POST['promotion_id'] ?? 0);
    if ($promotion_id <= 0) {
      wp_send_json_error(['message' => __('מזהה מבצע לא תקין', 'deliz-short')]);
      return;
    }

    $result = wp_delete_post($promotion_id, true);
    if ($result) {
      wp_send_json_success(['message' => __('המבצע נמחק בהצלחה', 'deliz-short')]);
    } else {
      wp_send_json_error(['message' => __('שגיאה במחיקת המבצע', 'deliz-short')]);
    }
  }

  /**
   * AJAX: Toggle promotion status
   */
  public static function ajax_toggle_status() {
    check_ajax_referer('ed-promotions-nonce', 'nonce');
    
    $promotion_id = intval($_POST['promotion_id'] ?? 0);
    $new_status = sanitize_text_field($_POST['status'] ?? 'active');
    
    if ($promotion_id <= 0) {
      wp_send_json_error(['message' => __('מזהה מבצע לא תקין', 'deliz-short')]);
      return;
    }

    update_post_meta($promotion_id, self::META_PREFIX . 'status', $new_status);
    wp_send_json_success(['message' => __('הסטטוס עודכן', 'deliz-short')]);
  }

  /**
   * Generate badge text from promotion data
   */
  public static function generate_badge_text($promotion_id) {
    $type = get_post_meta($promotion_id, self::META_PREFIX . 'type', true);
    $name = get_post($promotion_id)->post_title ?? '';

    if ($type === 'discount') {
      $discount = get_post_meta($promotion_id, self::META_PREFIX . 'discount_percent', true);
      return sprintf('%s%% הנחה', $discount);
    } elseif ($type === 'buy_x_pay_y') {
      $buy_kg = get_post_meta($promotion_id, self::META_PREFIX . 'buy_kg', true);
      $pay = get_post_meta($promotion_id, self::META_PREFIX . 'pay_amount', true);
      return sprintf('%s ק"ג ב-%s ש"ח', $buy_kg, $pay);
    }

    return $name;
  }

  /**
   * Get active promotions for a product
   */
  public static function get_product_promotions($product_id) {
    $promotions = get_posts([
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query' => [
        [
          'key' => self::META_PREFIX . 'status',
          'value' => 'active',
          'compare' => '=',
        ],
      ],
    ]);

    $applicable = [];
    $product = wc_get_product($product_id);
    if (!$product) {
      return $applicable;
    }

    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

    foreach ($promotions as $promotion) {
      $target_type = get_post_meta($promotion->ID, self::META_PREFIX . 'target_type', true);
      $target_id = intval(get_post_meta($promotion->ID, self::META_PREFIX . 'target_id', true));

      // Check if promotion is currently active (date-wise)
      if (!self::is_promotion_active($promotion->ID)) {
        continue;
      }

      if ($target_type === 'product' && $target_id === $product_id) {
        $applicable[] = $promotion;
      } elseif ($target_type === 'category' && in_array($target_id, $product_categories)) {
        $applicable[] = $promotion;
      }
    }

    return $applicable;
  }

  /**
   * Check if promotion is active (date-wise)
   */
  public static function is_promotion_active($promotion_id) {
    $start_date = get_post_meta($promotion_id, self::META_PREFIX . 'start_date', true);
    $end_date = get_post_meta($promotion_id, self::META_PREFIX . 'end_date', true);
    $has_end_date = get_post_meta($promotion_id, self::META_PREFIX . 'has_end_date', true) === '1';

    $now = current_time('Y-m-d H:i:s');
    
    if ($start_date && strtotime($start_date) > strtotime($now)) {
      return false; // Future promotion
    }

    if ($has_end_date && $end_date) {
      $end_datetime = $end_date . ' 23:59:59';
      if (strtotime($end_datetime) < strtotime($now)) {
        return false; // Ended promotion
      }
    }

    return true;
  }

  /**
   * Get promotion status (active/future/ended)
   */
  public static function get_promotion_status($promotion_id) {
    $status = get_post_meta($promotion_id, self::META_PREFIX . 'status', true);
    if ($status !== 'active') {
      return $status;
    }

    $start_date = get_post_meta($promotion_id, self::META_PREFIX . 'start_date', true);
    $end_date = get_post_meta($promotion_id, self::META_PREFIX . 'end_date', true);
    $has_end_date = get_post_meta($promotion_id, self::META_PREFIX . 'has_end_date', true) === '1';

    $now = current_time('Y-m-d H:i:s');

    if ($start_date && strtotime($start_date) > strtotime($now)) {
      return 'future';
    }

    if ($has_end_date && $end_date) {
      $end_datetime = $end_date . ' 23:59:59';
      if (strtotime($end_datetime) < strtotime($now)) {
        return 'ended';
      }
    }

    return 'active';
  }

  /**
   * Add promotion label to cart item name
   */
  public static function add_promotion_label_to_cart_item($name, $cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    $promotions = self::get_product_promotions($product_id);
    
    if (!empty($promotions)) {
      $badge_text = get_post_meta($promotions[0]->ID, self::META_PREFIX . 'badge_text', true);
      if ($badge_text) {
        $name .= ' <span class="ed-promotion-badge-inline">' . esc_html($badge_text) . '</span>';
      }
    }

    return $name;
  }

  /**
   * Display promotion message in cart
   */
  public static function display_promotion_message($cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    $promotions = self::get_product_promotions($product_id);
    
    if (empty($promotions)) {
      return;
    }

    $promotion = $promotions[0];
    $type = get_post_meta($promotion->ID, self::META_PREFIX . 'type', true);
    $badge_text = get_post_meta($promotion->ID, self::META_PREFIX . 'badge_text', true);

    if ($type === 'discount') {
      echo '<div class="ed-promotion-cart-message">';
      echo '<span class="ed-promotion-label">' . sprintf(__('משתתף במבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
      echo '</div>';
    } elseif ($type === 'buy_x_pay_y') {
      $buy_kg = floatval(get_post_meta($promotion->ID, self::META_PREFIX . 'buy_kg', true));
      $quantity = floatval($cart_item['quantity']);
      
      // Check if product is weighable
      $weighable = get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes';
      if (!$weighable) {
        // For non-weighable products, check by units
        $unit_weight = floatval(get_post_meta($product_id, '_ocwsu_unit_weight', true));
        if ($unit_weight > 0) {
          $quantity = $quantity / $unit_weight; // Convert to kg
        }
      }

      if ($quantity >= $buy_kg) {
        echo '<div class="ed-promotion-cart-message ed-promotion-fulfilled">';
        echo '<span class="ed-promotion-label">' . sprintf(__('קיבלת את המבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
        echo '</div>';
      } else {
        $remaining = $buy_kg - $quantity;
        echo '<div class="ed-promotion-cart-message ed-promotion-pending">';
        echo '<span class="ed-promotion-label">' . sprintf(__('חסר לך רק עוד %.2f ק"ג כדי לקבל את המבצע: %s', 'deliz-short'), $remaining, esc_html($badge_text)) . '</span>';
        echo '</div>';
      }
    }
  }

  /**
   * Display product badge
   */
  public static function display_product_badge() {
    global $product;
    if (!$product) {
      return;
    }

    $promotions = self::get_product_promotions($product->get_id());
    if (empty($promotions)) {
      return;
    }

    $promotion = $promotions[0];
    $badge_text = get_post_meta($promotion->ID, self::META_PREFIX . 'badge_text', true);
    
    if ($badge_text) {
      echo '<span class="ed-promotion-badge">' . esc_html($badge_text) . '</span>';
    }
  }

  /**
   * AJAX: Get product promotion badge
   */
  public static function ajax_get_product_promotion_badge() {
    check_ajax_referer('ed-promotions-front-nonce', 'nonce');
    
    $product_id = intval($_POST['product_id'] ?? 0);
    if ($product_id <= 0) {
      wp_send_json_success(['badge_html' => '']);
      return;
    }

    $promotions = self::get_product_promotions($product_id);
    if (empty($promotions)) {
      wp_send_json_success(['badge_html' => '']);
      return;
    }

    $promotion = $promotions[0];
    $badge_text = get_post_meta($promotion->ID, self::META_PREFIX . 'badge_text', true);
    
    if ($badge_text) {
      $badge_html = '<span class="ed-promotion-badge">' . esc_html($badge_text) . '</span>';
      wp_send_json_success(['badge_html' => $badge_html]);
    } else {
      wp_send_json_success(['badge_html' => '']);
    }
  }

  /**
   * Apply promotion discounts to cart
   */
  public static function apply_promotion_discounts() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
      return;
    }

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
      $product_id = $cart_item['product_id'];
      $promotions = self::get_product_promotions($product_id);
      
      if (empty($promotions)) {
        continue;
      }

      $promotion = $promotions[0];
      $type = get_post_meta($promotion->ID, self::META_PREFIX . 'type', true);

      if ($type === 'discount') {
        $discount_percent = floatval(get_post_meta($promotion->ID, self::META_PREFIX . 'discount_percent', true));
        $product = $cart_item['data'];
        $line_total = $product->get_price() * $cart_item['quantity'];
        $discount = ($line_total * $discount_percent) / 100;
        
        WC()->cart->add_fee(
          sprintf(__('הנחת מבצע: %s', 'deliz-short'), get_post($promotion->ID)->post_title),
          -$discount
        );
      } elseif ($type === 'buy_x_pay_y') {
        $buy_kg = floatval(get_post_meta($promotion->ID, self::META_PREFIX . 'buy_kg', true));
        $pay_amount = floatval(get_post_meta($promotion->ID, self::META_PREFIX . 'pay_amount', true));
        $quantity = floatval($cart_item['quantity']);
        
        // Check if product is weighable
        $weighable = get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes';
        if (!$weighable) {
          $unit_weight = floatval(get_post_meta($product_id, '_ocwsu_unit_weight', true));
          if ($unit_weight > 0) {
            $quantity = $quantity / $unit_weight;
          }
        }

        if ($quantity >= $buy_kg) {
          $product = $cart_item['data'];
          $original_price = $product->get_price() * $cart_item['quantity'];
          $promotion_price = $pay_amount;
          $discount = $original_price - $promotion_price;
          
          if ($discount > 0) {
            WC()->cart->add_fee(
              sprintf(__('מבצע: %s', 'deliz-short'), get_post($promotion->ID)->post_title),
              -$discount
            );
          }
        }
      }
    }
  }
}

// Initialize
ED_Promotions::init();

