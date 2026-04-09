<?php
if (!defined( 'ABSPATH' )) exit;
?>
<div class="woocommerce-order">
	<?php if ( $order ) : ?>
		<div class="thank-you-order">
			<h1><?php _e('Thank you for your order', 'woocommerce'); ?> <?php echo $order->get_billing_first_name(); ?></h1>
			<div id="order-num"><?php _e('Order Number', 'woocommerce'); ?> : <?php echo $order->get_order_number(); ?></div>
			<div id="order-email"><?php the_field('thank_you_text', 'options'); ?></div>
			<?php if(is_user_logged_in()){ ?>
				<a href="/my-account/view-order/<?php echo $order->get_order_number(); ?>"><?php _e('View your order', 'deliz-short'); ?></a>
			<?php }else{ ?>
				<a href="/"><?php _e('To home page', 'deliz-short'); ?></a>
			<?php } ?>
		</div>
	<?php else : ?>
		<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Thank you. Your order has been received.', 'woocommerce' ), null ); ?></p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>