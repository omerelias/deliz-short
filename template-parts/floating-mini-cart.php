<?php

// Exit if accessed directly

if ( ! defined('ABSPATH') ) exit;



if ( ! function_exists('WC') ) return;



$cart = WC()->cart;

?>

<aside class="ed-float-cart" id="ed-float-cart" aria-label="<?php esc_attr_e('Mini cart', 'deliz-short'); ?>">

  <div class="ed-float-cart__inner">

    <header class="ed-float-cart__header">

      <h3 class="ed-float-cart__title"><?php echo esc_html__('סל קניות', 'deliz-short'); ?></h3>



      <!--<div class="ed-float-cart__meta">

        <?php

          $count = $cart ? $cart->get_cart_contents_count() : 0;

          printf(

            '<span class="ed-float-cart__count">%s</span>',

            esc_html( sprintf(_n('%d מוצר', '%d מוצרים', $count, 'deliz-short'), $count) )

          );

        ?>

      </div>-->

      <button class="cart-close default-close-btn btn-empty" type="button" aria-label="<?php _e( 'סגירה של סל הקניות', 'deliz-short' ) ?>">

          <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" role="presentation" viewBox="0 0 16 14">

            <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>

          </svg>

      </button>

      <?php

      if ( class_exists('Oc_Woo_Shipping_Public') && is_callable(['Oc_Woo_Shipping_Public', 'show_chip_in_cart']) ) {

          Oc_Woo_Shipping_Public::show_chip_in_cart();

      }

      ?>        

    </header>



    <div class="ed-float-cart__items" role="list">

      <?php if ( ! $cart || $cart->is_empty() ) : ?>

        <div class="ed-float-cart__empty">

            <svg width="80" height="80" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">

                <path fill-rule="evenodd" clip-rule="evenodd" d="M23.0346 22.2883L21.8894 9.39264C21.8649 9.10634 21.6236 8.88957 21.3414 8.88957H18.9855C18.9528 6.73824 17.1941 5 15.0346 5C12.8751 5 11.1164 6.73824 11.0837 8.88957H8.72786C8.44156 8.88957 8.20434 9.10634 8.1798 9.39264L7.03461 22.2883C7.03461 22.2965 7.03359 22.3047 7.03256 22.3129L7.03256 22.3129L7.03256 22.3129C7.03154 22.3211 7.03052 22.3292 7.03052 22.3374C7.03052 23.8057 8.37612 25 10.0326 25H20.0367C21.6931 25 23.0387 23.8057 23.0387 22.3374C23.0387 22.3211 23.0387 22.3047 23.0346 22.2883ZM15.0346 6.10425C16.5847 6.10425 17.8485 7.3476 17.8812 8.88952H12.188C12.2207 7.3476 13.4845 6.10425 15.0346 6.10425ZM10.0326 23.8957H20.0367C21.0714 23.8957 21.9181 23.2086 21.9344 22.3619L20.8342 9.99792H18.9855V11.6748C18.9855 11.9816 18.7401 12.227 18.4334 12.227C18.1266 12.227 17.8812 11.9816 17.8812 11.6748V9.99792H12.1839V11.6748C12.1839 11.9816 11.9385 12.227 11.6318 12.227C11.325 12.227 11.0796 11.9816 11.0796 11.6748V9.99792H9.23094L8.13483 22.3619C8.15119 23.2086 8.99372 23.8957 10.0326 23.8957Z" fill="#0F0F0F"></path>

            </svg>

            <?php echo esc_html__('הסל ריק, אבל לא להרבה זמן :)', 'deliz-short'); ?>

        </div>

      <?php else : ?>



        <?php foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :

          $product = $cart_item['data'];

          if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) continue;



          $product_id   = $cart_item['product_id'];

          $name         = $product->get_name();

          $qty          = (int) $cart_item['quantity'];

          $permalink    = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';

          $thumbnail    = $product->get_image('woocommerce_thumbnail');

          $remove_url   = wc_get_cart_remove_url( $cart_item_key );

          $line_price   = WC()->cart->get_product_price( $product ); // מחיר ליחידה (עם מטבע)

          $subtotal     = WC()->cart->get_product_subtotal( $product, $qty ); // סה"כ שורה



          // oc-woo-sale-units: build display string for weighable products

          $ocwsu_display = '';

          $weighable = ( get_post_meta( $product_id, '_ocwsu_weighable', true ) === 'yes' ); 



          if ( $weighable ) {

            $quantity_in_units        = isset( $cart_item['ocwsu_quantity_in_units'] ) ? floatval( $cart_item['ocwsu_quantity_in_units'] ) : 0;

            $quantity_in_weight_units = isset( $cart_item['ocwsu_quantity_in_weight_units'] ) ? floatval( $cart_item['ocwsu_quantity_in_weight_units'] ) : 0;



            // Base weight from cart (always in kg, possibly fractional)

            $weight_qty   = floatval( $cart_item['quantity'] );

            $weight_value = $weight_qty;

            $weight_unit  = 'ק\"ג';



            if ( $weight_qty > 0 && $weight_qty < 1 ) {

              $weight_value = $weight_qty * 1000; // grams

              $weight_unit  = 'גרם';

            }



            // Format numbers nicely

            if ( $weight_unit === 'גרם' ) {

              $weight_value = wc_format_decimal( $weight_value, 0 );

            } else {

              $weight_value = wc_format_decimal( $weight_value, 2 );

            }



            if ( $quantity_in_units > 0 ) {

              // Example: "2 יחידות, 500 גרם"

              $units_label = ( $quantity_in_units == 1 ) ? 'יחידה' : 'יחידות';

              $ocwsu_display = sprintf(

                '%s %s, %s %s',

                wc_format_decimal( $quantity_in_units, 0 ),

                $units_label,

                $weight_value,

                $weight_unit

              );

            } else {

              // Only weight, e.g. "500 גרם" / "1.20 ק\"ג"

              $ocwsu_display = sprintf( '%s %s', $weight_value, $weight_unit );

            }

          }

        ?>

          <?php
          // Prepare cart item data for edit button
          $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
          $variation_attrs = isset($cart_item['variation']) ? $cart_item['variation'] : [];
          $product_note = isset($cart_item['product_note']) ? $cart_item['product_note'] : '';
          $ocwsu_quantity_in_units = isset($cart_item['ocwsu_quantity_in_units']) ? floatval($cart_item['ocwsu_quantity_in_units']) : 0;
          $ocwsu_quantity_in_weight_units = isset($cart_item['ocwsu_quantity_in_weight_units']) ? floatval($cart_item['ocwsu_quantity_in_weight_units']) : 0;
          $quantity = floatval($cart_item['quantity']);
          
          // Encode variation attributes as JSON for data attribute
          $variation_attrs_json = !empty($variation_attrs) ? htmlspecialchars(json_encode($variation_attrs), ENT_QUOTES, 'UTF-8') : '';
          ?>
          
          <div class="ed-float-cart__item" role="listitem" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">

            <a

              href="<?php echo esc_url( $remove_url ); ?>" 

              class="ed-float-cart__remove remove remove_from_cart_button"

              aria-label="<?php echo esc_attr( sprintf(__('הסר %s מהסל', 'deliz-short'), $name) ); ?>"

              data-product_id="<?php echo esc_attr( $product_id ); ?>"

              data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>"

              data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"

            >×</a>



            <div class="ed-float-cart__thumb">

              <?php

              //if ( $permalink ) {

               //echo '<a href="' . esc_url($permalink) . '">' . $thumbnail . '</a>';

              //} else {

                echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

              //}

              ?>

            </div>



            <div class="ed-float-cart__details">

              <div class="ed-float-cart__name">

                <?php //if ( $permalink ) : ?>

                  <!--<a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($name); ?></a>-->

                <?php //else : ?>

                  <?php echo esc_html($name); ?>

                <?php //endif; ?>

              </div>



              <?php

                // וריאציות/מטא

                $item_data = wc_get_formatted_cart_item_data($cart_item, true);

                if ( $item_data ) {

                  echo '<div class="ed-float-cart__meta2">' . $item_data . '</div>'; // phpcs:ignore

                }



                // כמות ומשקל למוצרים שקילים (oc-woo-sale-units)

                if ( $ocwsu_display ) {

                  echo '<div class="ed-float-cart__ocwsu-qty">' . esc_html( $ocwsu_display ) . '</div>';

                }

              ?>



              <div class="ed-float-cart__actions-row">

                <div class="ed-float-cart__quantity-controls">

                  <button type="button" class="ed-float-cart__qty-btn ed-float-cart__qty-btn--decrease" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e('הפחת כמות', 'deliz-short'); ?>">-</button>

                  <input type="number" 

                         class="ed-float-cart__qty-input" 

                         value="<?php echo esc_attr( $qty ); ?>" 

                         min="1" 

                         step="1"

                         data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"

                         aria-label="<?php esc_attr_e('כמות', 'deliz-short'); ?>">

                  <button type="button" class="ed-float-cart__qty-btn ed-float-cart__qty-btn--increase" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e('הוסף כמות', 'deliz-short'); ?>">+</button>

                </div>



                <button type="button" 

                        class="ed-float-cart__edit-btn" 

                        data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"

                        data-product-id="<?php echo esc_attr( $product_id ); ?>"

                        data-variation-id="<?php echo esc_attr( $variation_id ); ?>"

                        data-quantity="<?php echo esc_attr( $quantity ); ?>"

                        data-variation="<?php echo $variation_attrs_json; ?>"

                        data-product-note="<?php echo esc_attr( $product_note ); ?>"

                        data-ocwsu-quantity-in-units="<?php echo esc_attr( $ocwsu_quantity_in_units ); ?>"

                        data-ocwsu-quantity-in-weight-units="<?php echo esc_attr( $ocwsu_quantity_in_weight_units ); ?>"

                        aria-label="<?php esc_attr_e('ערוך מוצר', 'deliz-short'); ?>">

                  <?php esc_html_e('עריכה', 'deliz-short'); ?>

                </button>

              </div>



              <div class="ed-float-cart__price">

                <span class="ed-float-cart__unit"><?php echo wp_kses_post( $line_price ); ?></span>

                <span class="ed-float-cart__sep">×</span>

                <span class="ed-float-cart__qty"><?php echo esc_html( $qty ); ?></span>

                <span class="ed-float-cart__subtotal"><?php echo wp_kses_post( $subtotal ); ?></span>

              </div>

            </div>

          </div>

        <?php endforeach; ?>



      <?php endif; ?>

    </div>



    <footer class="ed-float-cart__footer">



      <?php if ( $cart && ! $cart->is_empty() ) : ?>

        <div class="ed-float-cart__totals">

          <div class="ed-float-cart__row">

            <span><?php echo esc_html__('סה"כ ביניים', 'deliz-short'); ?></span>

            <strong><?php echo wp_kses_post( wc_price( $cart->get_subtotal() ) ); ?></strong>

          </div>



          <?php

          // וידוא שהעגלה מחושבת לפני קבלת fees 
          // זה חשוב כי fees מחושבים רק ב-calculate_totals()
          $cart->calculate_totals();
          
          // הצגת שורות מבצעים (fees) וחישוב סה"כ אחרי מבצעים 
          $fees = $cart->get_fees();
          $subtotal_after_promotions = $cart->get_subtotal();
          
          // DEBUG: var_dump לבדיקה
            echo '<!-- DEBUG: Fees count: ' . count($fees) . ' -->';
            echo '<!-- DEBUG: Fees: ';
            var_dump($fees);
            echo ' -->';

          if ( !empty($fees) ) :
            foreach ( $fees as $fee ) :
              // רק fees שליליים (הנחות)
              if ( $fee->amount < 0 ) :
                $subtotal_after_promotions += $fee->amount; // fees שליליים מוסיפים (כי הם הנחות)
          ?>

            <div class="ed-float-cart__row ed-float-cart__row--promotion">

              <span><?php echo esc_html( $fee->name ); ?></span>

              <strong><?php echo wp_kses_post( wc_cart_totals_fee_html( $fee ) ); ?></strong>

            </div>

          <?php
              endif;
            endforeach;
            
            // הצגת סה"כ אחרי הנחה (רק אם יש הנחות)
            if ( $subtotal_after_promotions != $cart->get_subtotal() ) :
          ?>

            <div class="ed-float-cart__row ed-float-cart__row--total-after-discount">

              <span><?php echo esc_html__('סה"כ אחרי הנחה', 'deliz-short'); ?></span>

              <strong><?php echo wp_kses_post( wc_price( $subtotal_after_promotions ) ); ?></strong>

            </div>

          <?php
            endif;
          endif;
          ?>



          <?php

          // טקסט משלוח חינם/עלות משלוח (פשוט, בלי חישובים כבדים)

          $free_min = 0;

          $settings = get_option('woocommerce_free_shipping_1_settings');

          if ( is_array($settings) && isset($settings['min_amount']) ) {

            $free_min = (float) $settings['min_amount'];

          }

          if ( $free_min > 0 ) :

            $remaining = max(0, $free_min - (float) $subtotal_after_promotions);

          ?>

            <div class="ed-float-cart__shippinghint">

              <?php if ( $remaining > 0 ) : ?>

                <?php echo esc_html( 'חסר לך רק ' . wc_price($remaining) . ' למשלוח חינם!' ); ?>

              <?php else : ?>

                <?php echo esc_html__('מגיע לך משלוח חינם!', 'deliz-short'); ?>

              <?php endif; ?>

            </div>

          <?php endif; ?>

        </div>



        <div class="ed-float-cart__actions">

          <a class="ed-float-cart__btn ed-float-cart__btn--checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">

            <?php echo esc_html__('לתשלום', 'deliz-short'); ?>

          </a>

        </div>


      <div class="bottom-cart">
          <div class="cart-custom-notice"><?php _e('*The final price will be set after weighing.', 'deliz-short'); ?> <a href="javascript:void(0);"><?php _e('More Details', 'deliz-short'); ?></a></div>
      </div>           

      <?php endif; ?>   

    </footer>

  </div>

</aside>

