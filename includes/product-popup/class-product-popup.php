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
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    
    // Add product note to cart item data
    add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_product_note_to_cart'], 20, 2);
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
          // Clean attribute key
          $clean_key = str_replace(['attribute_pa_', 'attribute_'], '', $key);
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
      'addToCartUrl' => wc_get_cart_url() . '?wc-ajax=add_to_cart',
      'restNonce' => wp_create_nonce('wp_rest'),
    ]);
  }
  
  /**
   * Add product note to cart item data
   */
  public static function add_product_note_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['product_note']) && !empty($_POST['product_note'])) {
      $product_note = sanitize_textarea_field($_POST['product_note']);
      $cart_item_data['product_note'] = $product_note;
    }
    return $cart_item_data;
  }
}

// Initialize
ED_Product_Popup::init();

