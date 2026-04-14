/**
 * Product Popup Cart Functions
 * Handles add to cart, animations, error messages, and quantity badges
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState; // Access shared state

    /** Rapid clicks: queue multiple "add" actions instead of blocking until one request finishes */
    let pendingAddToCartCount = 0;
    let addToCartActivePromise = null;

    /**
     * Build FormData for add-to-cart from the current popup DOM (call right before each request).
     */
    function buildAddToCartFormData() {
        const formData = new FormData();
        const variationId = state.popupElement.dataset.variationId;

        if (state.popupData.type === 'variable') {
            if (!variationId) {
                return null;
            }
            formData.append('variation_id', variationId);
            formData.append('product_id', state.popupData.id);

            const selectedAttributes = state.popupElement.querySelectorAll('input[name^="attribute_"]:checked');
            if (selectedAttributes.length === 0) {
                return null;
            }

            let ignoredAttrs = [];
            if (state.popupElement.dataset.ignoredAttributes) {
                try {
                    ignoredAttrs = JSON.parse(state.popupElement.dataset.ignoredAttributes) || [];
                } catch (e) {
                    ignoredAttrs = [];
                }
            }

            selectedAttributes.forEach(radio => {
                const baseName = radio.name.replace(/^attribute_/, '');
                if (ignoredAttrs.includes(baseName)) {
                    return;
                }
                formData.append(radio.name, radio.value);
            });
        } else {
            formData.append('product_id', state.popupData.id);
        }

        if (window.wc_add_to_cart_params?.wc_add_to_cart_nonce) {
            formData.append('wc_add_to_cart_nonce', window.wc_add_to_cart_params.wc_add_to_cart_nonce);
        }

        window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();

        const canToggle = state.popupData.ocwsu?.weighable && state.popupData.ocwsu?.sold_by_units && state.popupData.ocwsu?.sold_by_weight;
        const currentMode = state.popupElement.dataset.quantityMode || (state.popupData.ocwsu?.sold_by_units ? 'units' : 'weight');
        let qtyInput = null;
        if (canToggle) {
            qtyInput = currentMode === 'units'
                ? state.popupElement.querySelector('#popup-quantity-units')
                : state.popupElement.querySelector('#popup-quantity-weight');
        } else {
            qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
        }

        const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
        const ocwsu = state.popupData.ocwsu || {};
        let quantityToSend = quantity;
        if (ocwsu.weighable) {
            quantityToSend = parseFloat(state.popupElement.dataset.quantityInKg || quantity);
        }

        formData.append('quantity', quantityToSend);
        formData.append('ocwsu_unit', state.popupElement.dataset.ocwsuUnit || 'unit');
        formData.append('ocwsu_unit_weight', state.popupElement.dataset.ocwsuUnitWeight || '0');
        formData.append('ocwsu_quantity_in_units', state.popupElement.dataset.ocwsuQuantityInUnits || '0');
        formData.append('ocwsu_quantity_in_weight_units', state.popupElement.dataset.ocwsuQuantityInWeightUnits || '0');

        const productNote = state.popupElement.querySelector('#popup-product-note');
        if (productNote && productNote.value.trim()) {
            formData.append('product_note', productNote.value.trim());
        }

        return formData;
    }

    function formDataToJsonRequest(formData) {
        const requestData = {};
        for (const [key, value] of formData.entries()) {
            requestData[key] = value;
        }
        return requestData;
    }

    /**
     * Coalesce N identical "add this line" actions into one request (same UX as N fast clicks).
     */
    function applyAddToCartBatchMultiplier(requestData, n) {
        if (!requestData || n <= 1) return;
        const scale = (key) => {
            if (!(key in requestData)) return;
            const v = parseFloat(requestData[key]);
            if (!isFinite(v)) return;
            requestData[key] = String(parseFloat((v * n).toFixed(6)));
        };
        scale('quantity');
        scale('ocwsu_quantity_in_units');
        scale('ocwsu_quantity_in_weight_units');
    }

    /**
     * One add-to-cart HTTP call; throws on failure; returns parsed JSON body.
     */
    async function addToCartFetchOnce(requestData) {
        const ajaxUrl = window.ED_POPUP_CONFIG?.addToCartUrl ||
            window.wc_add_to_cart_params?.wc_ajax_url?.toString().replace('%%endpoint%%', 'add_to_cart') ||
            '/?wc-ajax=add_to_cart';

        if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.trigger) {
            jQuery('body').trigger('adding_to_cart');
            jQuery('body').trigger('orak_adding_to_cart');
        }

        const response = await fetch(ajaxUrl, {
            method: 'POST',
            body: JSON.stringify(requestData),
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.ED_POPUP_CONFIG?.restNonce || '',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            let errorData = null;
            try {
                const errorText = await response.text();
                errorData = JSON.parse(errorText);
            } catch (e) {
                /* ignore */
            }

            const errorMessage = errorData?.errorMessage ||
                (errorData?.notices && errorData.notices.length > 0 ?
                    errorData.notices.map(n => n.notice || n).join(' ') :
                    `שגיאה בהוספה לסל: ${response.status} ${response.statusText}`);

            throw new Error(errorMessage);
        }

        const result = await response.json();

        if (result.error) {
            const errorMessage = result.errorMessage ||
                (result.notices && result.notices.length > 0 ?
                    result.notices.map(n => (typeof n === 'string' ? n : (n.notice || ''))).filter(m => m).join(' ') :
                    (typeof result.error === 'string' ? result.error : 'שגיאה לא ידועה'));

            throw new Error(errorMessage);
        }

        return result;
    }

    /**
     * מסנכרן את סרגל הסל התחתון לפי cart_count מהשרת — מציג מיד כשיש פריטים (גיבוי אם ה-HTML מ-fragments לא הוחל).
     */
    function syncEdBasketBarFromCartCount(result) {
        if (!result) return;
        if (result.error === true) return;
        const raw = result.cart_count != null ? result.cart_count : result.data?.cart_count;
        if (raw == null) return;
        const count = typeof raw === 'number' ? raw : parseInt(String(raw), 10);
        if (!Number.isFinite(count) || count < 0) return;
        const bar = document.getElementById('ed-basket-bar');
        if (!bar) return;
        if (count > 0) {
            bar.classList.add('basket-btn-active');
            const countEl = bar.querySelector('.ed-basket-bar__count');
            if (countEl) {
                countEl.textContent = String(count);
            }
        } else {
            bar.classList.remove('basket-btn-active');
            const countEl = bar.querySelector('.ed-basket-bar__count');
            if (countEl) {
                countEl.textContent = '0';
            }
        }
    }

    function applyAddToCartFragments(result) {
        if (result.fragments && typeof result.fragments === 'object') {
            const floatHeaderSel = '#ed-float-cart header.ed-float-cart__header';
            if (result.fragments[floatHeaderSel] && typeof jQuery !== 'undefined') {
                const headerEl = document.querySelector(floatHeaderSel);
                if (headerEl && String(result.fragments[floatHeaderSel]).length) {
                    jQuery(headerEl).replaceWith(result.fragments[floatHeaderSel]);
                }
            }
            const freeShipWrapSel = '#ed-float-cart .ed-float-cart__header-shipping';
            if (result.fragments[freeShipWrapSel] && typeof jQuery !== 'undefined') {
                const shipWrap = document.querySelector(freeShipWrapSel);
                if (shipWrap && String(result.fragments[freeShipWrapSel]).length) {
                    jQuery(shipWrap).replaceWith(result.fragments[freeShipWrapSel]);
                }
            }
            const checkoutActionsSel = '#ed-float-cart .ed-float-cart__actions';
            if (result.fragments[checkoutActionsSel] && typeof jQuery !== 'undefined') {
                const actionsEl = document.querySelector(checkoutActionsSel);
                if (actionsEl && String(result.fragments[checkoutActionsSel]).length) {
                    jQuery(actionsEl).replaceWith(result.fragments[checkoutActionsSel]);
                }
            }
            if (result.fragments['span.ed-float-cart__count']) {
                const countEl = document.querySelector('.ed-float-cart__count');
                if (countEl) {
                    countEl.outerHTML = result.fragments['span.ed-float-cart__count'];
                } else {
                    const titleEl = document.querySelector('.ed-float-cart__title');
                    if (titleEl && result.fragments['span.ed-float-cart__count']) {
                        titleEl.insertAdjacentHTML('beforeend', result.fragments['span.ed-float-cart__count']);
                    }
                }
            }

            const ocwsChipKey = 'div#ocws-delivery-data-chip';
            if (result.fragments[ocwsChipKey] && typeof jQuery !== 'undefined') {
                const chipEl = document.querySelector(ocwsChipKey);
                if (chipEl && String(result.fragments[ocwsChipKey]).trim().length) {
                    jQuery(chipEl).replaceWith(result.fragments[ocwsChipKey]);
                }
            }

            const basketBarSel = '#ed-basket-bar';
            if (result.fragments[basketBarSel] && typeof jQuery !== 'undefined') {
                const barEl = document.querySelector(basketBarSel);
                if (barEl && String(result.fragments[basketBarSel]).trim().length) {
                    jQuery(barEl).replaceWith(result.fragments[basketBarSel]);
                }
            }

            if (window.updateCartFragments) {
                window.updateCartFragments(result.fragments);
            } else if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
                jQuery('body').trigger('wc_fragment_refresh');
                jQuery('body').trigger('added_to_cart', [result.fragments, '', '', '']);
            }
        }
        syncEdBasketBarFromCartCount(result);
    }

    /**  
     * Handle add to cart
     */
    async function handleAddToCart(e) {
        const addBtn = e.target.closest('#popup-add-to-cart');
        if (!addBtn) return;

        e.preventDefault();

        if (!state.popupElement || !state.popupData) return;

        // Validate options again
        const requiredGroups = state.popupElement.querySelectorAll('.ed-product-popup__radio-group[data-option]');
        let missingOptions = [];

        requiredGroups.forEach(group => {
            const selected = group.querySelector('input[type="radio"]:checked');
            if (!selected) {
                missingOptions.push(group.dataset.option);
            }
        });

        if (missingOptions.length > 0) {
            const errorEl = state.popupElement.querySelector('#popup-option-error');
            if (errorEl) {
                errorEl.style.display = 'block';
                errorEl.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }
            return;
        }

        // Check stock before sending request
        if (!state.popupData || !state.popupData.in_stock) {
            const productName = state.popupData?.name || 'המוצר';
            showPopupError(`לא ניתן להוסיף את "${productName}" לסל הקניות - המוצר אזל מהמלאי.`);
            return;
        }

        // For variable products, MUST have variation selected
        if (state.popupData.type === 'variable') {
            console.group('🔍 [DEBUG] handleAddToCart - Variable Product Check');
            console.log('popupData.type:', state.popupData.type);
            console.log('popupData.attributes:', state.popupData.attributes);

            const variationId = state.popupElement.dataset.variationId;
            console.log('Current variationId:', variationId);

            // Check selected attributes
            const selectedAttributes = state.popupElement.querySelectorAll('input[name^="attribute_"]:checked');
            console.log('Selected attributes count:', selectedAttributes.length);
            selectedAttributes.forEach(radio => {
                console.log(`  ${radio.name} = ${radio.value}`);
            });

            // Check if variation is selected
            if (!variationId) {
                console.log('⚠️ No variationId, trying to update...');
                // Try to update variation selection one more time
                window.EDProductPopupVariations?.updateVariationSelection();
                const updatedVariationId = state.popupElement.dataset.variationId;
                console.log('Updated variationId:', updatedVariationId);

                if (!updatedVariationId) {
                    console.error('❌ Still no variationId after update');
                    console.groupEnd();
                    showPopupError('נא לבחור את כל האפשרויות הנדרשות');
                    return;
                }
            }

            console.log('✅ Variation ID found:', variationId || state.popupElement.dataset.variationId);
            console.groupEnd();

            // Check variation stock
            if (variationId) {
                const variation = state.popupData.variations.find(v => v.id == variationId);
                if (variation && !variation.in_stock) {
                    const productName = state.popupData.name || 'המוצר';
                    showPopupError(`לא ניתן להוסיף את "${productName}" לסל הקניות - המוצר אזל מהמלאי.`);
                    return;
                }
            }
        }

        const isEditMode = state.popupData.isEditMode && state.popupData.cartItemKey;

        if (isEditMode) {
            try {
                const removeUrl = window.wc_add_to_cart_params?.wc_ajax_url?.toString().replace('%%endpoint%%', 'remove_from_cart') ||
                    '/?wc-ajax=remove_from_cart';

                const removeFormData = new FormData();
                removeFormData.append('cart_item_key', state.popupData.cartItemKey);

                const removeResponse = await fetch(removeUrl, {
                    method: 'POST',
                    body: removeFormData,
                    credentials: 'same-origin'
                });

                if (!removeResponse.ok) {
                    console.warn('Failed to remove old cart item, continuing anyway...');
                }
            } catch (error) {
                console.warn('Error removing old cart item:', error);
            }
        }

        function logAddToCartDebug(formData) {
            const canToggle = state.popupData.ocwsu?.weighable && state.popupData.ocwsu?.sold_by_units && state.popupData.ocwsu?.sold_by_weight;
            const currentMode = state.popupElement.dataset.quantityMode || (state.popupData.ocwsu?.sold_by_units ? 'units' : 'weight');
            let qtyInput = null;
            if (canToggle) {
                qtyInput = currentMode === 'units'
                    ? state.popupElement.querySelector('#popup-quantity-units')
                    : state.popupElement.querySelector('#popup-quantity-weight');
            } else {
                qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
            }
            const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;

            console.group('🔍 [DEBUG] Add to Cart Request');
            console.log('📦 Product Data:', {
                productId: state.popupData.id,
                productType: state.popupData.type,
                variationId: state.popupElement.dataset.variationId || 'none',
                quantity: quantity,
                ocwsu: {
                    unit: state.popupElement.dataset.ocwsuUnit,
                    unitWeight: state.popupElement.dataset.ocwsuUnitWeight,
                    quantityInUnits: state.popupElement.dataset.ocwsuQuantityInUnits,
                    quantityInWeightUnits: state.popupElement.dataset.ocwsuQuantityInWeightUnits,
                    quantityInKg: state.popupElement.dataset.quantityInKg
                }
            });
            const formDataEntries = {};
            for (const [key, value] of formData.entries()) {
                formDataEntries[key] = value;
            }
            console.log('📋 FormData:', formDataEntries);
            console.groupEnd();
        }

        function displayQtyForBadge() {
            const canToggle = state.popupData.ocwsu?.weighable && state.popupData.ocwsu?.sold_by_units && state.popupData.ocwsu?.sold_by_weight;
            const currentMode = state.popupElement.dataset.quantityMode || (state.popupData.ocwsu?.sold_by_units ? 'units' : 'weight');
            let qtyInput = null;
            if (canToggle) {
                qtyInput = currentMode === 'units'
                    ? state.popupElement.querySelector('#popup-quantity-units')
                    : state.popupElement.querySelector('#popup-quantity-weight');
            } else {
                qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
            }
            const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
            return state.popupData.ocwsu?.sold_by_units ?
                state.popupElement.dataset.ocwsuQuantityInUnits || quantity :
                (state.popupData.ocwsu?.sold_by_weight ?
                    state.popupElement.dataset.ocwsuQuantityInWeightUnits || quantity :
                    quantity);
        }

        async function finishAddToCartSuccess() {
            await animateImageToCart();
            if (window.EDProductPopupCore?.closePopup) {
                window.EDProductPopupCore.closePopup();
            }
            showQuantityBadge(state.popupData.id, displayQtyForBadge());
        }

        if (isEditMode) {
            addBtn.classList.add('is-loading');
            try {
                const formData = buildAddToCartFormData();
                if (!formData) {
                    showPopupError('נא לבחור את כל האפשרויות הנדרשות');
                    return;
                }
                logAddToCartDebug(formData);
                const result = await addToCartFetchOnce(formDataToJsonRequest(formData));
                console.log('✅ Result (Full):', JSON.stringify(result, null, 2));
                applyAddToCartFragments(result);
                await finishAddToCartSuccess();
            } catch (error) {
                console.error('❌ [ERROR] Add to Cart Failed:', {
                    error: error,
                    message: error.message,
                    stack: error.stack
                });
                if (!state.popupElement.querySelector('.ed-product-popup__error-message')) {
                    showPopupError(error.message || 'שגיאה בהוספה לסל. נסה שוב.');
                }
            } finally {
                addBtn.classList.remove('is-loading');
            }
            return;
        }

        pendingAddToCartCount += 1;
        if (!addToCartActivePromise) {
            addToCartActivePromise = (async () => {
                addBtn.classList.add('is-loading');
                try {
                    let lastResult = null;
                    while (pendingAddToCartCount > 0) {
                        const batch = pendingAddToCartCount;
                        pendingAddToCartCount = 0;
                        const formData = buildAddToCartFormData();
                        if (!formData) {
                            showPopupError('נא לבחור את כל האפשרויות הנדרשות');
                            break;
                        }
                        logAddToCartDebug(formData);
                        const requestData = formDataToJsonRequest(formData);
                        if (batch > 1) {
                            applyAddToCartBatchMultiplier(requestData, batch);
                        }
                        lastResult = await addToCartFetchOnce(requestData);
                        console.log('✅ Result (Full):', JSON.stringify(lastResult, null, 2));
                        applyAddToCartFragments(lastResult);
                    }
                    if (lastResult && !lastResult.error) {
                        await finishAddToCartSuccess();
                    }
                } catch (error) {
                    pendingAddToCartCount = 0;
                    console.error('❌ [ERROR] Add to Cart Failed:', {
                        error: error,
                        message: error.message,
                        stack: error.stack
                    });
                    if (!state.popupElement.querySelector('.ed-product-popup__error-message')) {
                        showPopupError(error.message || 'שגיאה בהוספה לסל. נסה שוב.');
                    }
                } finally {
                    addBtn.classList.remove('is-loading');
                    addToCartActivePromise = null;
                }
            })();
        }
    }

    /**
     * Show error message in popup
     */
    function showPopupError(message) {
        if (!state.popupElement) return;

        // Remove existing error messages
        const existingErrors = state.popupElement.querySelectorAll('.ed-product-popup__error-message');
        existingErrors.forEach(el => el.remove());

        // Create error message element
        const errorEl = document.createElement('div');
        errorEl.className = 'ed-product-popup__error-message';
        errorEl.textContent = message;

        // Insert before add to cart button
        const addBtn = state.popupElement.querySelector('#popup-add-to-cart');
        if (addBtn && addBtn.parentElement) {
            addBtn.parentElement.insertBefore(errorEl, addBtn);
        } else {
            // Fallback: insert in footer
            const footer = state.popupElement.querySelector('.ed-product-popup__footer');
            if (footer) {
                footer.insertBefore(errorEl, footer.firstChild);
            }
        }

        // Scroll to error
        errorEl.scrollIntoView({behavior: 'smooth', block: 'nearest'});

        // Remove error after 5 seconds
        setTimeout(() => {
            errorEl.style.opacity = '0';
            errorEl.style.transition = 'opacity 0.3s';
            setTimeout(() => errorEl.remove(), 300);
        }, 5000);
    }

    /**
     * Animate image to cart
     */
    async function animateImageToCart() {
        if (!state.popupElement) return;

        const image = state.popupElement.querySelector('#popup-product-image');
        const isMobile = typeof window.matchMedia !== 'undefined' && window.matchMedia('(max-width: 768px)').matches;
        // On mobile: fly down to the button that opens the cart (basket bar or header icon)
        const cartTarget = isMobile
            ? (document.querySelector('#ed-basket-toggle') || document.querySelector('.ed-basket-bar__btn') || document.querySelector('.mini-cart-icon') || document.querySelector('.site-header-minicart button'))
            : document.querySelector('.ed-float-cart__inner');
        const targetEl = cartTarget || document.querySelector('.ed-float-cart__inner');

        if (!image || !targetEl) return;

        const imageRect = image.getBoundingClientRect();
        const cartRect = targetEl.getBoundingClientRect();

        // Create flying image
        const flyingImg = image.cloneNode(true);
        flyingImg.style.cssText = `
      position: fixed;
      left: ${imageRect.left}px;
      top: ${imageRect.top}px;
      width: ${imageRect.width}px;
      height: ${imageRect.height}px;
      z-index: 99999;
      pointer-events: none;
      transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    `;

        document.body.appendChild(flyingImg);

        // Trigger animation
        requestAnimationFrame(() => {
            flyingImg.style.left = `${cartRect.left + cartRect.width / 2}px`;
            flyingImg.style.top = `${cartRect.top + cartRect.height / 2}px`;
            flyingImg.style.width = '40px';
            flyingImg.style.height = '40px';
            flyingImg.style.opacity = '0';
            flyingImg.style.transform = 'scale(0.5)';
        });

        // Remove after animation
        setTimeout(() => {
            flyingImg.remove();
        }, 600);
    }

    /**
     * Show quantity badge on product
     */
    function showQuantityBadge(productId, quantity) {
        const productEl = document.querySelector(`[data-product-id="${productId}"], .product[data-id="${productId}"]`);
        if (!productEl) return;

        // Remove existing badge
        const existingBadge = productEl.querySelector('.ed-product-quantity-badge');
        if (existingBadge) existingBadge.remove();

        // Create badge
        const badge = document.createElement('div');
        badge.className = 'ed-product-quantity-badge';
        badge.textContent = `${quantity} ${state.popupData.ocwsu?.sold_by_units ? 'יח' : 'ק"ג'}`;

        const productImage = productEl.querySelector('img');
        if (productImage) {
            productImage.parentElement.style.position = 'relative';
            productImage.parentElement.appendChild(badge);
        }

        // Remove badge after 3 seconds
        setTimeout(() => {
            badge.style.opacity = '0';
            badge.style.transform = 'scale(0.8)';
            setTimeout(() => badge.remove(), 300);
        }, 3000);
    }

    // Expose functions to the global scope
    window.EDProductPopupCart = {
        handleAddToCart,
        animateImageToCart,
        showQuantityBadge,
        showPopupError
    };

})();

