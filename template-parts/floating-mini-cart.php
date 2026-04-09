<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if (!function_exists('WC')) return;

$cart = WC()->cart;
$count = $cart ? (int)$cart->get_cart_contents_count() : '';

// דיבוג: רץ תמיד כש-DELIZ_FREE_SHIP_BAR_DEBUG (גם סל ריק — קודם היה רק בתוך if !is_empty).
if (function_exists('deliz_short_free_ship_bar_debug')) {
	deliz_short_free_ship_bar_debug(
		'floating-mini-cart TEMPLATE START',
		array(
			'has_cart'  => (bool) $cart,
			'is_empty'  => ($cart && method_exists($cart, 'is_empty')) ? $cart->is_empty() : null,
			'const_on'  => defined('DELIZ_FREE_SHIP_BAR_DEBUG') ? DELIZ_FREE_SHIP_BAR_DEBUG : 'NOT_DEFINED',
			'filter_on' => function_exists('deliz_short_free_ship_bar_debug_is_enabled') ? deliz_short_free_ship_bar_debug_is_enabled() : null,
		)
	);
}

// סל ריק: עדיין להריץ את פונקציית הבר כדי שיופיעו DUMPS (RETURN empty).
if (
	function_exists('deliz_short_get_free_shipping_bar_data') &&
	function_exists('deliz_short_free_ship_bar_debug_is_enabled') &&
	deliz_short_free_ship_bar_debug_is_enabled() &&
	$cart &&
	$cart->is_empty()
) {
	deliz_short_get_free_shipping_bar_data($cart);
}
?>

<aside class="ed-float-cart" id="ed-float-cart" aria-label="<?php esc_attr_e('Mini cart', 'deliz-short'); ?>"
	<?php
	if (function_exists('deliz_short_free_ship_bar_debug_is_enabled') && deliz_short_free_ship_bar_debug_is_enabled()) {
		echo ' data-deliz-fs-bar-debug="1"';
	}
	?>
