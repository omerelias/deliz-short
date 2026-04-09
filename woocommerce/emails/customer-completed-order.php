<?php
/**
 * Customer completed order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-completed-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>


<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<title><?php echo get_bloginfo( 'name', 'display' ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
		<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
			<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
				<tr>
					<td align="center" valign="top">
						<div id="template_header_image">
							<?php
								if ( $img = get_option( 'woocommerce_email_header_image' ) ) {
									echo '<p style="margin-top:0;"><img src="' . esc_url( $img ) . '" alt="' . get_bloginfo( 'name', 'display' ) . '" /></p>';
								}
							?>
						</div>
						<table border="0" cellpadding="0" cellspacing="0" width="90%" id="template_container">
							<tr>
								<td align="center" valign="top">
									<!-- Body -->
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
										<tr>
											<td valign="top" id="body_content">
												<!-- Content -->
												<table border="0" cellpadding="20" cellspacing="0" width="100%">
													<tr>
														<td valign="top">
															<div id="body_content_inner">

<?php /* translators: %s: Customer first name */ ?>
<div class="email-head">
	<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
	<?php if ( $additional_content ) { echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );} ?>
	<?php /* translators: %s: Order number */ ?>
	<p style="font-size: 16px; font-weight: bold;"><?php 
		//printf( esc_html__( 'Below are your order number #%s', 'woocommerce' ), esc_html( $order->get_order_number() ) ); 
		echo wp_kses_post( sprintf( ' (<time datetime="%s">%s</time>)', $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	?></p>
</div>

<?php

$text_align = is_rtl() ? 'right' : 'left';


$address = sea2door_order_formatted_billing_address($order);
$shipping   = $order->get_formatted_shipping_address();

$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
$company_num = get_post_meta( $order_id , "_billing_company_num", true );
$company_name = $order->get_billing_company();
$billing_date = get_post_meta( $order_id , "_billing_date", true );
$house_num = get_post_meta( $order_id , "_billing_house_num", true );

$billing_apartment = get_post_meta( $order_id , "_billing_apartment", true );
$billing_floor = get_post_meta( $order_id , "_billing_floor", true );
$billing_enter_code = get_post_meta( $order_id , "_billing_enter_code", true );
$billing_notes = get_post_meta( $order_id , "_billing_notes", true );

$ocws_recipient_firstname = get_post_meta( $order_id , "ocws_recipient_firstname", true );
$ocws_recipient_lastname = get_post_meta( $order_id , "ocws_recipient_lastname", true );
$ocws_recipient_phone = get_post_meta( $order_id , "ocws_recipient_phone", true );
$ocws_recipient_greeting = get_post_meta( $order_id , "ocws_recipient_greeting", true );

foreach ( $order->get_shipping_methods() as $shipping_method ) {
	$shipping_method_name = $shipping_method->get_name();
}

// ERROR LOG - /home/seadoors/domains/sea2door.s409.upress.link/public_html/wp-content/themes/sea2door/woocommerce/emails/admin-new-order.php(93): ocws_render_shipping_date_info(Object(Automattic\WooCommerce\Admin\Overrides\Order))
$shipping_date_info = '';
if (function_exists('ocws_render_shipping_date_info')) {
	$shipping_date_info = ocws_render_shipping_date_info( $order );
}

?><table id="addresses" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
	<tr>
		<td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="50%">
			<!--<h2><?php /* esc_html_e( 'Billing address', 'woocommerce' ); */ ?></h2>-->

			<address class="address" style="padding: 20px;">
				<div style="width: 49.8%; display: inline-block; vertical-align: top;">
					<h3 style="font-size: 21px !important; margin-top: unset !important;"><?php _e('Costumer details:', 'deliz-short'); ?></h3>
					<ul style="padding: 0; margin: 0; list-style-type: none; font-size:18px;">
						<li><?php echo "<b>" . __('Name', 'woocommerce') . '</b>: ' . $order->get_billing_first_name() .' '. $order->get_billing_last_name(); ?></li>
						<li><?php echo "<b>" . __('Phone', 'woocommerce') .'</b>: ' . $order->get_billing_phone(); ?></li>
						<li><?php echo "<b>" . __('Email', 'woocommerce') .'</b>: ' . $order->get_billing_email(); ?></li>
						<?php if($company_name){ echo "<li><b>" . __('Company name.', 'deliz-short') .'</b>: ' . $company_name . '</li>'; }?>
						<?php if($company_num){ echo "<li><b>" . __('Company number', 'deliz-short') .'</b>: ' . $company_num . '</li>'; }?>
					</ul>
				</div>
				<div style="width: 48%; display: inline-block; vertical-align: top;">
					<h3 style="font-size: 21px !important; margin-top: unset !important;"><?php _e('Delivery / Pickup details:', 'deliz-short'); ?></h3>
					<ul style="padding: 0; margin: 0; list-style-type: none; font-size:18px;">
					
					<?php if( $shipping_method_name == "איסוף עצמי"){ ?>
						<li><?php echo do_shortcode("[ocws_render_shipping_info order_id='{$order->get_id()}']"); ?></li>
						<?php if($order->get_customer_note()){ ?><li><b><?php echo __('Customer note', 'woocommerce') . ':</b> ' . $order->get_customer_note(); ?></li><?php } ?>
						<?php if($billing_notes){ echo '<li><b>'.__('Order notes', 'deliz-short') .':</b> ' . $billing_notes . '</li>'; }?>
						<?php
					}else{ ?>
						<?php
						 $billing_city_title = apply_filters( 'ocws_get_city_title', $order->get_billing_city() );
						?>
						<?php if($ocws_recipient_firstname){ echo '<li><b>' . __('Recipient name:', 'deliz-short') . '</b> ' . $ocws_recipient_firstname . ' ' . $ocws_recipient_lastname . '</li>'; }?>
						<?php if($ocws_recipient_phone){ echo '<li><b>' . __('Recipient phone:', 'deliz-short') . '</b> ' . $ocws_recipient_phone . '</li>'; }?>
						<?php if($ocws_recipient_greeting){ echo '<li><b>' . __('Recipient greeting:', 'deliz-short') . '</b> ' . $ocws_recipient_greeting . '</li>'; }?>
						<?php //if($shipping_date_info){ echo '<li>' . $shipping_date_info . '</li>'; }?>
						<?php echo '<li>'.do_shortcode("[ocws_render_shipping_info order_id='{$order->get_id()}']").'</li>'; ?>
						<span><?php echo '<b>' . __('Address:', 'woocommerce') . '</b> <span style="display: inline-block;">' . $order->get_billing_city() .', '. $order->get_billing_address_1() .'</span><br> <b>' . __('Apartment:', 'deliz-short') . '</b> <span style="display: inline-block;">'. $billing_apartment .'</span> <b>' . __('Floor:', 'deliz-short') . '</b> <span style="display: inline-block;"> '. $billing_floor .'</span>'; ?><?php if($billing_enter_code): ?> <b> <?php echo __('Enter code:', 'deliz-short'); ?></b><span style="display: inline-block;"> <?php echo $billing_enter_code; ?></span><?php endif; ?></li>
						<?php if($order->get_customer_note()){ ?><li><?php echo '<b>' . __('Customer note:', 'woocommerce') . '</b>: ' . $order->get_customer_note(); ?></li><?php } ?>
						<?php if($billing_notes){ echo '<li><b>' . __('Order notes:', 'deliz-short') . '</b> ' . $billing_notes . '</li>'; }?>
					<?php } ?>
					</ul>
				</div>
			</address>
		</td>
		<?php if (0){ if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && $shipping ) : ?>
		<td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; padding:0;" valign="top" width="50%">
			<h2><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>

			<address class="address"><?php echo wp_kses_post( $shipping ); ?></address>
		</td>
		<?php endif; } ?>
	</tr>
</table>

<?php do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Ordered products', 'deliz-short' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Pcs / kg', 'deliz-short' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => false,
					'image_size'    => array( 32, 32 ),
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				)
			);
			?>
		</tbody>
		<tfoot>
			<?php
			$item_totals = $order->get_order_item_totals();

			if ( $item_totals ) {
				$i = 0;
				foreach ( $item_totals as $total ) {
					$i++;
					?>
					<tr>
						<th class="td th-foot-<?= $i ?>" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['label'] ); ?></th>
						<td class="td td-foot-<?= $i ?>" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
				}
			}
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>
<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );