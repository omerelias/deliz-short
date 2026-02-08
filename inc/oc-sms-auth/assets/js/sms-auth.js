jQuery(function($) {

    const smsAuth = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.sms-auth-form').on('submit', this.handleSendCode);
            $('.verify-button').on('click', this.handleVerifyCode);
            $(document).on('click', '.resend-code', this.handleResendCode);
        },

        showError: function(container, message) {
            const $container = $(container);
            if (!$container.find('.woocommerce-error').length) {
                $container.prepend(
                    '<div class="woocommerce-error">' + message + '</div>'
                );
            } else {
                $container.find('.woocommerce-error').html(message);
            }
            // Scroll to error message
            $('html, body').animate({
                scrollTop: $container.find('.woocommerce-error').offset().top - 100
            }, 500);
        },

        handleSendCode: function(e) {
            e.preventDefault();
            const $form = $(this);
            const phone = $form.find('.phone-input').val();
            const $button = $form.find('button[type="submit"]');

            $button.prop('disabled', true).addClass('disabled');
            $('.woocommerce-error').remove(); // Clear previous errors

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
                        $('.sms-auth-form').hide();
                        $('.sms-verification-form').show();
                        smsAuth.startCodeTimer();
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        if (response.data.show_register) {
                            // Switch to registration form
                            $('#customer_login').addClass('register-show');
                            // Pre-fill the phone number
                            $('#billing_phone').val(response.data.phone).parent().addClass('label-off');
                            // Show message
                            smsAuth.showError('.u-column2.col-2', response.data.message);
                            // Hide SMS auth forms
                            $('.sms-auth-form, .sms-verification-form').hide();
                        } else {
                            // Show other error messages
                            smsAuth.showError('.sms-auth-form', response.data.message || response.data);
                        }
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    smsAuth.showError('.sms-auth-form', oc_sms_auth.i18n.error_sending);
                }
            });
        },

        handleVerifyCode: function(e) {
            e.preventDefault();
            const $button = $(this);
            let code = '';
            $('.code-input').each(function () {
                const val = $(this).val();
                if (val) {
                    code = val;
                    return false; // עצור בלולאה
                }
            });

            let phone = '';
            $('.phone-input').each(function () {
                const val = $(this).val();
                if (val) {
                    phone = val;
                    return false;
                }
            });
            //disable the button
            $('.verify-button').prop('disabled', true).addClass('disabled');
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
                    $('.verify-button').prop('disabled', false).removeClass('disabled');
                    if (response.success) {
                        window.location.reload();
                    } else {
                        if(!$('.woocommerce-error').length) {
                            $('.sms-verification-form').prepend(
                                '<div class="woocommerce-error">' + 
                                response.data.message + 
                                '</div>'
                            );
                        } else {
                            $('.woocommerce-error').html(response.data.message);
                        }
                    }
                },
                error: function() {
                    if(!$('.woocommerce-error').length) {
                        $('.sms-verification-form').prepend(
                            '<div class="woocommerce-error">' + 
                            oc_sms_auth.i18n.error_verifying + 
                            '</div>'
                        );
                    } else {
                        $('.woocommerce-error').html(oc_sms_auth.i18n.error_verifying);
                    }
                }
            });
        },

        handleResendCode: function(e) {
            e.preventDefault();
            const $button = $(this);
            
            $button.prop('disabled', true).addClass('disabled');
            $('.woocommerce-error').remove(); // Clear previous errors

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
                        smsAuth.startCodeTimer();
                        // Show success message
                        $('.sms-verification-form').prepend(
                            '<div class="woocommerce-message">' + 
                            oc_sms_auth.i18n.code_resent + 
                            '</div>'
                        );
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        smsAuth.showError('.sms-verification-form', response.data.message || response.data);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    smsAuth.showError('.sms-verification-form', oc_sms_auth.i18n.error_resending);
                }
            });
        },

        startCodeTimer: function() {
            // Clear any existing timer
            if (this.codeTimer) {
                clearInterval(this.codeTimer);
            }
            
            let timeLeft = oc_sms_auth.code_expiry || 180;
            const $timerDisplay = $('.verification-code-timer');
            
            if (!$timerDisplay.length) {
                $('.verification-code-input').after('<div class="verification-code-timer"></div>');
            }
            
            // Store timer reference
            this.codeTimer = setInterval(() => {
                timeLeft--;
                $('.verification-code-timer').text(
                    `${Math.floor(timeLeft/60)}:${(timeLeft % 60).toString().padStart(2, '0')}`
                );
                
                if (timeLeft <= 0) {
                    clearInterval(this.codeTimer);
                    $('.sms-verification-form').hide();
                    $('.sms-auth-form').show();
                }
            }, 1000);
        }
    };

    smsAuth.init();
}); 