>
	<div class="ed-float-cart__inner">
		<header class="ed-float-cart__header">
			<h3 class="ed-float-cart__title">
				<?php echo esc_html__('My Order', 'deliz-short'); ?>
				<?php //f ($count) : ?>
					<!--<span class="ed-float-cart__count" aria-label="<?php esc_attr_e('כמות בסל', 'deliz-short'); ?>">
						(<?php //echo esc_html($count); ?>)
					</span>-->
				<?php //endif; ?>
			</h3>

			<!--<div class="ed-float-cart__meta">
				<?php
				$count = $cart ? $cart->get_cart_contents_count() : 0;
				printf(
					'<span class="ed-float-cart__count">%s</span>',
					esc_html(sprintf(_n('%d מוצר', '%d מוצרים', $count, 'deliz-short'), $count))
				);
				?>
			</div>-->

			<button class="cart-close default-close-btn btn-empty" type="button"
					aria-label="<?php esc_attr_e('סגירה של סל הקניות', 'deliz-short'); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" role="presentation"
					 viewBox="0 0 16 14">
					<path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
				</svg>
			</button>

			<?php
			// בר משלוח חינם בהדר (במקום ה-chip); ה-chip בפוטר — theme-free-shipping-bar.php
			?>
			<div class="ed-float-cart__header-shipping">
				<?php do_action('deliz_short_float_cart_header_shipping'); ?>
			</div>
		</header>

		<div class="ed-float-cart__items" role="list">
			<?php if (!$cart || $cart->is_empty()) : ?>
				<div class="ed-float-cart__empty">
					<svg width="80" height="80" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd"
							  d="M23.0346 22.2883L21.8894 9.39264C21.8649 9.10634 21.6236 8.88957 21.3414 8.88957H18.9855C18.9528 6.73824 17.1941 5 15.0346 5C12.8751 5 11.1164 6.73824 11.0837 8.88957H8.72786C8.44156 8.88957 8.20434 9.10634 8.1798 9.39264L7.03461 22.2883C7.03461 22.2965 7.03359 22.3047 7.03256 22.3129L7.03256 22.3129L7.03256 22.3129C7.03154 22.3211 7.03052 22.3292 7.03052 22.3374C7.03052 23.8057 8.37612 25 10.0326 25H20.0367C21.6931 25 23.0387 23.8057 23.0387 22.3374C23.0387 22.3211 23.0387 22.3047 23.0346 22.2883ZM15.0346 6.10425C16.5847 6.10425 17.8485 7.3476 17.8812 8.88952H12.188C12.2207 7.3476 13.4845 6.10425 15.0346 6.10425ZM10.0326 23.8957H20.0367C21.0714 23.8957 21.9181 23.2086 21.9344 22.3619L20.8342 9.99792H18.9855V11.6748C18.9855 11.9816 18.7401 12.227 18.4334 12.227C18.1266 12.227 17.8812 11.9816 17.8812 11.6748V9.99792H12.1839V11.6748C12.1839 11.9816 11.9385 12.227 11.6318 12.227C11.325 12.227 11.0796 11.9816 11.0796 11.6748V9.99792H9.23094L8.13483 22.3619C8.15119 23.2086 8.99372 23.8957 10.0326 23.8957Z"
							  fill="#0F0F0F"></path>
					</svg>

					<?php echo esc_html__('הסל ריק, אבל לא להרבה זמן :)', 'deliz-short'); ?>
				</div>
			<?php else :
				// הפריט שנוסף אחרון מוצג ראשון; true שומר מפתחות cart_item_key לעדכוני AJAX
				$deliz_float_cart_ordered = array_reverse($cart->get_cart(), true);
				foreach ($deliz_float_cart_ordered as $cart_item_key => $cart_item) :
					$product = $cart_item['data'];

					if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) continue;

					$product_id = $cart_item['product_id'];
					$name = $product->get_name();
					$name_full = $name;
					if (function_exists('deliz_short_float_cart_line_title_variation')) {
						list($float_cart_product_title, $float_cart_variation_html) = deliz_short_float_cart_line_title_variation($cart_item, $product);
					} else {
						$float_cart_product_title = $name;
						$float_cart_variation_html = '';
					}

					// WooCommerce cart quantity is float for weighable (kg); (int)0.5 === 0 breaks display + subtotal.
					$qty_raw = floatval($cart_item['quantity']);
					$weighable = (get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes');
					$sold_by_units = (get_post_meta($product_id, '_ocwsu_sold_by_units', true) === 'yes');
					$sold_by_weight = (get_post_meta($product_id, '_ocwsu_sold_by_weight', true) === 'yes');
					$product_weight_units = get_post_meta($product_id, '_ocwsu_product_weight_units', true);
					$unit_weight_type_oc = get_post_meta($product_id, '_ocwsu_unit_weight_type', true);
					$quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
					$ocwsu_units_qty_ui = ($weighable && $sold_by_units && $quantity_in_units > 0);
					$ocwsu_gram_weight_qty_ui = ($weighable && $sold_by_weight && !$ocwsu_units_qty_ui && function_exists('deliz_short_ocwsu_product_weight_is_grams') && deliz_short_ocwsu_product_weight_is_grams($product_weight_units));
					$ocwsu_weight_input_show_kg = false;
					$ocwsu_kg_per_unit = 0.0;

					if ($ocwsu_units_qty_ui) {
						$ocwsu_kg_per_unit = $qty_raw / max($quantity_in_units, 1e-9);
					}

					$qty_is_whole = (abs($qty_raw - round($qty_raw)) < 1e-9);

					$weight_step_meta = get_post_meta($product_id, '_ocwsu_weight_step', true);
					$weight_step = ($weight_step_meta !== '' && is_numeric($weight_step_meta) && floatval($weight_step_meta) > 0)
						? wc_format_decimal((float) $weight_step_meta, true)
						: 'any';

					$min_w_meta_oc = get_post_meta($product_id, '_ocwsu_min_weight', true);
					$min_w_numeric = ($min_w_meta_oc !== '' && is_numeric($min_w_meta_oc) && floatval($min_w_meta_oc) > 0)
						? floatval($min_w_meta_oc)
						: 0.0;
					if ($min_w_numeric <= 0 && $sold_by_weight && function_exists('deliz_short_ocwsu_meta_weight_to_grams')) {
						$min_w_numeric = 1.0;
					}
					$ocwsu_min_g = ($min_w_numeric > 0 && function_exists('deliz_short_ocwsu_meta_weight_to_grams'))
						? deliz_short_ocwsu_meta_weight_to_grams($min_w_numeric, $product_weight_units)
						: 0.0;
					if ($ocwsu_min_g <= 0 && $sold_by_weight && function_exists('deliz_short_ocwsu_meta_weight_to_grams')) {
						$ocwsu_min_g = deliz_short_ocwsu_meta_weight_to_grams(1.0, $product_weight_units);
					}
					$ocwsu_min_grams_str = wc_format_decimal(max(0, $ocwsu_min_g), true);
					$ocwsu_min_kg_str = wc_format_decimal(max(0.0001, max(0, $ocwsu_min_g) / 1000), true);
					$ocwsu_step_kg_str = ($weight_step !== 'any' && is_numeric($weight_step) && function_exists('deliz_short_ocwsu_meta_weight_to_grams'))
						? wc_format_decimal(deliz_short_ocwsu_meta_weight_to_grams((float) $weight_step, $product_weight_units) / 1000, true)
						: 'any';
					$ocwsu_step_grams_str = ($weight_step !== 'any' && is_numeric($weight_step) && function_exists('deliz_short_ocwsu_meta_weight_to_grams'))
						? wc_format_decimal(deliz_short_ocwsu_meta_weight_to_grams((float) $weight_step, $product_weight_units), true)
						: 'any';

					$ocwsu_float_cart_weight_attrs = ($weighable && $sold_by_weight && !$ocwsu_units_qty_ui && !$ocwsu_gram_weight_qty_ui);
					$ocwsu_raw_float_show_kg = false;

					if ($ocwsu_units_qty_ui) {
						$qty_display = wc_format_decimal($quantity_in_units, 0);
						$qty_input_val = $qty_display;
						$unit_weight_type = get_post_meta($product_id, '_ocwsu_unit_weight_type', true);
						$unit_kg_for_min = 0.0;
						if ($unit_weight_type === 'variable') {
							if ($ocwsu_kg_per_unit > 0) {
								$unit_kg_for_min = $ocwsu_kg_per_unit;
							}
						} else {
							$unit_w_meta = get_post_meta($product_id, '_ocwsu_unit_weight', true);
							$unit_w_raw = ($unit_w_meta !== '' && is_numeric($unit_w_meta)) ? floatval($unit_w_meta) : 0.0;
							if ($unit_w_raw > 0 && function_exists('deliz_short_ocwsu_meta_weight_to_grams')) {
								$unit_kg_for_min = deliz_short_ocwsu_meta_weight_to_grams($unit_w_raw, $product_weight_units) / 1000;
							}
						}
						$min_kg_line = $ocwsu_min_g / 1000;
						$ocwsu_min_units = 1;
						if ($unit_kg_for_min > 0 && $min_kg_line > 0) {
							$ocwsu_min_units = max(1, (int) ceil($min_kg_line / $unit_kg_for_min - 1e-9));
						}
						$qty_input_min = (string) $ocwsu_min_units;
						$qty_input_step = '1';
					} elseif ($ocwsu_gram_weight_qty_ui) {
						// סל בק"ג; תצוגת שדה: גרם מתחת ל־1000 גרם מעוגלים, מ־1000 ומעלה — ק"ג (1, 1.2).
						$grams_rounded = (int) round((float) $qty_raw * 1000);
						$ocwsu_weight_input_show_kg = ($grams_rounded >= 1000);
						if ($ocwsu_weight_input_show_kg) {
							$qty_input_val = deliz_short_format_ocwsu_cart_weight_display_value($qty_raw, false);
							$qty_display = $qty_input_val;
							$qty_input_min = $ocwsu_min_kg_str;
							$qty_input_step = $ocwsu_step_kg_str;
						} else {
							$qty_input_val = deliz_short_format_ocwsu_cart_weight_display_value($qty_raw * 1000, true);
							$qty_display = $qty_input_val;
							$qty_input_min = $ocwsu_min_grams_str;
							$qty_input_step = ($weight_step === 'any' ? 'any' : $ocwsu_step_grams_str);
						}
					} elseif (!$weighable || $qty_is_whole) {
						$qty_display = (string) (int) round($qty_raw);
						$qty_input_val = $qty_display;
					} else {
						$qty_display = wc_format_decimal($qty_raw, true);
						$qty_input_val = $qty_display;
					}

					if (!$ocwsu_units_qty_ui) {
						if (!$ocwsu_gram_weight_qty_ui) {
							if ($weighable && $sold_by_weight) {
								$qty_input_min = $ocwsu_min_kg_str;
								$qty_input_step = ($weight_step !== 'any' && is_numeric($weight_step)) ? $weight_step : 'any';
							} else {
								$qty_input_min = $weighable ? '0.0001' : '1';
								$qty_input_step = $weighable ? $weight_step : '1';
							}
						}
					}

					// מטא ק"ג (float): תמיד שדה+תווית בק"ג גם מתחת ל־1 (0.5 ולא 500 גרם)
					if ($ocwsu_float_cart_weight_attrs) {
						$ocwsu_raw_float_show_kg = true;
						$qty_input_val = function_exists('deliz_short_format_ocwsu_cart_weight_display_value')
							? deliz_short_format_ocwsu_cart_weight_display_value($qty_raw, false)
							: wc_format_decimal($qty_raw, true);
						$qty_display = $qty_input_val;
						$qty_input_min = $ocwsu_min_kg_str;
						$qty_input_step = ($ocwsu_step_kg_str === 'any' ? 'any' : $ocwsu_step_kg_str);
					}

					$permalink = $product->is_visible() ? $product->get_permalink($cart_item) : '';
					$thumbnail = $product->get_image('woocommerce_thumbnail');
					$line_price = WC()->cart->get_product_price($product); // מחיר ליחידה (עם מטבע)
					//$subtotal = WC()->cart->get_product_subtotal($product, $qty_raw); // סה"כ שורה


					// oc-woo-sale-units: weighable line under title — משקל בלבד (לא "יחידה"), ולמכירה לפי משקל המשקל מופיע ליד שדה הכמות
					$ocwsu_display = '';
					$ocwsu_weight_qty_label = '';

					if ($weighable) {
						// Base weight from cart (always in kg in WC). "גרם" רק כשמוצר מוגדר בגרם במטא OCWSU; בק"ג — תמיד תווית ק"ג גם ל-0.5.
						$weight_qty = floatval($cart_item['quantity']);
						if (function_exists('deliz_short_ocwsu_product_weight_is_grams') && deliz_short_ocwsu_product_weight_is_grams($product_weight_units)) {
							$use_grams = ($weight_qty > 0 && $weight_qty < 1);
						} else {
							$use_grams = false;
						}
						$weight_value = $use_grams ? $weight_qty * 1000 : $weight_qty;
						$weight_unit = $use_grams ? __('גרם', 'deliz-short') : __('ק"ג', 'deliz-short');
						if (function_exists('deliz_short_format_ocwsu_cart_weight_display_value')) {
							$weight_value = deliz_short_format_ocwsu_cart_weight_display_value($weight_value, $use_grams);
						} elseif ($use_grams) {
							$weight_value = wc_format_decimal($weight_value, 0);
						} else {
							$weight_value = wc_format_decimal($weight_value, 2);
						}

						$ocwsu_weight_qty_label = sprintf('%s %s', $weight_value, $weight_unit);

						// ed-float-cart__ocwsu-qty: משקל יחידה אחת בלבד (לא סך השורה).
						// מוצר גם לפי יחידות וגם לפי משקל: ברכישה לפי משקל בלבד אין להציג ~משקל יחידה ממטא המוצר.
						// גם יחידות וגם משקל במוצר: לא להציג שורת ~משקל יחידה
						$ocwsu_skip_tilde_unit_line = ( $sold_by_units && $sold_by_weight );

						if ( ! $ocwsu_skip_tilde_unit_line && $unit_weight_type_oc === 'fixed' && $sold_by_units && function_exists( 'deliz_short_ocwsu_format_fixed_unit_weight_display' ) ) {
							$unit_w_meta_fixed = get_post_meta( $product_id, '_ocwsu_unit_weight', true );
							if ( $unit_w_meta_fixed !== '' && is_numeric( $unit_w_meta_fixed ) ) {
								$ocwsu_display = deliz_short_ocwsu_format_fixed_unit_weight_display( (float) $unit_w_meta_fixed, $product_weight_units );
							}
						} elseif ( ! $ocwsu_skip_tilde_unit_line ) {
							$ocwsu_one_item_kg = 0.0;
							if ($sold_by_units && $quantity_in_units > 0) {
								$ocwsu_one_item_kg = $qty_raw / max($quantity_in_units, 1e-9);
							}
							$ocwsu_skip_unit_weight_meta_fallback = ( $sold_by_units && $sold_by_weight && $quantity_in_units <= 0 );
							if ( ! $ocwsu_skip_unit_weight_meta_fallback && $ocwsu_one_item_kg <= 0 && ( $sold_by_units || $sold_by_weight ) && function_exists( 'deliz_short_ocwsu_meta_weight_to_grams' ) ) {
								$unit_w_meta = get_post_meta($product_id, '_ocwsu_unit_weight', true);
								$unit_w_raw = ($unit_w_meta !== '' && is_numeric($unit_w_meta)) ? floatval($unit_w_meta) : 0.0;
								if ($unit_w_raw > 0) {
									$ocwsu_one_item_kg = deliz_short_ocwsu_meta_weight_to_grams($unit_w_raw, $product_weight_units) / 1000;
								}
							}
							if ($ocwsu_one_item_kg > 0 && function_exists('deliz_short_format_ocwsu_cart_weight_display_value')) {
								if ($ocwsu_one_item_kg < 1) {
									$uv = deliz_short_format_ocwsu_cart_weight_display_value($ocwsu_one_item_kg * 1000, true);
									$ocwsu_display = sprintf('%s %s', $uv, __('גרם', 'deliz-short'));
								} else {
									$uv = deliz_short_format_ocwsu_cart_weight_display_value($ocwsu_one_item_kg, false);
									$ocwsu_display = sprintf('%s %s', $uv, __('ק"ג', 'deliz-short'));
								}
							}
						}
					}
					?>

					<?php
					// Prepare cart item data for edit button
					$variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
					$variation_attrs = isset($cart_item['variation']) ? $cart_item['variation'] : [];
					$product_note = isset($cart_item['product_note']) ? $cart_item['product_note'] : '';
					$ocwsu_quantity_in_units = $quantity_in_units;
					$ocwsu_quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;
					$quantity = floatval($cart_item['quantity']);

					// Encode variation attributes as JSON for data attribute
					$variation_attrs_json = !empty($variation_attrs) ? htmlspecialchars(json_encode($variation_attrs), ENT_QUOTES, 'UTF-8') : '';
					?>

					<div class="ed-float-cart__item" role="listitem"
						 data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
						<div class="ed-float-cart__remove-confirm" hidden aria-hidden="true">
							<p class="ed-float-cart__remove-confirm-msg"><?php echo esc_html__('האם להסיר?', 'deliz-short'); ?></p>
							<div class="ed-float-cart__remove-confirm-actions">
								<button type="button" class="ed-float-cart__remove-confirm-yes">
									<?php echo esc_html__('כן', 'deliz-short'); ?>
								</button>
								<button type="button" class="ed-float-cart__remove-confirm-no">
									<?php echo esc_html__('לא', 'deliz-short'); ?>
								</button>
							</div>
						</div>
                        <div class="cart_item_inner">
						<button type="button"
						   class="ed-float-cart__remove"
						   aria-label="<?php echo esc_attr(sprintf(__('הסר %s מהסל', 'deliz-short'), $name_full)); ?>"
						   data-product_id="<?php echo esc_attr($product_id); ?>"
						   data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>"
						   data-product_sku="<?php echo esc_attr($product->get_sku()); ?>">×</button>

						<div class="ed-float-cart__thumb">
							<?php
							//if ( $permalink ) {
							// echo '<a href="' . esc_url($permalink) . '">' . $thumbnail . '</a>';
							//} else {
							echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							//}
							?>
						</div>

						<div class="ed-float-cart__details">
							<div class="ed-float-cart__name"><?php echo esc_html($float_cart_product_title); ?></div>

							<?php
							// וריאציות/מטא (בלי חזרה על מאפייני וריאציה — מוצגים בשורה ייעודית מתחת ל-ocwsu)
							$cart_item_for_item_data = $cart_item;
							if (!empty($cart_item['variation_id']) && !empty($cart_item['variation']) && is_array($cart_item['variation'])) {
								$cart_item_for_item_data = $cart_item;
								$cart_item_for_item_data['variation'] = array();
							}
							$item_data = wc_get_formatted_cart_item_data($cart_item_for_item_data, true);

							if ($item_data) {
								echo '<div class="ed-float-cart__meta2">' . $item_data . '</div>'; // phpcs:ignore
							}

							// כמות ומשקל למוצרים שקילים (oc-woo-sale-units)
							if ($ocwsu_display) {
								echo '<div class="ed-float-cart__ocwsu-qty">~' . esc_html($ocwsu_display) . '</div>';
							}
							if ($float_cart_variation_html !== '') {
								echo '<div class="ed-float-cart__ocwsu-qty ed-float-cart__variation">' . wp_kses_post($float_cart_variation_html) . '</div>';
							}
							?>
						</div>
 							<div class="ed-float-cart__actions-row">
								<div class="ed-float-cart__quantity-controls">
									<button type="button"
											class="ed-float-cart__qty-btn ed-float-cart__qty-btn--decrease"
											data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
											aria-label="<?php esc_attr_e('הפחת כמות', 'deliz-short'); ?>">-
									</button>

									<div class="ed-float-cart__qty-field<?php echo $ocwsu_units_qty_ui ? ' ed-float-cart__qty-field--ocwsu-units' : ''; ?>">
										<input type="text"
											   class="ed-float-cart__qty-input"
											   value="<?php echo esc_attr($qty_input_val); ?>"
											   min="<?php echo esc_attr($qty_input_min); ?>"
											   step="<?php echo esc_attr($qty_input_step); ?>"
											   data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
											   <?php if ($ocwsu_units_qty_ui) : ?>
											   data-ed-ocwsu-units-display="1"
											   data-ed-ocwsu-kg-per-unit="<?php echo esc_attr(wc_format_decimal($ocwsu_kg_per_unit, 6)); ?>"
											   aria-label="<?php esc_attr_e('מספר יחידות', 'deliz-short'); ?>"
											   <?php elseif ($ocwsu_gram_weight_qty_ui) : ?>
											   data-ed-ocwsu-cart-qty-unit="<?php echo $ocwsu_weight_input_show_kg ? 'kg' : 'grams'; ?>"
											   data-ed-ocwsu-gram-min="<?php echo esc_attr($ocwsu_min_grams_str); ?>"
											   data-ed-ocwsu-gram-step="<?php echo esc_attr($weight_step === 'any' ? 'any' : $ocwsu_step_grams_str); ?>"
											   data-ed-ocwsu-kg-min="<?php echo esc_attr($ocwsu_min_kg_str); ?>"
											   data-ed-ocwsu-kg-step="<?php echo esc_attr($ocwsu_step_kg_str === 'any' ? 'any' : $ocwsu_step_kg_str); ?>"
											   aria-label="<?php echo esc_attr($ocwsu_weight_input_show_kg ? __('משקל בק"ג', 'deliz-short') : __('משקל בגרמים', 'deliz-short')); ?>"
											   <?php elseif ($ocwsu_float_cart_weight_attrs) : ?>
											   data-ed-ocwsu-cart-qty-unit="<?php echo $ocwsu_raw_float_show_kg ? 'kg' : 'grams'; ?>"
											   data-ed-ocwsu-gram-min="<?php echo esc_attr($ocwsu_min_grams_str); ?>"
											   data-ed-ocwsu-gram-step="<?php echo esc_attr($weight_step === 'any' ? 'any' : $ocwsu_step_grams_str); ?>"
											   data-ed-ocwsu-kg-min="<?php echo esc_attr($ocwsu_min_kg_str); ?>"
											   data-ed-ocwsu-kg-step="<?php echo esc_attr($ocwsu_step_kg_str === 'any' ? 'any' : $ocwsu_step_kg_str); ?>"
											   aria-label="<?php echo esc_attr($ocwsu_raw_float_show_kg ? __('משקל בק"ג', 'deliz-short') : __('משקל בגרמים', 'deliz-short')); ?>"
											   <?php else : ?>
											   aria-label="<?php esc_attr_e('כמות', 'deliz-short'); ?>"
											   <?php endif; ?>
										>

										<?php if ($ocwsu_units_qty_ui) : ?>
											<span class="ed-float-cart__qty-units-label"><?php esc_html_e("יח'", 'deliz-short'); ?></span>
										<?php elseif ($ocwsu_gram_weight_qty_ui) : ?>
											<span class="ed-float-cart__qty-units-label"><?php echo esc_html($ocwsu_weight_input_show_kg ? __('ק"ג', 'deliz-short') : __('גרם', 'deliz-short')); ?></span>
										<?php elseif ($ocwsu_float_cart_weight_attrs) : ?>
											<span class="ed-float-cart__qty-units-label"><?php echo esc_html($ocwsu_raw_float_show_kg ? __('ק"ג', 'deliz-short') : __('גרם', 'deliz-short')); ?></span>
										<?php elseif ($weighable && $sold_by_weight && $ocwsu_weight_qty_label !== '') : ?>
											<span class="ed-float-cart__qty-units-label"><?php echo esc_html($weight_unit); ?></span>
										<?php endif; ?>
									</div>

									<button type="button"
											class="ed-float-cart__qty-btn ed-float-cart__qty-btn--increase"
											data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
											aria-label="<?php esc_attr_e('הוסף כמות', 'deliz-short'); ?>">+
									</button>
								</div>

								<button type="button"
										class="ed-float-cart__edit-btn"
										data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
										data-product-id="<?php echo esc_attr($product_id); ?>"
										data-variation-id="<?php echo esc_attr($variation_id); ?>"
										data-quantity="<?php echo esc_attr($quantity); ?>"
										data-variation="<?php echo $variation_attrs_json; ?>"
										data-product-note="<?php echo esc_attr($product_note); ?>"
										data-ocwsu-quantity-in-units="<?php echo esc_attr($ocwsu_quantity_in_units); ?>"
										data-ocwsu-quantity-in-weight-units="<?php echo esc_attr($ocwsu_quantity_in_weight_units); ?>"
										aria-label="<?php esc_attr_e('ערוך מוצר', 'deliz-short'); ?>">
									<?php esc_html_e('עריכה', 'deliz-short'); ?>
								</button>
							</div>                       
						<div class="ed-float-cart__price">
							<span class="ed-float-cart__subtotal"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?></span>
						</div>
                    </div>
                         <?php
							// הודעות קידום מבצעים
							if (class_exists('ED_Promotions')) {
								$promotions = ED_Promotions::get_product_promotions($product_id);

								if (!empty($promotions)) {
									$promotion = $promotions[0];
									$type = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'type', true);
									$badge_text = get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'badge_text', true);

									if ($type === 'discount') {
										// למבצע הנחה - תמיד מציגים "משתתף במבצע"
										echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float">';
										echo '<span class="ed-promotion-label">' . sprintf(__('משתתף במבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
										echo '</div>';
									} elseif ($type === 'buy_x_pay_y') {
										// למבצע קונים X תמורת Y - בודקים אם מימש או לא
										$buy_kg = floatval(get_post_meta($promotion->ID, ED_Promotions::META_PREFIX . 'buy_kg', true));
										$quantity = floatval($cart_item['quantity']);

										// בדיקה אם המוצר שקיל
										$weighable = get_post_meta($product_id, '_ocwsu_weighable', true) === 'yes';

										if (!$weighable) {
											// למוצרים לא שקילים - ממירים לפי משקל יחידה
											$unit_weight = floatval(get_post_meta($product_id, '_ocwsu_unit_weight', true));

											if ($unit_weight > 0) {
												$quantity = $quantity / $unit_weight; // המרה לק"ג
											}
										}

										if ($quantity >= $buy_kg) {
											// מימש את המבצע
											echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float ed-promotion-fulfilled">';
											echo '<span class="ed-promotion-label">' . sprintf(__('קיבלת את המבצע: %s', 'deliz-short'), esc_html($badge_text)) . '</span>';
											echo '</div>';
										} else {
											// לא מימש - מציגים כמה חסר
											$remaining = $buy_kg - $quantity;

											// פורמט יפה של המספר
											if ($remaining < 1) {
												$remaining_display = sprintf(__('%s גרם', 'deliz-short'), wc_format_decimal($remaining * 1000, 0));
											} else {
												$remaining_display = sprintf(__('%s ק"ג', 'deliz-short'), wc_format_decimal($remaining, 2));
											}

											echo '<div class="ed-promotion-cart-message ed-promotion-cart-message--float ed-promotion-pending">';
											echo '<span class="ed-promotion-label">' . sprintf(__('חסר לך רק עוד %s כדי לקבל את המבצע: %s', 'deliz-short'), $remaining_display, esc_html($badge_text)) . '</span>';
											echo '</div>';
										}
									}
								}
							}
                        ?>                   
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<footer class="ed-float-cart__footer">
			<?php if ($cart && !$cart->is_empty()) : ?>
				<?php
				// Coupon field (same UI as checkout "copy" form)
				if (function_exists('oc_woo_coupon_form_copy_for_checkout') && wc_coupons_enabled()) :
					?>
					<div class="ed-float-cart__coupon" aria-label="<?php esc_attr_e('קופון', 'deliz-short'); ?>">
						<label><?php echo esc_html__('הוספת קוד קופון', 'deliz-short'); ?></label>
						<?php oc_woo_coupon_form_copy_for_checkout(); ?>
						<div class="ed-float-cart__coupon-notices" aria-live="polite"></div>
					</div>
				<?php endif; ?>

				<div class="ed-float-cart__totals">
					<?php $cart->calculate_totals(); ?>

					<div class="ed-float-cart__row">
						<span><?php echo esc_html__('סה"כ ביניים', 'deliz-short'); ?></span>
						<strong><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></strong>
					</div>

					<?php
					$subtotal_base         = (float) $cart->get_subtotal();
					$coupon_discount_total = (float) $cart->get_discount_total();
					$running_total         = $subtotal_base - $coupon_discount_total;
					$fees                  = $cart->get_fees();

					foreach ($cart->get_coupons() as $code => $coupon) :
						?>
						<div class="ed-float-cart__row ed-float-cart__row--coupon cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
							<span><?php wc_cart_totals_coupon_label($coupon); ?></span>
							<strong><?php wc_cart_totals_coupon_html($coupon); ?></strong>
						</div>
						<?php
					endforeach;

					if ($cart->needs_shipping() && $cart->show_shipping()) :
						$running_total += (float) $cart->get_shipping_total();
						?>
						<div class="ed-float-cart__row ed-float-cart__row--shipping cart-shipping">
							<span><?php echo esc_html(deliz_short_get_float_cart_shipping_label()); ?></span>
							<strong><?php echo wp_kses_post($cart->get_cart_shipping_total()); ?></strong>
						</div>
						<?php
					endif;

					if (!empty($fees)) :
						foreach ($fees as $fee) :
							$fee_amount = (float) $fee->amount;
							$running_total += $fee_amount;
							$fee_row_class = 'ed-float-cart__row ed-float-cart__row--fee';
							if ($fee_amount < 0) {
								$fee_row_class .= ' ed-float-cart__row--promotion';
							}
							?>
							<div class="<?php echo esc_attr($fee_row_class); ?>">
								<span><?php echo esc_html($fee->name); ?></span>
								<strong><?php echo wp_kses_post(wc_cart_totals_fee_html($fee)); ?></strong>
							</div>
							<?php
						endforeach;
					endif;
					?>
				</div>

				<div class="ed-float-cart__footer-shipping-chip">
					<?php do_action('deliz_short_float_cart_footer_shipping'); ?>
				</div>

				<div class="ed-float-cart__actions">
					<a class="ed-float-cart__btn ed-float-cart__btn--checkout checkout-btn-trigger"
					   href="<?php echo esc_url(wc_get_checkout_url()); ?>"
					   data-checkout-url="<?php echo esc_url(wc_get_checkout_url()); ?>">
						<span><?php echo esc_html__('מעבר לתשלום', 'deliz-short'); ?></span>
						<?php //if ( abs( $running_total - $subtotal_base ) > 0.0001 ): ?>
							<span class="ed-float-cart__total-with-discounts">~ <?php echo wp_kses_post(wc_price($running_total)); ?></span>
						<?php //endif; ?>
					</a>
				</div>

				<div class="bottom-cart">
					<div class="cart-custom-notice">
						<?php _e('*The final price will be set after weighing.', 'deliz-short'); ?>
						<a href="javascript:void(0);"><?php _e('More Details', 'deliz-short'); ?></a>
					</div>
				</div>
			<?php else : ?>
				<div class="ed-float-cart__footer-shipping-chip ed-float-cart__footer-shipping-chip--empty-cart">
					<?php do_action('deliz_short_float_cart_footer_shipping'); ?>
				</div>
			<?php endif; ?>
		</footer>
	</div>
</aside>