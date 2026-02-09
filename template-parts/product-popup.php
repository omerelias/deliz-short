<?php
/**
 * Product Popup Template
 * 
 * @var array $product_data Product data from REST API
 */

if (!defined('ABSPATH')) exit;
if (empty($product_data)) return;

$product_id = $product_data['id'];
$ocwsu = $product_data['ocwsu'] ?? [];
$options = $product_data['options'] ?? [];
?>

<div class="ed-product-popup" id="ed-product-popup" role="dialog" aria-modal="true" aria-labelledby="popup-product-title">
  <div class="ed-product-popup__overlay"></div>
  
  <div class="ed-product-popup__container">
    <button class="ed-product-popup__close default-close-btn btn-empty" type="button" aria-label="<?php esc_attr_e('סגור פופאפ מוצר', 'deliz-short'); ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" role="presentation" viewBox="0 0 16 14">
        <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
      </svg>
    </button>

    <div class="ed-product-popup__content">
      <!-- Product Image -->
      <div class="ed-product-popup__image">
        <img src="<?php echo esc_url($product_data['image']['url']); ?>" 
             alt="<?php echo esc_attr($product_data['image']['alt']); ?>" 
             id="popup-product-image"
             loading="lazy">
      </div>

      <!-- Product Info -->
      <div class="ed-product-popup__info">
        <h2 class="ed-product-popup__title" id="popup-product-title">
          <?php echo esc_html($product_data['name']); ?>
        </h2>

        <!-- Price Display -->
        <div class="ed-product-popup__price">
          <?php if (!empty($ocwsu['average_weight']) && !empty($ocwsu['average_weight_label'])): ?>
            <span class="ed-product-popup__price-label">
              משקל ממוצע: <?php echo esc_html($ocwsu['average_weight']); ?> <?php echo esc_html($ocwsu['average_weight_label']); ?>
            </span>
            <span class="ed-product-popup__price-sep">-</span>
          <?php endif; ?>
          <span class="ed-product-popup__price-value">
            <?php echo wp_kses_post($product_data['price_html']); ?>
            <?php if ($ocwsu['weighable'] && ($ocwsu['sold_by_weight'] || ($ocwsu['sold_by_units'] && $ocwsu['display_price_per_100g']))): ?>
              <span class="ed-product-popup__price-unit">
                / <?php echo $ocwsu['product_weight_units'] === 'kg' ? 'ק"ג' : '100 גרם'; ?>
              </span>
            <?php elseif ($ocwsu['sold_by_units'] && !$ocwsu['weighable']): ?>
              <span class="ed-product-popup__price-unit">/ יחידה</span>
            <?php endif; ?>
          </span>
        </div>

        <hr class="ed-product-popup__divider">

        <!-- Product Description -->
        <?php if (!empty($product_data['description'])): ?>
          <div class="ed-product-popup__description">
            <?php echo wp_kses_post(wpautop($product_data['description'])); ?>
          </div>
          <hr class="ed-product-popup__divider">
        <?php endif; ?>

        <!-- Product Options -->
        <div class="ed-product-popup__options">
          <h3 class="ed-product-popup__options-title">הגדרות מוצר</h3>

          <!-- Unit Weight Selection (if variable) -->
          <?php if ($ocwsu['weighable'] && $ocwsu['sold_by_units'] && $ocwsu['unit_weight_type'] === 'variable' && !empty($ocwsu['unit_weight_options'])): ?>
            <div class="ed-product-popup__option-group">
              <label class="ed-product-popup__option-label">בחירת משקל ליחידה</label>
              <div class="ed-product-popup__radio-group" data-option="unit_weight">
                <?php foreach ($ocwsu['unit_weight_options'] as $weight): ?>
                  <?php
                  $show_weight = $weight;
                  $label = $ocwsu['product_weight_units'] === 'kg' ? 'ק"ג' : 'גרם';
                  if ($ocwsu['product_weight_units'] === 'kg' && $weight < 1) {
                    $show_weight = $weight * 1000;
                    $label = 'גרם';
                  }
                  ?>
                  <label class="ed-product-popup__radio">
                    <input type="radio" 
                           name="popup_unit_weight" 
                           value="<?php echo esc_attr($weight); ?>"
                           <?php echo ($weight === $ocwsu['unit_weight_options'][0]) ? 'checked' : ''; ?>>
                    <span class="ed-product-popup__radio-label">
                      <?php echo esc_html($show_weight); ?> <?php echo esc_html($label); ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Cutting Shapes -->
          <?php if (!empty($options['cutting_shapes']) && is_array($options['cutting_shapes'])): ?>
            <div class="ed-product-popup__option-group">
              <label class="ed-product-popup__option-label">צורות חיתוך</label>
              <div class="ed-product-popup__radio-group" data-option="cutting_shape">
                <?php foreach ($options['cutting_shapes'] as $shape): ?>
                  <?php if (is_array($shape) && isset($shape['label'])): ?>
                    <label class="ed-product-popup__radio">
                      <input type="radio" 
                             name="popup_cutting_shape" 
                             value="<?php echo esc_attr($shape['value'] ?? $shape['label']); ?>">
                      <span class="ed-product-popup__radio-label"><?php echo esc_html($shape['label']); ?></span>
                    </label>
                  <?php elseif (is_string($shape)): ?>
                    <label class="ed-product-popup__radio">
                      <input type="radio" name="popup_cutting_shape" value="<?php echo esc_attr($shape); ?>">
                      <span class="ed-product-popup__radio-label"><?php echo esc_html($shape); ?></span>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Notes for Butcher -->
          <?php if (!empty($options['notes_for_butcher'])): ?>
            <div class="ed-product-popup__option-group">
              <label class="ed-product-popup__option-label">הערות לקצב</label>
              <div class="ed-product-popup__radio-group" data-option="butcher_note">
                <?php 
                $notes = is_array($options['notes_for_butcher']) ? $options['notes_for_butcher'] : [$options['notes_for_butcher']];
                foreach ($notes as $note): 
                  if (is_array($note) && isset($note['label'])): ?>
                    <label class="ed-product-popup__radio">
                      <input type="radio" 
                             name="popup_butcher_note" 
                             value="<?php echo esc_attr($note['value'] ?? $note['label']); ?>">
                      <span class="ed-product-popup__radio-label"><?php echo esc_html($note['label']); ?></span>
                    </label>
                  <?php elseif (is_string($note)): ?>
                    <label class="ed-product-popup__radio">
                      <input type="radio" name="popup_butcher_note" value="<?php echo esc_attr($note); ?>">
                      <span class="ed-product-popup__radio-label"><?php echo esc_html($note); ?></span>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Error message for missing options -->
          <div class="ed-product-popup__error" id="popup-option-error" style="display: none;">
            נא לבחור אפשרות
          </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($options['related_products'])): ?>
          <div class="ed-product-popup__related">
            <h3 class="ed-product-popup__related-title">מוצרים נלווים</h3>
            <div class="ed-product-popup__related-list">
              <?php foreach ($options['related_products'] as $rel): ?>
                <div class="ed-product-popup__related-item" data-product-id="<?php echo esc_attr($rel['id']); ?>">
                  <img src="<?php echo esc_url($rel['image']); ?>" alt="<?php echo esc_attr($rel['name']); ?>" loading="lazy">
                  <div class="ed-product-popup__related-info">
                    <span class="ed-product-popup__related-name"><?php echo esc_html($rel['name']); ?></span>
                    <span class="ed-product-popup__related-price"><?php echo wp_kses_post($rel['price_html']); ?></span>
                  </div>
                  <button type="button" class="ed-product-popup__related-add" aria-label="<?php echo esc_attr(sprintf(__('הוסף %s לסל', 'deliz-short'), $rel['name'])); ?>">
                    <span>+</span>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sticky Bottom Section -->
      <div class="ed-product-popup__footer">
        <!-- Quantity Input -->
        <div class="ed-product-popup__quantity" id="popup-quantity-container">
          <!-- This will be populated by JavaScript based on ocwsu settings -->
        </div>

        <!-- Note Input -->
        <div class="ed-product-popup__note">
          <label for="popup-product-note">הערה למוצר השקיל</label>
          <textarea id="popup-product-note" 
                    name="product_note" 
                    rows="2" 
                    placeholder="הערות נוספות..."></textarea>
        </div>

        <!-- Add to Cart Button -->
        <button type="button" 
                class="ed-product-popup__add-btn" 
                id="popup-add-to-cart"
                data-product-id="<?php echo esc_attr($product_id); ?>"
                disabled>
          <span class="ed-product-popup__add-btn-text">הוסף לסל</span>
        </button>
      </div>
    </div>
  </div>
</div>

