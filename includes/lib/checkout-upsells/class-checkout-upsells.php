<?php
/**
 * Checkout Upsells System
 * Displays upsell products in a popup/modal before checkout completion
 */

if (!defined('ABSPATH')) {
  exit;
}

class ED_Checkout_Upsells {

  /**
   * Initialize the upsells system
   */
  public static function init() {
    // Register ACF fields
    add_action('acf/init', [__CLASS__, 'register_acf_fields']);
    
    // Enqueue assets
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    
    // Add popup to footer
    add_action('wp_footer', [__CLASS__, 'render_popup']);
    
    // AJAX handler for checking if upsells exist
    add_action('wp_ajax_ed_check_checkout_upsells', [__CLASS__, 'ajax_check_upsells']);
    add_action('wp_ajax_nopriv_ed_check_checkout_upsells', [__CLASS__, 'ajax_check_upsells']);
    
    // AJAX handler for getting upsell products
    add_action('wp_ajax_ed_get_checkout_upsells', [__CLASS__, 'ajax_get_upsells']);
    add_action('wp_ajax_nopriv_ed_get_checkout_upsells', [__CLASS__, 'ajax_get_upsells']);
  }

  /**
   * Register ACF fields for checkout upsells settings
   */
  public static function register_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) {
      return;
    }

    acf_add_local_field_group([
      'key' => 'group_checkout_upsells',
      'title' => 'מוצרי קופה - Upsells',
      'fields' => [
        [
          'key' => 'field_checkout_upsells_enabled',
          'label' => 'הפעל מוצרי קופה',
          'name' => 'checkout_upsells_enabled',
          'type' => 'true_false',
          'instructions' => 'הצג מוצרי קופה בפופאפ לפני השלמת הזמנה',
          'required' => 0,
          'default_value' => 0,
          'ui' => 1,
        ],
        [
          'key' => 'field_checkout_upsells_title',
          'label' => 'כותרת מוצרי קופה',
          'name' => 'checkout_upsells_title',
          'type' => 'text',
          'instructions' => 'הכותרת שתוצג מעל מוצרי הקופה',
          'required' => 0,
          'default_value' => 'מוצרי קופה',
          'conditional_logic' => [
            [
              [
                'field' => 'field_checkout_upsells_enabled',
                'operator' => '==',
                'value' => '1',
              ],
            ],
          ],
        ],
        [
          'key' => 'field_checkout_upsells_type',
          'label' => 'סוג מוצרי קופה',
          'name' => 'checkout_upsells_type',
          'type' => 'select',
          'instructions' => 'בחר איזה מוצרים להציג',
          'required' => 1,
          'choices' => [
            'related' => 'מוצרים נלווים',
            'category' => 'קטגוריה',
            'combined' => 'משולב - מוצרים נלווים וקטגוריה',
          ],
          'default_value' => 'related',
          'allow_null' => 0,
          'multiple' => 0,
          'ui' => 1,
          'ajax' => 0,
          'return_format' => 'value',
          'conditional_logic' => [
            [
              [
                'field' => 'field_checkout_upsells_enabled',
                'operator' => '==',
                'value' => '1',
              ],
            ],
          ],
        ],
        [
          'key' => 'field_checkout_upsells_category',
          'label' => 'בחירת קטגוריה',
          'name' => 'checkout_upsells_category',
          'type' => 'taxonomy',
          'instructions' => 'בחר קטגוריה להצגת מוצרים (נדרש אם בחרת "קטגוריה" או "משולב")',
          'required' => 0,
          'taxonomy' => 'product_cat',
          'field_type' => 'select',
          'allow_null' => 1,
          'add_term' => 0,
          'save_terms' => 0,
          'load_terms' => 0,
          'return_format' => 'id',
          'multiple' => 0,
          'conditional_logic' => [
            [
              [
                'field' => 'field_checkout_upsells_enabled',
                'operator' => '==',
                'value' => '1',
              ],
              [
                'field' => 'field_checkout_upsells_type',
                'operator' => '==',
                'value' => 'category',
              ],
            ],
            [
              [
                'field' => 'field_checkout_upsells_enabled',
                'operator' => '==',
                'value' => '1',
              ],
              [
                'field' => 'field_checkout_upsells_type',
                'operator' => '==',
                'value' => 'combined',
              ],
            ],
          ],
        ],
        [
          'key' => 'field_checkout_upsells_max_products',
          'label' => 'מספר מוצרים מקסימלי',
          'name' => 'checkout_upsells_max_products',
          'type' => 'number',
          'instructions' => 'מספר המוצרים המקסימלי להצגה',
          'required' => 0,
          'default_value' => 4,
          'min' => 1,
          'max' => 12,
          'step' => 1,
          'conditional_logic' => [
            [
              [
                'field' => 'field_checkout_upsells_enabled',
                'operator' => '==',
                'value' => '1',
              ],
            ],
          ],
        ],
      ],
      'location' => [
        [
          [
            'param' => 'options_page',
            'operator' => '==',
            'value' => 'site-settings',
          ],
        ],
      ],
      'menu_order' => 0,
      'position' => 'normal',
      'style' => 'default',
      'label_placement' => 'top',
      'instruction_placement' => 'label',
      'hide_on_screen' => '',
      'active' => true,
      'description' => '',
    ]);
  }

  /**
   * Check if upsells are enabled
   */
  private static function is_enabled() {
    if (!function_exists('get_field')) {
      return false;
    }
    return (bool) get_field('checkout_upsells_enabled', 'option');
  }

  /**
   * Get upsell products based on settings
   */
  public static function get_upsell_products() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
      return [];
    }
    $type = get_field('checkout_upsells_type', 'option') ?: 'related';
    $max_products = (int) get_field('checkout_upsells_max_products', 'option') ?: 4;
    $category_id = get_field('checkout_upsells_category', 'option');

    $product_ids = [];

    // Get products from cart
    $cart_product_ids = [];
    foreach (WC()->cart->get_cart() as $cart_item) {
      $cart_product_ids[] = $cart_item['product_id'];
    }
    // Related products
    if ($type === 'related' || $type === 'combined') {
      $related_ids = [];
      foreach ($cart_product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;
        
        // Get related products (WooCommerce default)
        $related = wc_get_related_products($product_id, $max_products);
        $related_ids = array_merge($related_ids, $related);
      }
      
      // Remove duplicates and products already in cart
      $related_ids = array_unique($related_ids);
      $related_ids = array_diff($related_ids, $cart_product_ids);
      
      $product_ids = array_merge($product_ids, $related_ids);
    }

    // Category products
      if ( ($type === 'category' || $type === 'combined') && $category_id ) {

          $args = [
              'limit'        => $max_products,
              'status'       => 'publish',
              'exclude'      => array_unique(array_merge($cart_product_ids, $product_ids)),
              'category_ids' => [ (int) $category_id ], // 👈 זה העיקר
              'return'       => 'ids',
          ];

          $category_ids = wc_get_products($args); // כבר מחזיר IDs

          $product_ids = array_merge($product_ids, $category_ids);
      }
    // Remove duplicates and limit
    $product_ids = array_unique($product_ids);
    $product_ids = array_slice($product_ids, 0, $max_products);

    // Filter only purchasable products
    $valid_products = [];
    foreach ($product_ids as $product_id) {
      $product = wc_get_product($product_id);
      if ($product && $product->is_purchasable() && $product->is_in_stock()) {
        $valid_products[] = $product_id;
      }
    }

    return $valid_products;
  }

  /**
   * AJAX handler for checking if upsells exist
   */
  public static function ajax_check_upsells() {
    check_ajax_referer('ed-checkout-upsells-nonce', 'nonce');

    if (!self::is_enabled()) {
      wp_send_json_success(['has_upsells' => false]);
      return;
    }

    $product_ids = self::get_upsell_products();

    wp_send_json_success([
      'has_upsells' => !empty($product_ids),
      'count' => count($product_ids),
    ]);
  }

  /**
   * Render upsells popup in footer
   */
  public static function render_popup() {
    if (!self::is_enabled()) {
      return;
    }

    // Only render on checkout page or if WooCommerce is active (for floating cart)
    if (!function_exists('WC') || !WC()->cart) {
      return;
    }

    $title = get_field('checkout_upsells_title', 'option') ?: 'מוצרי קופה';
    ?>
    <div class="ed-checkout-upsells-popup" id="ed-checkout-upsells-popup" style="display: none;">
      <div class="ed-checkout-upsells-popup__overlay"></div>
      <div class="ed-checkout-upsells-popup__content">
        <div class="ed-checkout-upsells-popup__header">
          <h3 class="ed-checkout-upsells-popup__title"><?php echo esc_html($title); ?></h3>
          <button type="button" class="ed-checkout-upsells-popup__close" aria-label="<?php esc_attr_e('סגור', 'deliz-short'); ?>">×</button>
        </div>
        <div class="ed-checkout-upsells-popup__body">
          <div class="ed-checkout-upsells-popup__products" id="ed-checkout-upsells-products">
            <div class="ed-checkout-upsells-popup__loading"><?php esc_html_e('טוען מוצרים...', 'deliz-short'); ?></div>
          </div>
        </div>
        <div class="ed-checkout-upsells-popup__footer">
          <button type="button" class="ed-checkout-upsells-popup__skip button" id="ed-checkout-upsells-skip">
            <?php esc_html_e('דלג והמשך לתשלום', 'deliz-short'); ?>
          </button>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * AJAX handler for getting upsell products
   */
  public static function ajax_get_upsells() {
    check_ajax_referer('ed-checkout-upsells-nonce', 'nonce');

    $product_ids = self::get_upsell_products();
    
    if (empty($product_ids)) {
      wp_send_json_success(['html' => '<p>' . __('לא נמצאו מוצרים להצגה', 'deliz-short') . '</p>', 'count' => 0]);
      return;
    }

    ob_start();
    ?>
    <ul class="products columns-4 ed-checkout-upsells-popup__products-grid">
      <?php
      global $post;
      foreach ($product_ids as $product_id) {
        $post_object = get_post($product_id);
        if (!$post_object) continue;
        
        setup_postdata($GLOBALS['post'] = $post_object);
        wc_get_template_part('content', 'product');
      }
      wp_reset_postdata();
      ?>
    </ul>
    <?php
    $html = ob_get_clean();

    wp_send_json_success([
      'html' => $html,
      'count' => count($product_ids),
    ]);
  }


  /**
   * Enqueue CSS and JS assets
   */
  public static function enqueue_assets() {
    if (!self::is_enabled()) {
      return;
    }

    // Only enqueue if WooCommerce is active
    if (!function_exists('WC') || !WC()->cart) {
      return;
    }

    // CSS
    wp_enqueue_style(
      'ed-checkout-upsells',
      get_template_directory_uri() . '/includes/lib/checkout-upsells/assets/css/checkout-upsells.css',
      [],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0'
    );

    // JS
    wp_enqueue_script(
      'ed-checkout-upsells',
      get_template_directory_uri() . '/includes/lib/checkout-upsells/assets/js/checkout-upsells.js',
      ['jquery'],
      defined('DELIZ_SHORT_VERSION') ? DELIZ_SHORT_VERSION : '1.0.0',
      true
    );

    // Check if product popup script exists
    $popup_enabled = wp_script_is('deliz-short-product-popup', 'enqueued') || wp_script_is('deliz-short-product-popup', 'registered');
    $popup_config = $popup_enabled ? [
      'endpoint' => rest_url('ed/v1/product-popup'),
      'addToCartUrl' => rest_url('ed/v1/add-to-cart'),
      'restNonce' => wp_create_nonce('wp_rest'),
    ] : null;

    // Get title for popup
    $title = get_field('checkout_upsells_title', 'option') ?: 'מוצרי קופה';

    // Localize script
    wp_localize_script('ed-checkout-upsells', 'ED_CHECKOUT_UPSELLS', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('ed-checkout-upsells-nonce'),
      'title' => $title,
      'popupEnabled' => $popup_enabled,
      'popupConfig' => $popup_config,
      'i18n' => [
        'adding' => __('מוסיף...', 'deliz-short'),
        'added' => __('נוסף לסל!', 'deliz-short'),
        'error' => __('שגיאה בהוספה לסל', 'deliz-short'),
      ],
    ]);
  }
}

// Initialize
ED_Checkout_Upsells::init();
