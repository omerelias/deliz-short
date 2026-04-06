<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user c annot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>

			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

				<div class="checkout-block checkout-block--order" data-block="order" data-popup-id="checkout-block-popup--order" id="order_review">
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					<div class="checkout-order-compact">
						<span class="checkout-order-compact__total"><?php wc_cart_totals_order_total_html(); ?></span>
						<span class="checkout-order-compact__sep">–</span>
						<button type="button" class="checkout-order-compact__products-link" data-popup-id="checkout-block-popup--order" aria-label="<?php esc_attr_e( 'צפה בפרטי ההזמנה', 'deliz-short' ); ?>">
							<?php
							$items_count = deliz_short_cart_display_items_count();

							echo esc_html( sprintf( _n( '%d מוצר', '%d מוצרים', $items_count, 'deliz-short' ), $items_count ) );
							?>
						</button>
						<div class="checkout-order-compact__note">
							<?php esc_html_e( 'המחיר כולל משלוח ויקבע לאחר השקילה - פרטים נוספים', 'deliz-short' ); ?>
						</div>
					</div>
					<div class="checkout-order-summary">
						<div class="checkout-summary-line checkout-summary-line--billing">
							<div class="checkout-summary-line__label"><?php esc_html_e( 'עבור', 'deliz-short' ); ?></div>
							<div class="checkout-summary-line__value js-checkout-summary-billing"></div>
							<button type="button" class="checkout-summary-line__edit" data-checkout-block-target="billing">
								<?php esc_html_e( 'שינוי', 'deliz-short' ); ?>
							</button>
						</div>
						<div class="checkout-summary-line checkout-summary-line--shipping">
							<div class="checkout-summary-line__label js-checkout-summary-shipping-label"><?php esc_html_e( 'משלוח ל', 'deliz-short' ); ?></div>
							<div class="checkout-summary-line__value js-checkout-summary-shipping"></div>
							<button type="button" class="checkout-summary-line__edit" data-checkout-block-target="shipping">
								<?php esc_html_e( 'שינוי', 'deliz-short' ); ?>
							</button>
						</div>
						<div class="checkout-summary-line checkout-summary-line--notes">
							<div class="checkout-summary-line__label"><?php esc_html_e( 'הערות להזמנה', 'deliz-short' ); ?></div>
							<div class="checkout-summary-line__value js-checkout-summary-notes"></div>
							<button type="button" class="checkout-summary-line__edit" data-checkout-block-target="notes">
								<?php esc_html_e( 'שינוי', 'deliz-short' ); ?>
							</button>
						</div>
					</div>
					<hr class="checkout-order-compact__hr" />
					<div class="checkout-order-payment-wrap">
						<?php do_action( 'woocommerce_checkout_payment' ); ?>
					</div>
					<div class="checkout-block-popup" id="checkout-block-popup--order" aria-hidden="true">
						<div class="checkout-block-popup__overlay"></div>
						<div class="checkout-block-popup__container checkout-block-popup__container--order">
							<button type="button" class="checkout-block-popup__close default-close-btn btn-empty" aria-label="<?php esc_attr_e( 'סגור', 'deliz-short' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" viewBox="0 0 16 14"><path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path></svg>
							</button>
							<div class="checkout-block-popup__inner">
								<h3 class="checkout-block-popup__title"><?php esc_html_e( 'ההזמנה שלי', 'deliz-short' ); ?></h3>
								<div class="checkout-order-popup-content">
									<?php do_action( 'woocommerce_checkout_order_review' ); ?>
								</div>
							</div>
						</div>
					</div>
					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>


</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
