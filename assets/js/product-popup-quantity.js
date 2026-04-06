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
     * Step for +/- — same rules as floating mini cart (input step, or "any" → 0.1 kg).
     */
    function getPopupQtyStep(input) {
        if (!input) return 1;
        if (input.id === 'popup-quantity-units') {
            return 1;
        }
        const raw = input.getAttribute('step');
        if (!raw || raw === 'any') {
            return input.id === 'popup-quantity-weight' ? 0.1 : 1;
        }
        const n = parseFloat(raw);
        return !isFinite(n) || n <= 0 ? 1 : n;
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

        bindQtyButtonListeners();
    }

    /**
     * Programmatic units/weight toggle (e.g. edit-from-cart prefill).
     */
    function setQuantityMode(mode) {
        if (!state.popupElement) return;
        const toggleButtons = state.popupElement.querySelectorAll('.ed-product-popup__toggle-btn');
        if (!toggleButtons.length) return;

        toggleButtons.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.mode === mode);
        });

        const unitsContainer = state.popupElement.querySelector('#popup-quantity-units-container');
        const weightContainer = state.popupElement.querySelector('#popup-quantity-weight-container');

        if (mode === 'units') {
            if (unitsContainer) unitsContainer.style.display = '';
            if (weightContainer) weightContainer.style.display = 'none';
            state.popupElement.dataset.quantityMode = 'units';
        } else if (mode === 'weight') {
            if (unitsContainer) unitsContainer.style.display = 'none';
            if (weightContainer) weightContainer.style.display = '';
            state.popupElement.dataset.quantityMode = 'weight';
        }

        syncQuantityLabelsFromToggle();
    }

    function bindQtyButtonListeners() {
        if (!state.popupElement) return;

        // Quantity buttons
        state.popupElement.querySelectorAll('.ed-product-popup__qty-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const action = this.dataset.action;
                const input = this.closest('.ed-product-popup__quantity-input').querySelector('input');
                const min = parseFloat(input.min) || 1;
                const step = getPopupQtyStep(input);
                let base = parseFloat(String(input.value).replace(',', '.'));
                if (!isFinite(base)) {
                    base = min;
                }

                let value = base;
                if (action === 'increase') {
                    value = Math.round((base + step) / step) * step;
                    if (!isFinite(value)) {
                        value = base + step;
                    }
                } else if (action === 'decrease') {
                    let candidate = Math.round((base - step) / step) * step;
                    if (!isFinite(candidate)) {
                        candidate = base - step;
                    }
                    const eps = 1e-9;
                    if (candidate < min - eps) {
                        value = min;
                    } else {
                        value = candidate;
                    }
                }

                value = parseFloat(value.toFixed(6));

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
        syncQuantityLabelsFromToggle,
        setQuantityMode
    };

})();

