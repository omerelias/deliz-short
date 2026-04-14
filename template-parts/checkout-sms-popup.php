<?php
/**
 * Checkout SMS Authentication Popup
 * 
 * This popup appears when a non-logged-in user tries to checkout
 */
if (!defined('ABSPATH')) exit;

$sms_auth = class_exists('OC_SMS_Auth') ? OC_SMS_Auth::get_instance() : null;
$settings = $sms_auth ? $sms_auth->get_settings() : array();
?>

<div class="checkout-sms-popup" id="checkout-sms-popup" style="display: none;">
    <div class="checkout-sms-popup__overlay"></div>
    <div class="checkout-sms-popup__container">
        <button class="checkout-sms-popup__close default-close-btn btn-empty" type="button" aria-label="<?php esc_attr_e('סגור', 'deliz-short'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" viewBox="0 0 16 14">
                <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
            </svg>
        </button>
        
        <div class="checkout-sms-popup__content">
            <h2 class="checkout-sms-popup__title checkout-sms-popup__title--main"><?php echo esc_html__('הכנס מס טלפון לביצוע הזמנה', 'deliz-short'); ?></h2>
            
            <!-- Step 1: Phone Input -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--phone active">
                <form class="checkout-sms-phone-form" method="post">
                    <div class="form-row-sms">
                        <input type="tel" name="phone" class="phone-input" required
                               placeholder="<?php echo esc_attr__('מספר טלפון', 'deliz-short'); ?>" />
                    </div>
                    <button type="submit" class="button submit-sms"><?php echo esc_html__('שלח קוד', 'deliz-short'); ?></button>
                    <div class="checkout-sms-popup__error"></div>
                </form>
            </div>
            
            <!-- Step 2: Code Verification -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--code">
                <div class="checkout-sms-popup__subtitle"><?php echo esc_html__('יש להזין את הקוד שהתקבל ב SMS', 'deliz-short'); ?></div>
                <div class="confirm-sms-code">
                    <div class="verification-code-input">
                        <input type="text" name="verification_code" maxlength="6" class="code-input" placeholder="<?php esc_attr_e('קוד אימות', 'deliz-short'); ?>" />
                    </div>
                    <div class="confirm-sms-code__actions">
                        <button type="submit" class="button verify-button">
                            <?php echo esc_html__('אימות', 'deliz-short'); ?>
                        </button>
                        <button type="button" class="button resend-code" data-resend-count="0">
                            <?php echo esc_html__('שלח קוד חדש', 'deliz-short'); ?>
                        </button>
                    </div>
                </div>
                <div class="checkout-sms-popup__error"></div>
            </div>
            
            <!-- Step 3: Registration Form (if user not found) -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--register">
                <div class="checkout-sms-popup__subtitle"><?php echo esc_html__('נזכור את הפרטים להזמנה הבאה :)', 'deliz-short'); ?></div>
                <form class="checkout-sms-register-form" method="post">
                    <div class="form-row">
                        <input type="text" name="first_name" class="input-text" required
                               placeholder="<?php echo esc_attr__('שם פרטי', 'deliz-short'); ?>" />
                    </div>
                    <div class="form-row">
                        <input type="text" name="last_name" class="input-text" required
                               placeholder="<?php echo esc_attr__('שם משפחה', 'deliz-short'); ?>" />
                    </div>
                    <div class="form-row">
                        <input type="email" name="email" class="input-text" required
                               placeholder="<?php echo esc_attr__('אימייל', 'deliz-short'); ?>" />
                    </div>
                    <input type="hidden" name="phone" class="register-phone-input" />
                    <button type="submit" class="button register-button">
                        <?php echo esc_html__('אישור והמשך', 'deliz-short'); ?>
                    </button>
                    <div class="checkout-sms-popup__error"></div>
                </form>
            </div>

            <!-- New customer: tabbed wizard (OCWS shipping_kind) -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--newuser-wizard">
                <div class="checkout-sms-wizard-tabs" role="tablist"> 
                    <button type="button" class="checkout-sms-wizard-tab is-active" data-tab="details" role="tab"><?php echo esc_html__( 'פרטים', 'deliz-short' ); ?></button>
                    <button type="button" class="checkout-sms-wizard-tab" data-tab="supply" role="tab"><?php echo esc_html__( 'אספקה', 'deliz-short' ); ?></button>
                </div>
                <?php
                /* #choose-shipping is a separate <form> — keep this wrap outside .checkout-sms-newuser-wizard-form (no nested forms). Shown only on the "אספקה" tab via JS. */
                ?>
                <div id="checkout-sms-ocws-embed-wrap" class="checkout-sms-ocws-embed-wrap" hidden aria-hidden="true">
                    <p class="checkout-sms-ocws-embed__title"><?php echo esc_html__( 'אופן קבלה ומשלוח', 'deliz-short' ); ?></p>
                    <div id="checkout-sms-ocws-embed-mount" class="checkout-sms-ocws-embed-mount" aria-live="polite"></div>
                </div>
                <form class="checkout-sms-newuser-wizard-form" method="post">
                    <div class="checkout-sms-wizard-panel is-active" data-panel="details">
                        <div class="form-row">
                            <input type="text" name="first_name" class="input-text nu-first-name" required
                                   placeholder="<?php echo esc_attr__( 'שם פרטי', 'deliz-short' ); ?>" />
                        </div>
                        <div class="form-row">
                            <input type="text" name="last_name" class="input-text nu-last-name" required
                                   placeholder="<?php echo esc_attr__( 'שם משפחה', 'deliz-short' ); ?>" />
                        </div>
                        <div class="form-row">
                            <input type="email" name="email" class="input-text nu-email" required
                                   placeholder="<?php echo esc_attr__( 'אימייל', 'deliz-short' ); ?>" />
                        </div>
                    </div>
                    <div class="checkout-sms-wizard-panel" data-panel="supply">
                        <?php /* anchor for JS: restore .checkout-sms-supply-floor-fields after embed unmount */ ?>
                        <div id="checkout-sms-supply-floor-placeholder" class="checkout-sms-supply-floor-placeholder" aria-hidden="true"></div>
                        <div class="checkout-sms-supply-floor-fields">
                            <div class="form-row">
                                <input type="text" name="billing_floor" class="input-text"
                                       placeholder="<?php echo esc_attr__( 'קומה', 'deliz-short' ); ?>" />
                            </div>
                            <div class="form-row">
                                <input type="text" name="billing_apartment" class="input-text"
                                       placeholder="<?php echo esc_attr__( 'דירה', 'deliz-short' ); ?>" />
                            </div>
                            <div class="form-row">
                                <input type="text" name="billing_enter_code" class="input-text"
                                       placeholder="<?php echo esc_attr__( 'קוד כניסה', 'deliz-short' ); ?>" />
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="phone" class="nu-register-phone-input" />
                    <button type="submit" class="button nu-wizard-submit">
                        <?php echo esc_html__( 'המשך', 'deliz-short' ); ?>
                    </button>
                    <div class="checkout-sms-popup__error"></div>
                </form>
            </div>

            <!-- After wizard when shipping was not chosen: open OCWS choose-shipping -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--how-receive">
                <p class="checkout-sms-how-receive-hint"><?php echo esc_html__( 'נפתח חלון בחירת משלוח או איסוף — כמו בצ\'קאאוט.', 'deliz-short' ); ?></p>
                <button type="button" class="button checkout-sms-open-ocws-btn">
                    <?php echo esc_html__( 'בחרו אופן קבלת ההזמנה', 'deliz-short' ); ?>
                </button>
                <div class="checkout-sms-popup__error"></div>
            </div>

            <!-- Step 4: Extra address details — only for home delivery (see checkout-sms-flow.js + deliz_short_checkout_sms_delivery_extra_for_localize) -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--shipping">
                <div class="checkout-sms-popup__shipping-intro" aria-live="polite"></div>
                <form class="checkout-sms-shipping-form" method="post">
                    <div class="form-row">
                        <input type="text" name="billing_floor" class="input-text"
                               placeholder="<?php echo esc_attr__('קומה', 'deliz-short'); ?>" />
                    </div>
                    <div class="form-row">
                        <input type="text" name="billing_apartment" class="input-text"
                               placeholder="<?php echo esc_attr__('דירה', 'deliz-short'); ?>" />
                    </div>
                    <div class="form-row">
                        <input type="text" name="billing_enter_code" class="input-text"
                               placeholder="<?php echo esc_attr__('קוד כניסה', 'deliz-short'); ?>" />
                    </div>
                    <button type="submit" class="button shipping-button">
                        <?php echo esc_html__('שמירת פרטי המשלוח והמשך', 'deliz-short'); ?>
                    </button>
                    <div class="checkout-sms-popup__error"></div>
                </form>
            </div>
        </div>

        <?php /* מוצג ב-JS בזמן מעבר לצ'קאאוט — מסתיר את כל .checkout-sms-popup__content (כולל OCWS embed) */ ?>
        <div class="checkout-sms-popup__checkout-loader" id="checkout-sms-checkout-loader" hidden aria-hidden="true">
            <span class="checkout-sms-popup__checkout-loader-spinner" aria-hidden="true"></span>
            <p class="checkout-sms-popup__checkout-loader-text"><?php echo esc_html__( 'מעבירים לתשלום…', 'deliz-short' ); ?></p>
        </div>
    </div>
</div>

