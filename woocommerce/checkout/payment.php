<?php
/**
 * Checkout Payment Section
 *
 * Template override for WooCommerce checkout payment area.
 * Adds a class when only one payment method is available so we can hide the list.
 *
 * @package WooCommerce\Templates
 * @version 3.5.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_checkout() ) {
	return;
}

?>
<div id="payment" class="woocommerce-checkout-payment">
	<h2><?php esc_html_e( 'Payment methods', 'deliz-short' ); ?></h2>
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<?php
		$available_gateways = WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : array();
		$has_multiple       = ! empty( $available_gateways ) && count( $available_gateways ) > 1;
		?>
		<ul class="wc_payment_methods payment_methods methods <?php echo $has_multiple ? '' : 'single-payment-method'; ?>">
			<?php if ( ! empty( $available_gateways ) ) : ?>
				<?php foreach ( $available_gateways as $gateway ) : ?>
					<?php wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">
					<?php echo wp_kses_post( apply_filters( 'woocommerce_no_available_payment_methods_message', __( 'Sorry, it seems that there are no available payment methods for your state.', 'woocommerce' ) ) ); ?>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<div class="form-row place-order">
		<noscript>
			<?php
			printf(
				/* translators: $1%s and $2%s opening and closing emphasis tags respectively */
				esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ),
				'<em>',
				'</em>'
			);
			?>
			<br/><button type="submit" class="button alt" name="woocommerce_checkout_update_totals"
				value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt checkout-order-submit-btn" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>

