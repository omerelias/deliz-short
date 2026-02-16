/**
 * Product Popup Variations Functions
 * Handles variation selection, attribute changes, and add to cart button validation
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState; // Access shared state

    /**
     * Handle attribute/variation changes
     */
    function handleAttributeChange(e) {
        if (!state.popupElement || !state.popupElement.contains(e.target)) return;

        const radio = e.target.closest('input[type="radio"]');
        if (!radio) return;

        // Hide error if option selected
        const errorEl = state.popupElement.querySelector('#popup-option-error');
        if (errorEl) errorEl.style.display = 'none';

        // If it's a variation attribute, update price and availability
        if (radio.name.startsWith('attribute_')) {
            updateVariationSelection();
        }

        validateAddToCartButton();

        // Update hidden fields for ocwsu
        window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();
    }

    /**
     * Update variation selection and price
     */
    function updateVariationSelection() {
        if (!state.popupElement || !state.popupData || state.popupData.type !== 'variable') return;

        console.group('🔍 [DEBUG] updateVariationSelection');
        console.log('popupData:', state.popupData);
        console.log('popupData.attributes:', state.popupData.attributes);
        console.log('popupData.variations:', state.popupData.variations);

        // Build selected attributes object
        // Key format: attr.name (e.g., 'pa_color' or 'custom_attr') - WITHOUT 'attribute_' prefix
        // This matches the format in variation.attributes from PHP
        const selectedAttributes = {};
        const checkedInputs = state.popupElement.querySelectorAll('input[name^="attribute_"]:checked');
        console.log('Checked inputs:', checkedInputs);

        checkedInputs.forEach(radio => {
            // Remove 'attribute_' prefix to match variation.attributes format
            const attrName = radio.name.replace('attribute_', '');
            selectedAttributes[attrName] = radio.value;
            console.log(`Selected: ${radio.name} = ${radio.value} (key: ${attrName})`);
        });

        console.log('selectedAttributes:', selectedAttributes);

        // Check if all required attributes are selected
        const allAttributesSelected = state.popupData.attributes.every(attr => {
            const found = state.popupElement.querySelector(`input[name="attribute_${attr.name}"]:checked`);
            console.log(`Checking attribute ${attr.name}:`, found ? 'SELECTED' : 'NOT SELECTED');
            return found;
        });

        console.log('allAttributesSelected:', allAttributesSelected);

        if (!allAttributesSelected) {
            // Not all attributes selected yet
            console.log('❌ Not all attributes selected');
            state.popupElement.dataset.variationId = '';
            console.groupEnd();
            return;
        }

        // Find matching variation
        // IMPORTANT: PHP removes 'pa_' prefix from variation attributes keys
        // So if attr.name is 'pa_צורת-חיתוך', variation.attributes key is 'צורת-חיתוך'
        console.log('🔍 Searching for matching variation...');
        const ignoredAttrsForMatch = []; // attributes that variation leaves empty and should be ignored when submitting

        const matchingVariation = state.popupData.variations.find(variation => {
            console.log(`Checking variation ${variation.id}:`, variation.attributes);
            console.log(`  Variation attributes keys:`, Object.keys(variation.attributes));
            console.log(`  Variation attributes entries:`, Object.entries(variation.attributes));

            // Check if variation has all selected attributes
            const matches = state.popupData.attributes.every(attr => {
                const attrName = attr.name; // e.g., 'pa_צורת-חיתוך' or 'custom_attr'

                // PHP removes 'pa_' prefix, so we need to check both with and without it
                // Try with pa_ prefix first (if it exists)
                let variationKey = attrName;

                if (attrName.startsWith('pa_')) {
                    // Remove 'pa_' prefix to match PHP format
                    variationKey = attrName.replace(/^pa_/, '');
                }

                const selectedValue = selectedAttributes[attrName];

                // Try to find the value in variation.attributes - check all possible keys
                let variationValue = variation.attributes[variationKey];

                // If not found, try to find by iterating through all keys (in case of encoding issues)
                if (variationValue === undefined) {
                    const allKeys = Object.keys(variation.attributes);
                    console.log(`    Trying to find matching key. Available keys:`, allKeys);

                    // Try exact match first
                    for (const key of allKeys) {
                        if (key === variationKey || decodeURIComponent(key) === variationKey || key === decodeURIComponent(variationKey)) {
                            variationValue = variation.attributes[key];
                            console.log(`    Found match with key: ${key}`);
                            break;
                        }
                    }

                    // If still not found, try to match by checking if any key contains the attribute name
                    if (variationValue === undefined && allKeys.length === 1) {
                        // If there's only one key, use it
                        const singleKey = allKeys[0];
                        variationValue = variation.attributes[singleKey];
                        console.log(`    Using single key: ${singleKey}`);
                    }
                }

                console.log(`  Attribute ${attrName}:`);
                console.log(`    selectedValue: ${selectedValue}`);
                console.log(`    variationKey (tried): ${variationKey}`);
                console.log(`    variationValue: ${variationValue}`);
                console.log(`    match: ${selectedValue === variationValue}`);

                // Must have some selected value
                if (!selectedValue) return false;

                // If the variation has no value for this attribute ('' / null / undefined),
                // treat it as a non-filtering attribute and don't block the match.
                // This handles cases where an attribute is defined on the product
                // but not actually set per-variation (e.g. size left empty / ANY in admin).
                if (variationValue === '' || variationValue === null || typeof variationValue === 'undefined') {
                    console.log(`    variationValue empty for ${attrName}, ignoring this attribute for matching`);
                    // Remember to ignore this attribute when sending data to server
                    if (!ignoredAttrsForMatch.includes(attrName)) {
                        ignoredAttrsForMatch.push(attrName);
                    }
                    return true;
                }

                // variation.attributes uses key WITHOUT 'pa_' prefix (PHP removes it)
                return variationValue === selectedValue;
            });

            console.log(`  Variation ${variation.id} matches:`, matches);
            return matches;
        });

        if (matchingVariation) {
            console.log('✅ Found matching variation:', matchingVariation.id);

            // Update price display
            const priceEl = state.popupElement.querySelector('.ed-product-popup__price-value');
            if (priceEl) {
                priceEl.innerHTML = matchingVariation.price_html;
            }

            // Store variation ID
            state.popupElement.dataset.variationId = matchingVariation.id;
            console.log('✅ Set variationId:', matchingVariation.id);

            // Store ignored attributes (those with empty variation values) for submit step
            if (ignoredAttrsForMatch.length > 0) {
                state.popupElement.dataset.ignoredAttributes = JSON.stringify(ignoredAttrsForMatch);
                console.log('✅ Ignored attributes for submit:', ignoredAttrsForMatch);
            } else {
                delete state.popupElement.dataset.ignoredAttributes;
            }

            // If get_weight_from_variation is enabled, store the variation weight and update ocwsu fields
            if (state.popupData.ocwsu?.get_weight_from_variation && matchingVariation.weight) {
                state.popupElement.dataset.variationWeight = matchingVariation.weight;
                console.log('✅ Set variationWeight:', matchingVariation.weight);
                // Update ocwsu fields immediately when variation changes (to update unitWeight)
                window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();
            }

            // Update stock status
            if (!matchingVariation.in_stock) {
                const addBtn = state.popupElement.querySelector('#popup-add-to-cart');
                if (addBtn) {
                    addBtn.disabled = true;
                    const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');
                    if (btnText) btnText.textContent = 'אזל מהמלאי';
                }
            } else {
                // Re-enable button if it was disabled
                const addBtn = state.popupElement.querySelector('#popup-add-to-cart');
                if (addBtn) {
                    addBtn.disabled = false;
                    validateAddToCartButton();
                }
            }
        } else {
            // No matching variation found
            console.log('❌ No matching variation found');
            console.log('Available variations:', state.popupData.variations.map(v => ({
                id: v.id,
                attributes: v.attributes
            })));
            state.popupElement.dataset.variationId = '';

            // Clear variation weight if get_weight_from_variation is enabled
            if (state.popupData.ocwsu?.get_weight_from_variation) {
                delete state.popupElement.dataset.variationWeight;
                window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();
            }

            // Clear ignored attributes list
            delete state.popupElement.dataset.ignoredAttributes;
        }

        console.groupEnd();
    }

    /**
     * Validate and enable/disable add to cart button
     */
    function validateAddToCartButton() {
        if (!state.popupElement) return;

        const addBtn = state.popupElement.querySelector('#popup-add-to-cart');
        if (!addBtn) return;

        // Check if product is in stock first
        if (!state.popupData || !state.popupData.in_stock) {
            addBtn.disabled = true;
            addBtn.classList.add('is-disabled');
            const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');
            if (btnText) btnText.textContent = 'אזל מהמלאי';
            return;
        }

        // For variable products, check if all attributes are selected
        if (state.popupData && state.popupData.type === 'variable' && state.popupData.attributes.length > 0) {
            const allAttributesSelected = state.popupData.attributes.every(attr => {
                return state.popupElement.querySelector(`input[name="attribute_${attr.name}"]:checked`);
            });

            if (!allAttributesSelected) {
                addBtn.disabled = true;
                addBtn.classList.add('is-disabled');
                return;
            }

            // Check if variation is in stock
            const variationId = state.popupElement.dataset.variationId;
            if (variationId) {
                const variation = state.popupData.variations.find(v => v.id == variationId);
                if (variation && !variation.in_stock) {
                    addBtn.disabled = true;
                    addBtn.classList.add('is-disabled');
                    return;
                }
            }
        }

        // Check if required options are selected (unit weight, etc.)
        const requiredGroups = state.popupElement.querySelectorAll('.ed-product-popup__radio-group[data-option]');
        let allSelected = true;

        requiredGroups.forEach(group => {
            const selected = group.querySelector('input[type="radio"]:checked');
            if (!selected) {
                allSelected = false;
            }
        });

        // Check quantity
        const qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
        const quantity = qtyInput ? parseFloat(qtyInput.value) : 0;
        const minQty = parseFloat(qtyInput?.min) || 1;

        if (allSelected && quantity >= minQty) {
            addBtn.disabled = false;
            addBtn.classList.remove('is-disabled');
            const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');
            if (btnText) btnText.textContent = state.popupData.isEditMode ? 'עדכן בסל' : 'הוסף לסל';
        } else {
            addBtn.disabled = true;
            addBtn.classList.add('is-disabled');
        }
    }

    // Expose functions to the global scope
    window.EDProductPopupVariations = {
        handleAttributeChange,
        updateVariationSelection,
        validateAddToCartButton
    };

})();

