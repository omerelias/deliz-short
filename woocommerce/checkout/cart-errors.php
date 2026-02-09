<?php
/**
 * Cart errors page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/cart-errors.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;
?>

<p><?php esc_html_e( 'There are some issues with the items in your cart. Please go back to the cart page and resolve these issues before checking out.', 'woocommerce' ); ?></p>

<?php do_action( 'woocommerce_cart_has_errors' ); ?>

<p style="padding-bottom: 50px;"><a class="button wc-backward woocommerce_cart_has_errors" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Return to cart', 'woocommerce' ); ?></a></p>

<style>
body.rtl.woocommerce-checkout:not(.woocommerce-order-pay):not(.woocommerce-order-received):not(.home) .site-content{
	background: #fff;
}
.entry-content{
	text-align: center;
}
a.woocommerce_cart_has_errors {
    border: 0!important;
    border-radius: 0!important;
    background: none!important;
    cursor: pointer!important;
    padding: 10px 22px!important;
    text-decoration: none!important;
    font-weight: 400!important;
    text-shadow: none!important;
    display: inline-block!important;
    -webkit-appearance: none!important;
    position: relative!important;
    background-color: var(--button-primary-background)!important;
    border-color: var(--button-primary-background)!important;
    color: var(--button-primary-color)!important;    
	margin-top: 30px!important;
}
a.woocommerce_cart_has_errors:hover{
    background-color: var(--button-primary-background-hover)!important;
    color: var(--button-primary-text-hover)!important;	
}
a.woocommerce_cart_has_errors:before{
    position: absolute!important;
    content: ""!important;
    display: block;
    left: 0!important;
    top: 0!important;
    right: 0!important;
    bottom: 0!important;
    width: 100%!important;
    height: 100%!important;
    transform: scale(1)!important;
    transform-origin: right center!important;
    z-index: -1!important;
    background-color:var(--button-primary-background)!important;
	border-radius: 3px;
}
</style>