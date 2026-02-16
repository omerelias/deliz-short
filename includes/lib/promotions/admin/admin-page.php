<?php
/**
 * Promotions Admin Page
 */

if (!defined('ABSPATH')) {
  exit;
}

$action = $_GET['action'] ?? 'list';
$promotion_id = intval($_GET['promotion_id'] ?? 0);

// Get promotions list
$promotions = get_posts([
  'post_type' => ED_Promotions::POST_TYPE,
  'post_status' => 'publish',
  'posts_per_page' => -1,
  'orderby' => 'date',
  'order' => 'DESC',
]);

// Get promotion data if editing
$promotion_data = null;
if ($promotion_id > 0 && $action === 'edit') {
  $promotion = get_post($promotion_id);
  if ($promotion && $promotion->post_type === ED_Promotions::POST_TYPE) {
    $target_type = get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'target_type', true);
    $target_id = intval(get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'target_id', true));
    $target_name = '';
    
    if ($target_type === 'product' && $target_id > 0) {
      $product = wc_get_product($target_id);
      $target_name = $product ? $product->get_name() : '';
    } elseif ($target_type === 'category' && $target_id > 0) {
      $term = get_term($target_id, 'product_cat');
      $target_name = $term && !is_wp_error($term) ? $term->name : '';
    }
    
    $promotion_data = [
      'id' => $promotion_id,
      'name' => $promotion->post_title,
      'type' => get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'type', true),
      'target_type' => $target_type,
      'target_id' => $target_id,
      'target_name' => $target_name,
      'discount_percent' => floatval(get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'discount_percent', true)),
      'buy_kg' => floatval(get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'buy_kg', true)),
      'pay_amount' => floatval(get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'pay_amount', true)),
      'start_date' => get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'start_date', true),
      'end_date' => get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'end_date', true),
      'has_end_date' => get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'has_end_date', true) === '1',
      'status' => get_post_meta($promotion_id, ED_Promotions::META_PREFIX . 'status', true),
    ];
  }
}
?>

