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
            <h2 class="checkout-sms-popup__title"><?php echo esc_html__('הכנס מס טלפון לביצוע הזמנה', 'deliz-short'); ?></h2>
            
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
                    <button type="submit" class="button verify-button">
                        <?php echo esc_html__('אימות', 'deliz-short'); ?>
                    </button>
                    <button type="button" class="button resend-code" data-resend-count="0">
                        <?php echo esc_html__('שלח קוד חדש', 'deliz-short'); ?>
                    </button>
                </div>
                <div class="checkout-sms-popup__error"></div>
            </div>
            
            <!-- Step 3: Registration Form (if user not found) -->
            <div class="checkout-sms-popup__step checkout-sms-popup__step--register">
                <div class="checkout-sms-popup__subtitle"><?php echo esc_html__('השלם את הפרטים להשלמת ההזמנה', 'deliz-short'); ?></div>
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
        </div>
    </div>
</div>

