/**
 * Product Popup Handler
 * Handles opening/closing popup, quantity inputs, add to cart, and animations
 */

(function() {
  'use strict'; 

  let popupData = null;
  let popupElement = null;
  let isOpen = false;

  /** 
   * Initialize popup 
   */
  function init() {
    // Listen for product clicks
    document.addEventListener('click', handleProductClick);
    
    // Listen for close button clicks
    document.addEventListener('click', handleCloseClick);
    
    // Listen for overlay clicks
    document.addEventListener('click', handleOverlayClick);
    
    // Listen for ESC key
    document.addEventListener('keydown', handleKeyDown);
    
    // Listen for add to cart button
    document.addEventListener('click', handleAddToCart);
    
    // Listen for attribute/variation changes
    document.addEventListener('change', handleAttributeChange);
  }

  /**
   * Handle product click - open popup
   */
  async function handleProductClick(e) {
    // Ignore clicks on popup itself
    if (e.target.closest('#ed-product-popup')) return;
    
    // Ignore clicks on elements that shouldn't open popup
    const ignoredSelectors = [
      '.ed-product-popup__close',
      '.ed-product-popup__overlay',
      '.ed-product-popup',
      'a[href^="#"]',
      'button[type="submit"]',
      '.skip-link'
    ];
    
    for (const selector of ignoredSelectors) {
      if (e.target.closest(selector)) return;
    }
    
    // Check for add to cart button first (should open popup, not add directly)
    const addToCartBtn = e.target.closest('a.add_to_cart_button, button.add_to_cart_button, .add_to_cart_button');
    let productId = null;
    let triggerElement = null;
    
    // Find the product element (prioritize li.product)
    let productEl = null;
    
    if (addToCartBtn) {
      // Get product element from button
      productEl = addToCartBtn.closest('li.product, .product, .woocommerce-loop-product__link');
      triggerElement = addToCartBtn;
    } else {
      // Check if clicked directly on li.product (highest priority)
      const clickedEl = e.target;
      
      // First check if clicked on li.product itself
      if (clickedEl.closest && clickedEl.closest('li.product')) {
        productEl = clickedEl.closest('li.product');
        triggerElement = productEl;
      } else {
        // Fallback to other product selectors
        productEl = clickedEl.closest('.product, li.product, .woocommerce-loop-product, .woocommerce-loop-product__link');
        triggerElement = productEl;
      }
    }
    
    if (!productEl) return;
    
    // Get product ID from various sources
    productId = productEl.dataset.productId || 
               productEl.dataset.product_id ||
               productEl.getAttribute('data-product_id') ||
               productEl.getAttribute('data-product-id') ||
               productEl.id?.match(/product-(\d+)/)?.[1];
    
    // Try to get from link inside product
    if (!productId) {
      const link = productEl.querySelector('a.woocommerce-loop-product__link, a[href*="/product/"]');
      if (link) {
        // Try product_id in URL
        const productIdMatch = link.href.match(/[?&]product_id=(\d+)/);
        if (productIdMatch) {
          productId = productIdMatch[1];
        } else {
          // Try slug in URL
          const slugMatch = link.href.match(/\/product\/([^\/\?]+)/);
          if (slugMatch) {
            productId = slugMatch[1];
          }
        }
      }
    }
    
    // Try to get from product image data attribute
    if (!productId) {
      const img = productEl.querySelector('img');
      if (img) {
        productId = img.dataset.productId || 
                   img.dataset.product_id ||
                   img.getAttribute('data-product-id') ||
                   img.getAttribute('data-product_id');
      }
    }
    
    // Try to get from add to cart button inside product
    if (!productId) {
      const btn = productEl.querySelector('a.add_to_cart_button, button.add_to_cart_button');
      if (btn) {
        productId = btn.dataset.productId || 
                   btn.dataset.product_id ||
                   btn.getAttribute('data-product_id') ||
                   btn.getAttribute('data-product-id') ||
                   btn.href?.match(/add-to-cart=(\d+)/)?.[1];
      }
    }
    
    if (!productId) return;
    
    // Prevent default navigation/action
    e.preventDefault();
    e.stopPropagation();
    
    // Get product ID (handle slugs)
    let id = parseInt(productId);
    if (isNaN(id)) {
      // Try to get ID from slug via REST API
      try {
        const apiUrl = window.location.origin + '/wp-json/wp/v2/product?slug=' + encodeURIComponent(productId);
        const response = await fetch(apiUrl);
        if (response.ok) {
          const products = await response.json();
          if (products.length) id = products[0].id;
          else {
            console.warn('Product not found:', productId);
            return;
          }
        } else {
          console.warn('Failed to fetch product:', response.status);
          return;
        }
      } catch (err) {
        console.warn('Could not resolve product slug:', err);
        return;
      }
    }
    
    await openPopup(id, triggerElement);
  }

  /**
   * Open popup with product data
   */
  async function openPopup(productId, triggerElement) {
    try {
      const response = await fetch(`${window.ED_POPUP_CONFIG?.endpoint || '/wp-json/ed/v1/product-popup'}?id=${productId}`);
      if (!response.ok) throw new Error('Failed to load product');
      
      popupData = await response.json();
      
      // Create popup HTML
      const popupHTML = await fetchPopupHTML(popupData);
      
      // Remove existing popup if any
      const existing = document.getElementById('ed-product-popup');
      if (existing) existing.remove();
      
      // Add to body
      document.body.insertAdjacentHTML('beforeend', popupHTML);
      popupElement = document.getElementById('ed-product-popup');
      
      // Initialize quantity inputs
      initQuantityInputs();
      
      // Initialize variation selection if variable product
      if (popupData.type === 'variable' && popupData.attributes.length > 0) {
        updateVariationSelection();
      }
      
      // Show popup
      setTimeout(() => {
        popupElement.classList.add('is-open');
        document.body.classList.add('popup-open');
        isOpen = true;
        
        // Focus management
        const closeBtn = popupElement.querySelector('.ed-product-popup__close');
        if (closeBtn) closeBtn.focus();
      }, 10);
      
      // Store trigger element for animation
      if (triggerElement) {
        popupElement.dataset.triggerElement = triggerElement.getBoundingClientRect();
      }
      
    } catch (error) {
      console.error('Error opening popup:', error);
    }
  }

  /**
   * Fetch popup HTML from server
   */
  async function fetchPopupHTML(data) {
    // We'll render it client-side, but in production you might want to fetch from server
    // For now, we'll use the template structure
    return renderPopupHTML(data);
  }

  /**
   * Helper function to sanitize title
   */
  function sanitizeTitle(str) {
    return String(str).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  /**
   * Render popup HTML
   */
  function renderPopupHTML(data) {
    const ocwsu = data.ocwsu || {};
    const attributes = data.attributes || [];
    const variations = data.variations || [];
    const isVariable = data.type === 'variable';
    
    let quantityHTML = '';
    
    // Generate quantity input based on ocwsu settings
    if (ocwsu.weighable && ocwsu.sold_by_units) {
      // Units input
      quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <input type="number" 
                 id="popup-quantity-units" 
                 name="quantity" 
                 value="1" 
                 min="1" 
                 step="1"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">יחידות</span>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
        </div>
      `;
    } else if (ocwsu.weighable && ocwsu.sold_by_weight) {
      // Weight input
      const minWeight = ocwsu.min_weight || 0.5;
      const step = ocwsu.weight_step || 0.1;
      const unit = ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם';
      
      quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease" data-step="${step}">-</button>
          <input type="number" 
                 id="popup-quantity-weight" 
                 name="quantity" 
                 value="${minWeight}" 
                 min="${minWeight}" 
                 step="${step}"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">${unit}</span>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase" data-step="${step}">+</button>
        </div>
      `;
    } else {
      // Simple quantity
      quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <input type="number" 
                 id="popup-quantity" 
                 name="quantity" 
                 value="1" 
                 min="1" 
                 step="1"
                 class="ed-product-popup__qty-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
        </div>
      `;
    }
    
    // Build options HTML
    let optionsHTML = '<div class="ed-product-popup__options"><h3 class="ed-product-popup__options-title">הגדרות מוצר</h3>';
    
    // Unit weight selection (if variable)
    if (ocwsu.weighable && ocwsu.sold_by_units && ocwsu.unit_weight_type === 'variable' && ocwsu.unit_weight_options?.length) {
      optionsHTML += '<div class="ed-product-popup__option-group"><label class="ed-product-popup__option-label">בחירת משקל ליחידה</label><div class="ed-product-popup__radio-group" data-option="unit_weight">';
      ocwsu.unit_weight_options.forEach((weight, idx) => {
        const showWeight = ocwsu.product_weight_units === 'kg' && weight < 1 ? weight * 1000 : weight;
        const label = ocwsu.product_weight_units === 'kg' && weight < 1 ? 'גרם' : (ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם');
        optionsHTML += `<label class="ed-product-popup__radio"><input type="radio" name="popup_unit_weight" value="${weight}" ${idx === 0 ? 'checked' : ''}><span class="ed-product-popup__radio-label">${showWeight} ${label}</span></label>`;
      });
      optionsHTML += '</div></div>';
    }
    
    // WooCommerce Attributes (for variations or simple product attributes)
    if (attributes && attributes.length > 0) {
      attributes.forEach(attr => {
        if (!attr || !attr.options || attr.options.length === 0) return;
        optionsHTML += `<div class="ed-product-popup__option-group"><label class="ed-product-popup__option-label">${attr.label || attr.name}</label><div class="ed-product-popup__radio-group" data-attribute="${attr.name}">`;
        attr.options.forEach((option, idx) => {
          const optionSlug = option.slug || sanitizeTitle(option.name || option);
          const optionName = option.name || option;
          optionsHTML += `<label class="ed-product-popup__radio"><input type="radio" name="attribute_${attr.name}" value="${optionSlug}" ${idx === 0 ? 'checked' : ''}><span class="ed-product-popup__radio-label">${optionName}</span></label>`;
        });
        optionsHTML += '</div></div>';
      });
    }
    
    optionsHTML += '<div class="ed-product-popup__error" id="popup-option-error" style="display: none;">נא לבחור אפשרות</div></div>';
    
    // Note textarea (only if weighable)
    const noteHTML = ocwsu.weighable ? `
      <div class="ed-product-popup__note">
        <label for="popup-product-note">הערה למוצר השקיל</label>
        <textarea id="popup-product-note" name="product_note" rows="2" placeholder="הערות לקצב"></textarea>
      </div>
    ` : '';
    
    return `
      <div class="ed-product-popup" id="ed-product-popup" role="dialog" aria-modal="true">
        <div class="ed-product-popup__overlay"></div>
        <div class="ed-product-popup__container">
          <button class="ed-product-popup__close default-close-btn btn-empty" type="button" aria-label="סגור">
            <svg xmlns="http://www.w3.org/2000/svg" class="Icon Icon--close" viewBox="0 0 16 14">
              <path d="M15 0L1 14m14 0L1 0" stroke="currentColor" fill="none" fill-rule="evenodd"></path>
            </svg>
          </button>
          <div class="ed-product-popup__content">
            <div class="ed-product-popup__image">
              <img src="${data.image.url}" alt="${data.image.alt}" id="popup-product-image" loading="lazy">
            </div>
            <div class="ed-product-popup__info">
              <h2 class="ed-product-popup__title">${data.name}</h2>
              <div class="ed-product-popup__price">
                ${ocwsu.average_weight ? `<span class="ed-product-popup__price-label">משקל ממוצע: ${ocwsu.average_weight} ${ocwsu.average_weight_label}</span><span class="ed-product-popup__price-sep">-</span>` : ''}
                <span class="ed-product-popup__price-value">${data.price_html}</span>
              </div>
              ${data.description ? `<hr class="ed-product-popup__divider"><div class="ed-product-popup__description">${data.description}</div><hr class="ed-product-popup__divider">` : ''}
              ${optionsHTML}
            </div>
            <div class="ed-product-popup__footer">
              <div class="ed-product-popup__quantity" id="popup-quantity-container">${quantityHTML}</div>
              ${noteHTML}
              <button type="button" class="ed-product-popup__add-btn" id="popup-add-to-cart" data-product-id="${data.id}" ${isVariable && attributes.length > 0 ? 'disabled' : ''}>
                <span class="ed-product-popup__add-btn-text">הוסף לסל</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Initialize quantity inputs
   */
  function initQuantityInputs() {
    if (!popupElement) return;
    
    // Quantity buttons
    popupElement.querySelectorAll('.ed-product-popup__qty-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const action = this.dataset.action;
        const input = this.closest('.ed-product-popup__quantity-input').querySelector('input');
        const step = parseFloat(this.dataset.step) || 1;
        const min = parseFloat(input.min) || 1;
        let value = parseFloat(input.value) || min;
        
        if (action === 'increase') {
          value += step;
        } else if (action === 'decrease' && value > min) {
          value -= step;
        }
        
        // Round to step precision
        const decimals = step.toString().split('.')[1]?.length || 0;
        value = parseFloat(value.toFixed(decimals));
        
        input.value = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        validateAddToCartButton();
      });
    });
    
    // Quantity input changes
    popupElement.querySelectorAll('.ed-product-popup__qty-input').forEach(input => {
      input.addEventListener('change', validateAddToCartButton);
    });
  }

  /**
   * Handle attribute/variation changes
   */
  function handleAttributeChange(e) {
    if (!popupElement || !popupElement.contains(e.target)) return;
    
    const radio = e.target.closest('input[type="radio"]');
    if (!radio) return;
    
    // Hide error if option selected
    const errorEl = popupElement.querySelector('#popup-option-error');
    if (errorEl) errorEl.style.display = 'none';
    
    // If it's a variation attribute, update price and availability
    if (radio.name.startsWith('attribute_')) {
      updateVariationSelection();
    }
    
    validateAddToCartButton();
    
    // Update hidden fields for ocwsu
    updateOcwsuHiddenFields();
  }
  
  /**
   * Update variation selection and price
   */
  function updateVariationSelection() {
    if (!popupElement || !popupData || popupData.type !== 'variable') return;
    
    const selectedAttributes = {};
    popupElement.querySelectorAll('input[name^="attribute_"]:checked').forEach(radio => {
      const attrName = radio.name.replace('attribute_', '');
      selectedAttributes[attrName] = radio.value;
    });
    
    // Find matching variation
    const matchingVariation = popupData.variations.find(variation => {
      return Object.keys(selectedAttributes).every(attrName => {
        return variation.attributes[attrName] === selectedAttributes[attrName];
      });
    });
    
    if (matchingVariation) {
      // Update price display
      const priceEl = popupElement.querySelector('.ed-product-popup__price-value');
      if (priceEl) {
        priceEl.innerHTML = matchingVariation.price_html;
      }
      
      // Store variation ID
      popupElement.dataset.variationId = matchingVariation.id;
      
      // Update stock status
      if (!matchingVariation.in_stock) {
        const addBtn = popupElement.querySelector('#popup-add-to-cart');
        if (addBtn) {
          addBtn.disabled = true;
          addBtn.textContent = 'לא במלאי';
        }
      }
    } else {
      // No matching variation found
      popupElement.dataset.variationId = '';
    }
  }

  /**
   * Update ocwsu hidden fields based on selections
   */
  function updateOcwsuHiddenFields() {
    if (!popupElement || !popupData) return;
    
    const ocwsu = popupData.ocwsu || {};
    
    // Get selected unit weight
    const unitWeightRadio = popupElement.querySelector('input[name="popup_unit_weight"]:checked');
    const unitWeight = unitWeightRadio ? parseFloat(unitWeightRadio.value) : (ocwsu.unit_weight || 0);
    
    // Get quantity
    const qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
    const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
    
    // Calculate quantity in kg
    let quantityInKg = quantity;
    if (ocwsu.weighable && ocwsu.sold_by_units && unitWeight) {
      quantityInKg = quantity * unitWeight * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
    } else if (ocwsu.weighable && ocwsu.sold_by_weight) {
      quantityInKg = quantity * (ocwsu.product_weight_units === 'kg' ? 1 : 0.001);
    }
    
    // Store in data attributes for add to cart
    popupElement.dataset.ocwsuUnit = ocwsu.sold_by_units ? 'unit' : (ocwsu.product_weight_units || 'kg');
    popupElement.dataset.ocwsuUnitWeight = unitWeight || 0;
    popupElement.dataset.ocwsuQuantityInUnits = ocwsu.sold_by_units ? quantity : 0;
    popupElement.dataset.ocwsuQuantityInWeightUnits = ocwsu.sold_by_weight ? quantity : 0;
    popupElement.dataset.quantityInKg = quantityInKg;
  }

  /**
   * Validate and enable/disable add to cart button
   */
  function validateAddToCartButton() {
    if (!popupElement) return;
    
    const addBtn = popupElement.querySelector('#popup-add-to-cart');
    if (!addBtn) return;
    
    // For variable products, check if all attributes are selected
    if (popupData && popupData.type === 'variable' && popupData.attributes.length > 0) {
      const allAttributesSelected = popupData.attributes.every(attr => {
        return popupElement.querySelector(`input[name="attribute_${attr.name}"]:checked`);
      });
      
      if (!allAttributesSelected) {
        addBtn.disabled = true;
        addBtn.classList.add('is-disabled');
        return;
      }
      
      // Check if variation is in stock
      const variationId = popupElement.dataset.variationId;
      if (variationId) {
        const variation = popupData.variations.find(v => v.id == variationId);
        if (variation && !variation.in_stock) {
          addBtn.disabled = true;
          addBtn.classList.add('is-disabled');
          return;
        }
      }
    }
    
    // Check if required options are selected (unit weight, etc.)
    const requiredGroups = popupElement.querySelectorAll('.ed-product-popup__radio-group[data-option]');
    let allSelected = true;
    
    requiredGroups.forEach(group => {
      const selected = group.querySelector('input[type="radio"]:checked');
      if (!selected) {
        allSelected = false;
      }
    });
    
    // Check quantity
    const qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
    const quantity = qtyInput ? parseFloat(qtyInput.value) : 0;
    const minQty = parseFloat(qtyInput?.min) || 1;
    
    if (allSelected && quantity >= minQty) {
      addBtn.disabled = false;
      addBtn.classList.remove('is-disabled');
      const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');
      if (btnText) btnText.textContent = 'הוסף לסל';
    } else {
      addBtn.disabled = true;
      addBtn.classList.add('is-disabled');
    }
  }

  /**
   * Handle add to cart
   */
  async function handleAddToCart(e) {
    const addBtn = e.target.closest('#popup-add-to-cart');
    if (!addBtn || addBtn.disabled) return;
    
    e.preventDefault();
    
    if (!popupElement || !popupData) return;
    
    // Validate options again
    const requiredGroups = popupElement.querySelectorAll('.ed-product-popup__radio-group[data-option]');
    let missingOptions = [];
    
    requiredGroups.forEach(group => {
      const selected = group.querySelector('input[type="radio"]:checked');
      if (!selected) {
        missingOptions.push(group.dataset.option);
      }
    });
    
    if (missingOptions.length > 0) {
      const errorEl = popupElement.querySelector('#popup-option-error');
      if (errorEl) {
        errorEl.style.display = 'block';
        errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }
    
    // Get form data
    const formData = new FormData();
    
    // Product ID or Variation ID
    const variationId = popupElement.dataset.variationId;
    if (variationId && popupData.type === 'variable') {
      formData.append('variation_id', variationId);
      formData.append('product_id', popupData.id);
      
      // Add selected attributes
      popupElement.querySelectorAll('input[name^="attribute_"]:checked').forEach(radio => {
        const attrName = radio.name.replace('attribute_', '');
        const attrKey = attrName.startsWith('pa_') ? `attribute_${attrName}` : `attribute_${attrName}`;
        formData.append(attrKey, radio.value);
      });
    } else {
      formData.append('product_id', popupData.id);
    }
    
    // Quantity
    const qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');
    const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
    
    // ocwsu fields
    updateOcwsuHiddenFields();
    formData.append('quantity', popupElement.dataset.quantityInKg || quantity);
    formData.append('ocwsu_unit', popupElement.dataset.ocwsuUnit || 'unit');
    formData.append('ocwsu_unit_weight', popupElement.dataset.ocwsuUnitWeight || '0');
    formData.append('ocwsu_quantity_in_units', popupElement.dataset.ocwsuQuantityInUnits || '0');
    formData.append('ocwsu_quantity_in_weight_units', popupElement.dataset.ocwsuQuantityInWeightUnits || '0');
    
    // Product note (only if weighable)
    const productNote = popupElement.querySelector('#popup-product-note');
    if (productNote && productNote.value.trim()) {
      formData.append('product_note', productNote.value.trim());
    }
    
    // Add to cart via WooCommerce AJAX
    try {
      addBtn.disabled = true;
      addBtn.classList.add('is-loading');
      
      // Use WooCommerce AJAX endpoint
      const ajaxUrl = window.wc_add_to_cart_params?.wc_ajax_url?.toString().replace('%%endpoint%%', 'add_to_cart') || 
                     window.ED_POPUP_CONFIG?.addToCartUrl || 
                     '/?wc-ajax=add_to_cart';
      
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (!response.ok) throw new Error('Add to cart failed');
      
      const result = await response.json();
      
      // Animate image to cart
      await animateImageToCart();
      
      // Close popup
      closePopup();
      
      // Update cart fragments
      if (result.fragments && typeof result.fragments === 'object') {
        if (window.updateCartFragments) {
          window.updateCartFragments(result.fragments);
        } else if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
          // WooCommerce way
          jQuery('body').trigger('wc_fragment_refresh');
          jQuery('body').trigger('added_to_cart', [result.fragments, '', '', '']);
        }
      }
      
      // Show quantity badge on product
      const displayQuantity = popupData.ocwsu?.sold_by_units ? 
        popupElement.dataset.ocwsuQuantityInUnits || quantity : 
        (popupData.ocwsu?.sold_by_weight ? 
          popupElement.dataset.ocwsuQuantityInWeightUnits || quantity : 
          quantity);
      showQuantityBadge(popupData.id, displayQuantity);
      
    } catch (error) {
      console.error('Error adding to cart:', error);
      alert('שגיאה בהוספה לסל. נסה שוב.');
    } finally {
      addBtn.disabled = false;
      addBtn.classList.remove('is-loading');
    }
  }

  /**
   * Animate image to cart
   */
  async function animateImageToCart() {
    if (!popupElement) return;
    
    const image = popupElement.querySelector('#popup-product-image');
    const cartBtn = document.querySelector('#ed-basket-toggle, .cart-contents');
    
    if (!image || !cartBtn) return;
    
    const imageRect = image.getBoundingClientRect();
    const cartRect = cartBtn.getBoundingClientRect();
    
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
    badge.textContent = `${quantity} ${popupData.ocwsu?.sold_by_units ? 'יח' : 'ק"ג'}`;
    
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


  /**
   * Handle close button click
   */
  function handleCloseClick(e) {
    if (!e.target.closest('.ed-product-popup__close')) return;
    e.preventDefault();
    closePopup();
  }

  /**
   * Handle overlay click
   */
  function handleOverlayClick(e) {
    if (!e.target.closest('.ed-product-popup__overlay')) return;
    closePopup();
  }

  /**
   * Handle keyboard
   */
  function handleKeyDown(e) {
    if (e.key === 'Escape' && isOpen) {
      closePopup();
    }
  }

  /**
   * Close popup
   */
  function closePopup() {
    if (!popupElement || !isOpen) return;
    
    popupElement.classList.remove('is-open');
    document.body.classList.remove('popup-open');
    
    setTimeout(() => {
      if (popupElement) {
        popupElement.remove();
        popupElement = null;
        popupData = null;
        isOpen = false;
      }
    }, 300);
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose for external use
  window.EDProductPopup = {
    open: openPopup,
    close: closePopup
  };

})();

