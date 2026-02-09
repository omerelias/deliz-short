<?php
/**
 * Product Popup Functionality
 * Handles REST API endpoint, enqueue scripts, and cart integration
 */

if (!defined('ABSPATH')) {
  exit;
}

class ED_Product_Popup {

  /**
   * Initialize the popup functionality
   */
  public static function init() {
    // Register REST API endpoint
    add_action('rest_api_init', [__CLASS__, 'register_rest_endpoint']);
    
    // Register custom add to cart endpoint for debugging
    add_action('rest_api_init', [__CLASS__, 'register_add_to_cart_endpoint']);
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    
    // Add product note to cart item data
    add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_product_note_to_cart'], 20, 2);
    
    // Display product note in cart and mini cart
    add_filter('woocommerce_get_item_data', [__CLASS__, 'display_product_note_in_cart'], 10, 2);
    
    // Add product note to order item meta
    add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_product_note_to_order'], 10, 4);
    
    // Debug hooks
    add_action('woocommerce_add_to_cart', [__CLASS__, 'debug_add_to_cart'], 10, 6);
    add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'debug_add_to_cart_validation'], 999, 5);
  }
  
  /**
   * Register REST API endpoint for product popup data
   */
  public static function register_rest_endpoint() {
    register_rest_route('ed/v1', '/product-popup', [
      'methods'  => 'GET',
      'permission_callback' => '__return_true',
      'args' => [
        'id' => ['required' => true, 'type' => 'integer'],
      ],
      'callback' => [__CLASS__, 'get_product_popup_data'],
    ]);
  }
  
  /**
   * Get product popup data via REST API
   */
  public static function get_product_popup_data(\WP_REST_Request $req) {
    $product_id = (int) $req->get_param('id');
    $product = wc_get_product($product_id);
    
    if (!$product || !$product->is_purchasable()) {
      return new \WP_REST_Response(['error' => 'Product not found'], 404);
    }

    // Basic product data
    $data = [
      'id' => $product_id,
      'name' => $product->get_name(),
      'description' => $product->get_short_description() ?: $product->get_description(),
      'price_html' => $product->get_price_html(),
      'price' => $product->get_price(),
      'regular_price' => $product->get_regular_price(),
      'sale_price' => $product->get_sale_price(),
      'image' => [
        'url' => wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_single') ?: wc_placeholder_img_src(),
        'alt' => $product->get_name(),
      ],
      'permalink' => $product->get_permalink(),
      'in_stock' => $product->is_in_stock(),
      'stock_quantity' => $product->get_stock_quantity(),
      'type' => $product->get_type(),
    ];

    // oc-woo-sale-units data
    $weighable = get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes';
    $sold_by_units = get_post_meta($product_id, '_ocwsu_sold_by_units', true) === 'yes';
    $sold_by_weight = get_post_meta($product_id, '_ocwsu_sold_by_weight', true) === 'yes';
    $unit_weight_type = get_post_meta($product_id, '_ocwsu_unit_weight_type', true);
    $unit_weight = get_post_meta($product_id, '_ocwsu_unit_weight', true);
    $unit_weight_options_str = get_post_meta($product_id, '_ocwsu_unit_weight_options', true);
    $unit_weight_options = !empty($unit_weight_options_str) ? array_map('trim', explode("\n", $unit_weight_options_str)) : [];
    $product_weight_units = get_post_meta($product_id, '_ocwsu_product_weight_units', true);
    $min_weight = get_post_meta($product_id, '_ocwsu_min_weight', true);
    $weight_step = get_post_meta($product_id, '_ocwsu_weight_step', true);
    $display_price_per_100g = get_post_meta($product_id, '_ocwsu_display_price_per_100g', true) === 'yes';

    $data['ocwsu'] = [
      'weighable' => $weighable,
      'sold_by_units' => $sold_by_units,
      'sold_by_weight' => $sold_by_weight,
      'unit_weight_type' => $unit_weight_type,
      'unit_weight' => $unit_weight ? (float) $unit_weight : null,
      'unit_weight_options' => array_filter(array_map('floatval', $unit_weight_options)),
      'product_weight_units' => $product_weight_units ?: 'kg',
      'min_weight' => $min_weight ? (float) $min_weight : null,
      'weight_step' => $weight_step ? (float) $weight_step : null,
      'display_price_per_100g' => $display_price_per_100g,
    ];

    // Calculate average weight if sold by units
    if ($weighable && $sold_by_units) {
      if ($unit_weight_type === 'fixed' && $unit_weight) {
        $data['ocwsu']['average_weight'] = (float) $unit_weight;
        $data['ocwsu']['average_weight_label'] = $product_weight_units === 'kg' ? 'ק"ג' : 'גרם';
      } elseif ($unit_weight_type === 'variable' && !empty($unit_weight_options)) {
        $avg = array_sum($unit_weight_options) / count($unit_weight_options);
        $data['ocwsu']['average_weight'] = $avg;
        $data['ocwsu']['average_weight_label'] = $product_weight_units === 'kg' ? 'ק"ג' : 'גרם';
      }
    }

    // WooCommerce Attributes and Variations
    $attributes = [];
    $variations = [];
    
    if ($product->is_type('variable')) {
      // Get product attributes
      $product_attributes = $product->get_attributes();
      foreach ($product_attributes as $attribute) {
        if (!$attribute->get_variation()) continue; // Only variation attributes
        
        $attr_name = $attribute->get_name();
        $attr_label = wc_attribute_label($attr_name);
        $attr_options = [];
        
        if ($attribute->is_taxonomy()) {
          $terms = wc_get_product_terms($product_id, $attr_name, ['fields' => 'all']);
          foreach ($terms as $term) {
            $attr_options[] = [
              'slug' => $term->slug,
              'name' => $term->name,
            ];
          }
        } else {
          $options = $attribute->get_options();
          foreach ($options as $option) {
            $attr_options[] = [
              'slug' => sanitize_title($option),
              'name' => $option,
            ];
          }
        }
        
        if (!empty($attr_options)) {
          $attributes[] = [
            'name' => $attr_name,
            'label' => $attr_label,
            'options' => $attr_options,
          ];
        }
      }
      
      // Get variations
      $variation_ids = $product->get_children();
      foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_purchasable()) continue;
        
        $variation_attributes = $variation->get_variation_attributes();
        $variation_data = [
          'id' => $variation_id,
          'price' => $variation->get_price(),
          'price_html' => $variation->get_price_html(),
          'in_stock' => $variation->is_in_stock(),
          'attributes' => [],
        ];
        
        foreach ($variation_attributes as $key => $value) {
          // Clean attribute key - remove 'attribute_pa_' or 'attribute_' prefix
          $clean_key = str_replace(['attribute_pa_', 'attribute_'], '', $key);
          // Decode URL encoding if present (WooCommerce sometimes stores keys as URL encoded)
          $clean_key = urldecode($clean_key);
          $variation_data['attributes'][$clean_key] = $value;
        }
        
        $variations[] = $variation_data;
      }
    } else {
      // For simple products, get all attributes (not just variation attributes)
      $product_attributes = $product->get_attributes();
      foreach ($product_attributes as $attribute) {
        $attr_name = $attribute->get_name();
        $attr_label = wc_attribute_label($attr_name);
        $attr_options = [];
        
        if ($attribute->is_taxonomy()) {
          $terms = wc_get_product_terms($product_id, $attr_name, ['fields' => 'all']);
          foreach ($terms as $term) {
            $attr_options[] = [
              'slug' => $term->slug,
              'name' => $term->name,
            ];
          }
        } else {
          $options = $attribute->get_options();
          foreach ($options as $option) {
            $attr_options[] = [
              'slug' => sanitize_title($option),
              'name' => $option,
            ];
          }
        }
        
        if (!empty($attr_options)) {
          $attributes[] = [
            'name' => $attr_name,
            'label' => $attr_label,
            'options' => $attr_options,
          ];
        }
      }
    }
    
    $data['attributes'] = $attributes;
    $data['variations'] = $variations;

    return new \WP_REST_Response($data, 200);
  }
  
  /**
   * Enqueue popup assets (CSS and JS)
   */
  public static function enqueue_assets() {
    if (!function_exists('WC')) {
      return;
    }
    
    wp_enqueue_style(
      'deliz-short-product-popup',
      get_template_directory_uri() . '/assets/css/product-popup.css',
      [],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0'
    );

    wp_enqueue_script(
      'deliz-short-product-popup',
      get_template_directory_uri() . '/assets/js/product-popup.js',
      ['jquery'],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0',
      true
    );

    // Popup config
    wp_localize_script('deliz-short-product-popup', 'ED_POPUP_CONFIG', [
      'endpoint' => rest_url('ed/v1/product-popup'),
      'addToCartUrl' => rest_url('ed/v1/add-to-cart'), // Use our custom endpoint for debugging
      'restNonce' => wp_create_nonce('wp_rest'),
    ]);
  }
  
  /**
   * Register custom add to cart endpoint for debugging
   */
  public static function register_add_to_cart_endpoint() {
    register_rest_route('ed/v1', '/add-to-cart', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [__CLASS__, 'handle_add_to_cart'],
    ]);
  }
  
  /**
   * Handle add to cart with debugging
   */
  public static function handle_add_to_cart(\WP_REST_Request $req) {
    // Ensure WooCommerce is loaded
    if (!function_exists('WC') || !class_exists('WooCommerce')) {
      error_log('ERROR: WooCommerce not loaded');
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'WooCommerce is not available',
      ], 500);
    }
    
    // Get all request data
    $params = $req->get_params();
    $body = $req->get_json_params() ?: $req->get_body_params();
    
    // Log everything
    error_log('=== ED POPUP ADD TO CART DEBUG ===');
    error_log('Request Params: ' . print_r($params, true));
    error_log('Request Body (JSON): ' . print_r($body, true));
    error_log('POST Data: ' . print_r($_POST, true));
    
    // Set $_POST for WooCommerce compatibility
    foreach ($body as $key => $value) {
      $_POST[$key] = $value;
    }
    
    $product_id = isset($body['product_id']) ? (int) $body['product_id'] : 0;
    $variation_id = isset($body['variation_id']) ? (int) $body['variation_id'] : 0;
    $quantity = isset($body['quantity']) ? floatval($body['quantity']) : 1;
    
    error_log("Product ID: {$product_id}");
    error_log("Variation ID: {$variation_id}");
    error_log("Quantity: {$quantity}");
    
    // Get product
    $product = wc_get_product($variation_id ? $variation_id : $product_id);
    if (!$product) {
      error_log('ERROR: Product not found');
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'Product not found',
        'debug' => [
          'product_id' => $product_id,
          'variation_id' => $variation_id,
        ]
      ], 400);
    }
    
    error_log("Product Type: " . $product->get_type());
    error_log("Product Name: " . $product->get_name());
    
    // Determine which product to check (variation or main product)
    $product_to_check = $product;
    if ($variation_id && $product->is_type('variable')) {
      $variation_product = wc_get_product($variation_id);
      if ($variation_product) {
        $product_to_check = $variation_product;
      }
    }
    
    // Check stock availability BEFORE trying to add to cart
    if (!$product_to_check->is_in_stock()) {
      $product_name = $product_to_check->get_name();
      error_log("ERROR: Product out of stock - {$product_name}");
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => sprintf(__('לא ניתן להוסיף את "%s" לסל הקניות - המוצר אזל מהמלאי.', 'woocommerce'), $product_name),
        'notices' => [[
          'notice' => sprintf(__('לא ניתן להוסיף את "%s" לסל הקניות - המוצר אזל מהמלאי.', 'woocommerce'), $product_name),
          'data' => []
        ]],
        'debug' => [
          'product_id' => $product_id,
          'variation_id' => $variation_id,
          'in_stock' => false,
          'stock_quantity' => $product_to_check->get_stock_quantity(),
        ]
      ], 400);
    }
    
    // Check if quantity exceeds available stock
    if ($product_to_check->managing_stock() && $product_to_check->get_stock_quantity() < $quantity) {
      $product_name = $product_to_check->get_name();
      $available_stock = $product_to_check->get_stock_quantity();
      error_log("ERROR: Quantity exceeds stock - Requested: {$quantity}, Available: {$available_stock}");
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => sprintf(__('הכמות המבוקשת (%s) גדולה מהמלאי הזמין (%s) עבור "%s".', 'woocommerce'), $quantity, $available_stock, $product_name),
        'notices' => [[
          'notice' => sprintf(__('הכמות המבוקשת (%s) גדולה מהמלאי הזמין (%s) עבור "%s".', 'woocommerce'), $quantity, $available_stock, $product_name),
          'data' => []
        ]],
        'debug' => [
          'product_id' => $product_id,
          'variation_id' => $variation_id,
          'requested_quantity' => $quantity,
          'available_stock' => $available_stock,
        ]
      ], 400);
    }
    
    // Build variation attributes array
    $variation = [];
    if ($variation_id && $product->is_type('variable')) {
      $variation_product = wc_get_product($variation_id);
      if ($variation_product) {
        $variation = $variation_product->get_variation_attributes();
        error_log("Variation Attributes: " . print_r($variation, true));
      }
    } else {
      // Get attributes from request
      // Request sends: attribute_pa_xxx or attribute_xxx
      // WooCommerce expects: attribute_pa_xxx or attribute_xxx (same format)
      foreach ($body as $key => $value) {
        if (strpos($key, 'attribute_') === 0) {
          // Keep the full key name as WooCommerce expects it
          $variation[$key] = $value;
        }
      }
      error_log("Variation from request: " . print_r($variation, true));
    }
    
    // Ensure WooCommerce cart is initialized
    if (!WC()->cart) {
      wc_load_cart();
    }
    
    if (!WC()->cart) {
      error_log('ERROR: WooCommerce cart could not be initialized');
      return new \WP_REST_Response([ 
        'error' => true,
        'errorMessage' => 'Cart could not be initialized',
        'debug' => [
          'wc_exists' => function_exists('WC'),
          'cart_class_exists' => class_exists('WC_Cart'),
        ]
      ], 500);
    }
    
    // IMPORTANT: Clear any old error notices at the start to prevent accumulation
    // We'll read them if needed, but don't want them to accumulate across requests
    wc_clear_notices('error');
    
    // Try to add to cart
    try {
      $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
      
      if ($cart_item_key) {
        error_log("SUCCESS: Added to cart with key: {$cart_item_key}");
        
        // Clear any error notices that might have accumulated
        wc_clear_notices('error');
        
        // Get fragments
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
        
        return new \WP_REST_Response([
          'error' => false,
          'success' => true,
          'cart_item_key' => $cart_item_key,
          'fragments' => $fragments,
          'cart_hash' => WC()->cart->get_cart_hash(),
        ], 200);
      } else {
        error_log('ERROR: add_to_cart returned false');
        
        // Get all notices (error, success, info) BEFORE clearing
        $error_notices = wc_get_notices('error');
        $all_notices = wc_get_notices();
        
        error_log("WooCommerce Error Notices: " . print_r($error_notices, true));
        error_log("WooCommerce All Notices: " . print_r($all_notices, true));
        
        // Extract notice messages
        $notice_messages = [];
        foreach ($error_notices as $notice) {
          if (is_array($notice) && isset($notice['notice'])) {
            $notice_messages[] = $notice['notice'];
          } elseif (is_string($notice)) {
            $notice_messages[] = $notice;
          }
        }
        
        // If no notices, create a default one
        if (empty($notice_messages)) {
          $notice_messages[] = __('לא ניתן להוסיף את המוצר לסל הקניות.', 'woocommerce');
        }
        
        $error_message = implode(' ', $notice_messages);
        
        // IMPORTANT: Clear notices after reading them to prevent accumulation
        wc_clear_notices();
        
        return new \WP_REST_Response([
          'error' => true,
          'errorMessage' => $error_message,
          'notices' => $error_notices ?: [[
            'notice' => $error_message,
            'data' => []
          ]],
          'debug' => [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'variation' => $variation,
            'all_notices' => $all_notices,
          ]
        ], 400);
      }
    } catch (\Exception $e) {
      error_log('EXCEPTION: ' . $e->getMessage());
      error_log('Stack trace: ' . $e->getTraceAsString());
      
      // Clear notices on exception too
      wc_clear_notices('error');
      
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => $e->getMessage(),
        'exception' => [
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
        ]
      ], 500);
    }
  }
  
  /**
   * Debug add to cart validation
   */
  public static function debug_add_to_cart_validation($passed, $product_id, $quantity, $variation_id = 0, $variations = []) {
    if (!$passed) {
      error_log('=== VALIDATION FAILED ===');
      error_log("Product ID: {$product_id}");
      error_log("Variation ID: {$variation_id}");
      error_log("Quantity: {$quantity}");
      error_log("Variations: " . print_r($variations, true));
      error_log("POST Data: " . print_r($_POST, true));
    }
    return $passed;
  }
  
  /**
   * Debug add to cart
   */
  public static function debug_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    error_log('=== ADD TO CART SUCCESS ===');
    error_log("Cart Item Key: {$cart_item_key}");
    error_log("Product ID: {$product_id}");
    error_log("Variation ID: {$variation_id}");
    error_log("Quantity: {$quantity}");
    error_log("Variation: " . print_r($variation, true));
    error_log("Cart Item Data: " . print_r($cart_item_data, true));
  }
  
  /**
   * Add product note to cart item data
   */
  public static function add_product_note_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['product_note']) && !empty($_POST['product_note'])) {
      $product_note = sanitize_textarea_field($_POST['product_note']);
      $cart_item_data['product_note'] = $product_note;
      error_log("✅ Product note added to cart item data: {$product_note}");
    } else {
      error_log("⚠️ No product_note in POST data");
      error_log("POST data: " . print_r($_POST, true));
    }
    return $cart_item_data;
  }
  
  /**
   * Display product note in cart and mini cart
   */
  public static function display_product_note_in_cart($item_data, $cart_item) {
    if (isset($cart_item['product_note']) && !empty($cart_item['product_note'])) {
      $item_data[] = [
        'key' => __('הערות לקצב', 'woocommerce'),
        'value' => wp_kses_post($cart_item['product_note']),
      ];
      error_log("✅ Product note displayed in cart: " . $cart_item['product_note']);
    } else {
      error_log("⚠️ No product_note in cart_item");
      error_log("Cart item keys: " . print_r(array_keys($cart_item), true));
    }
    return $item_data;
  }
  
  /**
   * Add product note to order item meta
   */
  public static function add_product_note_to_order($item, $cart_item_key, $cart_item, $order) {
    if (isset($cart_item['product_note']) && !empty($cart_item['product_note'])) {
      $item->add_meta_data(__('הערות לקצב', 'woocommerce'), wp_kses_post($cart_item['product_note']));
      error_log("✅ Product note added to order: " . $cart_item['product_note']);
    } else {
      error_log("⚠️ No product_note in cart_item for order");
    }
  }
}

// Initialize
ED_Product_Popup::init();

