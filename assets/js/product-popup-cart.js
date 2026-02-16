/**
 * Product Popup Cart Functions
 * Handles add to cart, animations, error messages, and quantity badges
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState; // Access shared state

    /**
     * Handle add to cart
     */
    async function handleAddToCart(e) {
        const addBtn = e.target.closest('#popup-add-to-cart');
        if (!addBtn || addBtn.disabled) return;

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

        // Get form data
        const formData = new FormData();

        // Product ID or Variation ID
        const variationId = state.popupElement.dataset.variationId;

        if (state.popupData.type === 'variable') {
            // For variable products, MUST send variation_id and attributes
            if (!variationId) {
                showPopupError('נא לבחור את כל האפשרויות הנדרשות');
                return;
            }

            formData.append('variation_id', variationId);
            formData.append('product_id', state.popupData.id);

            // Add selected attributes (WooCommerce format: attribute_pa_xxx for taxonomy, attribute_xxx for custom)
            const selectedAttributes = state.popupElement.querySelectorAll('input[name^="attribute_"]:checked');

            if (selectedAttributes.length === 0) {
                showPopupError('נא לבחור את כל האפשרויות הנדרשות');
                return;
            }

            // Attributes that should be ignored for this variation (e.g. size ANY with empty value)
            let ignoredAttrs = [];
            if (state.popupElement.dataset.ignoredAttributes) {
                try {
                    ignoredAttrs = JSON.parse(state.popupElement.dataset.ignoredAttributes) || [];
                } catch (e) {
                    ignoredAttrs = [];
                }
            }

            selectedAttributes.forEach(radio => {
                // Base attribute name, e.g. 'pa_גודל'
                const baseName = radio.name.replace(/^attribute_/, '');
                if (ignoredAttrs.includes(baseName)) {
                    // Don't send attributes that the chosen variation leaves empty / ANY.
                    // This prevents server-side validation like "גודל הוא שדה חובה" כשאין ערך אמיתי בווריאציה.
                    return;
                }
                // The name is already in format "attribute_pa_xxx" or "attribute_xxx" from the HTML
                // WooCommerce expects exactly this format
                formData.append(radio.name, radio.value);
            });
        } else {
            // Simple product
            formData.append('product_id', state.popupData.id);
        }

        // Add WooCommerce nonce if available (for security)
        if (window.wc_add_to_cart_params?.wc_add_to_cart_nonce) {
            formData.append('wc_add_to_cart_nonce', window.wc_add_to_cart_params.wc_add_to_cart_nonce);
        }

        // ocwsu fields - update before using (must be called first)
        window.EDProductPopupOcwsu?.updateOcwsuHiddenFields();

        // Check if product can toggle between units and weight
        const canToggle = state.popupData.ocwsu?.weighable && state.popupData.ocwsu?.sold_by_units && state.popupData.ocwsu?.sold_by_weight;
        const currentMode = state.popupElement.dataset.quantityMode || (state.popupData.ocwsu?.sold_by_units ? 'units' : 'weight');

        // Get quantity from active input based on mode
        let qtyInput = null;
        if (canToggle) {
            // Get quantity from active input based on mode
            qtyInput = currentMode === 'units'
                ? state.popupElement.querySelector('#popup-quantity-units')
                : state.popupElement.querySelector('#popup-quantity-weight');
        } else {
            qtyInput = state.popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
        }

        const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;

        // For oc-woo-sale-units plugin, quantity should be in the base unit (kg for weighable products)
        // Always use quantityInKg for weighable products (backend expects kg)
        const ocwsu = state.popupData.ocwsu || {};
        let quantityToSend = quantity;

        if (ocwsu.weighable) {
            // For weighable products, always send quantity in kg
            quantityToSend = parseFloat(state.popupElement.dataset.quantityInKg || quantity);
        }

        // Send quantity (WooCommerce expects this)
        formData.append('quantity', quantityToSend);

        // ocwsu fields (for the plugin)
        formData.append('ocwsu_unit', state.popupElement.dataset.ocwsuUnit || 'unit');
        formData.append('ocwsu_unit_weight', state.popupElement.dataset.ocwsuUnitWeight || '0');
        formData.append('ocwsu_quantity_in_units', state.popupElement.dataset.ocwsuQuantityInUnits || '0');
        formData.append('ocwsu_quantity_in_weight_units', state.popupElement.dataset.ocwsuQuantityInWeightUnits || '0');

        // Product note (always send if field exists and has value)
        const productNote = state.popupElement.querySelector('#popup-product-note');
        if (productNote && productNote.value.trim()) {
            formData.append('product_note', productNote.value.trim());
        }

        // Debug: Log all FormData entries
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

        // Log FormData entries
        const formDataEntries = {};
        for (const [key, value] of formData.entries()) {
            formDataEntries[key] = value;
        }
        console.log('📋 FormData:', formDataEntries);

        // Check if we're in edit mode (updating existing cart item)
        const isEditMode = state.popupData.isEditMode && state.popupData.cartItemKey;

        // If editing, remove the old cart item first
        if (isEditMode) {
            try {
                // Remove old cart item via WooCommerce AJAX
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

        // Add to cart via WooCommerce AJAX
        try {
            addBtn.disabled = true;
            addBtn.classList.add('is-loading');

            // Use our custom endpoint for debugging (or fallback to WooCommerce)
            const ajaxUrl = window.ED_POPUP_CONFIG?.addToCartUrl ||
                window.wc_add_to_cart_params?.wc_ajax_url?.toString().replace('%%endpoint%%', 'add_to_cart') ||
                '/?wc-ajax=add_to_cart';

            console.log('🌐 AJAX URL:', ajaxUrl);
            console.log('🔧 Available URLs:', {
                ED_POPUP_CONFIG: window.ED_POPUP_CONFIG?.addToCartUrl,
                wc_add_to_cart_params: window.wc_add_to_cart_params?.wc_ajax_url,
                fallback: '/?wc-ajax=add_to_cart'
            });

            console.log('📤 Sending request...');

            // Convert FormData to JSON for REST API
            const requestData = {};
            for (const [key, value] of formData.entries()) {
                requestData[key] = value;
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

            console.log('📥 Response received:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries())
            });

            if (!response.ok) {
                // Try to parse JSON error response
                let errorData = null;
                try {
                    const errorText = await response.text();
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    // Not JSON, use status text
                }

                console.error('❌ Response Error:', {
                    status: response.status,
                    statusText: response.statusText,
                    errorData: errorData
                });

                // Use errorMessage from response if available, otherwise use status
                const errorMessage = errorData?.errorMessage ||
                    (errorData?.notices && errorData.notices.length > 0 ?
                        errorData.notices.map(n => n.notice || n).join(' ') :
                        `שגיאה בהוספה לסל: ${response.status} ${response.statusText}`);

                showPopupError(errorMessage);
                throw new Error(errorMessage);
            }

            const result = await response.json();
            console.log('✅ Result (Full):', JSON.stringify(result, null, 2));

            // Check for errors in result
            if (result.error) {
                // Our custom endpoint returns detailed error info
                // ALWAYS use errorMessage from result, not status or other fields
                const errorMessage = result.errorMessage ||
                    (result.notices && result.notices.length > 0 ?
                        result.notices.map(n => (typeof n === 'string' ? n : (n.notice || ''))).filter(m => m).join(' ') :
                        (typeof result.error === 'string' ? result.error : 'שגיאה לא ידועה'));

                console.error('❌ Result Error Details:', {
                    error: result.error,
                    errorMessage: errorMessage,
                    debug: result.debug,
                    notices: result.notices,
                    exception: result.exception,
                    fullResult: result
                });

                if (result.debug) {
                    console.error('🔍 Debug Info:', result.debug);
                }

                // Show error in popup - ALWAYS use errorMessage from result
                showPopupError(errorMessage);

                throw new Error(errorMessage);
            }

            console.groupEnd();

            // Animate image to cart
            await animateImageToCart();

            // Close popup
            if (window.EDProductPopupCore?.closePopup) {
                window.EDProductPopupCore.closePopup();
            }

            // Update cart fragments
            if (result.fragments && typeof result.fragments === 'object') {
                // Update cart count element directly
                if (result.fragments['span.ed-float-cart__count']) {
                    const countEl = document.querySelector('.ed-float-cart__count');
                    if (countEl) {
                        countEl.outerHTML = result.fragments['span.ed-float-cart__count'];
                    } else {
                        // If count element doesn't exist, try to add it to the title
                        const titleEl = document.querySelector('.ed-float-cart__title');
                        if (titleEl && result.fragments['span.ed-float-cart__count']) {
                            titleEl.insertAdjacentHTML('beforeend', result.fragments['span.ed-float-cart__count']);
                        }
                    }
                }

                if (window.updateCartFragments) {
                    window.updateCartFragments(result.fragments);
                } else if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
                    // WooCommerce way
                    jQuery('body').trigger('wc_fragment_refresh');
                    jQuery('body').trigger('added_to_cart', [result.fragments, '', '', '']);
                }
            }

            // Show quantity badge on product
            const displayQuantity = state.popupData.ocwsu?.sold_by_units ?
                state.popupElement.dataset.ocwsuQuantityInUnits || quantity :
                (state.popupData.ocwsu?.sold_by_weight ?
                    state.popupElement.dataset.ocwsuQuantityInWeightUnits || quantity :
                    quantity);
            showQuantityBadge(state.popupData.id, displayQuantity);

        } catch (error) {
            console.groupEnd();
            console.error('❌ [ERROR] Add to Cart Failed:', {
                error: error,
                message: error.message,
                stack: error.stack
            });

            // Show error in popup (if not already shown)
            if (!state.popupElement.querySelector('.ed-product-popup__error-message')) {
                showPopupError(error.message || 'שגיאה בהוספה לסל. נסה שוב.');
            }
        } finally {
            addBtn.disabled = false;
            addBtn.classList.remove('is-loading');
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
        const cartTarget = document.querySelector('.ed-float-cart__inner');

        if (!image || !cartTarget) return;

        const imageRect = image.getBoundingClientRect();
        const cartRect = cartTarget.getBoundingClientRect();

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

