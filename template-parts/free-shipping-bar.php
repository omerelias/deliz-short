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
			<svg width="25" height="25" class="ed-free-ship-bar__truck" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
			<rect width="22" height="22" fill="url(#pattern0_36_857)"/>
			<defs>
			<pattern id="pattern0_36_857" patternContentUnits="objectBoundingBox" width="1" height="1">
			<use xlink:href="#image0_36_857" transform="scale(0.005)"/>
			</pattern>
			<image id="image0_36_857" width="200" height="200" preserveAspectRatio="none" xlink:href="data:image/png;base64,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"/>
			</defs>
			</svg>
		</div>
	</div>
</div>
