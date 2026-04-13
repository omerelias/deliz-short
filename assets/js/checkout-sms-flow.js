jQuery(function($) {
    'use strict';

    const CheckoutSMSFlow = {
        /** True when opened from header / "sign in" — phone + code only, then full page reload. */
        headerSmsMode: false,

        init: function() {
            console.log('[Checkout SMS] Initializing...');
            console.log('[Checkout SMS] oc_sms_auth available:', typeof oc_sms_auth !== 'undefined');
            if (typeof oc_sms_auth !== 'undefined') {
                console.log('[Checkout SMS] oc_sms_auth:', oc_sms_auth);
                if (oc_sms_auth.delivery_extra_debug) {
                    console.info('[Checkout SMS] delivery_extra_debug (PHP log: wp-content/deliz-short-wc-debug.log):', oc_sms_auth.delivery_extra_debug);
                }
            }
            this.bindEvents();
            console.log('[Checkout SMS] Events bound'); 
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

            // New user wizard (tabs + optional supply fields)
            $(document).on('submit', '.checkout-sms-newuser-wizard-form', this.handleNewUserWizard);
            $(document).on('click', '.checkout-sms-wizard-tab', this.onWizardTabClick);
            $(document).on('click', '.checkout-sms-open-ocws-btn', this.handleOpenOcwsHowReceive);
            
            // Shipping details form (after registration)
            $(document).on('submit', '.checkout-sms-shipping-form', this.handleShippingDetails);
            
            // Close popup (X or dimmed backdrop — does not navigate to checkout)
            $(document).on('click', '.checkout-sms-popup__close, .checkout-sms-popup__overlay', function(e) {
                e.preventDefault();
                e.stopPropagation();
                CheckoutSMSFlow.closePopup();
            });
        },

        handleCheckoutClick: function(e) {
            // Floating-cart checkout: checkout-upsells runs first (upsell → SMS). Do not open SMS here.
            if ($(e.currentTarget).hasClass('ed-float-cart__btn--checkout')) {
                return;
            }

            console.log('[Checkout SMS] Checkout button clicked');
            console.log('[Checkout SMS] oc_sms_auth defined:', typeof oc_sms_auth !== 'undefined');
            
            // Store checkout URL for later use
            const $link = $(this);
            const checkoutUrl = $link.attr('href') || $link.data('checkout-url') || (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.checkout_url : '/checkout/');
            window.edCheckoutUrl = checkoutUrl;
            console.log('[Checkout SMS] Stored checkout URL:', checkoutUrl);
            
            if (typeof oc_sms_auth !== 'undefined') {
                console.log('[Checkout SMS] is_logged_in:', oc_sms_auth.is_logged_in);
                console.log('[Checkout SMS] is_logged_in type:', typeof oc_sms_auth.is_logged_in);
            }
            
            // Check if user is logged in
            if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.is_logged_in == 0) {
                console.log('[Checkout SMS] User not logged in - showing popup');
                e.preventDefault();
                e.stopPropagation();
                CheckoutSMSFlow.showPopup();
                return false;
            } else {
                console.log('[Checkout SMS] User is logged in or oc_sms_auth not defined - allowing normal navigation');
            }
            // If logged in, allow normal navigation
        },

        /**
         * Header "Login" — same SMS UI as checkout, without register/shipping steps; success → reload.
         */
        showHeaderSmsPopup: function() {
            CheckoutSMSFlow.headerSmsMode = true;
            $('#checkout-sms-popup').addClass('checkout-sms-popup--header-only');
            CheckoutSMSFlow.showPopup();
        },

        showPopup: function() {
            console.log('[Checkout SMS] showPopup called');
            const $popup = $('#checkout-sms-popup');
            console.log('[Checkout SMS] Popup element found:', $popup.length);
            
            if ($popup.length === 0) {
                console.error('[Checkout SMS] Popup element not found! Make sure checkout-sms-popup.php is included in footer.');
                return;
            }

            if (!CheckoutSMSFlow.headerSmsMode) {
                CheckoutSMSFlow.refreshCheckoutSmsContext();
            }

            $popup.fadeIn(300);
            $('body').addClass('checkout-sms-popup-open');
            $popup.find('.checkout-sms-popup__step--phone').addClass('active');
            $popup.find('.phone-input').focus();
            console.log('[Checkout SMS] Popup shown');
        },

        /**
         * Re-fetch show_delivery_extra / shipping_intro_html from server (nonce: oc_sms_auth).
         */
        refreshCheckoutSmsContext: function(done) {
            const cb = typeof done === 'function' ? done : function() {};
            if (typeof oc_sms_auth === 'undefined' || !oc_sms_auth.ajaxurl) {
                cb();
                return;
            }
            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'deliz_short_sms_flow_full_context',
                    nonce: oc_sms_auth.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        oc_sms_auth.show_delivery_extra = !!response.data.show_delivery_extra;
                        if (response.data.shipping_intro_html !== undefined) {
                            oc_sms_auth.shipping_intro_html = response.data.shipping_intro_html;
                        }
                        if (response.data.shipping_chosen !== undefined) {
                            oc_sms_auth.shipping_chosen = !!response.data.shipping_chosen;
                        }
                        if (response.data.shipping_kind !== undefined) {
                            oc_sms_auth.shipping_kind = response.data.shipping_kind;
                        }
                    }
                    cb();
                },
                error: function() {
                    cb();
                }
            });
        },

        /** After SMS code — existing customer: restore last order + slot if no shipping chosen */
        maybeRestoreExistingCustomerShipping: function(done) {
            const cb = typeof done === 'function' ? done : function() {};
            if (typeof oc_sms_auth === 'undefined' || !oc_sms_auth.ajaxurl) {
                cb();
                return;
            }
            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'deliz_short_sms_post_existing_restore',
                    nonce: oc_sms_auth.nonce
                },
                complete: function() {
                    cb();
                }
            });
        },

        onWizardTabClick: function(e) {
            e.preventDefault();
            const tab = $(e.currentTarget).data('tab');
            const $popup = $('#checkout-sms-popup');
            $popup.find('.checkout-sms-wizard-tab').removeClass('is-active');
            $popup.find('.checkout-sms-wizard-tab[data-tab="' + tab + '"]').addClass('is-active');
            $popup.find('.checkout-sms-wizard-panel').removeClass('is-active');
            $popup.find('.checkout-sms-wizard-panel[data-panel="' + tab + '"]').addClass('is-active');
        },

        applyNewUserWizardChrome: function() {
            const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
            const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
            const $popup = $('#checkout-sms-popup');
            const $tabs = $popup.find('.checkout-sms-wizard-tabs');
            const $titleMain = $popup.find('.checkout-sms-popup__title--main');
            $tabs.removeClass('is-hidden');
            if (sk === 'pickup') {
                $titleMain.text(i18n.pickup_order_title || 'על שם מי ההזמנה?');
                $tabs.addClass('is-hidden');
            } else if (sk === 'delivery') {
                $titleMain.text(i18n.delivery_complete_title || 'השלם את פרטי המשלוח');
            } else {
                $titleMain.text(i18n.data_verify_title || 'אימות נתונים');
            }
            if (sk === 'pickup') {
                $popup.find('.checkout-sms-wizard-panel[data-panel="supply"]').removeClass('is-active');
                $popup.find('.checkout-sms-wizard-panel[data-panel="details"]').addClass('is-active');
                $popup.find('.checkout-sms-wizard-tab').removeClass('is-active');
                $popup.find('.checkout-sms-wizard-tab[data-tab="details"]').addClass('is-active');
            }
        },

        handleNewUserWizard: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $button = $form.find('.nu-wizard-submit');
            const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
            const formData = {
                first_name: $form.find('.nu-first-name').val(),
                last_name: $form.find('.nu-last-name').val(),
                email: $form.find('.nu-email').val(),
                phone: $form.find('.nu-register-phone-input').val(),
                billing_floor: $form.find('[name="billing_floor"]').val(),
                billing_apartment: $form.find('[name="billing_apartment"]').val(),
                billing_enter_code: $form.find('[name="billing_enter_code"]').val()
            };
            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('newuser-wizard', '');
            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_register_user',
                    nonce: oc_sms_auth.nonce,
                    ...formData
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('disabled');
                    if (response.success) {
                        if (sk === 'none') {
                            CheckoutSMSFlow.showStep('how-receive');
                        } else {
                            CheckoutSMSFlow.afterSuccessfulAuth();
                        }
                    } else {
                        CheckoutSMSFlow.showError('newuser-wizard', response.data && response.data.message ? response.data.message : 'שגיאה ברישום');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('newuser-wizard', 'שגיאה ברישום');
                }
            });
        },

        ocwsWaitPoll: null,

        handleOpenOcwsHowReceive: function(e) {
            e.preventDefault();
            CheckoutSMSFlow.showError('how-receive', '');
            if (typeof window.delizOpenOcwsDeliveryPopup === 'function') {
                window.delizOpenOcwsDeliveryPopup();
            } else {
                const $chip = $('#ocws-delivery-data-chip .cds-button-change');
                if ($chip.length) {
                    $chip.trigger('click');
                } else {
                    $('.choose-shipping-popup').addClass('shown');
                    $('body').css({ overflow: 'hidden' });
                }
            }
            if (CheckoutSMSFlow.ocwsWaitPoll) {
                clearInterval(CheckoutSMSFlow.ocwsWaitPoll);
            }
            const gateUrl = (typeof window.delizOcwsCheckoutGate !== 'undefined' && window.delizOcwsCheckoutGate.ajaxUrl)
                ? window.delizOcwsCheckoutGate.ajaxUrl
                : oc_sms_auth.ajaxurl;
            CheckoutSMSFlow.ocwsWaitPoll = setInterval(function() {
                $.post(gateUrl, { action: 'deliz_ocws_shipping_status' }, function(r) {
                    if (r && r.success && r.data && r.data.confirmed) {
                        clearInterval(CheckoutSMSFlow.ocwsWaitPoll);
                        CheckoutSMSFlow.ocwsWaitPoll = null;
                        CheckoutSMSFlow.redirectAfterAuth();
                    }
                });
            }, 500);
        },

        /** After SMS login or new registration: shipping step when home delivery, else upsells/checkout. */
        afterSuccessfulAuth: function() {
            if (CheckoutSMSFlow.headerSmsMode) {
                window.location.reload();
                return;
            }
            CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.show_delivery_extra) {
                    CheckoutSMSFlow.showStep('shipping');
                } else {
                    CheckoutSMSFlow.redirectAfterAuth();
                }
            });
        },

        closePopup: function() {
            if (CheckoutSMSFlow.ocwsWaitPoll) {
                clearInterval(CheckoutSMSFlow.ocwsWaitPoll);
                CheckoutSMSFlow.ocwsWaitPoll = null;
            }
            if (CheckoutSMSFlow.codeTimer) {
                clearInterval(CheckoutSMSFlow.codeTimer);
                CheckoutSMSFlow.codeTimer = null;
            }
            if (typeof window.edCheckoutUrl !== 'undefined') {
                window.edCheckoutUrl = null;
            }
            CheckoutSMSFlow.headerSmsMode = false;
            $('#checkout-sms-popup').removeClass('checkout-sms-popup--header-only');
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

            // Update title/description per step
            const $titleMain = $popup.find('.checkout-sms-popup__title--main');
            if (step === 'shipping') {
                $titleMain.hide();
            } else {
                $titleMain.show();
            }
            if (step === 'phone') {
                $titleMain.text('הכנס מס טלפון לביצוע הזמנה');
            } else if (step === 'code') {
                $titleMain.text('אימות מספר הטלפון');
            } else if (step === 'register') {
                $titleMain.text('השלמת פרטי לקוח');
            } else if (step === 'newuser-wizard') {
                CheckoutSMSFlow.applyNewUserWizardChrome();
            } else if (step === 'how-receive') {
                const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
                $titleMain.text(i18n.how_receive_title || 'איך תרצו לקבל');
                $titleMain.show();
            } else if (step === 'shipping') {
                const $intro = $popup.find('.checkout-sms-popup__shipping-intro');
                if ($intro.length && typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_intro_html) {
                    $intro.html(oc_sms_auth.shipping_intro_html);
                } else if ($intro.length) {
                    $intro.empty();
                }
            }
        },

        showError: function(step, message) {
            const $popup = $('#checkout-sms-popup');
            const $step = $popup.find('.checkout-sms-popup__step--' + step);
            const $error = $step.find('.checkout-sms-popup__error');
            if (!message) {
                $error.empty();
                return;
            }
            if (typeof message === 'string' && /class\s*=\s*["']woocommerce-message["']/.test(message)) {
                $error.html(message);
                return;
            }
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
                            if (CheckoutSMSFlow.headerSmsMode) {
                                $.ajax({
                                    url: oc_sms_auth.ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'oc_send_auth_sms',
                                        phone: phone,
                                        nonce: oc_sms_auth.nonce,
                                        force_sms: '1'
                                    },
                                    success: function(r2) {
                                        $button.prop('disabled', false).removeClass('disabled');
                                        if (r2.success) {
                                            CheckoutSMSFlow.showStep('code');
                                            CheckoutSMSFlow.startCodeTimer();
                                        } else {
                                            const msg = (r2.data && r2.data.message) ? r2.data.message : oc_sms_auth.i18n.error_sending;
                                            CheckoutSMSFlow.showError('phone', msg);
                                        }
                                    },
                                    error: function() {
                                        $button.prop('disabled', false).removeClass('disabled');
                                        CheckoutSMSFlow.showError('phone', oc_sms_auth.i18n.error_sending);
                                    }
                                });
                                return;
                            }
                            $button.prop('disabled', false).removeClass('disabled');
                            CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                                $('.nu-register-phone-input').val(response.data.phone || phone);
                                $('.register-phone-input').val(response.data.phone || phone);
                                CheckoutSMSFlow.showStep('newuser-wizard');
                            });
                        } else {
                            $button.prop('disabled', false).removeClass('disabled');
                            CheckoutSMSFlow.showStep('code');
                            CheckoutSMSFlow.startCodeTimer();
                        }
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        if (response.data && response.data.show_register) {
                            if (CheckoutSMSFlow.headerSmsMode) {
                                CheckoutSMSFlow.showError('phone', response.data.message || response.data || oc_sms_auth.i18n.error_sending);
                            } else {
                                CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                                    $('.nu-register-phone-input').val(response.data.phone || phone);
                                    $('.register-phone-input').val(response.data.phone || phone);
                                    CheckoutSMSFlow.showStep('newuser-wizard');
                                });
                            }
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
                        CheckoutSMSFlow.maybeRestoreExistingCustomerShipping(function() {
                            CheckoutSMSFlow.afterSuccessfulAuth();
                        });
                    } else {
                        if (response.data && (response.data.show_register || response.data.message && response.data.message.includes('not found'))) {
                            if (CheckoutSMSFlow.headerSmsMode) {
                                CheckoutSMSFlow.showError('code', response.data.message || response.data || oc_sms_auth.i18n.error_verifying);
                            } else {
                                CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                                    $('.nu-register-phone-input').val(phone);
                                    $('.register-phone-input').val(phone);
                                    CheckoutSMSFlow.showStep('newuser-wizard');
                                });
                            }
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
                        CheckoutSMSFlow.showError('code', '<div class="woocommerce-message" role="status">' + oc_sms_auth.i18n.code_resent + '</div>');
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
                        $button.prop('disabled', false).removeClass('disabled');
                        CheckoutSMSFlow.afterSuccessfulAuth();
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

        handleShippingDetails: function(e) {
            e.preventDefault();
            const $form   = $(this);
            const $button = $form.find('.shipping-button');

            const data = {
                billing_floor:     $form.find('[name="billing_floor"]').val(),
                billing_apartment: $form.find('[name="billing_apartment"]').val(),
                billing_enter_code:$form.find('[name="billing_enter_code"]').val()
            };

            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.showError('shipping', '');

            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_update_shipping_details',
                    nonce: oc_sms_auth.nonce,
                    ...data
                },
                success: function(response) {
                    if (response.success) {
                        // After saving shipping details, proceed to checkout / upsells like normal login
                        CheckoutSMSFlow.redirectAfterAuth();
                    } else {
                        $button.prop('disabled', false).removeClass('disabled');
                        CheckoutSMSFlow.showError('shipping', response.data && response.data.message ? response.data.message : 'שגיאה בשמירת פרטי המשלוח');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showError('shipping', 'שגיאה בשמירת פרטי המשלוח');
                }
            });
        },

        redirectAfterAuth: function() {
            const checkoutUrl = window.edCheckoutUrl || $('.checkout-btn-trigger').data('checkout-url') || (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.checkout_url : '/checkout/');

            if (typeof window.ED_CHECKOUT_UPSELLS !== 'undefined' && window.ED_CHECKOUT_UPSELLS.ajaxUrl) {
                $.ajax({
                    url: window.ED_CHECKOUT_UPSELLS.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ed_check_checkout_upsells',
                        nonce: window.ED_CHECKOUT_UPSELLS.nonce,
                    },
                    success: function(upsellResponse) {
                        if (upsellResponse.success && upsellResponse.data && upsellResponse.data.has_upsells) {
                            // Show upsells popup
                            window.edCheckoutUrl = checkoutUrl;
                            if (typeof window.showCheckoutUpsellsPopup === 'function') {
                                window.showCheckoutUpsellsPopup();
                            } else {
                                window.location.href = checkoutUrl;
                            }
                        } else {
                            window.location.href = checkoutUrl;
                        }
                    },
                    error: function() {
                        window.location.href = checkoutUrl;
                    },
                });
            } else {
                window.location.href = checkoutUrl;
            }
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

