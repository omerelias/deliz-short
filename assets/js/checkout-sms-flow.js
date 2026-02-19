jQuery(function($) {
    'use strict';

    const CheckoutSMSFlow = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Intercept checkout button click
            $(document).on('click', '.checkout-btn-trigger', this.handleCheckoutClick);
            
            // SMS form submission
            $(document).on('submit', '.checkout-sms-phone-form', this.handleSendCode);
            
            // Code verification
            $(document).on('click', '.checkout-sms-popup .verify-button', this.handleVerifyCode);
            
            // Resend code
            $(document).on('click', '.checkout-sms-popup .resend-code', this.handleResendCode);
            
            // Registration form
            $(document).on('submit', '.checkout-sms-register-form', this.handleRegister);
            
            // Close popup
            $(document).on('click', '.checkout-sms-popup__close, .checkout-sms-popup__overlay', this.closePopup);
        },

        handleCheckoutClick: function(e) {
            // Check if user is logged in
            if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.is_logged_in == 0) {
                e.preventDefault();
                CheckoutSMSFlow.showPopup();
            }
            // If logged in, allow normal navigation
        },

        showPopup: function() {
            const $popup = $('#checkout-sms-popup');
            $popup.fadeIn(300);
            $('body').addClass('checkout-sms-popup-open');
            $popup.find('.checkout-sms-popup__step--phone').addClass('active');
            $popup.find('.phone-input').focus();
        },

        closePopup: function() {
            const $popup = $('#checkout-sms-popup');
            $popup.fadeOut(300);
            $('body').removeClass('checkout-sms-popup-open');
            CheckoutSMSFlow.resetSteps();
        },

        resetSteps: function() {
            const $popup = $('#checkout-sms-popup');
            $popup.find('.checkout-sms-popup__step').removeClass('active');
            $popup.find('.checkout-sms-popup__step--phone').addClass('active');
            $popup.find('form')[0]?.reset();
            $popup.find('.checkout-sms-popup__error').empty();
        },

        showStep: function(step) {
            const $popup = $('#checkout-sms-popup');
            $popup.find('.checkout-sms-popup__step').removeClass('active');
            $popup.find('.checkout-sms-popup__step--' + step).addClass('active');
            $popup.find('.checkout-sms-popup__error').empty();
        },

        showError: function(step, message) {
            const $popup = $('#checkout-sms-popup');
            const $step = $popup.find('.checkout-sms-popup__step--' + step);
            const $error = $step.find('.checkout-sms-popup__error');
            $error.html('<div class="woocommerce-error">' + message + '</div>');
        },

        handleSendCode: function(e) {
            e.preventDefault();
            const $form = $(this);
            const phone = $form.find('.phone-input').val();
            const $button = $form.find('button[type="submit"]');

            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('phone', '');

            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_send_auth_sms',
                    phone: phone,
                    nonce: oc_sms_auth.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data && response.data.user_not_found) {
                            // User not found - show registration form directly
                            CheckoutSMSFlow.showStep('register');
                            $('.register-phone-input').val(response.data.phone || phone);
                        } else {
                            // User found - show code verification
                            CheckoutSMSFlow.showStep('code');
                            CheckoutSMSFlow.startCodeTimer();
                        }
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        if (response.data && response.data.show_register) {
                            // User not found - show registration form
                            CheckoutSMSFlow.showStep('register');
                            $('.register-phone-input').val(response.data.phone || phone);
                        } else {
                            CheckoutSMSFlow.showError('phone', response.data.message || response.data || oc_sms_auth.i18n.error_sending);
                        }
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('phone', oc_sms_auth.i18n.error_sending);
                }
            });
        },

        handleVerifyCode: function(e) {
            e.preventDefault();
            const $button = $(this);
            const code = $('.checkout-sms-popup .code-input').val();
            const phone = $('.checkout-sms-popup .phone-input').val();

            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('code', '');

            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_verify_sms_code',
                    code: code,
                    phone: phone,
                    nonce: oc_sms_auth.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('disabled');
                    if (response.success) {
                        // User logged in successfully - redirect to checkout
                        const checkoutUrl = $('.checkout-btn-trigger').data('checkout-url') || (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.checkout_url : '/checkout/');
                        window.location.href = checkoutUrl;
                    } else {
                        // Check if we need to show registration
                        if (response.data && (response.data.show_register || response.data.message && response.data.message.includes('not found'))) {
                            // User not found - show registration
                            CheckoutSMSFlow.showStep('register');
                            $('.register-phone-input').val(phone);
                        } else {
                            CheckoutSMSFlow.showError('code', response.data.message || response.data || oc_sms_auth.i18n.error_verifying);
                        }
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('code', oc_sms_auth.i18n.error_verifying);
                }
            });
        },

        handleResendCode: function(e) {
            e.preventDefault();
            const $button = $(this);
            
            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('code', '');

            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_resend_auth_code',
                    nonce: oc_sms_auth.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('disabled');
                    if (response.success) {
                        CheckoutSMSFlow.startCodeTimer();
                        CheckoutSMSFlow.showError('code', '<div class="woocommerce-message">' + oc_sms_auth.i18n.code_resent + '</div>');
                    } else {
                        CheckoutSMSFlow.showError('code', response.data.message || response.data || oc_sms_auth.i18n.error_resending);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('code', oc_sms_auth.i18n.error_resending);
                }
            });
        },

        handleRegister: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $button = $form.find('.register-button');
            const formData = {
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val()
            };

            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('register', '');

            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_register_user',
                    nonce: oc_sms_auth.nonce,
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        // User registered and logged in - redirect to checkout
                        window.location.href = $('.checkout-btn-trigger').data('checkout-url') || wc_checkout_params.checkout_url;
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        CheckoutSMSFlow.showError('register', response.data.message || response.data || 'שגיאה ברישום');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('register', 'שגיאה ברישום');
                }
            });
        },

        startCodeTimer: function() {
            if (this.codeTimer) {
                clearInterval(this.codeTimer);
            }
            
            let timeLeft = oc_sms_auth.code_expiry || 180;
            const $popup = $('#checkout-sms-popup');
            
            if (!$popup.find('.verification-code-timer').length) {
                $popup.find('.verification-code-input').after('<div class="verification-code-timer"></div>');
            }
            
            this.codeTimer = setInterval(() => {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                $popup.find('.verification-code-timer').text(
                    minutes + ':' + (seconds < 10 ? '0' : '') + seconds
                );
                
                if (timeLeft <= 0) {
                    clearInterval(this.codeTimer);
                    CheckoutSMSFlow.showStep('phone');
                }
            }, 1000);
        }
    };

    // Initialize
    CheckoutSMSFlow.init();
    
    // Expose to global scope if needed
    window.CheckoutSMSFlow = CheckoutSMSFlow;
});

