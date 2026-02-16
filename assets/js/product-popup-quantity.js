/**
 * Product Popup Quantity Input Management
 * Handles quantity input initialization and toggle functionality
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState;

    /**
     * Sync quantity labels from toggle buttons
     */
    function syncQuantityLabelsFromToggle() {
        if (!state.popupElement) return;

        const toggleButtons = state.popupElement.querySelectorAll('.ed-product-popup__toggle-btn');
        if (!toggleButtons || toggleButtons.length === 0) return;

        const unitsText = state.popupElement.querySelector('.ed-product-popup__toggle-btn[data-mode="units"] span')?.textContent?.trim();
        const weightText = state.popupElement.querySelector('.ed-product-popup__toggle-btn[data-mode="weight"] span')?.textContent?.trim();

        const unitsLabel = state.popupElement.querySelector('#popup-quantity-units-container .ed-product-popup__qty-label');
        const weightLabel = state.popupElement.querySelector('#popup-quantity-weight-container .ed-product-popup__qty-label');

        if (unitsLabel && unitsText) unitsLabel.textContent = unitsText;
        if (weightLabel && weightText) weightLabel.textContent = weightText;
    }

    /**
     * Initialize quantity inputs
     */
    function initQuantityInputs() {
        if (!state.popupElement) return;

        // Toggle buttons for units/weight switching
        const toggleButtons = state.popupElement.querySelectorAll('.ed-product-popup__toggle-btn');

        toggleButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const mode = this.dataset.mode;
                if (!mode) return;

                // Update active state
                toggleButtons.forEach(b => b.classList.remove('is-active'));
                this.classList.add('is-active');

                // Show/hide appropriate quantity input
                const unitsContainer = state.popupElement.querySelector('#popup-quantity-units-container');
                const weightContainer = state.popupElement.querySelector('#popup-quantity-weight-container');

                if (mode === 'units') {
                    if (unitsContainer) unitsContainer.style.display = '';
                    if (weightContainer) weightContainer.style.display = 'none';
                    state.popupElement.dataset.quantityMode = 'units';
                    syncQuantityLabelsFromToggle();
                } else if (mode === 'weight') {
                    if (unitsContainer) unitsContainer.style.display = 'none';
                    if (weightContainer) weightContainer.style.display = '';
                    state.popupElement.dataset.quantityMode = 'weight';
                    syncQuantityLabelsFromToggle();
                }

                // Update ocwsu fields
                if (window.EDProductPopupOcwsu?.updateOcwsuHiddenFields) {
                    window.EDProductPopupOcwsu.updateOcwsuHiddenFields();
                }
                if (window.EDProductPopupVariations?.validateAddToCartButton) {
                    window.EDProductPopupVariations.validateAddToCartButton();
                }
            });
        });

        // Set initial mode
        if (toggleButtons.length > 0) {
            state.popupElement.dataset.quantityMode = 'units'; // Default to units
            syncQuantityLabelsFromToggle();
        }

        // Quantity buttons
        state.popupElement.querySelectorAll('.ed-product-popup__qty-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const action = this.dataset.action;
                const input = this.closest('.ed-product-popup__quantity-input').querySelector('input');
                const min = parseFloat(input.min) || 1;
                let value = parseFloat(input.value) || min;

                // Check if this is a weight input (has step attribute on input, not button)
                const isWeightInput = input.id === 'popup-quantity-weight';

                // For weight inputs, always use step of 1 (as per user requirement)
                // For other inputs, use the step from data attribute or default to 1
                let step = 1;
                if (!isWeightInput) {
                    step = parseFloat(this.dataset.step) || 1;
                }

                if (action === 'increase') {
                    value += step;
                } else if (action === 'decrease' && value > min) {
                    value -= step;
                    // Ensure value doesn't go below minimum
                    if (value < min) {
                        value = min;
                    }
                }

                // Round to step precision (for weight, step is 1, so round to 1 decimal)
                const decimals = isWeightInput ? 1 : (step.toString().split('.')[1]?.length || 0);
                value = parseFloat(value.toFixed(decimals));

                input.value = value;
                input.dispatchEvent(new Event('change', {bubbles: true}));

                if (window.EDProductPopupOcwsu?.updateOcwsuHiddenFields) {
                    window.EDProductPopupOcwsu.updateOcwsuHiddenFields();
                }
                if (window.EDProductPopupVariations?.validateAddToCartButton) {
                    window.EDProductPopupVariations.validateAddToCartButton();
                }
            });
        });

        // Quantity input changes
        state.popupElement.querySelectorAll('.ed-product-popup__qty-input').forEach(input => {
            input.addEventListener('change', function () {
                if (window.EDProductPopupOcwsu?.updateOcwsuHiddenFields) {
                    window.EDProductPopupOcwsu.updateOcwsuHiddenFields();
                }
                if (window.EDProductPopupVariations?.validateAddToCartButton) {
                    window.EDProductPopupVariations.validateAddToCartButton();
                }
            });
        });
    }

    // Expose functions
    window.EDProductPopupQuantity = {
        initQuantityInputs,
        syncQuantityLabelsFromToggle
    };

})();

