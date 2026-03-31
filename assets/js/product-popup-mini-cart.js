/**
 * Product Popup Mini Cart Functions
 * Handles mini cart quantity controls, edit functionality, and cart item updates
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState; // Access shared state
    const core = window.EDProductPopupCore; // Access core functions like openPopup

    /** Serialize qty updates per line; rapid +/- resolves to last target (avoids stale AJAX overwrites). */
    const pendingQtyByCartKey = new Map();
    const qtyWorkerByCartKey = new Map();
 
    function isOcwsuUnitsQtyInput(input) {
        return input && input.getAttribute('data-ed-ocwsu-units-display') === '1';
    }

    /** Sold by weight with product unit "grams" — input shows grams; Woo cart qty is kg. */
    function isOcwsuGramCartQtyInput(input) {
        return input && input.getAttribute('data-ed-ocwsu-cart-qty-unit') === 'grams';
    }

    function edMiniCartQtyDebugEnabled() {
        try {
            return !!(window.ED_POPUP_CONFIG && window.ED_POPUP_CONFIG.debugCartQty)
                || window.localStorage.getItem('edDebugCartQty') === '1';
        } catch (err) {
            return false;
        }
    }

    function dbgMiniCartQty() {
        if (edMiniCartQtyDebugEnabled()) {
            const args = ['[ed-mini-cart qty]'].concat([].slice.call(arguments));
            console.log.apply(console, args);
        }
    }

    /** @returns {number|null} kg */
    function gramsDisplayToKg(gramsDisplay) {
        const v = parseFloat(String(gramsDisplay).replace(',', '.'));
        if (!isFinite(v)) {
            return null;
        }
        return parseFloat((v * 0.001).toFixed(6));
    }

    function kgFromOcwsuUnitsInput(input, units) {
        const kgPer = parseFloat(input.getAttribute('data-ed-ocwsu-kg-per-unit'));
        const u = parseFloat(String(units).replace(',', '.'));
        if (!isFinite(kgPer) || kgPer <= 0 || !isFinite(u)) {
            return null;
        }
        return parseFloat((u * kgPer).toFixed(6));
    }

    function setQtyInputDisplayValue(input, quantityKg) {
        if (!input) return;
        if (isFinite(quantityKg) && quantityKg <= 0) {
            input.value = '0';
            return;
        }
        if (isOcwsuUnitsQtyInput(input)) {
            const kgPer = parseFloat(input.getAttribute('data-ed-ocwsu-kg-per-unit'));
            if (isFinite(kgPer) && kgPer > 0 && isFinite(quantityKg)) {
                const units = quantityKg / kgPer;
                input.value = Math.max(1, Math.round(units));
                return;
            }
        }
        if (isOcwsuGramCartQtyInput(input) && isFinite(quantityKg)) {
            input.value = String(parseFloat((quantityKg * 1000).toFixed(6)));
            return;
        }
        input.value = quantityKg;
    }

    /**
     * Numeric step for +/- (Woo weighable lines use decimal qty + step="any" or small step).
     */
    function getQtyStep(input) {
        if (isOcwsuUnitsQtyInput(input)) {
            return 1;
        }
        const raw = input.getAttribute('step');
        if (!raw || raw === 'any') {
            return 0.1;
        }
        const n = parseFloat(raw);
        return !isFinite(n) || n <= 0 ? 0.1 : n;
    }

    /**
     * Snap typed weight qty to the next step multiple (ceil), e.g. step 0.5 + value 0.2 → 0.5.
     * Values already on the grid stay unchanged (within epsilon).
     */
    function snapWeightQtyToStepCeil(value, step, minNum) {
        if (!isFinite(value)) {
            return minNum;
        }
        if (!isFinite(step) || step <= 0 || !isFinite(minNum)) {
            return Math.max(minNum, value);
        }
        const eps = 1e-9;
        const ratio = value / step;
        const nearest = Math.round(ratio);
        if (Math.abs(ratio - nearest) < 1e-6) {
            let snapped = nearest * step;
            snapped = parseFloat(snapped.toFixed(10));
            return Math.max(minNum, snapped);
        }
        let snapped = Math.ceil(ratio - eps) * step;
        snapped = parseFloat(snapped.toFixed(10));
        if (snapped < minNum) {
            snapped = Math.ceil((minNum - eps) / step) * step;
            snapped = parseFloat(snapped.toFixed(10));
        }
        return Math.max(minNum, snapped);
    }

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
        const step = getQtyStep(input);
        const currentValue = parseFloat(String(input.value).replace(',', '.'));
        const min = parseFloat(input.min);
        const minNum = isFinite(min) ? min : 1;
        const base = isFinite(currentValue) ? currentValue : minNum;
        let newValue = base;

        if (action === 'increase') {
            newValue = Math.round((base + step) / step) * step;
            if (!isFinite(newValue)) {
                newValue = base + step;
            }
            newValue = parseFloat(newValue.toFixed(6));
        } else if (action === 'decrease') {
            let candidate = Math.round((base - step) / step) * step;
            candidate = parseFloat(candidate.toFixed(6));
            if (!isFinite(candidate)) {
                candidate = parseFloat((base - step).toFixed(6));
            }
            const eps = 1e-9;
            if (candidate < minNum - eps || candidate <= 0) {
                updateCartItemQuantity(cartItemKey, 0);
                return;
            }
            newValue = candidate;
        }

        if (newValue !== base) {
            // Optimistic display so rapid +/- does not re-read a stale input value
            if (isOcwsuUnitsQtyInput(input)) {
                input.value = String(Math.max(1, Math.round(newValue)));
            } else {
                input.value = String(newValue);
            }
            if (isOcwsuUnitsQtyInput(input)) {
                const kg = kgFromOcwsuUnitsInput(input, newValue);
                if (kg != null) {
                    dbgMiniCartQty('units line +/-', {newValueUnits: newValue, kg});
                    updateCartItemQuantity(cartItemKey, kg);
                }
            } else {
                const qtyKg = isOcwsuGramCartQtyInput(input) ? gramsDisplayToKg(newValue) : newValue;
                if (isOcwsuGramCartQtyInput(input)) {
                    dbgMiniCartQty('grams line +/-', {
                        action,
                        step,
                        baseDisplay: base,
                        newDisplayGrams: newValue,
                        qtyKgForWoo: qtyKg
                    });
                }
                if (qtyKg != null && isFinite(qtyKg)) {
                    updateCartItemQuantity(cartItemKey, qtyKg);
                }
            }
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

        const parsed = parseFloat(String(input.value).replace(',', '.'));
        const min = parseFloat(input.min);  
        const minNum = isFinite(min) ? min : 1;
 
        // Explicit 0 (or negative) removes the line — no debounce (avoid showing "0" in the field)
        if (isFinite(parsed) && parsed <= 0) {
            clearTimeout(input._updateTimeout);
            updateCartItemQuantity(cartItemKey, 0);
            return;
        }

        let newValue = isFinite(parsed) ? parsed : minNum;
        if (isOcwsuUnitsQtyInput(input)) {
            newValue = Math.max(minNum, Math.round(newValue));
        } else {
            newValue = snapWeightQtyToStepCeil(newValue, getQtyStep(input), minNum);
        }
        const finalValue = Math.max(minNum, newValue);

        const displayVal = String(parseFloat(finalValue.toFixed(6)));
        if (displayVal !== String(input.value).replace(',', '.').trim()) {
            input.value = displayVal;
        }

        // Debounce the update
        clearTimeout(input._updateTimeout);
        input._updateTimeout = setTimeout(() => {
            if (isOcwsuUnitsQtyInput(input)) {
                const kg = kgFromOcwsuUnitsInput(input, finalValue);
                if (kg != null) {
                    dbgMiniCartQty('units line change', {finalValue, kg});
                    updateCartItemQuantity(cartItemKey, kg);
                }
            } else {
                const qtyKg = isOcwsuGramCartQtyInput(input) ? gramsDisplayToKg(finalValue) : finalValue;
                if (isOcwsuGramCartQtyInput(input)) {
                    dbgMiniCartQty('grams line change', {finalDisplayGrams: finalValue, qtyKgForWoo: qtyKg});
                }
                if (qtyKg != null && isFinite(qtyKg)) {
                    updateCartItemQuantity(cartItemKey, qtyKg);
                }
            }
        }, 500);
    }

    /** Match ed_menu_products fadeOutBox (category switch on .ed-mp__link) */
    const ED_MP_BOX_TRANSITION = 'opacity 0.22s ease, transform 0.22s ease';

    /**
     * Same motion as category product box fade-out; resolves when transition ends (or fallback).
     */
    function startFloatCartRowRemovalAnimation(row) {
        return new Promise((resolve) => {
            let done = false;
            const complete = () => {
                if (done) return;
                done = true;
                row.removeEventListener('transitionend', onEnd);
                clearTimeout(fallbackId);
                resolve();
            };
            const onEnd = (e) => {
                if (e.target !== row || e.propertyName !== 'opacity') return;
                complete();
            };
            row.addEventListener('transitionend', onEnd);
            const fallbackId = setTimeout(complete, 350);

            requestAnimationFrame(() => {
                row.style.transition = ED_MP_BOX_TRANSITION;
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
            });
        });
    }

    function revertRemovingRow(state) {
        if (!state) return;
        const { row, opacity, transition, pointerEvents, transform } = state;
        row.style.transition = 'none';
        row.style.opacity = opacity;
        row.style.transform = transform;
        row.style.pointerEvents = pointerEvents;
        row.style.transition = transition;
        row.querySelectorAll('button, .ed-float-cart__qty-input').forEach((el) => {
            el.disabled = false;
        });
    }

    /**
     * Single AJAX update for one cart line (internal).
     */
    async function performCartQuantityUpdate(cartItemKey, quantity) {
        const isRemoval = isFinite(Number(quantity)) && Number(quantity) <= 0;
        let removeRowState = null;
        let removalAnimPromise = null;
        let qtyInput = null;
        let oldValue = null;

        try {
            const hitEl = document.querySelector(`[data-cart-item-key="${cartItemKey}"]`);
            const row = hitEl && typeof hitEl.closest === 'function'
                ? hitEl.closest('.ed-float-cart__item')
                : null;
            qtyInput = row ? row.querySelector('.ed-float-cart__qty-input') : null;

            if (qtyInput) {
                oldValue = qtyInput.value;
            }

            // Removing: Volt-style fade/slide like ed-mp category box — fragments apply after animation ends
            if (isRemoval && row) {
                removeRowState = {
                    row,
                    opacity: row.style.opacity,
                    transition: row.style.transition,
                    pointerEvents: row.style.pointerEvents,
                    transform: row.style.transform
                };
                row.style.pointerEvents = 'none';
                row.querySelectorAll('button, .ed-float-cart__qty-input').forEach((el) => {
                    el.disabled = true;
                });
                removalAnimPromise = startFloatCartRowRemovalAnimation(row);
            } else if (qtyInput) {
                setQtyInputDisplayValue(qtyInput, quantity);
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
                revertRemovingRow(removeRowState);
                removeRowState = null;
                if (qtyInput) {
                    qtyInput.value = oldValue;
                }
                console.error('Failed to update cart item');
                return;
            }

            const result = await response.json();

            if (!result.success || result.data?.error) {
                revertRemovingRow(removeRowState);
                removeRowState = null;
                if (qtyInput) {
                    qtyInput.value = oldValue;
                }
                console.error('Error updating cart:', result.data?.errorMessage || 'Unknown error');
                return;
            }

            if (removalAnimPromise) {
                await removalAnimPromise;
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

                const headerFragKey = '#ed-float-cart header.ed-float-cart__header';
                if (fragments[headerFragKey] && typeof jQuery !== 'undefined') {
                    const headerEl = document.querySelector(headerFragKey);
                    if (headerEl && String(fragments[headerFragKey]).length) {
                        jQuery(headerEl).replaceWith(fragments[headerFragKey]);
                    }
                }

                // Fragment values are full outer HTML (e.g. entire .ed-float-cart__items wrapper) — replace nodes, do not nest via innerHTML.
                if (fragments['div.ed-float-cart__items'] && typeof jQuery !== 'undefined') {
                    const miniCartItems = document.querySelector('#ed-float-cart .ed-float-cart__items');
                    if (miniCartItems) {
                        jQuery(miniCartItems).replaceWith(fragments['div.ed-float-cart__items']);
                    }
                }

                if (fragments['div.ed-float-cart__totals'] && typeof jQuery !== 'undefined') {
                    const totalsEl = document.querySelector('#ed-float-cart .ed-float-cart__totals');
                    if (totalsEl && fragments['div.ed-float-cart__totals'].length) {
                        jQuery(totalsEl).replaceWith(fragments['div.ed-float-cart__totals']);
                    }
                }

                const checkoutActionsSel = '#ed-float-cart .ed-float-cart__actions';
                if (fragments[checkoutActionsSel] && typeof jQuery !== 'undefined') {
                    const actionsEl = document.querySelector(checkoutActionsSel);
                    if (actionsEl && String(fragments[checkoutActionsSel]).length) {
                        jQuery(actionsEl).replaceWith(fragments[checkoutActionsSel]);
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
            revertRemovingRow(removeRowState);
            if (qtyInput) {
                qtyInput.value = oldValue;
            }
        }
    }

    async function drainPendingQtyWorker(cartItemKey) {
        try {
            while (pendingQtyByCartKey.has(cartItemKey)) {
                const q = pendingQtyByCartKey.get(cartItemKey);
                pendingQtyByCartKey.delete(cartItemKey);
                await performCartQuantityUpdate(cartItemKey, q);
            }
        } finally {
            qtyWorkerByCartKey.delete(cartItemKey);
        }
    }

    /**
     * Update cart item quantity (queues rapid clicks per line so last target wins).
     */
    async function updateCartItemQuantity(cartItemKey, quantity) {
        const isRemoval = isFinite(Number(quantity)) && Number(quantity) <= 0;

        if (isRemoval) {
            pendingQtyByCartKey.delete(cartItemKey);
            const inFlight = qtyWorkerByCartKey.get(cartItemKey);
            if (inFlight) {
                await inFlight.catch(() => {});
            }
            await performCartQuantityUpdate(cartItemKey, quantity);
            return;
        }

        pendingQtyByCartKey.set(cartItemKey, quantity);
        if (!qtyWorkerByCartKey.has(cartItemKey)) {
            const run = drainPendingQtyWorker(cartItemKey);
            qtyWorkerByCartKey.set(cartItemKey, run);
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
     * OCWSU product stores min/step in grams when meta product_weight_units is grams.
     */
    function ocwsuPopupWeightIsGrams(ocwsu) {
        const u = String((ocwsu && ocwsu.product_weight_units) || '').toLowerCase();
        return u === 'grams' || u.indexOf('gram') !== -1;
    }

    /**
     * Value to show in #popup-quantity-weight when editing from cart (same convention as mini cart input).
     */
    function popupEditWeightDisplayValue(ocwsu, cartItemData) {
        const wcKgRaw = parseFloat(cartItemData.quantity);
        const wcKg = isFinite(wcKgRaw) ? wcKgRaw : 0;
        const wLineRaw = parseFloat(cartItemData.ocwsu_quantity_in_weight_units);
        const wLine = isFinite(wLineRaw) && wLineRaw > 0 ? wLineRaw : 0;
        if (ocwsuPopupWeightIsGrams(ocwsu)) {
            if (wLine > 0) {
                return wLine;
            }
            return parseFloat((wcKg * 1000).toFixed(6));
        }
        if (wLine > 0) {
            return wLine;
        }
        return wcKg;
    }

    /**
     * Pre-fill popup with cart item data
     */
    function prefillPopupWithCartData(cartItemData) {
        if (!state.popupElement || !cartItemData) return;

        // Quantity: WC line qty is kg for weighable; units UI must use ocwsu_quantity_in_units (e.g. 23 יח').
        const ocwsu = state.popupData.ocwsu || {};
        const unitsInCart = parseFloat(cartItemData.ocwsu_quantity_in_units) || 0;
        const weightLineQty = parseFloat(cartItemData.ocwsu_quantity_in_weight_units) || 0;
        const wcQtyRaw = parseFloat(cartItemData.quantity);
        const wcQty = isFinite(wcQtyRaw) ? wcQtyRaw : 0;

        const unitsInput = state.popupElement.querySelector('#popup-quantity-units');
        const weightInput = state.popupElement.querySelector('#popup-quantity-weight');
        const simpleInput = state.popupElement.querySelector('#popup-quantity');
        const hasToggle = ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight;
        const weightDisplayEdit = popupEditWeightDisplayValue(ocwsu, cartItemData);

        if (ocwsu.weighable && (ocwsu.sold_by_units || ocwsu.sold_by_weight)) {
            let wuForDataset = weightLineQty;
            if (ocwsuPopupWeightIsGrams(ocwsu) && (!wuForDataset || wuForDataset <= 0) && wcQty > 0) {
                wuForDataset = parseFloat((wcQty * 1000).toFixed(6));
            }
            state.popupElement.dataset.ocwsuQuantityInUnits = String(unitsInCart || 0);
            state.popupElement.dataset.ocwsuQuantityInWeightUnits = String(wuForDataset || 0);
        }

        if (hasToggle) {
            if (unitsInCart > 0) {
                window.EDProductPopupQuantity?.setQuantityMode('units');
                if (unitsInput) {
                    unitsInput.value = String(unitsInCart);
                    unitsInput.dispatchEvent(new Event('change', {bubbles: true}));
                }
                if (weightInput && weightDisplayEdit > 0) {
                    weightInput.value = String(weightDisplayEdit);
                }
            } else {
                window.EDProductPopupQuantity?.setQuantityMode('weight');
                if (weightInput && weightDisplayEdit > 0) {
                    weightInput.value = String(weightDisplayEdit);
                    weightInput.dispatchEvent(new Event('change', {bubbles: true}));
                }
            }
        } else if (unitsInput && !weightInput) {
            if (unitsInCart > 0) {
                unitsInput.value = String(unitsInCart);
            }
            unitsInput.dispatchEvent(new Event('change', {bubbles: true}));
        } else if (weightInput && !unitsInput && weightDisplayEdit > 0) {
            weightInput.value = String(weightDisplayEdit);
            weightInput.dispatchEvent(new Event('change', {bubbles: true}));
        } else if (simpleInput) {
            const v = wcQty > 0 ? wcQty : 1;
            simpleInput.value = String(v);
            simpleInput.dispatchEvent(new Event('change', {bubbles: true}));
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

