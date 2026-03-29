<?php
/**
 * Free-shipping progress bar (Figma: סל קניות) — single truck goal marker.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$bar = get_query_var( 'deliz_free_ship_bar', array() );
if ( ! function_exists( 'WC' ) || empty( $bar['show'] ) ) {
	return;
}
$_ed_pct = isset( $bar['percent'] ) ? (int) $bar['percent'] : 0;
$_ed_pct = min( 100, max( 0, $_ed_pct ) );
$_ed_ok  = ! empty( $bar['reached'] );
?>
<div
	class="ed-free-ship-bar<?php echo $_ed_ok ? ' ed-free-ship-bar--complete' : ''; ?>"
	role="progressbar"
	aria-valuemin="0"
	aria-valuemax="100"
	aria-valuenow="<?php echo esc_attr( (string) $_ed_pct ); ?>"
	aria-label="<?php esc_attr_e( 'התקדמות עד משלוח חינם', 'deliz-short' ); ?>"
>
	<p class="ed-free-ship-bar__label" dir="rtl"><?php echo isset( $bar['label_html'] ) ? $bar['label_html'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<div class="ed-free-ship-bar__visual" dir="ltr">
		<div class="ed-free-ship-bar__track">
			<div class="ed-free-ship-bar__fill" style="width: <?php echo esc_attr( (string) $_ed_pct ); ?>%;"></div>
		</div>
		<div class="ed-free-ship-bar__goal" aria-hidden="true">
			<svg class="ed-free-ship-bar__truck" width="22" height="17" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg" role="presentation" focusable="false">
				<path d="M1 3.5h7v5H1v-5Z" stroke="#191919" stroke-width="1.15" stroke-linejoin="round"/>
				<path d="M8 5.5h3.5l3 2.5V8.5H8V5.5Z" stroke="#191919" stroke-width="1.15" stroke-linejoin="round"/>
				<circle cx="3.5" cy="11" r="1.35" stroke="#191919" stroke-width="1.15"/>
				<circle cx="11.5" cy="11" r="1.35" stroke="#191919" stroke-width="1.15"/>
				<path d="M5.5 11h4.5" stroke="#191919" stroke-width="1.15" stroke-linecap="round"/>
			</svg>
		</div>
	</div>
</div>
