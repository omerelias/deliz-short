/**
 * Product Popup Event Handlers
 * Handles close button, overlay, and keyboard events
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState;

    /**
     * Handle close button click
     */
    function handleCloseClick(e) {
        if (!e.target.closest('.ed-product-popup__close')) return;
        e.preventDefault();
        if (window.EDProductPopupCore?.closePopup) {
            window.EDProductPopupCore.closePopup();
        }
    }

    /**
     * Handle overlay click
     */
    function handleOverlayClick(e) {
        if (!e.target.closest('.ed-product-popup__overlay')) return;
        if (window.EDProductPopupCore?.closePopup) {
            window.EDProductPopupCore.closePopup();
        }
    }

    /**
     * Handle keyboard
     */
    function handleKeyDown(e) {
        if (e.key === 'Escape' && state.isOpen) {
            if (window.EDProductPopupCore?.closePopup) {
                window.EDProductPopupCore.closePopup();
            }
        }
    }

    // Expose functions
    window.EDProductPopupEvents = {
        handleCloseClick,
        handleOverlayClick,
        handleKeyDown
    };

})();