<div class="wrap ed-promotions-admin">
  <h1 class="wp-heading-inline"><?php esc_html_e('מבצעים', 'deliz-short'); ?></h1>
  
  <?php if ($action === 'list' || $action === ''): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ed-promotions&action=new')); ?>" class="page-title-action">
      <?php esc_html_e('הוסף מבצע חדש', 'deliz-short'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <table class="wp-list-table widefat fixed striped ed-promotions-table">
      <thead>
        <tr>
          <th class="column-name"><?php esc_html_e('שם המבצע', 'deliz-short'); ?></th>
          <th class="column-type"><?php esc_html_e('סוג המבצע', 'deliz-short'); ?></th>
          <th class="column-target"><?php esc_html_e('תחולת המבצע', 'deliz-short'); ?></th>
          <th class="column-end-date"><?php esc_html_e('תאריך סיום', 'deliz-short'); ?></th>
          <th class="column-status"><?php esc_html_e('סטטוס', 'deliz-short'); ?></th>
          <th class="column-actions"><?php esc_html_e('פעולות', 'deliz-short'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($promotions)): ?>
          <tr>
            <td colspan="6" class="no-items">
              <?php esc_html_e('לא נמצאו מבצעים', 'deliz-short'); ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($promotions as $promotion): ?>
            <?php
            $type = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'type', true);
            $target_type = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'target_type', true);
            $target_id = intval(get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'target_id', true));
            $end_date = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'end_date', true);
            $has_end_date = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'has_end_date', true) === '1';
            $status = ED_Promotions::get_promotion_status($promotion->ID);
            
            $type_label = $type === 'discount' ? __('X% הנחה', 'deliz-short') : __('X ק"ג במחיר Y', 'deliz-short');
            
            $target_label = '';
            if ($target_type === 'product' && $target_id > 0) {
              $product = wc_get_product($target_id);
              $target_label = $product ? $product->get_name() : __('מוצר לא נמצא', 'deliz-short');
            } elseif ($target_type === 'category' && $target_id > 0) {
              $term = get_term($target_id, 'product_cat');
              $target_label = $term && !is_wp_error($term) ? $term->name : __('קטגוריה לא נמצאה', 'deliz-short');
            }
            
            $status_labels = [
              'active' => __('פעיל', 'deliz-short'),
              'future' => __('עתידי', 'deliz-short'),
              'ended' => __('הסתיים', 'deliz-short'),
              'disabled' => __('מושבת', 'deliz-short'),
            ];
            $status_label = $status_labels[$status] ?? $status;
            ?>
            <tr>
              <td class="column-name">
                <strong><?php echo esc_html($promotion->post_title); ?></strong>
              </td>
              <td class="column-type"><?php echo esc_html($type_label); ?></td>
              <td class="column-target"><?php echo esc_html($target_label); ?></td>
              <td class="column-end-date">
                <?php echo $has_end_date && $end_date ? esc_html(date_i18n('d/m/Y', strtotime($end_date))) : '—'; ?>
              </td>
              <td class="column-status">
                <span class="ed-promotion-status ed-promotion-status-<?php echo esc_attr($status); ?>">
                  <?php echo esc_html($status_label); ?>
                </span>
              </td>
              <td class="column-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ed-promotions&action=edit&promotion_id=' . $promotion->ID)); ?>" class="button button-small">
                  <?php esc_html_e('ערוך', 'deliz-short'); ?>
                </a>
                <button type="button" class="button button-small ed-toggle-status" data-promotion-id="<?php echo esc_attr($promotion->ID); ?>" data-current-status="<?php echo esc_attr($status); ?>">
                  <?php echo $status === 'disabled' ? esc_html__('הפעל', 'deliz-short') : esc_html__('השבת', 'deliz-short'); ?>
                </button>
                <button type="button" class="button button-small button-link-delete ed-delete-promotion" data-promotion-id="<?php echo esc_attr($promotion->ID); ?>">
                  <?php esc_html_e('מחק', 'deliz-short'); ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    
  <?php elseif ($action === 'new' || $action === 'edit'): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ed-promotions')); ?>" class="page-title-action">
      <?php esc_html_e('חזור לרשימה', 'deliz-short'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div class="ed-promotion-form-wrapper">
      <form id="ed-promotion-form" class="ed-promotion-form">
        <input type="hidden" name="promotion_id" value="<?php echo esc_attr($promotion_data['id'] ?? 0); ?>">
        
        <!-- Step 1: Choose Promotion Type -->
        <div class="ed-promotion-step" data-step="1">
          <h2><?php esc_html_e('שלב 1: בחירת סוג המבצע', 'deliz-short'); ?></h2>
          
          <div class="ed-promotion-type-selector">
            <label class="ed-promotion-type-option">
              <input type="radio" name="promotion_type" value="discount" <?php checked($promotion_data['type'] ?? '', 'discount'); ?> required>
              <div class="ed-promotion-type-card">
                <h3><?php esc_html_e('הנחה של X% על מוצרים', 'deliz-short'); ?></h3>
                <p><?php esc_html_e('לדוגמה: 50% הנחה על מחלקת הבשר', 'deliz-short'); ?></p>
              </div>
            </label>
            
            <label class="ed-promotion-type-option">
              <input type="radio" name="promotion_type" value="buy_x_pay_y" <?php checked($promotion_data['type'] ?? '', 'buy_x_pay_y'); ?> required>
              <div class="ed-promotion-type-card">
                <h3><?php esc_html_e('קונים X ק"ג ומשלמים Y', 'deliz-short'); ?></h3>
                <p><?php esc_html_e('לדוגמה: 3 ק"ג כבד עוף ב-4 ₪', 'deliz-short'); ?></p>
              </div>
            </label>
          </div>
          
          <div class="ed-promotion-form-actions">
            <button type="button" class="button button-primary ed-next-step"><?php esc_html_e('המשך', 'deliz-short'); ?></button>
          </div>
        </div>
        
        <!-- Step 2: Promotion Settings -->
        <div class="ed-promotion-step" data-step="2" style="display: none;">
          <h2><?php esc_html_e('שלב 2: הגדרות המבצע', 'deliz-short'); ?></h2>
          
          <div class="ed-promotion-step-content">
            <div class="ed-promotion-step-layout">
              <div class="ed-promotion-form-fields">
                <!-- Common fields -->
                <div class="ed-form-field">
                  <label for="promotion_name">
                    <?php esc_html_e('שם המבצע', 'deliz-short'); ?>
                    <span class="required">*</span>
                  </label>
                  <input type="text" id="promotion_name" name="promotion_name" value="<?php echo esc_attr($promotion_data['name'] ?? ''); ?>" required>
                </div>
                
                <!-- Discount type fields -->
                <div class="ed-promotion-type-fields ed-promotion-type-discount" style="display: none;">
                  <div class="ed-form-field">
                    <label for="discount_percent">
                      <?php esc_html_e('קבל', 'deliz-short'); ?>
                    </label>
                    <input type="number" id="discount_percent" name="discount_percent" step="0.1" min="0" max="100" value="<?php echo esc_attr($promotion_data['discount_percent'] ?? ''); ?>">
                    <span class="field-suffix">%</span>
                    <span class="field-label-after"><?php esc_html_e('הנחה', 'deliz-short'); ?></span>
                  </div>
                  
                  <div class="ed-form-field">
                    <label for="target_type_discount">
                      <?php esc_html_e('על', 'deliz-short'); ?>
                    </label>
                    <select id="target_type_discount" name="target_type_discount">
                      <option value="product" <?php selected($promotion_data['target_type'] ?? '', 'product'); ?>><?php esc_html_e('מוצר', 'deliz-short'); ?></option>
                      <option value="category" <?php selected($promotion_data['target_type'] ?? '', 'category'); ?>><?php esc_html_e('קטגוריה', 'deliz-short'); ?></option>
                    </select>
                  </div>
                  
                  <div class="ed-form-field">
                    <label for="target_search_discount">
                      <?php esc_html_e('חיפוש מוצר / קטגוריה', 'deliz-short'); ?>
                    </label>
                    <input type="text" id="target_search_discount" class="ed-target-search" data-target-type-field="target_type_discount" placeholder="<?php esc_attr_e('התחל להקליד...', 'deliz-short'); ?>" value="<?php echo esc_attr(($promotion_data['type'] ?? '') === 'discount' ? ($promotion_data['target_name'] ?? '') : ''); ?>">
                    <input type="hidden" id="target_id_discount" name="target_id_discount" value="<?php echo esc_attr(($promotion_data['type'] ?? '') === 'discount' ? ($promotion_data['target_id'] ?? '') : ''); ?>">
                    <div id="target_results_discount" class="ed-target-results"></div>
                  </div>
                </div>
                
                <!-- Buy X Pay Y type fields -->
                <div class="ed-promotion-type-fields ed-promotion-type-buy_x_pay_y" style="display: none;">
                  <div class="ed-form-field">
                    <label for="buy_kg">
                      <?php esc_html_e('קנה', 'deliz-short'); ?>
                    </label>
                    <input type="number" id="buy_kg" name="buy_kg" step="0.01" min="0" value="<?php echo esc_attr($promotion_data['buy_kg'] ?? ''); ?>">
                    <span class="field-suffix">ק"ג</span>
                  </div>
                  
                  <div class="ed-form-field">
                    <label for="target_type_buy">
                      <?php esc_html_e('מ', 'deliz-short'); ?>
                    </label>
                    <select id="target_type_buy" name="target_type_buy">
                      <option value="product" <?php selected($promotion_data['target_type'] ?? '', 'product'); ?>><?php esc_html_e('מוצר', 'deliz-short'); ?></option>
                      <option value="category" <?php selected($promotion_data['target_type'] ?? '', 'category'); ?>><?php esc_html_e('קטגוריה', 'deliz-short'); ?></option>
                    </select>
                  </div>
                  
                  <div class="ed-form-field">
                    <label for="target_search_buy">
                      <?php esc_html_e('חיפוש מוצר / קטגוריה', 'deliz-short'); ?>
                    </label>
                    <input type="text" id="target_search_buy" class="ed-target-search" data-target-type-field="target_type_buy" placeholder="<?php esc_attr_e('התחל להקליד...', 'deliz-short'); ?>" value="<?php echo esc_attr(($promotion_data['type'] ?? '') === 'buy_x_pay_y' ? ($promotion_data['target_name'] ?? '') : ''); ?>">
                    <input type="hidden" id="target_id_buy" name="target_id_buy" value="<?php echo esc_attr(($promotion_data['type'] ?? '') === 'buy_x_pay_y' ? ($promotion_data['target_id'] ?? '') : ''); ?>">
                    <div id="target_results_buy" class="ed-target-results"></div>
                  </div>
                  
                  <div class="ed-form-field">
                    <label for="pay_amount">
                      <?php esc_html_e('שלם', 'deliz-short'); ?>
                    </label>
                    <input type="number" id="pay_amount" name="pay_amount" step="0.01" min="0" value="<?php echo esc_attr($promotion_data['pay_amount'] ?? ''); ?>">
                    <span class="field-suffix">ש"ח</span>
                  </div>
                </div>
              </div>
              
              <!-- Preview Section -->
              <div class="ed-promotion-preview-section">
                <h3><?php esc_html_e('תצוגה מקדימה', 'deliz-short'); ?></h3>
                <p class="description"><?php esc_html_e('כך יראה המבצע על המוצרים באתר', 'deliz-short'); ?></p>
                
                <div class="ed-promotion-preview">
                  <div class="ed-promotion-preview-product">
                    <?php
                    // Get a random product for preview (or placeholder)
                    $preview_product = null;
                    $products = wc_get_products(['limit' => 1, 'orderby' => 'rand']);
                    if (!empty($products)) {
                      $preview_product = $products[0];
                    }
                    ?>
                    <div class="ed-preview-product-image">
                      <?php if ($preview_product && $preview_product->get_image_id()): ?>
                        <?php echo wp_get_attachment_image($preview_product->get_image_id(), 'woocommerce_thumbnail'); ?>
                      <?php else: ?>
                        <div class="ed-preview-placeholder-image">
                          <span class="dashicons dashicons-format-image"></span>
                        </div>
                      <?php endif; ?>
                      <div class="ed-preview-badge-container">
                        <span class="ed-promotion-badge ed-preview-badge" id="ed-preview-badge">
                          <?php esc_html_e('תווית המבצע', 'deliz-short'); ?>
                        </span>
                      </div>
                    </div>
                    <div class="ed-preview-product-info">
                      <h4 class="ed-preview-product-name">
                        <?php echo $preview_product ? esc_html($preview_product->get_name()) : esc_html__('שם מוצר לדוגמה', 'deliz-short'); ?>
                      </h4>
                      <div class="ed-preview-product-price">
                        <?php 
                        if ($preview_product) {
                          echo wp_kses_post($preview_product->get_price_html());
                        } else {
                          echo '<span class="price">₪99.00</span>';
                        }
                        ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="ed-promotion-form-actions">
            <button type="button" class="button ed-prev-step"><?php esc_html_e('חזור', 'deliz-short'); ?></button>
            <button type="button" class="button button-primary ed-next-step"><?php esc_html_e('המשך', 'deliz-short'); ?></button>
          </div>
        </div>
        
        <!-- Step 3: Timing -->
        <div class="ed-promotion-step" data-step="3" style="display: none;">
          <h2><?php esc_html_e('שלב 3: תזמון המבצע', 'deliz-short'); ?></h2>
          
          <div class="ed-promotion-step-content">
            <div class="ed-form-field">
              <label for="start_date">
                <?php esc_html_e('תאריך התחלה', 'deliz-short'); ?>
              </label>
              <input type="text" id="start_date" name="start_date" class="ed-datepicker" value="<?php echo esc_attr($promotion_data['start_date'] ?? date('Y-m-d')); ?>" readonly>
              <button type="button" class="button ed-select-date"><?php esc_html_e('בחר תאריך אחר', 'deliz-short'); ?></button>
            </div>
            
            <div class="ed-form-field">
              <label>
                <input type="checkbox" id="has_end_date" name="has_end_date" <?php checked($promotion_data['has_end_date'] ?? false); ?>>
                <?php esc_html_e('קבע תאריך סיום', 'deliz-short'); ?>
              </label>
            </div>
            
            <div class="ed-form-field ed-end-date-field" style="display: <?php echo ($promotion_data['has_end_date'] ?? false) ? 'block' : 'none'; ?>;">
              <label for="end_date">
                <?php esc_html_e('תאריך סיום', 'deliz-short'); ?>
                <span class="field-note"><?php esc_html_e('(מסתיים ב-23:59)', 'deliz-short'); ?></span>
              </label>
              <input type="text" id="end_date" name="end_date" class="ed-datepicker" value="<?php echo esc_attr($promotion_data['end_date'] ?? ''); ?>" readonly>
            </div>
          </div>
          
          <div class="ed-promotion-form-actions">
            <button type="button" class="button ed-prev-step"><?php esc_html_e('חזור', 'deliz-short'); ?></button>
            <button type="submit" class="button button-primary"><?php esc_html_e('שמור מבצע', 'deliz-short'); ?></button>
          </div>
        </div>
        
        <div class="ed-promotion-form-messages"></div>
      </form>
    </div>
    
  <?php endif; ?>
</div>

