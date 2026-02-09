<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.1.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order">
	<div class="thankyou-inner">
	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</p>

		<?php else : ?>
			
			<?php //wc_get_template( 'checkout/order-received.php', array( 'order' => $order ) ); ?>
			<div class="thankyou-content">
				<h1>THANK YOU</h1>
				<div class="thanks">הזמנתכם התקבלה. תודה שרכשתם אצלנו!</div>
				<div class="order-num">מספר הזמנתכם הינו: <b><?php echo $order->get_order_number(); ?></b><br/>אישור ופרטי ההזמנה נשלחו אליכם במייל.</div>
				<div class="contact">
					<p>מוקד שירות הלקוחות שלנו זמין עבורכם לכל שאלה:</p>
					<p class="contact-inner">
						<span class="phone">טלפון - <?php echo do_shortcode('[oc_theme_contact_phone]'); ?></span>
						<span class="mail">אימייל - <?php echo do_shortcode('[oc_theme_contact_email]'); ?></span>
					<p>
				</div>
				<div class="social">
					<h2>LET’S BE FRIENDS</h2>
					<div class="social-txt">בקרו אותנו ברשתות החברתיות והישארו מעודכנים בכל הפריטים החדשים וההנחות המתחלפות.</div>
					<?php echo do_shortcode('[oc_footer_social_network_list]'); ?>
				</div>
			</div>
        <?php
        // Add the missing action here
        do_action( 'woocommerce_thankyou', $order->get_id() );
        ?>
		<?php endif; ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>
	</div>
</div>
