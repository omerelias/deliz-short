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
    
    // Register update cart item endpoint
    add_action('rest_api_init', [__CLASS__, 'register_update_cart_endpoint']);
    
    // Register AJAX endpoint for updating cart (better session handling)
    add_action('wp_ajax_ed_update_cart', [__CLASS__, 'ajax_update_cart']);
    add_action('wp_ajax_nopriv_ed_update_cart', [__CLASS__, 'ajax_update_cart']);
    
    // Register get cart item endpoint (for editing)
    add_action('rest_api_init', [__CLASS__, 'register_get_cart_item_endpoint']);
    
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
    
    // Fix cart_id generation - exclude product_note from cart_id calculation
    // This ensures products with/without notes can be added to cart properly
    add_filter('woocommerce_cart_id', [__CLASS__, 'fix_cart_id_for_product_note'], 10, 5);
    
    // Add mini cart to fragments for AJAX updates
    add_filter('woocommerce_add_to_cart_fragments', [__CLASS__, 'add_mini_cart_to_fragments']);
  }
  
  /**
   * Add mini cart HTML to fragments for AJAX updates
   */
  public static function add_mini_cart_to_fragments($fragments) {
    if (!function_exists('WC') || !WC()->cart) {
      return $fragments;
    }
    
    $cart = WC()->cart;
    ob_start();
 
    if (!$cart || $cart->is_empty()) {
      echo '<div class="ed-float-cart__empty">';
      echo '<svg width="80" height="80" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">';
      echo '<path fill-rule="evenodd" clip-rule="evenodd" d="M23.0346 22.2883L21.8894 9.39264C21.8649 9.10634 21.6236 8.88957 21.3414 8.88957H18.9855C18.9528 6.73824 17.1941 5 15.0346 5C12.8751 5 11.1164 6.73824 11.0837 8.88957H8.72786C8.44156 8.88957 8.20434 9.10634 8.1798 9.39264L7.03461 22.2883C7.03461 22.2965 7.03359 22.3047 7.03256 22.3129L7.03256 22.3129L7.03256 22.3129C7.03154 22.3211 7.03052 22.3292 7.03052 22.3374C7.03052 23.8057 8.37612 25 10.0326 25H20.0367C21.6931 25 23.0387 23.8057 23.0387 22.3374C23.0387 22.3211 23.0387 22.3047 23.0346 22.2883ZM15.0346 6.10425C16.5847 6.10425 17.8485 7.3476 17.8812 8.88952H12.188C12.2207 7.3476 13.4845 6.10425 15.0346 6.10425ZM10.0326 23.8957H20.0367C21.0714 23.8957 21.9181 23.2086 21.9344 22.3619L20.8342 9.99792H18.9855V11.6748C18.9855 11.9816 18.7401 12.227 18.4334 12.227C18.1266 12.227 17.8812 11.9816 17.8812 11.6748V9.99792H12.1839V11.6748C12.1839 11.9816 11.9385 12.227 11.6318 12.227C11.325 12.227 11.0796 11.9816 11.0796 11.6748V9.99792H9.23094L8.13483 22.3619C8.15119 23.2086 8.99372 23.8957 10.0326 23.8957Z" fill="#0F0F0F"></path>';
      echo '</svg>';
      echo esc_html__('הסל ריק, אבל לא להרבה זמן :)', 'deliz-short');
      echo '</div>';
    } else {
      // Render cart items using the same template logic
      $template_file = get_template_directory() . '/template-parts/floating-mini-cart.php';
      if (file_exists($template_file)) {
        // Extract the items section from the template
        $template_content = file_get_contents($template_file);
        // We'll use a simpler approach - just include the template and extract
        // But for now, let's use get_template_part with a custom output buffer
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
          $product = $cart_item['data'];
          if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) continue;
          
          // Use the same variables as the template
          $product_id = $cart_item['product_id'];
          $name = $product->get_name();
          $qty = (int) $cart_item['quantity'];
          $thumbnail = $product->get_image('woocommerce_thumbnail');
          $remove_url = wc_get_cart_remove_url($cart_item_key);
          $line_price = WC()->cart->get_product_price($product);
          $subtotal = WC()->cart->get_product_subtotal($product, $qty);
          
          // ocwsu display
          $ocwsu_display = '';
          $weighable = (get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes');
          if ($weighable) {
            $quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
            $quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;
            $weight_qty = floatval($cart_item['quantity']);
            $weight_value = $weight_qty;
            $weight_unit = 'ק"ג';
            if ($weight_qty > 0 && $weight_qty < 1) {
              $weight_value = $weight_qty * 1000;
              $weight_unit = 'גרם';
            }
            if ($weight_unit === 'גרם') {
              $weight_value = wc_format_decimal($weight_value, 0);
            } else {
              $weight_value = wc_format_decimal($weight_value, 2);
            }
            if ($quantity_in_units > 0) {
              $units_label = ($quantity_in_units == 1) ? 'יחידה' : 'יחידות';
              $ocwsu_display = sprintf('%s %s, %s %s', wc_format_decimal($quantity_in_units, 0), $units_label, $weight_value, $weight_unit);
            } else {
              $ocwsu_display = sprintf('%s %s', $weight_value, $weight_unit);
            }
          }
          
          // Prepare edit button data
          $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
          $variation_attrs = isset($cart_item['variation']) ? $cart_item['variation'] : [];
          $product_note = isset($cart_item['product_note']) ? $cart_item['product_note'] : '';
          $ocwsu_quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
          $ocwsu_quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;
          $quantity = floatval($cart_item['quantity']);
          $variation_attrs_json = !empty($variation_attrs) ? htmlspecialchars(json_encode($variation_attrs), ENT_QUOTES, 'UTF-8') : '';
          
          // Item data
          $item_data = wc_get_formatted_cart_item_data($cart_item, true);
          
          // Output the cart item HTML (same structure as template)
          ?>
          <div class="ed-float-cart__item" role="listitem" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
            <a href="<?php echo esc_url($remove_url); ?>" class="ed-float-cart__remove remove remove_from_cart_button" aria-label="<?php echo esc_attr(sprintf(__('הסר %s מהסל', 'deliz-short'), $name)); ?>" data-product_id="<?php echo esc_attr($product_id); ?>" data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>" data-product_sku="<?php echo esc_attr($product->get_sku()); ?>">×</a>
            <div class="ed-float-cart__thumb"><?php echo $thumbnail; // phpcs:ignore ?></div>
            <div class="ed-float-cart__details">
              <div class="ed-float-cart__name"><?php echo esc_html($name); ?></div>
              <?php if ($item_data): ?>
                <div class="ed-float-cart__meta2"><?php echo $item_data; // phpcs:ignore ?></div>
              <?php endif; ?>
              <?php if ($ocwsu_display): ?>
                <div class="ed-float-cart__ocwsu-qty"><?php echo esc_html($ocwsu_display); ?></div>
              <?php endif; ?>
              
              <?php
              // הודעות קידום מבצעים
              if (class_exists('ED_Promotions')) {
                $promotions = ED_Promotions::get_product_promotions($product_id);
                
                if (!empty($promotions)) {
                  $promotion = $promotions[0];
                  $type = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'type', true);
                  $badge_text = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'badge_text', true);
                  
                  if ($type === 'discount') {
                    // למבצע הנחה - תמיד מציגים "משתתף במבצע"
                    echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float">';
                    echo '<span class="ed-promotion-label">' . sprintf(__('משתתף במבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
                    echo '</div>';
                  } elseif ($type === 'buy_x_pay_y') {
                    // למבצע קונים X תמורת Y - בודקים אם מימש או לא
                    $buy_kg = floatval(get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'buy_kg', true));
                    $quantity = floatval($cart_item['quantity']);
                    
                    // בדיקה אם המוצר שקיל
                    $weighable = get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes';
                    if (!$weighable) {
                      // למוצרים לא שקילים - ממירים לפי משקל יחידה
                      $unit_weight = floatval(get_post_meta($product_id, '_ocwsu_unit_weight', true));
                      if ($unit_weight > 0) {
                        $quantity = $quantity / $unit_weight; // המרה לק"ג
                      }
                    }
                    
                    if ($quantity >= $buy_kg) {
                      // מימש את המבצע
                      echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float ed-promotion-fulfilled">';
                      echo '<span class="ed-promotion-label">' . sprintf(__('קיבלת את המבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
                      echo '</div>';
                    } else {
                      // לא מימש - מציגים כמה חסר
                      $remaining = $buy_kg - $quantity;
                      // פורמט יפה של המספר
                      if ($remaining < 1) {
                        $remaining_display = wc_format_decimal($remaining * 1000, 0) . ' גרם';
                      } else {
                        $remaining_display = wc_format_decimal($remaining, 2) . ' ק"ג';
                      }
                      echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float ed-promotion-pending">';
                      echo '<span class="ed-promotion-label">' . sprintf(__('חסר לך רק עוד %s כדי לקבל את המבצע: %s', 'deliz-short'), $remaining_display, esc_html($badge_text)) . '</span>';
                      echo '</div>';
                    }
                  }
                }
              }
              ?>

              <div class="ed-float-cart__actions-row">
                <div class="ed-float-cart__quantity-controls">
                  <button type="button" class="ed-float-cart__qty-btn ed-float-cart__qty-btn--decrease" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php esc_attr_e('הפחת כמות', 'deliz-short'); ?>">-</button>
                  <input type="number" class="ed-float-cart__qty-input" value="<?php echo esc_attr($qty); ?>" min="1" step="1" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php esc_attr_e('כמות', 'deliz-short'); ?>">
                  <button type="button" class="ed-float-cart__qty-btn ed-float-cart__qty-btn--increase" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php esc_attr_e('הוסף כמות', 'deliz-short'); ?>">+</button>
                </div>
                <button type="button" class="ed-float-cart__edit-btn" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" data-product-id="<?php echo esc_attr($product_id); ?>" data-variation-id="<?php echo esc_attr($variation_id); ?>" data-quantity="<?php echo esc_attr($quantity); ?>" data-variation="<?php echo $variation_attrs_json; ?>" data-product-note="<?php echo esc_attr($product_note); ?>" data-ocwsu-quantity-in-units="<?php echo esc_attr($ocwsu_quantity_in_units); ?>" data-ocwsu-quantity-in-weight-units="<?php echo esc_attr($ocwsu_quantity_in_weight_units); ?>" aria-label="<?php esc_attr_e('ערוך מוצר', 'deliz-short'); ?>"><?php esc_html_e('עריכה', 'deliz-short'); ?></button>
              </div>
              <div class="ed-float-cart__price">
                <span class="ed-float-cart__unit"><?php echo wp_kses_post($line_price); ?></span>
                <span class="ed-float-cart__sep">×</span>
                <span class="ed-float-cart__qty"><?php echo esc_html($qty); ?></span>
                <span class="ed-float-cart__subtotal"><?php echo wp_kses_post($subtotal); ?></span>
              </div>
            </div>
          </div>
          <?php
        }
      }
    }
    
    $mini_cart_items = ob_get_clean();
    $fragments['div.ed-float-cart__items'] = '<div class="ed-float-cart__items" role="list">' . $mini_cart_items . '</div>';
    
    // Also add totals to fragments
    if ($cart && !$cart->is_empty()) {
      ob_start();
      ?>
      <div class="ed-float-cart__totals">
        <div class="ed-float-cart__row">
          <span><?php echo esc_html__('סה"כ ביניים', 'deliz-short'); ?></span>
          <strong><?php echo wp_kses_post(wc_price($cart->get_subtotal())); ?></strong>
        </div>
        
        <?php
        // וידוא שהעגלה מחושבת לפני קבלת fees
        $cart->calculate_totals();
        
        // הצגת שורות מבצעים (fees) וחישוב סה"כ אחרי מבצעים
        $fees = $cart->get_fees();
        $subtotal_after_promotions = $cart->get_subtotal();
        
        if ( !empty($fees) ) :
          foreach ( $fees as $fee ) :
            // רק fees שליליים (הנחות)
            if ( $fee->amount < 0 ) :
              $subtotal_after_promotions += $fee->amount; // fees שליליים מוסיפים (כי הם הנחות)
        ?>
          <div class="ed-float-cart__row ed-float-cart__row--promotion">
            <span><?php echo esc_html( $fee->name ); ?></span>
            <strong><?php echo wp_kses_post( wc_cart_totals_fee_html( $fee ) ); ?></strong>
          </div>
        <?php
            endif;
          endforeach;
          
          // הצגת סה"כ אחרי הנחה (רק אם יש הנחות)
          if ( $subtotal_after_promotions != $cart->get_subtotal() ) :
        ?>
          <div class="ed-float-cart__row ed-float-cart__row--total-after-discount">
            <span><?php echo esc_html__('סה"כ אחרי הנחה', 'deliz-short'); ?></span>
            <strong><?php echo wp_kses_post( wc_price( $subtotal_after_promotions ) ); ?></strong>
          </div>
        <?php
          endif;
        endif;
        ?>
        
        <?php
        // טקסט משלוח חינם/עלות משלוח
        $free_min = 0;
        $settings = get_option('woocommerce_free_shipping_1_settings');
        if (is_array($settings) && isset($settings['min_amount'])) {
          $free_min = (float) $settings['min_amount'];
        }
        
        if ($free_min > 0):
          $remaining = max(0, $free_min - (float) $subtotal_after_promotions);
        ?>
          <div class="ed-float-cart__shippinghint">
            <?php if ($remaining > 0): ?>
              <?php echo esc_html('חסר לך רק ' . wc_price($remaining) . ' למשלוח חינם!'); ?>
            <?php else: ?>
              <?php echo esc_html__('מגיע לך משלוח חינם!', 'deliz-short'); ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php
      $totals_html = ob_get_clean();
      // Use the full div with class as key for fragment replacement
      $fragments['div.ed-float-cart__totals'] = $totals_html;
    } else {
      // Empty cart - remove totals
      $fragments['div.ed-float-cart__totals'] = '';
    }
    
    // Also add the row specifically for easier updates
    if ($cart && !$cart->is_empty()) {
      ob_start();
      ?>
      <div class="ed-float-cart__row">
        <span><?php echo esc_html__('סה"כ ביניים', 'deliz-short'); ?></span>
        <strong><?php echo wp_kses_post(wc_price($cart->get_subtotal())); ?></strong>
      </div>
      <?php
      $row_html = ob_get_clean();
      $fragments['div.ed-float-cart__row'] = $row_html;
    }

    // Add cart count fragment for header update
    $count = $cart ? (int) $cart->get_cart_contents_count() : 0;
    if ($count > 0) {
      ob_start();
      ?>
      <span class="ed-float-cart__count" aria-label="<?php esc_attr_e('כמות בסל', 'deliz-short'); ?>">
          (<?php echo esc_html($count); ?>)
      </span>
      <?php
      $count_html = ob_get_clean();
      $fragments['span.ed-float-cart__count'] = $count_html;
    } else {
      // Empty cart - remove count
      $fragments['span.ed-float-cart__count'] = '';
    }
    
    return $fragments;
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
    $get_weight_from_variation = get_post_meta($product_id, '_ocwsu_get_weight_from_variation', true) === 'yes';
 
    // If get_weight_from_variation is enabled and product is variable, collect weights from variations
    $final_unit_weight_options = array_filter(array_map('floatval', $unit_weight_options));
    
    // Debug logging
    error_log('=== ED POPUP: Variation Weight Collection ===');
    error_log('get_weight_from_variation: ' . ($get_weight_from_variation ? 'yes' : 'no'));
    error_log('product->is_type(variable): ' . ($product->is_type('variable') ? 'yes' : 'no'));
    error_log('weighable: ' . ($weighable ? 'yes' : 'no'));
    error_log('sold_by_units: ' . ($sold_by_units ? 'yes' : 'no'));
    error_log('unit_weight_type: ' . $unit_weight_type);
    error_log('product_weight_units: ' . $product_weight_units);
    
    if ($get_weight_from_variation && $product->is_type('variable') && $weighable && $sold_by_units && $unit_weight_type === 'variable') {
      $variation_weights = [];
      
      // Use get_available_variations() like the plugin does - this returns formatted variation data
      $available_variations = $product->get_available_variations();
      
      error_log('Available variations count: ' . count($available_variations));
      
      foreach ($available_variations as $variation_data) {
        $variation_id = isset($variation_data['variation_id']) ? $variation_data['variation_id'] : 0;
        $variation_weight = isset($variation_data['weight']) ? $variation_data['weight'] : null;
        
        error_log("Variation {$variation_id}: weight from get_available_variations = " . ($variation_weight !== null ? $variation_weight : 'null'));
        
        // Also try to get from variation object directly
        if ($variation_weight === null || $variation_weight === '') {
          $variation_obj = wc_get_product($variation_id);
          if ($variation_obj) {
            $variation_weight = $variation_obj->get_weight();
            error_log("Variation {$variation_id}: weight from object = " . ($variation_weight ?: 'empty'));
          }
        }
        
        if ($variation_weight && $variation_weight > 0) {
          // WooCommerce stores weight in kg
          $weight_in_kg = (float) $variation_weight;
          
          error_log("Variation {$variation_id}: weight_in_kg = {$weight_in_kg}, product_weight_units = {$product_weight_units}");
          
          // Check product_weight_units - it might be stored as constant value
          // From the plugin: PRODUCT_UNIT_WEIHGT_LABEL_KG = 'kg', PRODUCT_UNIT_WEIHGT_LABEL_GRAM = 'grams'
          $is_kg = ($product_weight_units === 'kg' || $product_weight_units === 'Kg' || strpos(strtolower($product_weight_units), 'kg') !== false);
          
          // Convert to product weight units if needed
          if (!$is_kg || ($is_kg && $weight_in_kg < 1)) {
            // If product uses grams or weight is less than 1kg, convert to grams
            $weight_value = $weight_in_kg * 1000;
            error_log("Variation {$variation_id}: converted to grams = {$weight_value}");
          } else {
            // Keep in kg
            $weight_value = $weight_in_kg;
            error_log("Variation {$variation_id}: kept in kg = {$weight_value}");
          }
          
          // Only add unique weights (use tolerance for float comparison)
          $found = false;
          foreach ($variation_weights as $existing_weight) {
            if (abs($existing_weight - $weight_value) < 0.001) {
              $found = true;
              break;
            }
          }
          
          if (!$found) {
            $variation_weights[] = $weight_value;
            error_log("Variation {$variation_id}: Added weight {$weight_value} to array");
          } else {
            error_log("Variation {$variation_id}: Weight {$weight_value} already exists, skipping");
          }
        }
      }
      
      error_log('Final variation_weights: ' . print_r($variation_weights, true));
      
      // Replace unit_weight_options with variation weights if found
      if (!empty($variation_weights)) {
        sort($variation_weights); // Sort ascending
        $final_unit_weight_options = $variation_weights;
        error_log('Replaced unit_weight_options with variation weights: ' . print_r($final_unit_weight_options, true));
      } else {
        error_log('No variation weights found, keeping original unit_weight_options');
      }
    } else {
      error_log('Conditions not met for variation weight collection');
    }
    
    error_log('Final unit_weight_options: ' . print_r($final_unit_weight_options, true));
    error_log('=== END DEBUG ===');

    $data['ocwsu'] = [
      'weighable' => $weighable,
      'sold_by_units' => $sold_by_units,
      'sold_by_weight' => $sold_by_weight,
      'unit_weight_type' => $unit_weight_type,
      'unit_weight' => $unit_weight ? (float) $unit_weight : null,
      'unit_weight_options' => $final_unit_weight_options,
      'product_weight_units' => $product_weight_units ?: 'kg',
      'min_weight' => $min_weight ? (float) $min_weight : null,
      'weight_step' => $weight_step ? (float) $weight_step : null,
      'display_price_per_100g' => $display_price_per_100g,
      'get_weight_from_variation' => $get_weight_from_variation,
    ];

    // Calculate average weight if sold by units
    if ($weighable && $sold_by_units) {
      if ($unit_weight_type === 'fixed' && $unit_weight) {
        $data['ocwsu']['average_weight'] = (float) $unit_weight;
        $data['ocwsu']['average_weight_label'] = $product_weight_units === 'kg' ? 'ק"ג' : 'גרם';
      } elseif ($unit_weight_type === 'variable' && !empty($final_unit_weight_options)) {
        $avg = array_sum($final_unit_weight_options) / count($final_unit_weight_options);
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
        
        // If get_weight_from_variation is enabled, include weight in variation data
        if ($get_weight_from_variation && $weighable && $sold_by_units) {
          $variation_weight = $variation->get_weight();
          if ($variation_weight && $variation_weight > 0) {
            // WooCommerce stores weight in kg
            $weight_in_kg = (float) $variation_weight;
            
            // Convert to product weight units if needed
            $is_kg = ($product_weight_units === 'kg' || $product_weight_units === 'Kg' || strpos(strtolower($product_weight_units), 'kg') !== false);
            
            if (!$is_kg || ($is_kg && $weight_in_kg < 1)) {
              // Convert to grams
              $weight_value = $weight_in_kg * 1000;
            } else {
              // Keep in kg
              $weight_value = $weight_in_kg;
            }
            
            $variation_data['weight'] = $weight_value;
          }
        }
        
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

    // Enqueue modular popup scripts in correct order
    $version = defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0';
    $base_path = get_template_directory_uri() . '/assets/js/';
    
    // 1. State management (no dependencies)
    wp_enqueue_script(
      'deliz-short-product-popup-state',
      $base_path . 'product-popup-state.js',
      [],
      $version,
      true
    );
    
    // 2. Render functions (depends on state)
    wp_enqueue_script(
      'deliz-short-product-popup-render',
      $base_path . 'product-popup-render.js',
      ['deliz-short-product-popup-state'],
      $version,
      true
    );
    
    // 3. Quantity inputs (depends on state)
    wp_enqueue_script(
      'deliz-short-product-popup-quantity',
      $base_path . 'product-popup-quantity.js',
      ['deliz-short-product-popup-state'],
      $version,
      true
    );
    
    // 4. OCWSU fields (depends on state, quantity)
    wp_enqueue_script(
      'deliz-short-product-popup-ocwsu',
      $base_path . 'product-popup-ocwsu.js',
      ['deliz-short-product-popup-state', 'deliz-short-product-popup-quantity'],
      $version,
      true
    );

    // 5. Variations (depends on state, ocwsu)
    wp_enqueue_script(
      'deliz-short-product-popup-variations',
      $base_path . 'product-popup-variations.js',
      ['deliz-short-product-popup-state', 'deliz-short-product-popup-ocwsu'],
      $version,
      true
    );
    
    // 6. Events (depends on state)
    wp_enqueue_script(
      'deliz-short-product-popup-events',
      $base_path . 'product-popup-events.js',
      ['deliz-short-product-popup-state'],
      $version,
      true
    );
    
    // 7. Core functions (must load before cart and mini-cart)
    wp_enqueue_script(
      'deliz-short-product-popup-core',
      $base_path . 'product-popup-core.js',
      [
        'jquery',
        'deliz-short-product-popup-state',
        'deliz-short-product-popup-render',
        'deliz-short-product-popup-quantity',
        'deliz-short-product-popup-ocwsu',
        'deliz-short-product-popup-variations',
        'deliz-short-product-popup-events'
      ],
      $version,
      true
    );
    
    // 8. Cart functions (depends on state, ocwsu, variations, core)
    wp_enqueue_script(
      'deliz-short-product-popup-cart',
      $base_path . 'product-popup-cart.js',
      ['deliz-short-product-popup-state', 'deliz-short-product-popup-ocwsu', 'deliz-short-product-popup-variations', 'deliz-short-product-popup-core'],
      $version,
      true
    );
    
    // 9. Mini cart (depends on state, core)
    wp_enqueue_script(
      'deliz-short-product-popup-mini-cart',
      $base_path . 'product-popup-mini-cart.js',
      ['deliz-short-product-popup-state', 'deliz-short-product-popup-core'],
      $version,
      true
    );

    // Popup config (localize to core script which initializes everything)
    wp_localize_script('deliz-short-product-popup-core', 'ED_POPUP_CONFIG', [
      'endpoint' => rest_url('ed/v1/product-popup'),
      'addToCartUrl' => rest_url('ed/v1/add-to-cart'), // Use our custom endpoint for debugging
      'updateCartUrl' => rest_url('ed/v1/update-cart'),
      'updateCartAjaxUrl' => admin_url('admin-ajax.php'),
      'getCartItemUrl' => rest_url('ed/v1/cart-item'),
      'getCartItemAjaxUrl' => admin_url('admin-ajax.php'),
      'restNonce' => wp_create_nonce('wp_rest'),
      'cartItemNonce' => wp_create_nonce('ed-cart-item-nonce'),
      'updateCartNonce' => wp_create_nonce('ed-update-cart-nonce'),
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
      // Log cart state BEFORE adding
      $cart_before = WC()->cart->get_cart();
      $cart_count_before = WC()->cart->get_cart_contents_count();
      error_log("Cart BEFORE add: {$cart_count_before} items");
      error_log("Cart items BEFORE: " . print_r(array_keys($cart_before), true));
      
      // Check if product already exists in cart
      $existing_cart_item_key = null;
      foreach ($cart_before as $key => $item) {
        if ($item['product_id'] == $product_id && 
            ($variation_id == 0 || $item['variation_id'] == $variation_id)) {
          // Check if variations match
          $variations_match = true;
          if (!empty($variation)) {
            foreach ($variation as $attr_key => $attr_value) {
              if (!isset($item['variation'][$attr_key]) || $item['variation'][$attr_key] != $attr_value) {
                $variations_match = false;
                break;
              }
            }
          }
          if ($variations_match) {
            $existing_cart_item_key = $key;
            error_log("Found existing cart item: {$key}");
            error_log("Existing item data: " . print_r($item, true));
            break;
          }
        }
      }
      
      $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
      
      // Log cart state AFTER adding
      $cart_after = WC()->cart->get_cart();
      $cart_count_after = WC()->cart->get_cart_contents_count();
      error_log("Cart AFTER add: {$cart_count_after} items");
      error_log("Cart items AFTER: " . print_r(array_keys($cart_after), true));
      
      if ($cart_item_key) {
        error_log("SUCCESS: Added to cart with key: {$cart_item_key}");
        
        // Check if quantity was actually increased or new item was added
        if ($existing_cart_item_key && $existing_cart_item_key === $cart_item_key) {
          $existing_item = $cart_after[$cart_item_key];
          error_log("Item was merged - new quantity: " . $existing_item['quantity']);
        } else {
          error_log("New item was added to cart");
        }
        
        // Clear any error notices that might have accumulated
        wc_clear_notices('error');
        
        // Get fragments - use WC_AJAX method to ensure proper fragments
        // First, trigger the action hook that WooCommerce uses
        do_action('woocommerce_add_to_cart', $cart_item_key, $product_id, $quantity, $variation_id, $variation, []);
        
        // Now get fragments properly
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
        error_log("Fragments count: " . count($fragments));
        error_log("Fragment keys: " . print_r(array_keys($fragments), true));
        
        return new \WP_REST_Response([
          'error' => false,
          'success' => true,
          'cart_item_key' => $cart_item_key,
          'fragments' => $fragments,
          'cart_hash' => WC()->cart->get_cart_hash(),
          'cart_count' => $cart_count_after,
          'was_merged' => ($existing_cart_item_key && $existing_cart_item_key === $cart_item_key),
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
   * IMPORTANT: Only add product_note if it's not empty, to prevent cart_id issues
   */
  public static function add_product_note_to_cart($cart_item_data, $product_id) {
    // Ensure cart_item_data is an array
    if (!is_array($cart_item_data)) {
      $cart_item_data = [];
    }
    
    if (isset($_POST['product_note']) && !empty(trim($_POST['product_note']))) {
      $product_note = sanitize_textarea_field($_POST['product_note']);
      $cart_item_data['product_note'] = $product_note;
      error_log("✅ Product note added to cart item data: {$product_note}");
    } else {
      // IMPORTANT: If product_note is empty or not set, ensure it's NOT in cart_item_data
      // This prevents empty product_note from affecting cart_id generation
      if (isset($cart_item_data['product_note'])) {
        unset($cart_item_data['product_note']);
        error_log("⚠️ Removed empty product_note from cart_item_data");
      }
      error_log("⚠️ No product_note in POST data or it was empty");
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
  
  /**
   * Fix cart_id generation to exclude product_note
   * This ensures that products with/without notes can be added to cart properly
   * when there's already a product in the cart
   * 
   * IMPORTANT: This filter runs AFTER WooCommerce generates the cart_id, but we can override it.
   * However, the real fix should be in add_product_note_to_cart to prevent empty product_note
   * from being added to cart_item_data in the first place.
   */
  public static function fix_cart_id_for_product_note($cart_id, $product_id, $variation_id, $variation, $cart_item_data) {
    error_log("=== fix_cart_id_for_product_note called ===");
    error_log("Original cart_id: {$cart_id}");
    error_log("Product ID: {$product_id}, Variation ID: {$variation_id}");
    error_log("Cart item data: " . print_r($cart_item_data, true));
    
    // If cart_item_data is empty or not an array, return original cart_id
    if (!is_array($cart_item_data) || empty($cart_item_data)) {
      error_log("Cart item data is empty, returning original cart_id");
      return $cart_id;
    }
    
    // Check if product_note exists in cart_item_data
    if (!isset($cart_item_data['product_note'])) {
      error_log("No product_note in cart_item_data, returning original cart_id");
      return $cart_id;
    }
    
    // If product_note is empty, remove it from cart_item_data for cart_id calculation
    // This ensures products with same attributes but different notes (or no notes)
    // will have the same cart_id and can be added together
    $product_note_value = $cart_item_data['product_note'];
    error_log("Product note value: '{$product_note_value}'");
    
    // Remove product_note from cart_item_data for cart_id calculation
    $cart_item_data_for_id = $cart_item_data;
    unset($cart_item_data_for_id['product_note']);
    
    // If cart_item_data is now empty after removing product_note, return original cart_id
    if (empty($cart_item_data_for_id)) {
      error_log("Cart item data is empty after removing product_note, returning original cart_id");
      return $cart_id;
    }
    
    // Recalculate cart_id without product_note
    // Use the same logic as oc-woo-sale-units plugin
    $id_parts = array($product_id);
    
    if ($variation_id && 0 !== $variation_id) {
      $id_parts[] = $variation_id;
    }
    
    if (is_array($variation) && !empty($variation)) {
      $variation_key = '';
      foreach ($variation as $key => $value) {
        $variation_key .= trim($key) . trim($value);
      }
      $id_parts[] = $variation_key;
    }
    
    if (is_array($cart_item_data_for_id) && !empty($cart_item_data_for_id)) {
      $cart_item_data_key = '';
      foreach ($cart_item_data_for_id as $key => $value) {
        // Skip fields that shouldn't affect cart_id (same as oc-woo-sale-units plugin)
        if ($key == 'ocwsu_quantity_in_units' || $key == 'ocwsu_quantity_in_weight_units') {
          continue;
        }
        if (is_array($value) || is_object($value)) {
          $value = http_build_query($value);
        }
        $cart_item_data_key .= trim($key) . trim($value);
      }
      if (!empty($cart_item_data_key)) {
        $id_parts[] = $cart_item_data_key;
      }
    }
    
    $new_cart_id = md5(implode('_', $id_parts));
    error_log("New cart_id (without product_note): {$new_cart_id}");
    error_log("=== End fix_cart_id_for_product_note ===");
    
    return $new_cart_id;
  }
  
  /**
   * Register update cart item endpoint
   */
  public static function register_update_cart_endpoint() {
    register_rest_route('ed/v1', '/update-cart', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [__CLASS__, 'handle_update_cart'],
    ]);
  }
  
  /**
   * AJAX handler for updating cart item quantity (better session handling)
   */
  public static function ajax_update_cart() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ed-update-cart-nonce')) {
      wp_send_json_error(['errorMessage' => 'Invalid nonce']);
      return;
    }
    
    // Get cart_item_key and quantity from POST
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    
    if (empty($cart_item_key) || $quantity <= 0) {
      wp_send_json_error(['errorMessage' => 'Invalid parameters']);
      return;
    }
    
    // WooCommerce session is already loaded in AJAX context
    if (!WC()->cart) {
      wp_send_json_error(['errorMessage' => 'WooCommerce cart is not available']);
      return;
    }
    
    $cart_item = WC()->cart->get_cart_item($cart_item_key);
    
    if (!$cart_item) {
      wp_send_json_error([
        'errorMessage' => 'Cart item not found',
        'debug' => [
          'cart_item_key' => $cart_item_key,
          'cart_count' => count(WC()->cart->get_cart()),
        ]
      ]);
      return;
    }
    
    // Update quantity
    $updated = WC()->cart->set_quantity($cart_item_key, $quantity);
    
    if ($updated) {
      // Get fragments
      $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
      
      wp_send_json_success([
        'cart_hash' => WC()->cart->get_cart_hash(),
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'fragments' => $fragments,
      ]);
    } else {
      wp_send_json_error(['errorMessage' => 'Failed to update cart item']);
    }
  }
  
  /**
   * Handle update cart item quantity
   */
  public static function handle_update_cart(\WP_REST_Request $req) {
    // Ensure WooCommerce is loaded
    if (!function_exists('WC')) {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'WooCommerce is not available',
      ], 500);
    }
    
    // CRITICAL: Initialize WooCommerce session BEFORE accessing cart
    // In REST API context, session is not automatically loaded
    if (!WC()->session) {
      WC()->initialize_session();
    }
    
    // CRITICAL: Start session if not already started
    if (!WC()->session->has_session()) {
      WC()->session->set_customer_session_cookie(true);
    }
    
    // CRITICAL: Load session data from cookie/database
    WC()->session->get_session_data();
    
    // Load cart if not already loaded
    if (!WC()->cart) {
      wc_load_cart();
    }
    
    if (!WC()->cart) {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'WooCommerce cart is not available',
      ], 500);
    }
    
    // CRITICAL: Force cart to load from session
    do_action('woocommerce_load_cart_from_session');
    WC()->cart->calculate_totals();
    
    $body = $req->get_json_params() ?: $req->get_body_params();
    $cart_item_key = isset($body['cart_item_key']) ? sanitize_text_field($body['cart_item_key']) : '';
    $quantity = isset($body['quantity']) ? floatval($body['quantity']) : 0;
    
    if (empty($cart_item_key) || $quantity <= 0) {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'Invalid parameters',
      ], 400);
    }
    $cart_item = WC()->cart->get_cart_item($cart_item_key);
    if (!$cart_item) {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'Cart item not found',
      ], 404);
    }
    
    // Update quantity
    $updated = WC()->cart->set_quantity($cart_item_key, $quantity);
    
    if ($updated) {
      // Get fragments
      $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
      
      return new \WP_REST_Response([
        'error' => false,
        'success' => true,
        'cart_hash' => WC()->cart->get_cart_hash(),
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'fragments' => $fragments,
      ], 200);
    } else {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'Failed to update cart item',
      ], 400);
    }
  }
  
  /**
   * Register get cart item endpoint (for editing)
   * Using AJAX endpoint instead of REST API for better session handling
   */
  public static function register_get_cart_item_endpoint() {
    // Register REST API endpoint
    register_rest_route('ed/v1', '/cart-item', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'args' => [
        'cart_item_key' => ['required' => true, 'type' => 'string'],
      ],
      'callback' => [__CLASS__, 'get_cart_item_data'],
    ]);
    
    // Also register as AJAX endpoint for better session handling
    add_action('wp_ajax_ed_get_cart_item', [__CLASS__, 'ajax_get_cart_item_data']);
    add_action('wp_ajax_nopriv_ed_get_cart_item', [__CLASS__, 'ajax_get_cart_item_data']);
  }
  
  /**
   * AJAX handler for getting cart item data (better session handling)
   */
  public static function ajax_get_cart_item_data() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ed-cart-item-nonce')) {
      wp_send_json_error(['errorMessage' => 'Invalid nonce']);
      return;
    }
    
    // Get cart_item_key from POST (sent via FormData)
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    
    if (empty($cart_item_key)) {
      wp_send_json_error(['errorMessage' => 'Cart item key is required']);
      return;
    }
    
    // WooCommerce session is already loaded in AJAX context
    if (!WC()->cart) {
      wp_send_json_error(['errorMessage' => 'WooCommerce cart is not available']);
      return;
    }
    
    $cart_item = WC()->cart->get_cart_item($cart_item_key);
    
    if (!$cart_item) {
      wp_send_json_error([
        'errorMessage' => 'Cart item not found',
        'debug' => [
          'cart_item_key' => $cart_item_key,
          'cart_count' => count(WC()->cart->get_cart()),
        ]
      ]);
      return;
    }
    
    // Extract product data
    $product_id = $cart_item['product_id'];
    $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
    $quantity = $cart_item['quantity'];
    $variation = isset($cart_item['variation']) ? $cart_item['variation'] : [];
    $product_note = isset($cart_item['product_note']) ? $cart_item['product_note'] : '';
    
    // ocwsu data
    $ocwsu_quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
    $ocwsu_quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;
    
    wp_send_json_success([
      'cart_item_key' => $cart_item_key,
      'product_id' => $product_id,
      'variation_id' => $variation_id,
      'quantity' => $quantity,
      'variation' => $variation,
      'product_note' => $product_note,
      'ocwsu_quantity_in_units' => $ocwsu_quantity_in_units,
      'ocwsu_quantity_in_weight_units' => $ocwsu_quantity_in_weight_units,
    ]);
  }
  
  /**
   * Get cart item data for editing
   */
  public static function get_cart_item_data(\WP_REST_Request $req) {
    // Ensure WooCommerce is loaded
    if (!function_exists('WC')) {
      return new \WP_REST_Response([ 
        'error' => true,
        'errorMessage' => 'WooCommerce is not available',
      ], 500);
    }

    // CRITICAL: Initialize WooCommerce session BEFORE accessing cart
    // In REST API context, session is not automatically loaded
    if (!WC()->session) {
      WC()->initialize_session();
    }
    
    // CRITICAL: Start session if not already started
    // This ensures cookies are read and session data is loaded
    if (!WC()->session->has_session()) {
      // Try to get customer ID from cookie
      $customer_id = WC()->session->get_customer_id();
      if (!$customer_id) {
        // Create new session
        WC()->session->set_customer_session_cookie(true);
      }
    }
    
    // CRITICAL: Load session data from cookie/database
    // This is what actually loads the cart from session
    WC()->session->get_session_data();

    // Load cart if not already loaded
    if (!WC()->cart) {
      wc_load_cart();
    }
    
    if (!WC()->cart) {
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'WooCommerce cart is not available',
      ], 500);
    }
    
    // CRITICAL: Force cart to load from session
    // In REST API context, WooCommerce might not auto-load cart from session
    // We need to explicitly trigger the cart loading from session
    do_action('woocommerce_load_cart_from_session');
    
    // Force cart to calculate totals which also loads from session
    WC()->cart->calculate_totals();

    // Now we can search for the product
    $cart_item_key = sanitize_text_field($req->get_param('cart_item_key'));
    $cart_item = WC()->cart->get_cart_item($cart_item_key);

    if (!$cart_item) {
      // Debug info
      $cart_count = count(WC()->cart->get_cart());
      $session_cart = WC()->session->get('cart', []);
      $session_cart_count = is_array($session_cart) ? count($session_cart) : 0;
      
      return new \WP_REST_Response([
        'error' => true,
        'errorMessage' => 'Cart item not found',
        'debug' => [
          'cart_item_key' => $cart_item_key,
          'cart_count' => $cart_count,
          'session_cart_count' => $session_cart_count,
          'session_has_cart' => !empty($session_cart),
          'available_keys' => array_keys(WC()->cart->get_cart()),
        ]
      ], 404);
    }

    // Extract product data (including ocwsu support)
    $product_id = $cart_item['product_id'];
    $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
    $quantity = $cart_item['quantity'];
    $variation = isset($cart_item['variation']) ? $cart_item['variation'] : [];
    $product_note = isset($cart_item['product_note']) ? $cart_item['product_note'] : '';

    // ocwsu data from cart item meta
    $ocwsu_quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
    $ocwsu_quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;

    return new \WP_REST_Response([
      'error' => false,
      'cart_item_key' => $cart_item_key,
      'product_id' => $product_id,
      'variation_id' => $variation_id,
      'quantity' => $quantity,
      'variation' => $variation,
      'product_note' => $product_note,
      'ocwsu_quantity_in_units' => $ocwsu_quantity_in_units,
      'ocwsu_quantity_in_weight_units' => $ocwsu_quantity_in_weight_units,
    ], 200);
  }
}

// Initialize
ED_Product_Popup::init();

