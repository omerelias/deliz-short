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

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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
        const shopLoopAfterTitle = data.shop_loop_after_title || {};
        const afterTitleLoopHtml = (shopLoopAfterTitle.fixed_unit_html || '') + (shopLoopAfterTitle.price_per_html || '');

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
            const hasNumericStep = ocwsu.weight_step && ocwsu.weight_step > 0;
            const stepAttr = hasNumericStep ? ocwsu.weight_step : 'any';
            const weightLabel = ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם';

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
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-weight" 
                 name="quantity" 
                 value="${minWeight}" 
                 min="${minWeight}" 
                 step="${stepAttr}"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">${weightLabel}</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
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
          <span class="ed-product-popup__qty-label">יח'</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
        </div>
      `;
        } else if (ocwsu.weighable && ocwsu.sold_by_weight) {
            // Weight input only
            const minWeight = ocwsu.min_weight || 0.5;
            const hasNumericStep = ocwsu.weight_step && ocwsu.weight_step > 0;
            const stepAttr = hasNumericStep ? ocwsu.weight_step : 'any';
            const unit = ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם';

            quantityHTML = `
        <div class="ed-product-popup__quantity-input">
          <button type="button" class="ed-product-popup__qty-btn" data-action="decrease">-</button>
          <div class="qty-wrap">
          <input type="number" 
                 id="popup-quantity-weight" 
                 name="quantity" 
                 value="${minWeight}" 
                 min="${minWeight}" 
                 step="${stepAttr}"
                 class="ed-product-popup__qty-input">
          <span class="ed-product-popup__qty-label">${unit}</span>
          </div>
          <button type="button" class="ed-product-popup__qty-btn" data-action="increase">+</button>
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
            optionsHTML += '<div class="ed-product-popup__option-group ed-product-popup__option-group--unit-weight"><label class="ed-product-popup__option-label">בחירת משקל ליחידה</label><div class="ed-product-popup__unit-weight-toggle" data-option="unit_weight" role="radiogroup" aria-label="בחירת משקל ליחידה">';

            ocwsu.unit_weight_options.forEach((weight, idx) => {
                const showWeight = ocwsu.product_weight_units === 'kg' && weight < 1 ? weight * 1000 : weight;
                const label = ocwsu.product_weight_units === 'kg' && weight < 1 ? 'גרם' : (ocwsu.product_weight_units === 'kg' ? 'ק"ג' : 'גרם');
                optionsHTML += `<label class="ed-product-popup__toggle-btn"><input type="radio" name="popup_unit_weight" value="${weight}" ${idx === 0 ? 'checked' : ''}><span>${showWeight} ${label}</span></label>`;
            });

            optionsHTML += '</div></div>';
        }

          function normalizeOptionValue(value) {
              return String(value ?? '').trim().toLowerCase();
          }

          function buildVariationStockMap(variations) {
              const stockMap = {};

              if (!Array.isArray(variations)) return stockMap;

              variations.forEach((variation) => {
                  if (!variation || !variation.attributes) return;

                  const attrValues = Object.values(variation.attributes).filter(Boolean);
                  if (!attrValues.length) return;

                  const value = normalizeOptionValue(attrValues[0]);

                  stockMap[value] = !!variation.in_stock;
              });

              return stockMap;
          }

        // WooCommerce Attributes (for variations or simple product attributes)
        const variationStockMap = buildVariationStockMap(variations);
        console.log('variationStockMap:', variationStockMap);
        console.log('variations raw:', variations);
        if (attributes && attributes.length > 0) {
            attributes.forEach(attr => {
                if (!attr || !attr.options || attr.options.length === 0) return;

                const attrName = attr.name;

                console.log('attr:', attr);

                optionsHTML += `<div class="ed-product-popup__option-group"><label class="ed-product-popup__option-label">${attr.label || attr.name}</label><div class="ed-product-popup__radio-group" data-attribute="${attrName}">`;

                attr.options.forEach((option, idx) => {
                    const optionName = option?.name || option;
                    const optionSlug = option?.slug || sanitizeTitle(optionName);

                    const normalizedName = normalizeOptionValue(optionName);
                    const normalizedSlug = normalizeOptionValue(optionSlug);

                    const hasMatchByName = Object.prototype.hasOwnProperty.call(variationStockMap, normalizedName);
                    const hasMatchBySlug = Object.prototype.hasOwnProperty.call(variationStockMap, normalizedSlug);

                    const optionOutOfStock =
                        (hasMatchByName && variationStockMap[normalizedName] === false) ||
                        (hasMatchBySlug && variationStockMap[normalizedSlug] === false);

                    console.log('option debug:', {
                        option,
                        optionName,
                        optionSlug,
                        normalizedName,
                        normalizedSlug,
                        hasMatchByName,
                        hasMatchBySlug,
                        optionOutOfStock
                    });

                    const optionLabel = optionOutOfStock ? `${optionName} - אזל מהמלאי` : optionName;
                    const optionClass = optionOutOfStock ? ' is-out-of-stock' : '';

                    optionsHTML += `
                        <label class="ed-product-popup__radio${optionClass}">
                            <input type="radio" name="attribute_${attrName}" value="${optionSlug}" ${idx === 0 ? 'checked' : ''}>
                            <span class="ed-product-popup__radio-label">${optionLabel}</span>
                        </label>
                    `;
                });

                optionsHTML += '</div></div>';
            });
        }

        optionsHTML += '<div class="ed-product-popup__error" id="popup-option-error" style="display: none;">נא לבחור אפשרות</div></div>';

        const showProductNote = data.show_product_note !== false && data.show_product_note !== 'false' && data.show_product_note !== 0 && data.show_product_note !== '0';
        const defaultNoteLabel = 'הערה לקצב';
        const rawNoteLabel = (data.product_note_label && String(data.product_note_label).trim()) ? String(data.product_note_label).trim() : defaultNoteLabel;
        const noteLabelHtml = escapeHtml(rawNoteLabel);
        const cfg = typeof window !== 'undefined' ? window.ED_POPUP_CONFIG : null;
        let adminEditUrl = data.admin_edit_url && String(data.admin_edit_url).trim() ? String(data.admin_edit_url).trim() : '';
        if (!adminEditUrl && cfg && cfg.userCanEditProducts && cfg.adminEditProductUrlBase && data.id) {
            adminEditUrl = String(cfg.adminEditProductUrlBase) + String(data.id);
        }
        const adminEditHTML = adminEditUrl
            ? `<a class="ed-product-popup__admin-edit" href="${escapeHtml(adminEditUrl)}" target="_blank" rel="noopener noreferrer" aria-label="עריכת מוצר בלוח הבקרה" title="עריכת מוצר בלוח הבקרה"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" fill="currentColor"/></svg></a>`
            : '';
        const noteHTML = showProductNote ? ` 
      <div class="ed-product-popup__baker-note">
        <label class="ed-product-popup__baker-note-label" for="popup-product-note">${noteLabelHtml}</label>
        <input type="text" id="popup-product-note" name="product_note" class="ed-product-popup__baker-note-input" value="" autocomplete="off" />
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
          ${adminEditHTML}
          <div class="ed-product-popup__content">
            <div class="ed-product-popup__image">
              <img src="${data.image.url}" alt="${data.image.alt}" id="popup-product-image" loading="lazy">
            </div> 
            <div class="ed-product-popup__info"> 
              <h2 class="ed-product-popup__title">${data.name}</h2>
              ${afterTitleLoopHtml ? `<div class="ed-product-popup__after-title-loop">${afterTitleLoopHtml}</div>` : ''}
              <div class="ed-product-popup__price">
                ${ocwsu.average_weight_display ? `<span class="ed-product-popup__price-label">משקל ממוצע: ~${ocwsu.average_weight_display}</span><span class="ed-product-popup__price-sep">-</span>` : (ocwsu.average_weight && ocwsu.unit_weight_type !== 'variable' ? `<span class="ed-product-popup__price-label">משקל ממוצע: ${ocwsu.average_weight} ${ocwsu.average_weight_label}</span><span class="ed-product-popup__price-sep">-</span>` : '')}
 
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

