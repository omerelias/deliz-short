/**
 * Product Popup Render Functions
 * Handles HTML rendering for the popup
 */

(function () {
    'use strict';

    /**
     * Helper function to sanitize title
     */
    function sanitizeTitle(str) {
        return String(str).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
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
     * Render popup HTML
     */
    function renderPopupHTML(data) {
        const ocwsu = data.ocwsu || {};
        const attributes = data.attributes || [];
        const variations = data.variations || [];
        const isVariable = data.type === 'variable';

        let quantityHTML = '';
        let toggleHTML = '';

        // Check if product can be sold both by units AND by weight
        const canToggle = ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight;
        const footerClass = 'ed-product-popup__footer' + (canToggle ? ' ed-product-popup__footer--has-quantity-toggle' : '');

        // Generate toggle buttons if product can be sold both ways
        if (canToggle) {
            toggleHTML = `
        <div class="ed-product-popup__quantity-toggle">
          <button type="button" class="ed-product-popup__toggle-btn is-active" data-mode="units" aria-label="קנה לפי יחידות">
            <span>יח'</span>
          </button>
          <button type="button" class="ed-product-popup__toggle-btn" data-mode="weight" aria-label="קנה לפי משקל">
            <span>ק"ג</span>
          </button>
        </div>
      `;
        }

        // Generate quantity input based on ocwsu settings
        if (ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight) {
            // Product can be sold both ways - show toggle and default to units
            const minWeight = ocwsu.min_weight || 0.5;
            const step = ocwsu.weight_step || 0.1;
            const unit = ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם';

            quantityHTML = `
        <!-- Units input (default, shown first) -->
        <div class="ed-product-popup__quantity-input" data-quantity-mode="units" id="popup-quantity-units-container">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-units" 
                 name="quantity"  
                 value="1" 
                 min="1" 
                 step="1"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">יח'</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
        </div>
        <!-- Weight input (hidden by default) -->
        <div class="ed-product-popup__quantity-input" data-quantity-mode="weight" id="popup-quantity-weight-container" style="display: none;">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease" data-step="${step}">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-weight" 
                 name="quantity" 
                 value="${minWeight}" 
                 min="${minWeight}" 
                 step="${step}"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">ק"ג</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase" data-step="${step}">+</button>
        </div>
      `;
        } else if (ocwsu.weighable && ocwsu.sold_by_units) {
            // Units input only
            quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-units" 
                 name="quantity" 
                 value="1" 
                 min="1" 
                 step="1"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">יחידות</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
        </div>
      `;
        } else if (ocwsu.weighable && ocwsu.sold_by_weight) {
            // Weight input only
            const minWeight = ocwsu.min_weight || 0.5;
            const step = ocwsu.weight_step || 0.1;
            const unit = ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם';

            quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease" data-step="${step}">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-weight" 
                 name="quantity" 
                 value="${minWeight}" 
                 min="${minWeight}" 
                 step="${step}"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">${unit}</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase" data-step="${step}">+</button>
        </div>
      `;
        } else {
            // Simple quantity
            quantityHTML = `
        <div class="ed-product-popup__quantity-input simple">
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
        let optionsHTML = '<div class="ed-product-popup__options">';

        // Unit weight selection (if variable) - BUT NOT if get_weight_from_variation is enabled
        // If get_weight_from_variation is enabled, we'll use the variation's weight directly
        if (ocwsu.weighable && ocwsu.sold_by_units && ocwsu.unit_weight_type === 'variable' && ocwsu.unit_weight_options?.length && !ocwsu.get_weight_from_variation) {
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
                // Use the full attribute name (including pa_ prefix if exists)
                const attrName = attr.name; // This already includes pa_ if it's a taxonomy
                optionsHTML += `<div class="ed-product-popup__option-group"><label class="ed-product-popup__option-label">${attr.label || attr.name}</label><div class="ed-product-popup__radio-group" data-attribute="${attrName}">`;

                attr.options.forEach((option, idx) => {
                    const optionSlug = option.slug || sanitizeTitle(option.name || option);
                    const optionName = option.name || option;
                    // Use full attribute name in input name (attribute_pa_xxx or attribute_xxx)
                    optionsHTML += `<label class="ed-product-popup__radio"><input type="radio" name="attribute_${attrName}" value="${optionSlug}" ${idx === 0 ? 'checked' : ''}><span class="ed-product-popup__radio-label">${optionName}</span></label>`;
                });

                optionsHTML += '</div></div>';
            });
        }

        optionsHTML += '<div class="ed-product-popup__error" id="popup-option-error" style="display: none;">נא לבחור אפשרות</div></div>';

        // Note textarea - always show (as per user request)
        const noteHTML = `
      <div class="ed-product-popup__note">
        <label for="popup-product-note">הערות לקצב</label>
        <textarea id="popup-product-note" name="product_note" rows="2" placeholder="הערות לקצב"></textarea>
      </div>
    `;

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
                ${ocwsu.average_weight && ocwsu.unit_weight_type !== 'variable' ? `<span class="ed-product-popup__price-label">משקל ממוצע: ${ocwsu.average_weight} ${ocwsu.average_weight_label}</span><span class="ed-product-popup__price-sep">-</span>` : ''}
 
                <span class="ed-product-popup__price-value">${data.price_html}</span> 
              </div> 
              ${data.description ? `<hr class="ed-product-popup__divider"><div class="ed-product-popup__description">${data.description}</div><hr class="ed-product-popup__divider">` : ''}
              ${optionsHTML}
              ${noteHTML}
            </div>  
 
            <div class="${footerClass}">
              ${toggleHTML}
              <div class="ed-product-popup__quantity" id="popup-quantity-container">${quantityHTML}</div>
              <button type="button" class="ed-product-popup__add-btn" id="popup-add-to-cart" data-product-id="${data.id}" ${!data.in_stock ? 'disabled' : (isVariable && attributes.length > 0 ? 'disabled' : '')}>
                <span class="ed-product-popup__add-btn-text">${!data.in_stock ? 'אזל מהמלאי' : (data.isEditMode ? 'עדכן בסל' : 'הוסף לסל')}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    }

    // Expose functions
    window.EDProductPopupRender = {
        renderPopupHTML,
        fetchPopupHTML,
        sanitizeTitle
    };

})();

