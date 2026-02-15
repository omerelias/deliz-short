<?php
/**
 * Template for displaying checkout upsells
 * 
 * @var string $template_title
 * @var array $template_product_ids
 */

if (!defined('ABSPATH')) {
  exit;
}

// Use template variables or fallback to function scope
$title = isset($template_title) ? $template_title : 'מוצרי קופה';
$product_ids = isset($template_product_ids) ? $template_product_ids : [];
?>

<div class="ed-checkout-upsells" id="ed-checkout-upsells">
  <h3 class="ed-checkout-upsells__title"><?php echo esc_html($title); ?></h3>
  
  <div class="ed-checkout-upsells__products">
    <?php
    foreach ($product_ids as $product_id) {
      $product = wc_get_product($product_id);
      if (!$product) continue;
      
      $image = $product->get_image('woocommerce_thumbnail', ['class' => 'ed-checkout-upsells__product-image']);
      $name = $product->get_name();
      $price = $product->get_price_html();
      $permalink = $product->get_permalink();
      $in_stock = $product->is_in_stock();
      ?>
      <div class="ed-checkout-upsells__product" data-product-id="<?php echo esc_attr($product_id); ?>">
        <a href="<?php echo esc_url($permalink); ?>" class="ed-checkout-upsells__product-link">
          <div class="ed-checkout-upsells__product-image-wrap">
            <?php echo $image; // phpcs:ignore ?>
          </div>
          <h4 class="ed-checkout-upsells__product-name"><?php echo esc_html($name); ?></h4>
          <div class="ed-checkout-upsells__product-price"><?php echo $price; // phpcs:ignore ?></div>
        </a>
        <?php if ($in_stock): ?>
          <button type="button" 
                  class="ed-checkout-upsells__add-btn button" 
                  data-product-id="<?php echo esc_attr($product_id); ?>"
                  aria-label="<?php echo esc_attr(sprintf(__('הוסף %s לסל', 'deliz-short'), $name)); ?>">
            <span class="ed-checkout-upsells__add-btn-text"><?php esc_html_e('הוסף לסל', 'deliz-short'); ?></span>
            <span class="ed-checkout-upsells__add-btn-loader" style="display: none;"><?php esc_html_e('מוסיף...', 'deliz-short'); ?></span>
          </button>
        <?php else: ?>
          <span class="ed-checkout-upsells__out-of-stock"><?php esc_html_e('זמנית אזל המלאי', 'deliz-short'); ?></span>
        <?php endif; ?>
      </div>
      <?php
    }
    ?>
  </div>
</div>

