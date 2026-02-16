/**
 * Product Popup OCWSU (oc-woo-sale-units) Management
 * Handles OCWSU hidden fields updates
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState;

    /**
     * Update ocwsu hidden fields based on selections
     */
    function updateOcwsuHiddenFields() {
        if (!state.popupElement || !state.popupData) return;

        const ocwsu = state.popupData.ocwsu || {};

        // Check if product can toggle between units and weight
        const canToggle = ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight;
        const currentMode = state.popupElement.dataset.quantityMode || (ocwsu.sold_by_units ? 'units' : 'weight');

        // Get selected unit weight
        let unitWeight = 0;

        // If get_weight_from_variation is enabled, get weight from selected variation
        if (ocwsu.get_weight_from_variation && state.popupData.type === 'variable') {
            const variationId = state.popupElement.dataset.variationId;
            if (variationId) {
                const variation = state.popupData.variations.find(v => v.id == variationId);
                if (variation && variation.weight) {
                    // Variation weight is already in the correct units (from PHP)
                    unitWeight = parseFloat(variation.weight);
                    console.log('Using variation weight:', unitWeight, 'from variation:', variationId);
                }
            }
        } else {
            // Normal flow: get from radio button or default
            const unitWeightRadio = state.popupElement.querySelector('input[name="popup_unit_weight"]:checked');
            unitWeight = unitWeightRadio ? parseFloat(unitWeightRadio.value) : (ocwsu.unit_weight || 0);
        }

        // Get quantity based on current mode
        let qtyInput = null;
        let quantity = 1;
        let quantityInUnits = 0;
        let quantityInWeightUnits = 0;

        if (canToggle) {
            // Product can toggle - get quantity from active input
            if (currentMode === 'units') {
                qtyInput = state.popupElement.querySelector('#popup-quantity-units');
                quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
                quantityInUnits = quantity;
                quantityInWeightUnits = 0;
            } else {
                qtyInput = state.popupElement.querySelector('#popup-quantity-weight');
                quantity = qtyInput ? parseFloat(qtyInput.value) : (ocwsu.min_weight || 0.5);
                quantityInUnits = 0;
                quantityInWeightUnits = quantity;
            }
        } else {
            // Product has only one mode
            qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
            quantity = qtyInput ? parseFloat(qtyInput.value) : 1;

            if (ocwsu.sold_by_units) {
                quantityInUnits = quantity;
                quantityInWeightUnits = 0;
            } else if (ocwsu.sold_by_weight) {
                quantityInUnits = 0;
                quantityInWeightUnits = quantity;
            }
        }

        // Calculate quantity in kg
        let quantityInKg = quantity;

        if (canToggle && currentMode === 'units' && unitWeight) {
            // Units mode: convert to kg
            quantityInKg = quantity * unitWeight * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
        } else if (canToggle && currentMode === 'weight') {
            // Weight mode: already in weight units, convert to kg
            quantityInKg = quantity * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
        } else if (ocwsu.weighable && ocwsu.sold_by_units && unitWeight) {
            quantityInKg = quantity * unitWeight * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
        } else if (ocwsu.weighable && ocwsu.sold_by_weight) {
            quantityInKg = quantity * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
        }

        // Store in data attributes for add to cart
        state.popupElement.dataset.ocwsuUnit = (canToggle && currentMode === 'units') || ocwsu.sold_by_units ? 'unit' : (ocwsu.product_weight_units || 'kg');
        state.popupElement.dataset.ocwsuUnitWeight = unitWeight || 0;
        state.popupElement.dataset.ocwsuQuantityInUnits = quantityInUnits;
        state.popupElement.dataset.ocwsuQuantityInWeightUnits = quantityInWeightUnits;
        state.popupElement.dataset.quantityInKg = quantityInKg;
    }

    // Expose functions
    window.EDProductPopupOcwsu = {
        updateOcwsuHiddenFields
    };

})();

