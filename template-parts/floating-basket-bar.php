<?php
if ( ! defined('ABSPATH') ) exit;
if ( ! function_exists('WC') ) return;

$cart  = WC()->cart;
$count = $cart ? (int) $cart->get_cart_contents_count() : 0;
$total = $cart ? $cart->get_cart_total() : wc_price(0);

$is_active = $count > 0;
?>
<div class="ed-basket-bar <?php echo $is_active ? 'basket-btn-active' : ''; ?>" id="ed-basket-bar" aria-label="<?php esc_attr_e('Basket bar', 'deliz-short'); ?>">
  <button
    type="button"
    class="ed-basket-bar__btn"
    id="ed-basket-toggle"
    aria-expanded="false"
  >
    <span class="ed-basket-bar__price"><?php echo wp_kses_post($total); ?></span>
    <span class="ed-basket-bar__label"><?php echo esc_html__('צפה בהזמנה', 'deliz-short'); ?></span>
    <span class="ed-basket-bar__count" aria-label="<?php esc_attr_e('Items in cart', 'deliz-short'); ?>">
      <?php echo esc_html($count); ?>
    </span>
  </button>
</div>
