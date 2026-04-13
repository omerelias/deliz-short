/**
 * OCWS: block "מעבר לתשלום" until choose-shipping was submitted; open delivery popup if missing.
 * When checkout-upsells is active, it calls delizOcwsCheckoutGateRun before the upsell AJAX.
 * When upsells is off, the fallback handler below runs the same check.
 */
(function (window, document) {
    'use strict';

    window.delizOcwsCheckoutGateRun = function (callback) {
        var cfg = window.delizOcwsCheckoutGate || {};
        if (!cfg.ocwsActive) {
            callback(true);
            return;
        }
        if (typeof jQuery === 'undefined' || !cfg.ajaxUrl) {
            callback(true);
            return;
        }
        jQuery.post(cfg.ajaxUrl, { action: 'deliz_ocws_shipping_status' }, function (response) {
            var ok = response && response.success && response.data && response.data.confirmed;
            callback(!!ok);
        }).fail(function () {
            callback(true);
        });
    };

    window.delizOpenOcwsDeliveryPopup = function () {
        if (typeof jQuery === 'undefined') {
            return;
        }
        var $ = jQuery;
        var $chip = $('#ocws-delivery-data-chip .cds-button-change');
        if ($chip.length) {
            $chip.trigger('click');
            return;
        }
        $('.choose-shipping-popup').addClass('shown');
        $('body').css({ overflow: 'hidden' });
    };

    if (typeof jQuery === 'undefined') {
        return;
    }

    jQuery(function ($) {
        var cfg = window.delizOcwsCheckoutGate || {};
        if (!cfg.ocwsActive) {
            return;
        }
        // Non-empty cart on page load: open choose-shipping only if theme did not disable it (confirmed in session).
        if (cfg.autoOpenShippingOnLoad && !$('body').hasClass('ocws-deli-style')) {
            if (window.console && window.console.debug) {
                window.console.debug('[deliz-ocws-gate] auto-opening choose-shipping popup (autoOpenShippingOnLoad)');
            }
            setTimeout(function () {
                if (typeof window.delizOpenOcwsDeliveryPopup === 'function') {
                    window.delizOpenOcwsDeliveryPopup();
                }
            }, 200);
        } else if (window.console && window.console.debug) {
            window.console.debug('[deliz-ocws-gate] skip auto-open choose-shipping', { autoOpenShippingOnLoad: !!cfg.autoOpenShippingOnLoad });
        }
        // checkout-upsells.js handles this path when enabled (ED_CHECKOUT_UPSELLS is defined).
        if (typeof window.ED_CHECKOUT_UPSELLS !== 'undefined' && window.ED_CHECKOUT_UPSELLS.ajaxUrl) {
            return;
        }

        $(document).on('click', '.ed-float-cart__btn--checkout', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var href = $(this).attr('href');
            window.delizOcwsCheckoutGateRun(function (confirmed) {
                if (confirmed) {
                    window.location.href = href;
                } else {
                    window.delizOpenOcwsDeliveryPopup();
                }
            });
        });
    });
})(window, document);
