/**
 * Product Popup Mini Cart Functions
 * Handles mini cart quantity controls, edit functionality, and cart item updates
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState; // Access shared state
    const core = window.EDProductPopupCore; // Access core functions like openPopup

    /**
     * Handle mini cart quantity button clicks (plus/minus)
     */
    function handleMiniCartQuantityClick(e) {
        const btn = e.target.closest('.ed-float-cart__qty-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const cartItemKey = btn.dataset.cartItemKey;
        if (!cartItemKey) return;

        const input = btn.closest('.ed-float-cart__quantity-controls')?.querySelector('.ed-float-cart__qty-input');
        if (!input) return;

        const action = btn.classList.contains('ed-float-cart__qty-btn--increase') ? 'increase' : 'decrease';
        const currentValue = parseFloat(input.value) || 1;
        const min = parseFloat(input.min) || 1;
        let newValue = currentValue;

        if (action === 'increase') {
            newValue = currentValue + 1;
        } else if (action === 'decrease' && currentValue > min) {
            newValue = Math.max(min, currentValue - 1);
        }

        if (newValue !== currentValue) {
            updateCartItemQuantity(cartItemKey, newValue);
        }
    }

    /**
     * Handle mini cart quantity input changes
     */
    function handleMiniCartQuantityChange(e) {
        const input = e.target.closest('.ed-float-cart__qty-input');
        if (!input || !input.classList.contains('ed-float-cart__qty-input')) return;

        const cartItemKey = input.dataset.cartItemKey;
        if (!cartItemKey) return;

        const newValue = parseFloat(input.value) || 1;
        const min = parseFloat(input.min) || 1;
        const finalValue = Math.max(min, newValue);

        if (finalValue !== newValue) {
            input.value = finalValue;
        }

        // Debounce the update
        clearTimeout(input._updateTimeout);
        input._updateTimeout = setTimeout(() => {
            updateCartItemQuantity(cartItemKey, finalValue);
        }, 500);
    }

    /**
     * Update cart item quantity
     */
    async function updateCartItemQuantity(cartItemKey, quantity) {
        try {
            // Find the input element and update it immediately (optimistic update)
            const cartItem = document.querySelector(`[data-cart-item-key="${cartItemKey}"]`);
            const qtyInput = cartItem?.querySelector('.ed-float-cart__qty-input');
            let oldValue = null;

            if (qtyInput) {
                oldValue = qtyInput.value;
                qtyInput.value = quantity;
                qtyInput.disabled = true; // Disable during update
            }

            // Use our AJAX endpoint for updating cart (better session handling)
            const ajaxUrl = window.ED_POPUP_CONFIG?.updateCartAjaxUrl || '/wp-admin/admin-ajax.php';

            const formData = new FormData();
            formData.append('action', 'ed_update_cart');
            formData.append('nonce', window.ED_POPUP_CONFIG?.updateCartNonce || '');
            formData.append('cart_item_key', cartItemKey);
            formData.append('quantity', quantity);

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            if (!response.ok) {
                // Revert on error
                if (qtyInput) {
                    qtyInput.value = oldValue;
                    qtyInput.disabled = false;
                }
                console.error('Failed to update cart item');
                return;
            }

            const result = await response.json();

            if (!result.success || result.data?.error) {
                // Revert on error
                if (qtyInput) {
                    qtyInput.value = oldValue;
                    qtyInput.disabled = false;
                }
                console.error('Error updating cart:', result.data?.errorMessage || 'Unknown error');
                return;
            }

            // Re-enable input
            if (qtyInput) {
                qtyInput.disabled = false;
            }

            // Update cart fragments using WooCommerce method
            const fragments = result.data?.fragments || {};
            if (fragments && typeof fragments === 'object') {
                // Update cart count in header
                if (fragments['span.ed-float-cart__count']) {
                    const countEl = document.querySelector('.ed-float-cart__count');
                    if (countEl) {
                        countEl.outerHTML = fragments['span.ed-float-cart__count'];
                    } else {
                        // If count element doesn't exist, try to add it to the title
                        const titleEl = document.querySelector('.ed-float-cart__title');
                        if (titleEl && fragments['span.ed-float-cart__count']) {
                            titleEl.insertAdjacentHTML('beforeend', fragments['span.ed-float-cart__count']);
                        }
                    }
                }

                // Update mini cart HTML
                if (fragments['div.ed-float-cart__items']) {
                    const miniCartItems = document.querySelector('.ed-float-cart__items');
                    if (miniCartItems) {
                        miniCartItems.innerHTML = fragments['div.ed-float-cart__items'];
                    }
                }

                // Update totals
                if (fragments['div.ed-float-cart__totals']) {
                    const totalsEl = document.querySelector('.ed-float-cart__totals');
                    if (totalsEl) {
                        totalsEl.innerHTML = fragments['div.ed-float-cart__totals'];
                    }
                }

                // Also update the row directly if exists
                if (fragments['div.ed-float-cart__row']) {
                    const rowEl = document.querySelector('.ed-float-cart__row');
                    if (rowEl) {
                        rowEl.outerHTML = fragments['div.ed-float-cart__row'];
                    }
                }

                // Update cart count badge if exists
                if (fragments['div.widget_shopping_cart_content']) {
                    const cartWidget = document.querySelector('.widget_shopping_cart_content');
                    if (cartWidget) {
                        cartWidget.innerHTML = fragments['div.widget_shopping_cart_content'];
                    }
                }

                // Trigger WooCommerce cart updated event
                if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
                    jQuery('body').trigger('wc_fragment_refresh');
                    jQuery('body').trigger('updated_wc_div');
                }
            }

        } catch (error) {
            console.error('Error updating cart item quantity:', error);

            // Revert on error
            const cartItem = document.querySelector(`[data-cart-item-key="${cartItemKey}"]`);
            const qtyInput = cartItem?.querySelector('.ed-float-cart__qty-input');
            if (qtyInput) {
                qtyInput.disabled = false;
            }
        }
    }

    /**
     * Handle mini cart edit button click
     */
    async function handleMiniCartEditClick(e) {
        const editBtn = e.target.closest('.ed-float-cart__edit-btn');
        if (!editBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const cartItemKey = editBtn.dataset.cartItemKey;
        const productId = editBtn.dataset.productId;

        if (!cartItemKey || !productId) return;

        try {
            // Extract cart item data directly from HTML data attributes
            // No need for AJAX - all data is already in the page
            const cartItemData = {
                cart_item_key: cartItemKey,
                product_id: parseInt(productId),
                variation_id: parseInt(editBtn.dataset.variationId || 0),
                quantity: parseFloat(editBtn.dataset.quantity || 1),
                variation: editBtn.dataset.variation ? JSON.parse(editBtn.dataset.variation) : {},
                product_note: editBtn.dataset.productNote || '',
                ocwsu_quantity_in_units: parseFloat(editBtn.dataset.ocwsuQuantityInUnits || 0),
                ocwsu_quantity_in_weight_units: parseFloat(editBtn.dataset.ocwsuQuantityInWeightUnits || 0),
            };

            // Open popup with product data and cart item data
            await openPopupForEdit(parseInt(productId), cartItemData);

        } catch (error) {
            console.error('Error opening popup for edit:', error);
        }
    }

    /**
     * Open popup for editing cart item
     */
    async function openPopupForEdit(productId, cartItemData) {
        try {
            // Get product data
            const response = await fetch(`${window.ED_POPUP_CONFIG?.endpoint || '/wp-json/ed/v1/product-popup'}?id=${productId}`);
            if (!response.ok) throw new Error('Failed to load product');

            state.popupData = await response.json();

            // Store cart item data for later use
            state.popupElement = null;
            state.popupData.cartItemKey = cartItemData.cart_item_key || null;
            state.popupData.cartItemData = cartItemData;
            state.popupData.isEditMode = true;

            // Create popup HTML
            const popupHTML = await window.EDProductPopupRender?.fetchPopupHTML(state.popupData);

            // Remove existing popup if any
            const existing = document.getElementById('ed-product-popup');
            if (existing) existing.remove();

            // Add to body
            document.body.insertAdjacentHTML('beforeend', popupHTML);
            state.popupElement = document.getElementById('ed-product-popup');

            // Initialize quantity inputs
            window.EDProductPopupQuantity?.initQuantityInputs();

            // Pre-fill with cart item data
            prefillPopupWithCartData(cartItemData);

            // Initialize variation selection if variable product
            if (state.popupData.type === 'variable' && state.popupData.attributes.length > 0) {
                setTimeout(() => {
                    window.EDProductPopupVariations?.updateVariationSelection();
                    window.EDProductPopupVariations?.validateAddToCartButton();
                }, 50);
            }

            // Show popup
            setTimeout(() => {
                state.popupElement.classList.add('is-open');
                document.body.classList.add('popup-open');
                state.isOpen = true;

                // Focus management
                const closeBtn = state.popupElement.querySelector('.ed-product-popup__close');
                if (closeBtn) closeBtn.focus();
            }, 10);

        } catch (error) {
            console.error('Error opening popup for edit:', error);
        }
    }

    /**
     * Pre-fill popup with cart item data
     */
    function prefillPopupWithCartData(cartItemData) {
        if (!state.popupElement || !cartItemData) return;

        // Set quantity
        const qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
        if (qtyInput && cartItemData.quantity) {
            qtyInput.value = cartItemData.quantity;
            qtyInput.dispatchEvent(new Event('change', {bubbles: true}));
        }

        // Set variation if exists
        if (cartItemData.variation_id && state.popupData.type === 'variable') {
            state.popupElement.dataset.variationId = cartItemData.variation_id;

            // Set variation attributes
            if (cartItemData.variation && typeof cartItemData.variation === 'object') {
                Object.keys(cartItemData.variation).forEach(attrKey => {
                    const attrValue = cartItemData.variation[attrKey];
                    const radio = state.popupElement.querySelector(`input[name="attribute_${attrKey}"][value="${attrValue}"]`);
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            }
        }

        // Set product note
        const noteInput = state.popupElement.querySelector('#popup-product-note');
        if (noteInput && cartItemData.product_note) {
            noteInput.value = cartItemData.product_note;
        }

        // Set ocwsu data if exists
        if (cartItemData.ocwsu_quantity_in_units > 0 || cartItemData.ocwsu_quantity_in_weight_units > 0) {
            // Store ocwsu data in popup element for later use
            state.popupElement.dataset.ocwsuQuantityInUnits = cartItemData.ocwsu_quantity_in_units || 0;
            state.popupElement.dataset.ocwsuQuantityInWeightUnits = cartItemData.ocwsu_quantity_in_weight_units || 0;

            // If product has ocwsu toggle, set the correct mode
            const ocwsu = state.popupData.ocwsu || {};
            if (ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight) {
                // Determine mode based on which quantity is set
                if (cartItemData.ocwsu_quantity_in_units > 0) {
                    state.popupElement.dataset.quantityMode = 'units';
                    const unitsInput = state.popupElement.querySelector('#popup-quantity-units');
                    if (unitsInput) {
                        unitsInput.value = cartItemData.ocwsu_quantity_in_units;
                        unitsInput.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                } else if (cartItemData.ocwsu_quantity_in_weight_units > 0) {
                    state.popupElement.dataset.quantityMode = 'weight';
                    const weightInput = state.popupElement.querySelector('#popup-quantity-weight');
                    if (weightInput) {
                        weightInput.value = cartItemData.ocwsu_quantity_in_weight_units;
                        weightInput.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                }
            }
        }

        // Sync unit label from toggle (יח' / ק"ג)
        window.EDProductPopupQuantity?.syncQuantityLabelsFromToggle();

        // Update ocwsu fields
        window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();
        window.EDProductPopupVariations?.validateAddToCartButton();
    }

    // Expose functions to the global scope
    window.EDProductPopupMiniCart = {
        handleMiniCartQuantityClick,
        handleMiniCartQuantityChange,
        handleMiniCartEditClick,
        updateCartItemQuantity,
        openPopupForEdit,
        prefillPopupWithCartData
    };

})();

