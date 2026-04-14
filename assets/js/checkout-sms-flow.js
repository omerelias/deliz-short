jQuery(function($) {
    'use strict'; 

    const CheckoutSMSFlow = {
        /** True when opened from header / "sign in" — phone + code only, then full page reload. */
        headerSmsMode: false,

        /** OCWS #choose-shipping embedded above "אספקה" fields (wrap stays outside wizard <form>); restored on close. */
        embedOcwsInWizardIfNeeded: function() { 
            const log = function(msg, extra) { 
                if (extra !== undefined) { 
                    console.log('[Checkout SMS][OCWS embed]', msg, extra);
                } else {
                    console.log('[Checkout SMS][OCWS embed]', msg);
                }
            };
            if (CheckoutSMSFlow.headerSmsMode) {
                log('skip: headerSmsMode');
                return;
            }
            const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
            log('state', {
                shipping_kind: sk,
                shipping_chosen: typeof oc_sms_auth !== 'undefined' ? oc_sms_auth.shipping_chosen : undefined,
                delizOcwsCheckoutGate: typeof window.delizOcwsCheckoutGate !== 'undefined' ? window.delizOcwsCheckoutGate : undefined,
                chooseShippingPopups: $('.choose-shipping-popup.ocws-popup').length
            });
            if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_debug) {
                log('למה נראה שכבר נבחרה שיטה / מאיפה shipping_kind', oc_sms_auth.shipping_debug);
            }
            if (sk !== 'none') {
                log('skip: shipping_kind is not "none" — embed only when no method yet. unmounting if needed.');
                CheckoutSMSFlow.unmountOcwsFromWizard();
                return;
            }
            const ocwsActive = typeof window.delizOcwsCheckoutGate !== 'undefined' && window.delizOcwsCheckoutGate.ocwsActive;
            if (!ocwsActive) {
                log('skip: delizOcwsCheckoutGate.ocwsActive is false or gate script missing');
                return;
            }
            const $mount = $('#checkout-sms-ocws-embed-mount');
            const $wrap = $('#checkout-sms-ocws-embed-wrap');
            if (!$mount.length || !$wrap.length) {
                log('skip: mount/wrap DOM missing', { mount: $mount.length, wrap: $wrap.length });
                return;
            }
            const $ocws = $('.choose-shipping-popup.ocws-popup').first();
            if (!$ocws.length) {
                log('skip: no .choose-shipping-popup.ocws-popup in page (OCWS popup not rendered?)');
                return;
            }
            if (!$ocws.closest('#checkout-sms-ocws-embed-mount').length) {
                if (!$('#checkout-sms-ocws-dom-marker').length) {
                    $('<div id="checkout-sms-ocws-dom-marker" style="display:none;" aria-hidden="true"></div>').insertBefore($ocws);
                }
                $ocws.appendTo($mount);
                log('moved .choose-shipping-popup into #checkout-sms-ocws-embed-mount');
            }
            $ocws.addClass('choose-shipping-popup--embedded-in-sms shown');
            $ocws.find('.inner-wrapper .pop-close').hide();
            $('#checkout-sms-popup .checkout-sms-popup__content').addClass('checkout-sms-popup__content--ocws-embed');
            $wrap.removeAttr('hidden').show().attr('data-ocws-active', '1');
            log('wrap activated; calling syncOcwsEmbedTabVisibility (אספקה tab must be active to show)');
            CheckoutSMSFlow.syncOcwsEmbedTabVisibility();
            CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
            CheckoutSMSFlow.relocateSupplyFloorFieldsAfterShippingOptions();
        },

        /**
         * קומה/דירה/קוד — מקור יחיד לעומת .ocws-checkout-address-extras-pp (מוסתר ב-CSS ב-embed).
         * ממוקם אחרי #popup-shipping-options בתוך #choose-shipping כדי להישלח עם submit של OCWS (סשן צ'קאאוט) ולשמור ערכים לפני הרישום.
         */
        relocateSupplyFloorFieldsAfterShippingOptions: function() {
            if (CheckoutSMSFlow.headerSmsMode) {
                return;
            }
            const $floor = $('#checkout-sms-popup .checkout-sms-supply-floor-fields').first();
            const $opts = $('#choose-shipping #popup-shipping-options').first();
            if (!$floor.length || !$opts.length) {
                return;
            }
            if ($floor.prev()[0] === $opts[0]) {
                $floor.attr('data-deliz-floor-embedded', '1');
                return;
            }
            $floor.insertAfter($opts);
            $floor.attr('data-deliz-floor-embedded', '1');
        },

        /** להחזיר לוויזארד לפני סגירת פופאב — לא לקרוא מ-unmount לפני submit של #choose-shipping (השדות חייבים להישאר בטופס). */
        restoreSupplyFloorFieldsToWizard: function() {
            const $floor = $('.checkout-sms-supply-floor-fields[data-deliz-floor-embedded="1"]').first();
            const $ph = $('#checkout-sms-supply-floor-placeholder');
            if (!$floor.length || !$ph.length) {
                return;
            }
            $floor.insertAfter($ph);
            $floor.removeAttr('data-deliz-floor-embedded');
        },

        /** ערכי קומה/דירה/קוד בין אם השדות בטופס הוויזארד ובין אם הוזזו ל-#choose-shipping */
        getNewUserWizardBillingExtras: function() {
            const $box = $('.checkout-sms-supply-floor-fields').first();
            if (!$box.length) {
                return { billing_floor: '', billing_apartment: '', billing_enter_code: '' };
            }
            return {
                billing_floor: String($box.find('[name="billing_floor"]').val() || '').trim(),
                billing_apartment: String($box.find('[name="billing_apartment"]').val() || '').trim(),
                billing_enter_code: String($box.find('[name="billing_enter_code"]').val() || '').trim()
            };
        },

        unmountOcwsFromWizard: function() {
            if (CheckoutSMSFlow._wizardOcwsPollIv) {
                clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                CheckoutSMSFlow._wizardOcwsPollIv = null;
            }
            const $ocws = $('.choose-shipping-popup.ocws-popup').first();
            const $marker = $('#checkout-sms-ocws-dom-marker');
            if ($ocws.length && $ocws.closest('#checkout-sms-ocws-embed-mount').length && $marker.length) {
                $ocws.insertBefore($marker);
            } 
            $ocws.removeClass('choose-shipping-popup--embedded-in-sms');
            $ocws.removeClass('shown');
            $ocws.find('.inner-wrapper .pop-close').show();
            $('#checkout-sms-popup .checkout-sms-popup__content').removeClass('checkout-sms-popup__content--ocws-embed');
            const $wrap = $('#checkout-sms-ocws-embed-wrap'); 
            $wrap.attr('hidden', 'hidden').hide().removeAttr('data-ocws-active').attr('aria-hidden', 'true');
        },

        /**
         * OCWS google-maps-init runs ocwsInitChooseShippingPopupAutocomplete on shipping_popup_loaded or non-checkout pages.
         * After embed is visible on the אספקה tab, trigger init once so Places binds when the field is usable.
         */
        maybeInitOcwsGoogleAutocomplete: function() {
            const $wrap = $('#checkout-sms-ocws-embed-wrap');
            if (!$wrap.attr('data-ocws-active') || !$wrap.is(':visible')) {
                return;
            }
            const $inp = $('#choose-shipping input[name="billing_google_autocomplete"]');
            if (!$inp.length) {
                return;
            }
            /* בצ'קאאוט init של #choose-shipping לא רץ ב-DOM ready — צריך קריאה ישירה אחרי שהשדה גלוי (SMS embed / טאב אספקה). */
            const runInit = function() {
                if (typeof window.ocwsInitChooseShippingPopupAutocomplete === 'function') {
                    try {
                        window.ocwsInitChooseShippingPopupAutocomplete();
                    } catch (err) {
                        console.warn('[Checkout SMS] ocwsInitChooseShippingPopupAutocomplete failed', err);
                        $(document.body).trigger('shipping_popup_loaded');
                    }
                } else {
                    $(document.body).trigger('shipping_popup_loaded');
                }
                if (typeof google !== 'undefined' && google.maps && google.maps.event) {
                    setTimeout(function() {
                        google.maps.event.trigger(window, 'resize');
                    }, 120);
                }
            };
            setTimeout(runInit, 100);
        },

        syncOcwsEmbedTabVisibility: function() {
            const $wrap = $('#checkout-sms-ocws-embed-wrap');
            if (!$wrap.attr('data-ocws-active')) {
                console.log('[Checkout SMS][OCWS embed] sync: no data-ocws-active on wrap — embed not mounted yet');
                return;
            }
            const supplyActive = $('#checkout-sms-popup .checkout-sms-wizard-tab[data-tab="supply"]').hasClass('is-active');
            console.log('[Checkout SMS][OCWS embed] sync tab visibility', { supplyTabActive: supplyActive, wrapVisible: $wrap.is(':visible') });
            if (supplyActive) {
                $wrap.show().attr('aria-hidden', 'false');
                CheckoutSMSFlow.maybeInitOcwsGoogleAutocomplete();
            } else {
                $wrap.hide().attr('aria-hidden', 'true');
                console.log('[Checkout SMS][OCWS embed] wrap hidden — switch to "אספקה" to choose משלוח / איסוף');
            }
        },

        /** After wp_set_auth_cookie(), the page nonce (guest) is invalid — use nonce from JSON if present. */
        applyNonceFromAuthResponse: function(response) {
            if (typeof oc_sms_auth !== 'undefined' && response && response.data && typeof response.data === 'object' && response.data.nonce) {
                oc_sms_auth.nonce = response.data.nonce;
                console.log('[Checkout SMS] nonce refreshed after login/register'); 
            }
        },

        init: function() {
            console.log('[Checkout SMS] Initializing...');
            console.log('[Checkout SMS] oc_sms_auth available:', typeof oc_sms_auth !== 'undefined');
            if (typeof oc_sms_auth !== 'undefined') {
                console.log('[Checkout SMS] oc_sms_auth:', oc_sms_auth);
                if (oc_sms_auth.shipping_debug) {
                    console.log('[Checkout SMS] למה נראה שכבר נבחרה שיטה (טעינה ראשונית, shipping_debug)', oc_sms_auth.shipping_debug);
                }
                if (oc_sms_auth.delivery_extra_debug) {
                    console.info('[Checkout SMS] delivery_extra_debug (PHP log: wp-content/deliz-short-wc-debug.log):', oc_sms_auth.delivery_extra_debug);
                }
            }
            this.bindEvents();
            $(document.body).on('ocws_min_total_continue_sync', function() {
                CheckoutSMSFlow.syncNuWizardSubmitButtonLabel();
            });
            $(document.body).on('ocws_cart_fragments_refreshed', function() {
                if (!$('#checkout-sms-popup').is(':visible')) {
                    return;
                }
                if (!$('.choose-shipping-popup--embedded-in-sms').length) {
                    return;
                }
                CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                    CheckoutSMSFlow.applyNewUserWizardChrome();
                    CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
                    const skAfter = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
                    if (skAfter !== 'none') {
                        CheckoutSMSFlow.unmountOcwsFromWizard();
                    } else {
                        CheckoutSMSFlow.embedOcwsInWizardIfNeeded();
                    }
                });
            });
            console.log('[Checkout SMS] Events bound');
            CheckoutSMSFlow.syncNuInvoiceCompanyField();
            CheckoutSMSFlow.syncNuWizardSubmitButtonLabel();
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
            $(document).on('change', '.checkout-sms-newuser-wizard-form .nu-invoice-other-name', this.syncNuInvoiceCompanyField);
            $(document).on('change', '#choose-shipping input[name="popup-shipping-method"]', function() {
                CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
            });
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
                        if (response.data.shipping_debug !== undefined) {
                            oc_sms_auth.shipping_debug = response.data.shipping_debug;
                        }
                        console.log('[Checkout SMS] refreshCheckoutSmsContext →', {
                            shipping_kind: oc_sms_auth.shipping_kind,
                            shipping_chosen: oc_sms_auth.shipping_chosen,
                            show_delivery_extra: oc_sms_auth.show_delivery_extra
                        });
                        if (oc_sms_auth.shipping_debug) {
                            console.log('[Checkout SMS] למה נראה שכבר נבחרה שיטה (shipping_debug)', oc_sms_auth.shipping_debug);
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
                console.warn('[Checkout SMS] post_existing_restore skipped: oc_sms_auth missing');
                cb();
                return;
            }
            console.log('[Checkout SMS] post_existing_restore request…');
            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'deliz_short_sms_post_existing_restore',
                    nonce: oc_sms_auth.nonce
                },
                success: function(response) {
                    console.log('[Checkout SMS] post_existing_restore response (full)', response);
                    if (response && Object.prototype.hasOwnProperty.call(response, 'data')) {
                        console.log('[Checkout SMS] post_existing_restore data', response.data);
                    }
                    cb();
                },
                error: function(xhr, status, err) {
                    console.error('[Checkout SMS] post_existing_restore ajax error', status, err, xhr && xhr.responseText);
                    cb();
                }
            });
        },

        /** true אם בוחרים איסוף עצמי בפופאב OCWS */
        isOcwsChooseShippingPickupSelected: function() {
            const $c = $('#choose-shipping input[name="popup-shipping-method"]:checked');
            if (!$c.length) {
                return false;
            }
            const v = String($c.val() || '');
            return v.indexOf('oc_woo_local_pickup_method') === 0;
        },

        /** קומה / דירה / קוד — רק למשלוח; באיסוף מסתירים */
        syncCheckoutSmsSupplyFloorFieldsVisibility: function() {
            const $wrap = $('#checkout-sms-popup .checkout-sms-supply-floor-fields');
            if (!$wrap.length) {
                return;
            }
            if (CheckoutSMSFlow.isOcwsChooseShippingPickupSelected()) {
                $wrap.attr('hidden', 'hidden').hide().attr('aria-hidden', 'true');
            } else {
                $wrap.removeAttr('hidden').show().attr('aria-hidden', 'false');
            }
        },

        validateNewUserWizardDetails: function($form) {
            const fn = ($form.find('.nu-first-name').val() || '').trim();
            const ln = ($form.find('.nu-last-name').val() || '').trim();
            const em = ($form.find('.nu-email').val() || '').trim();
            if (!fn || !ln || !em) {
                return false;
            }
            const $email = $form.find('.nu-email');
            if ($email.length && $email[0].checkValidity && typeof $email[0].checkValidity === 'function') {
                if (!$email[0].checkValidity()) {
                    return false;
                }
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
                return false;
            }
            if ($form.find('.nu-invoice-other-name').is(':checked')) {
                const co = ($form.find('.nu-billing-company').val() || '').trim();
                if (!co) {
                    return false;
                }
            }
            return true;
        },

        /** כפתור שליחה משותף: בטאב פרטי מזמין — "המשך לבחירת אספקה"; בטאב שיטת אספקה — "המשך" או "הוסף עוד מוצרים" כשהעגלה מתחת למינימום למשלוח (OCWS) */
        syncNuWizardSubmitButtonLabel: function() {
            const $btn = $('#checkout-sms-popup .nu-wizard-submit');
            if (!$btn.length) {
                return;
            }
            const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
            const $popup = $('#checkout-sms-popup');
            if (!$popup.find('.checkout-sms-popup__step--newuser-wizard').hasClass('active')) {
                return;
            }
            const detailsActive = $popup.find('.checkout-sms-wizard-panel[data-panel="details"]').hasClass('is-active');
            if (detailsActive) {
                $btn.text(i18n.wizard_continue_to_supply || 'המשך לבחירת אספקה');
                return;
            }
            const belowMin = typeof window.ocwsMinTotalBelowMin !== 'undefined' && window.ocwsMinTotalBelowMin;
            if (belowMin) {
                const addLbl = (typeof ocws !== 'undefined' && ocws.localize && ocws.localize.messages && ocws.localize.messages.addMoreProductsContinue)
                    ? ocws.localize.messages.addMoreProductsContinue
                    : 'הוסף עוד מוצרים';
                $btn.text(addLbl);
                return;
            }
            $btn.text(i18n.wizard_continue || 'המשך');
        },

        syncNuInvoiceCompanyField: function() {
            const $form = $('#checkout-sms-popup .checkout-sms-newuser-wizard-form');
            const $cb = $form.find('.nu-invoice-other-name');
            const $wrap = $form.find('#checkout-sms-nu-company-wrap');
            const $inp = $form.find('.nu-billing-company');
            if (!$wrap.length) {
                return;
            }
            if ($cb.is(':checked')) {
                $wrap.removeAttr('hidden').show().attr('aria-hidden', 'false');
                $inp.prop('required', true);
            } else {
                $wrap.attr('hidden', 'hidden').hide().attr('aria-hidden', 'true');
                $inp.prop('required', false).val('');
            }
        },

        /**
         * אותה בדיקה כמו ב־ajax_register_user (email_exists) — לפני מעבר לטאב אספקה / שלב שני.
         */
        checkRegistrationEmailAvailable: function($form, callback) {
            if (typeof oc_sms_auth === 'undefined' || !oc_sms_auth.ajaxurl) {
                if (typeof callback === 'function') {
                    callback(true);
                }
                return;
            }
            const email = ($form.find('.nu-email').val() || '').trim();
            $.ajax({
                url: oc_sms_auth.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_check_register_email',
                    nonce: oc_sms_auth.nonce,
                    email: email
                },
                success: function(response) {
                    if (response && response.success) {
                        callback(true);
                        return;
                    }
                    const msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : 'כתובת האימייל כבר קיימת במערכת';
                    callback(false, msg);
                },
                error: function() {
                    callback(false, 'שגיאת רשת — נסה שוב');
                }
            });
        },

        /**
         * שלב 1 (פרטי מזמין) → is-active. אחרי מעבר לשלב 2: שלב 1 = is-completed (הושלם), שלב 2 = is-active.
         */
        activateWizardTab: function(tab) {
            CheckoutSMSFlow.showError('newuser-wizard', '');
            const $popup = $('#checkout-sms-popup');
            const $detailsTab = $popup.find('.checkout-sms-wizard-tab[data-tab="details"]');
            const $supplyTab = $popup.find('.checkout-sms-wizard-tab[data-tab="supply"]');
            $popup.find('.checkout-sms-wizard-tab').removeClass('is-active is-completed');
            $popup.find('.checkout-sms-wizard-panel').removeClass('is-active');
            $popup.find('.checkout-sms-wizard-panel[data-panel="' + tab + '"]').addClass('is-active');
            if (tab === 'supply') {
                $detailsTab.addClass('is-completed');
                $supplyTab.addClass('is-active');
                $detailsTab.attr('aria-selected', 'false');
                $supplyTab.attr('aria-selected', 'true');
            } else {
                $detailsTab.addClass('is-active');
                $supplyTab.attr('aria-selected', 'false');
                $detailsTab.attr('aria-selected', 'true');
            }
            CheckoutSMSFlow.syncOcwsEmbedTabVisibility();
            CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
            CheckoutSMSFlow.syncNuWizardSubmitButtonLabel();
        },

        onWizardTabClick: function(e) {
            e.preventDefault();
            const tab = $(e.currentTarget).data('tab');
            const $popup = $('#checkout-sms-popup');
            const $form = $popup.find('.checkout-sms-newuser-wizard-form');
            if (tab === 'supply' && !CheckoutSMSFlow.validateNewUserWizardDetails($form)) {
                const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
                let msg = i18n.wizard_details_required || 'נא למלא שם פרטי, משפחה ואימייל';
                if ($form.find('.nu-invoice-other-name').is(':checked') && !String($form.find('.nu-billing-company').val() || '').trim()) {
                    msg = i18n.wizard_company_required || 'נא למלא שם לחשבונית';
                }
                CheckoutSMSFlow.showError('newuser-wizard', msg);
                return;
            }
            if (tab === 'supply') {
                CheckoutSMSFlow.checkRegistrationEmailAvailable($form, function(ok, message) {
                    if (!ok) {
                        CheckoutSMSFlow.showError('newuser-wizard', message || '');
                        return;
                    }
                    CheckoutSMSFlow.activateWizardTab(tab);
                });
                return;
            }
            CheckoutSMSFlow.activateWizardTab(tab);
        },

        applyNewUserWizardChrome: function() {
            const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
            const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
            const $popup = $('#checkout-sms-popup');
            const $tabs = $popup.find('.checkout-sms-wizard-tabs');
            const $titleMain = $popup.find('.checkout-sms-popup__title--main');
            console.log('[Checkout SMS] applyNewUserWizardChrome', { shipping_kind: sk });
            $tabs.removeClass('is-hidden');
            if (sk === 'pickup') {
                /* שם/אימייל כבר ב"פרטים" — לא מציגים "על שם מי ההזמנה" ולא מסתירים טאבים */
                $titleMain.text(i18n.data_verify_title || 'אימות נתונים');
            } else if (sk === 'delivery') {
                $titleMain.text(i18n.delivery_complete_title || 'השלם את פרטי המשלוח');
            } else {
                $titleMain.text(i18n.data_verify_title || 'אימות נתונים');
            }
            if (sk !== 'none') {
                CheckoutSMSFlow.unmountOcwsFromWizard();
            }
            CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
        },

        /**
         * רישום אחרי ששיטת המשלוח כבר נשמרה ב-session (אותו AJAX כמו קודם).
         */
        runNewUserWizardRegisterAjax: function($form, $button, formData) {
            if (typeof oc_sms_auth === 'undefined' || !oc_sms_auth.ajaxurl) {
                $button.prop('disabled', false).removeClass('disabled');
                return;
            }
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
                        CheckoutSMSFlow.applyNonceFromAuthResponse(response);
                        CheckoutSMSFlow.prepareCheckoutNavigationLoader();
                        CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                            const sk2 = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
                            const ocwsA = typeof window.delizOcwsCheckoutGate !== 'undefined' && window.delizOcwsCheckoutGate.ocwsActive;
                            if (sk2 === 'none' && !ocwsA) {
                                CheckoutSMSFlow.revealSmsPopupForStep('how-receive');
                            } else {
                                CheckoutSMSFlow.afterSuccessfulAuth();
                            }
                        });
                    } else {
                        CheckoutSMSFlow.showWizardErrorAfterSupplyStrip(response.data && response.data.message ? response.data.message : 'שגיאה ברישום');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('disabled');
                    CheckoutSMSFlow.showWizardErrorAfterSupplyStrip('שגיאה ברישום');
                }
            });
        },

        /**
         * בטאב אספקה עם embed: "המשך" שולח את #choose-shipping (במקום כפתור אישור שהוסתר) ואז ממשיך לרישום כשה-session מתעדכן.
         */
        submitEmbeddedOcwsThenRegister: function($form, $button, formData) {
            if (CheckoutSMSFlow._wizardOcwsPollIv) {
                clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                CheckoutSMSFlow._wizardOcwsPollIv = null;
            }
            CheckoutSMSFlow._wizardOcwsRegisterStarted = false;
            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.clearWizardErrorIfContainerExists();
            if (typeof window.ocwsSubmitChooseShippingWhenReady === 'function') {
                window.ocwsSubmitChooseShippingWhenReady();
            } else {
                $('#choose-shipping').trigger('submit');
            }

            var attempts = 0;
            var maxAttempts = 45;
            var validationTimer = setTimeout(function() {
                var $errs = $('#choose-shipping').closest('.choose-shipping-popup').find(
                    '#popup-form-messages .error, #popup-pickup-form-messages .error, #popup-shipping-form-messages .error'
                );
                if ($errs.length && $errs.text().trim()) {
                    if (CheckoutSMSFlow._wizardOcwsPollIv) {
                        clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                        CheckoutSMSFlow._wizardOcwsPollIv = null;
                    }
                    $button.prop('disabled', false).removeClass('disabled');
                }
            }, 600);

            function pollSave() {
                attempts++;
                CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                    const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
                    if (sk !== 'none') {
                        if (CheckoutSMSFlow._wizardOcwsRegisterStarted) {
                            return;
                        }
                        CheckoutSMSFlow._wizardOcwsRegisterStarted = true;
                        clearTimeout(validationTimer);
                        if (CheckoutSMSFlow._wizardOcwsPollIv) {
                            clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                            CheckoutSMSFlow._wizardOcwsPollIv = null;
                        }
                        /* לא applyNewUserWizardChrome — זה מחליף כותרת ל"השלם את פרטי המשלוח" רגע לפני רישום; מספיק unmount */
                        CheckoutSMSFlow.unmountOcwsFromWizard();
                        CheckoutSMSFlow.syncCheckoutSmsSupplyFloorFieldsVisibility();
                        CheckoutSMSFlow.runNewUserWizardRegisterAjax($form, $button, formData);
                    } else if (attempts >= maxAttempts) {
                        clearTimeout(validationTimer);
                        if (CheckoutSMSFlow._wizardOcwsPollIv) {
                            clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                            CheckoutSMSFlow._wizardOcwsPollIv = null;
                        }
                        $button.prop('disabled', false).removeClass('disabled');
                        const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
                        CheckoutSMSFlow.showWizardErrorAfterSupplyStrip(i18n.wizard_need_shipping || 'נא להשלים את בחירת המשלוח');
                    }
                });
            }
            pollSave();
            CheckoutSMSFlow._wizardOcwsPollIv = setInterval(pollSave, 350);
        },

        handleNewUserWizard: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $button = $form.find('.nu-wizard-submit');
            const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
            if (!CheckoutSMSFlow.validateNewUserWizardDetails($form)) {
                let msg = i18n.wizard_details_required || 'נא למלא שם פרטי, משפחה ואימייל';
                if ($form.find('.nu-invoice-other-name').is(':checked') && !String($form.find('.nu-billing-company').val() || '').trim()) {
                    msg = i18n.wizard_company_required || 'נא למלא שם לחשבונית';
                }
                CheckoutSMSFlow.showError('newuser-wizard', msg);
                return;
            }
            const $popup = $('#checkout-sms-popup');
            const detailsPanelActive = $popup.find('.checkout-sms-wizard-panel[data-panel="details"]').hasClass('is-active');
            if (detailsPanelActive) {
                $button.prop('disabled', true).addClass('disabled');
                CheckoutSMSFlow.checkRegistrationEmailAvailable($form, function(ok, message) {
                    $button.prop('disabled', false).removeClass('disabled');
                    if (!ok) {
                        CheckoutSMSFlow.showError('newuser-wizard', message || '');
                        return;
                    }
                    CheckoutSMSFlow.activateWizardTab('supply');
                });
                return;
            }

            const billingExtras = CheckoutSMSFlow.getNewUserWizardBillingExtras();
            const companyVal = $form.find('.nu-invoice-other-name').is(':checked')
                ? String($form.find('.nu-billing-company').val() || '').trim()
                : '';
            const formData = {
                first_name: $form.find('.nu-first-name').val(),
                last_name: $form.find('.nu-last-name').val(),
                email: $form.find('.nu-email').val(),
                phone: $form.find('.nu-register-phone-input').val(),
                billing_company: companyVal,
                billing_floor: billingExtras.billing_floor,
                billing_apartment: billingExtras.billing_apartment,
                billing_enter_code: billingExtras.billing_enter_code
            };

            const skPre = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
            const supplyActive = $('#checkout-sms-popup .checkout-sms-wizard-tab[data-tab="supply"]').hasClass('is-active');
            const embedOn = $('#checkout-sms-ocws-embed-wrap').attr('data-ocws-active') && $('.choose-shipping-popup--embedded-in-sms').length;

            CheckoutSMSFlow.removeCheckoutSmsPopupContainer();

            if (skPre === 'none' && supplyActive && embedOn) {
                CheckoutSMSFlow.submitEmbeddedOcwsThenRegister($form, $button, formData);
                return;
            }

            $button.prop('disabled', true).addClass('disabled');
            CheckoutSMSFlow.clearWizardErrorIfContainerExists();
            CheckoutSMSFlow.refreshCheckoutSmsContext(function() {
                const sk = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.shipping_kind) ? oc_sms_auth.shipping_kind : 'none';
                const ocwsActive = typeof window.delizOcwsCheckoutGate !== 'undefined' && window.delizOcwsCheckoutGate.ocwsActive;
                if (sk === 'none' && ocwsActive) {
                    $button.prop('disabled', false).removeClass('disabled');
                    const i18n = (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.i18n) ? oc_sms_auth.i18n : {};
                    CheckoutSMSFlow.showWizardErrorAfterSupplyStrip(i18n.wizard_need_shipping || 'נא להשלים את בחירת המשלוח');
                    return;
                }
                CheckoutSMSFlow.runNewUserWizardRegisterAjax($form, $button, formData);
            });
        },

        _wizardOcwsPollIv: null,
        _wizardOcwsRegisterStarted: false,

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
                const extra = typeof oc_sms_auth !== 'undefined' ? oc_sms_auth.show_delivery_extra : undefined;
                const kind = typeof oc_sms_auth !== 'undefined' ? oc_sms_auth.shipping_kind : undefined;
                console.log('[Checkout SMS] afterSuccessfulAuth context', { show_delivery_extra: extra, shipping_kind: kind });
                if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.show_delivery_extra) {
                    console.log('[Checkout SMS] showing floor/apt step (delivery extra)');
                    CheckoutSMSFlow.revealSmsPopupForStep('shipping');
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
            CheckoutSMSFlow.hideCheckoutNavigationLoader();
            CheckoutSMSFlow.restoreSupplyFloorFieldsToWizard();
            CheckoutSMSFlow.unmountOcwsFromWizard();
            $popup.fadeOut(300);
            $('body').removeClass('checkout-sms-popup-open');
            CheckoutSMSFlow.resetSteps();
        },

        /**
         * שלב אספקה: מוחק את .checkout-sms-popup__container מה-DOM. מעביר את OCWS ל-body לפני המחיקה כדי ש־#choose-shipping ימשיך לעבוד.
         */
        removeCheckoutSmsPopupContainer: function() {
            CheckoutSMSFlow.unmountOcwsFromWizard();
            const $ocws = $('.choose-shipping-popup.ocws-popup').first();
            if ($ocws.length && $ocws.closest('#checkout-sms-popup').length) {
                $ocws.appendTo('body');
            }
            $('#checkout-sms-popup .checkout-sms-popup__container').remove();
            CheckoutSMSFlow.hideCheckoutNavigationLoader();
            $('#checkout-sms-popup').hide();
            $('body').removeClass('checkout-sms-popup-open');
            $('body').css('overflow', '');
        },

        showWizardErrorAfterSupplyStrip: function(message) {
            if (!message) {
                return;
            }
            if (!$('#checkout-sms-popup .checkout-sms-popup__container').length) {
                window.alert(message);
                return;
            }
            CheckoutSMSFlow.showError('newuser-wizard', message);
        },

        clearWizardErrorIfContainerExists: function() {
            if ($('#checkout-sms-popup .checkout-sms-popup__container').length) {
                CheckoutSMSFlow.showError('newuser-wizard', '');
            }
        },

        /**
         * מציג loader ומסתיר את תוכן .checkout-sms-popup__content (כולל --ocws-embed) עד לניווט או לשלב הבא.
         */
        prepareCheckoutNavigationLoader: function() {
            if (!$('#checkout-sms-popup .checkout-sms-popup__container').length) {
                return;
            }
            if (CheckoutSMSFlow._wizardOcwsPollIv) {
                clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                CheckoutSMSFlow._wizardOcwsPollIv = null;
            }
            CheckoutSMSFlow._wizardOcwsRegisterStarted = false;
            if (CheckoutSMSFlow.ocwsWaitPoll) {
                clearInterval(CheckoutSMSFlow.ocwsWaitPoll);
                CheckoutSMSFlow.ocwsWaitPoll = null;
            }
            CheckoutSMSFlow.restoreSupplyFloorFieldsToWizard();
            CheckoutSMSFlow.unmountOcwsFromWizard();
            $('.choose-shipping-popup').removeClass('shown');
            $('body').css('overflow', '');
            const $sms = $('#checkout-sms-popup');
            const $loader = $('#checkout-sms-checkout-loader');
            if (!$sms.length) {
                return;
            }
            $sms.stop(true, true).show();
            $('body').addClass('checkout-sms-popup-open');
            $sms.addClass('checkout-sms-popup--checkout-transition');
            if ($loader.length) {
                $loader.removeAttr('hidden').attr('aria-hidden', 'false');
            }
        },

        hideCheckoutNavigationLoader: function() {
            const $sms = $('#checkout-sms-popup');
            const $loader = $('#checkout-sms-checkout-loader');
            $sms.removeClass('checkout-sms-popup--checkout-transition');
            if ($loader.length) {
                $loader.attr('hidden', 'hidden').attr('aria-hidden', 'true');
            }
        },

        /**
         * סוגר פופאפ SMS, OCWS choose-shipping ושכבות — לפני מעבר לצ'קאאוט / אפסייל (מונע הבזק UI).
         */
        dismissAllCheckoutOverlaysForNavigation: function() {
            CheckoutSMSFlow.hideCheckoutNavigationLoader();
            if (CheckoutSMSFlow._wizardOcwsPollIv) {
                clearInterval(CheckoutSMSFlow._wizardOcwsPollIv);
                CheckoutSMSFlow._wizardOcwsPollIv = null;
            }
            CheckoutSMSFlow._wizardOcwsRegisterStarted = false;
            if (CheckoutSMSFlow.ocwsWaitPoll) {
                clearInterval(CheckoutSMSFlow.ocwsWaitPoll);
                CheckoutSMSFlow.ocwsWaitPoll = null;
            }
            if ($('#checkout-sms-popup .checkout-sms-popup__container').length) {
                CheckoutSMSFlow.restoreSupplyFloorFieldsToWizard();
            }
            CheckoutSMSFlow.unmountOcwsFromWizard();
            $('.choose-shipping-popup').removeClass('shown');
            $('body').css('overflow', '');
            const $sms = $('#checkout-sms-popup');
            if ($sms.length) {
                $sms.stop(true, true).hide();
            }
            $('body').removeClass('checkout-sms-popup-open');
        },

        /**
         * מציג מחדש את פופאפ SMS אחרי dismiss (למשל אחרי רישום) ומעביר לשלב מסוים.
         */
        revealSmsPopupForStep: function(step) {
            const $p = $('#checkout-sms-popup');
            if (!$p.length) {
                return;
            }
            if (!$p.find('.checkout-sms-popup__container').length) {
                window.location.reload();
                return;
            }
            CheckoutSMSFlow.hideCheckoutNavigationLoader();
            $p.stop(true, true).show();
            $('body').addClass('checkout-sms-popup-open');
            CheckoutSMSFlow.showStep(step);
        },

        resetSteps: function() {
            const $popup = $('#checkout-sms-popup');
            if (!$popup.find('.checkout-sms-popup__container').length) {
                return;
            }
            CheckoutSMSFlow.restoreSupplyFloorFieldsToWizard();
            CheckoutSMSFlow.unmountOcwsFromWizard();
            $popup.find('.checkout-sms-popup__step').removeClass('active');
            $popup.find('.checkout-sms-popup__step--phone').addClass('active');
            $popup.find('form')[0]?.reset();
            $popup.find('.checkout-sms-popup__error').empty();
            $popup.find('.checkout-sms-wizard-tab').removeClass('is-active is-completed');
            $popup.find('.checkout-sms-wizard-tab[data-tab="details"]').addClass('is-active');
            $popup.find('.checkout-sms-wizard-tab[data-tab="details"]').attr('aria-selected', 'true');
            $popup.find('.checkout-sms-wizard-tab[data-tab="supply"]').attr('aria-selected', 'false');
            $popup.find('.checkout-sms-wizard-panel').removeClass('is-active');
            $popup.find('.checkout-sms-wizard-panel[data-panel="details"]').addClass('is-active');
            CheckoutSMSFlow.syncNuInvoiceCompanyField();
            CheckoutSMSFlow.syncNuWizardSubmitButtonLabel();
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
                console.log('[Checkout SMS] showStep → newuser-wizard');
                CheckoutSMSFlow.applyNewUserWizardChrome();
                CheckoutSMSFlow.syncNuWizardSubmitButtonLabel();
                setTimeout(function() {
                    CheckoutSMSFlow.embedOcwsInWizardIfNeeded();
                }, 0);
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
                        CheckoutSMSFlow.applyNonceFromAuthResponse(response);
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
                        CheckoutSMSFlow.applyNonceFromAuthResponse(response);
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
                        CheckoutSMSFlow.prepareCheckoutNavigationLoader();
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
            CheckoutSMSFlow.prepareCheckoutNavigationLoader();
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
                            window.edCheckoutUrl = checkoutUrl;
                            CheckoutSMSFlow.dismissAllCheckoutOverlaysForNavigation();
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

