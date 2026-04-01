jQuery(function($) {

    const smsAuth = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.sms-auth-form').on('submit', this.handleSendCode);
            // Scope to login popup only — checkout uses #checkout-sms-popup .verify-button (handled by checkout-sms-flow.js)
            $(document).on('click', '.sms-auth-container .verify-button', this.handleVerifyCode);
            $(document).on('click', '.sms-auth-container .resend-code', this.handleResendCode);
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
                        const $scope = $form.closest('.sms-auth-container');
                        $scope.find('.sms-auth-form').hide();
                        $scope.find('.sms-verification-form').show();
                        smsAuth.startCodeTimer($scope);
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
            const $scope = $(e.target).closest('.sms-auth-container');
            if (!$scope.length) {
                return;
            }
            const $verifyForm = $scope.find('.sms-verification-form');
            const code = ($scope.find('.sms-verification-form .code-input').val() || '').trim();
            const phone = ($scope.find('.sms-auth-form .phone-input').val() || '').trim();

            $scope.find('.verify-button').prop('disabled', true).addClass('disabled');
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
                    $scope.find('.verify-button').prop('disabled', false).removeClass('disabled');
                    if (response.success) {
                        window.location.reload();
                    } else {
                        const errMsg = (response.data && response.data.message) ? response.data.message : '';
                        if (!$verifyForm.find('.woocommerce-error').length) {
                            $verifyForm.prepend(
                                '<div class="woocommerce-error">' +
                                errMsg +
                                '</div>'
                            );
                        } else {
                            $verifyForm.find('.woocommerce-error').html(errMsg);
                        }
                    }
                },
                error: function() {
                    if (!$verifyForm.find('.woocommerce-error').length) {
                        $verifyForm.prepend(
                            '<div class="woocommerce-error">' +
                            oc_sms_auth.i18n.error_verifying +
                            '</div>'
                        );
                    } else {
                        $verifyForm.find('.woocommerce-error').html(oc_sms_auth.i18n.error_verifying);
                    }
                }
            });
        },

        handleResendCode: function(e) {
            e.preventDefault();
            const $scope = $(e.target).closest('.sms-auth-container');
            if (!$scope.length) {
                return;
            }
            const $button = $(this);
            const $verifyForm = $scope.find('.sms-verification-form');

            $button.prop('disabled', true).addClass('disabled');
            $verifyForm.find('.woocommerce-error').remove();

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
                        smsAuth.startCodeTimer($scope);
                        $verifyForm.prepend(
                            '<div class="woocommerce-message">' +
                            oc_sms_auth.i18n.code_resent +
                            '</div>'
                        );
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        smsAuth.showError($verifyForm, response.data.message || response.data);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    smsAuth.showError($verifyForm, oc_sms_auth.i18n.error_resending);
                }
            });
        },

        startCodeTimer: function($scope) {
            const $root = $scope && $scope.length ? $scope : $('.sms-auth-container').first();
            if (!$root.length) {
                return;
            }
            if (this.codeTimer) {
                clearInterval(this.codeTimer);
            }

            let timeLeft = oc_sms_auth.code_expiry || 180;
            let $timerDisplay = $root.find('.verification-code-timer');

            if (!$timerDisplay.length) {
                $root.find('.verification-code-input').first().after('<div class="verification-code-timer"></div>');
                $timerDisplay = $root.find('.verification-code-timer');
            }

            this.codeTimer = setInterval(() => {
                timeLeft--;
                $timerDisplay.text(
                    `${Math.floor(timeLeft / 60)}:${(timeLeft % 60).toString().padStart(2, '0')}`
                );

                if (timeLeft <= 0) {
                    clearInterval(this.codeTimer);
                    $root.find('.sms-verification-form').hide();
                    $root.find('.sms-auth-form').show();
                }
            }, 1000);
        }
    };

    smsAuth.init();
}); 