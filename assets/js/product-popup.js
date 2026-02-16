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

    

    // Check if URL contains product slug - open popup on page load

    checkUrlForProduct();

    

    // Listen for mini cart quantity controls

    document.addEventListener('click', handleMiniCartQuantityClick);

    

    // Listen for mini cart quantity input changes

    document.addEventListener('change', handleMiniCartQuantityChange);

    

    // Listen for edit button clicks in mini cart

    document.addEventListener('click', handleMiniCartEditClick);

  }

  

  /**

   * Check URL for product slug and open popup if found

   */

  async function checkUrlForProduct() {

    // Don't check if popup is already open

    if (isOpen || popupElement) return;

    

    const url = new URL(window.location.href);

    const pathname = url.pathname;

    

    // Check for /cat/{category}/product/{slug}/ pattern

    const catProductMatch = pathname.match(/^\/cat\/([^\/]+)\/product\/([^\/]+)\/?$/);

    if (catProductMatch) {

      const categorySlug = catProductMatch[1];

      const productSlug = catProductMatch[2];

      

      // Get product ID from slug

      try {

        const apiUrl = window.location.origin + '/wp-json/wp/v2/product?slug=' + encodeURIComponent(productSlug);

        const response = await fetch(apiUrl);

        if (response.ok) {

          const products = await response.json();

          if (products.length) {

            const productId = products[0].id;

            

            // Update URL to category only (for SEO - stay in category context)

            const categoryUrl = window.location.origin + '/cat/' + categorySlug + '/';

            history.replaceState({category: categorySlug}, '', categoryUrl);

            

            // Open popup after a short delay to ensure page is loaded

            setTimeout(() => {

              openPopup(productId);

            }, 100);

          }

        }

      } catch (err) {

        console.warn('Could not resolve product slug from URL:', err);

      }

      return;

    }

    

    // Check for standard WooCommerce product URL: /product/{slug}/

    const productMatch = pathname.match(/^\/product\/([^\/]+)\/?$/);

    if (productMatch) {

      const productSlug = productMatch[1];

      

      // Get product ID from slug

      try {

        const apiUrl = window.location.origin + '/wp-json/wp/v2/product?slug=' + encodeURIComponent(productSlug);

        const response = await fetch(apiUrl);

        if (response.ok) {

          const products = await response.json();

          if (products.length) {

            const productId = products[0].id;

            const product = products[0];

            

            // Try to get category from product

            let categorySlug = null;

            if (product.product_cat && product.product_cat.length > 0) {

              // Get category slug from REST API response

              const catId = product.product_cat[0];

              const catResponse = await fetch(window.location.origin + '/wp-json/wp/v2/product_cat/' + catId);

              if (catResponse.ok) {

                const cat = await catResponse.json();

                categorySlug = cat.slug;

              }

            }

            

            // Update URL to category if available, otherwise stay on home

            if (categorySlug) {

              const categoryUrl = window.location.origin + '/cat/' + categorySlug + '/';

              history.replaceState({category: categorySlug}, '', categoryUrl);

            } else {

              // Fallback to home page

              const homeUrl = window.location.origin + '/';

              history.replaceState({}, '', homeUrl);

            }

            

            // Open popup after a short delay to ensure page is loaded

            setTimeout(() => {

              openPopup(productId);

            }, 100);

          }

        }

      } catch (err) {

        console.warn('Could not resolve product slug from URL:', err);

      }

      return;

    }

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

    

    // Check if clicked on out of stock product BEFORE processing

    const clickedEl = e.target;

    const potentialProductEl = clickedEl.closest('li.product, .product, .woocommerce-loop-product, .woocommerce-loop-product__link');

    if (potentialProductEl) {

      // Check if product has out-of-stock indicators

      if (potentialProductEl.classList.contains('outofstock') || 

          potentialProductEl.classList.contains('product-out-of-stock') ||

          potentialProductEl.querySelector('.outofstock') ||

          potentialProductEl.querySelector('.stock.out-of-stock') ||

          potentialProductEl.querySelector('[class*="out-of-stock"]')) {

        e.preventDefault();

        e.stopPropagation();

        return;

      }

    }

    

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

    let productSlug = null;

    

    if (isNaN(id)) {

      // Try to get ID from slug via REST API

      try {

        productSlug = productId;

        const apiUrl = window.location.origin + '/wp-json/wp/v2/product?slug=' + encodeURIComponent(productId);

        const response = await fetch(apiUrl);

        if (response.ok) {

          const products = await response.json();

          if (products.length) {

            id = products[0].id;

            // Get product slug from response if not already set

            if (!productSlug) productSlug = products[0].slug;

          } else {

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

    } else {

      // If we have ID, get slug from product link

      const productLink = productEl?.querySelector('a.woocommerce-loop-product__link, a[href*="/product/"]');

      if (productLink) {

        const hrefMatch = productLink.href.match(/\/product\/([^\/]+)/);

        if (hrefMatch) productSlug = hrefMatch[1];

      }

    }

    

    // Get current category from URL or try to get from product

    let categorySlug = null;

    const url = new URL(window.location.href);

    const pathname = url.pathname;

    

    // Check if we're in a category page

    const catMatch = pathname.match(/^\/cat\/([^\/]+)/);

    if (catMatch) {

      categorySlug = catMatch[1];

    } else {

      // Try to get category from product element or REST API

      if (id && !isNaN(id)) {

        try {

          const apiUrl = window.location.origin + '/wp-json/wp/v2/product/' + id;

          const response = await fetch(apiUrl);

          if (response.ok) {

            const product = await response.json();

            if (product.product_cat && product.product_cat.length > 0) {

              const catId = product.product_cat[0];

              const catResponse = await fetch(window.location.origin + '/wp-json/wp/v2/product_cat/' + catId);

              if (catResponse.ok) {

                const cat = await catResponse.json();

                categorySlug = cat.slug;

              }

            }

          }

        } catch (err) {

          console.warn('Could not get product category:', err);

        }

      }

    }

    

    // Update URL to /cat/{category}/product/{slug}/ if we have both

    if (categorySlug && productSlug) {

      const newUrl = window.location.origin + '/cat/' + categorySlug + '/product/' + productSlug + '/';

      history.pushState({category: categorySlug, product: productSlug}, '', newUrl);

    } else if (productSlug) {

      // Fallback: just update to product URL if no category

      const newUrl = window.location.origin + '/product/' + productSlug + '/';

      history.pushState({product: productSlug}, '', newUrl);

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

      

      // Debug: log popup data to check ocwsu.weighable

      console.log('Popup Data:', popupData);

      console.log('ocwsu.weighable:', popupData.ocwsu?.weighable);

      

      // Check if product is in stock - if not, don't open popup

      if (!popupData.in_stock) {

        return;

      }

      

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

        // Trigger updateVariationSelection after a short delay to ensure DOM is ready

        setTimeout(() => {

          updateVariationSelection();

          validateAddToCartButton();

        }, 50);

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



  /**

   * Initialize quantity inputs

   */

  function syncQuantityLabelsFromToggle() {
    if (!popupElement) return;

    const toggleButtons = popupElement.querySelectorAll('.ed-product-popup__toggle-btn');
    if (!toggleButtons || toggleButtons.length === 0) return;

    const unitsText = popupElement.querySelector('.ed-product-popup__toggle-btn[data-mode="units"] span')?.textContent?.trim();
    const weightText = popupElement.querySelector('.ed-product-popup__toggle-btn[data-mode="weight"] span')?.textContent?.trim();

    const unitsLabel = popupElement.querySelector('#popup-quantity-units-container .ed-product-popup__qty-label');
    const weightLabel = popupElement.querySelector('#popup-quantity-weight-container .ed-product-popup__qty-label');

    if (unitsLabel && unitsText) unitsLabel.textContent = unitsText;
    if (weightLabel && weightText) weightLabel.textContent = weightText;
  }

  function initQuantityInputs() {

    if (!popupElement) return;

    

    // Toggle buttons for units/weight switching

    const toggleButtons = popupElement.querySelectorAll('.ed-product-popup__toggle-btn');

    toggleButtons.forEach(btn => {

      btn.addEventListener('click', function() {

        const mode = this.dataset.mode;

        if (!mode) return;

        

        // Update active state

        toggleButtons.forEach(b => b.classList.remove('is-active'));

        this.classList.add('is-active');

        

        // Show/hide appropriate quantity input

        const unitsContainer = popupElement.querySelector('#popup-quantity-units-container');

        const weightContainer = popupElement.querySelector('#popup-quantity-weight-container');

        

        if (mode === 'units') {

          if (unitsContainer) unitsContainer.style.display = '';

          if (weightContainer) weightContainer.style.display = 'none';

          popupElement.dataset.quantityMode = 'units';
          syncQuantityLabelsFromToggle();

        } else if (mode === 'weight') {

          if (unitsContainer) unitsContainer.style.display = 'none';

          if (weightContainer) weightContainer.style.display = '';

          popupElement.dataset.quantityMode = 'weight';
          syncQuantityLabelsFromToggle();

        }

        

        // Update ocwsu fields

        updateOcwsuHiddenFields();

        validateAddToCartButton();

      });

    });

    

    // Set initial mode

    if (toggleButtons.length > 0) {

      popupElement.dataset.quantityMode = 'units'; // Default to units
      syncQuantityLabelsFromToggle();

    }

    

    // Quantity buttons

    popupElement.querySelectorAll('.ed-product-popup__qty-btn').forEach(btn => {

      btn.addEventListener('click', function() {

        const action = this.dataset.action;

        const input = this.closest('.ed-product-popup__quantity-input').querySelector('input');

        const min = parseFloat(input.min) || 1;

        let value = parseFloat(input.value) || min;

        

        // Check if this is a weight input (has step attribute on input, not button)

        const isWeightInput = input.id === 'popup-quantity-weight';

        

        // For weight inputs, always use step of 1 (as per user requirement)

        // For other inputs, use the step from data attribute or default to 1

        let step = 1;

        if (!isWeightInput) {

          step = parseFloat(this.dataset.step) || 1;

        }

        

        if (action === 'increase') {

          value += step;

        } else if (action === 'decrease' && value > min) {

          value -= step;

          // Ensure value doesn't go below minimum

          if (value < min) {

            value = min;

          }

        }

        

        // Round to step precision (for weight, step is 1, so round to 1 decimal)

        const decimals = isWeightInput ? 1 : (step.toString().split('.')[1]?.length || 0);

        value = parseFloat(value.toFixed(decimals));

        

        input.value = value;

        input.dispatchEvent(new Event('change', { bubbles: true }));

        updateOcwsuHiddenFields();

        validateAddToCartButton();

      });

    });

    

    // Quantity input changes

    popupElement.querySelectorAll('.ed-product-popup__qty-input').forEach(input => {

      input.addEventListener('change', function() {

        updateOcwsuHiddenFields();

        validateAddToCartButton();

      });

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

    

    console.group('🔍 [DEBUG] updateVariationSelection');

    console.log('popupData:', popupData);

    console.log('popupData.attributes:', popupData.attributes);

    console.log('popupData.variations:', popupData.variations);

    

    // Build selected attributes object

    // Key format: attr.name (e.g., 'pa_color' or 'custom_attr') - WITHOUT 'attribute_' prefix

    // This matches the format in variation.attributes from PHP

    const selectedAttributes = {};

    const checkedInputs = popupElement.querySelectorAll('input[name^="attribute_"]:checked');

    console.log('Checked inputs:', checkedInputs);

    

    checkedInputs.forEach(radio => {

      // Remove 'attribute_' prefix to match variation.attributes format

      const attrName = radio.name.replace('attribute_', '');

      selectedAttributes[attrName] = radio.value;

      console.log(`Selected: ${radio.name} = ${radio.value} (key: ${attrName})`);

    });

    

    console.log('selectedAttributes:', selectedAttributes);

    

    // Check if all required attributes are selected

    const allAttributesSelected = popupData.attributes.every(attr => {

      const found = popupElement.querySelector(`input[name="attribute_${attr.name}"]:checked`);

      console.log(`Checking attribute ${attr.name}:`, found ? 'SELECTED' : 'NOT SELECTED');

      return found;

    });

    

    console.log('allAttributesSelected:', allAttributesSelected);

    

    if (!allAttributesSelected) {

      // Not all attributes selected yet

      console.log('❌ Not all attributes selected');

      popupElement.dataset.variationId = '';

      console.groupEnd();

      return;

    }

    

    // Find matching variation

    // IMPORTANT: PHP removes 'pa_' prefix from variation attributes keys

    // So if attr.name is 'pa_צורת-חיתוך', variation.attributes key is 'צורת-חיתוך'

    console.log('🔍 Searching for matching variation...');

    const ignoredAttrsForMatch = []; // attributes that variation leaves empty and should be ignored when submitting

    const matchingVariation = popupData.variations.find(variation => {

      console.log(`Checking variation ${variation.id}:`, variation.attributes);

      console.log(`  Variation attributes keys:`, Object.keys(variation.attributes));

      console.log(`  Variation attributes entries:`, Object.entries(variation.attributes));

      

      // Check if variation has all selected attributes

      const matches = popupData.attributes.every(attr => {

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

      const priceEl = popupElement.querySelector('.ed-product-popup__price-value');

      if (priceEl) {

        priceEl.innerHTML = matchingVariation.price_html;

      }

      

      // Store variation ID

      popupElement.dataset.variationId = matchingVariation.id;

      console.log('✅ Set variationId:', matchingVariation.id);

      

      // Store ignored attributes (those with empty variation values) for submit step

      if (ignoredAttrsForMatch.length > 0) {

        popupElement.dataset.ignoredAttributes = JSON.stringify(ignoredAttrsForMatch);

        console.log('✅ Ignored attributes for submit:', ignoredAttrsForMatch);

      } else {

        delete popupElement.dataset.ignoredAttributes;

      }

      

      // If get_weight_from_variation is enabled, store the variation weight and update ocwsu fields

      if (popupData.ocwsu?.get_weight_from_variation && matchingVariation.weight) {

        popupElement.dataset.variationWeight = matchingVariation.weight;

        console.log('✅ Set variationWeight:', matchingVariation.weight);

        // Update ocwsu fields immediately when variation changes (to update unitWeight)

        updateOcwsuHiddenFields();

      }

      

      // Update stock status

      if (!matchingVariation.in_stock) {

        const addBtn = popupElement.querySelector('#popup-add-to-cart');

        if (addBtn) {

          addBtn.disabled = true;

          const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');

          if (btnText) btnText.textContent = 'אזל מהמלאי';

        }

      } else {

        // Re-enable button if it was disabled

        const addBtn = popupElement.querySelector('#popup-add-to-cart');

        if (addBtn) {

          addBtn.disabled = false;

          validateAddToCartButton();

        }

      }

    } else {

      // No matching variation found

      console.log('❌ No matching variation found');

      console.log('Available variations:', popupData.variations.map(v => ({

        id: v.id,

        attributes: v.attributes

      })));

      popupElement.dataset.variationId = '';

      // Clear variation weight if get_weight_from_variation is enabled

      if (popupData.ocwsu?.get_weight_from_variation) {

        delete popupElement.dataset.variationWeight;

        updateOcwsuHiddenFields();

      }

      // Clear ignored attributes list

      delete popupElement.dataset.ignoredAttributes;

    }

    

    console.groupEnd();

  }



  /**

   * Update ocwsu hidden fields based on selections

   */

  function updateOcwsuHiddenFields() {

    if (!popupElement || !popupData) return;

    

    const ocwsu = popupData.ocwsu || {};

    

    // Check if product can toggle between units and weight

    const canToggle = ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight;

    const currentMode = popupElement.dataset.quantityMode || (ocwsu.sold_by_units ? 'units' : 'weight');

    

    // Get selected unit weight

    let unitWeight = 0;

    

    // If get_weight_from_variation is enabled, get weight from selected variation

    if (ocwsu.get_weight_from_variation && popupData.type === 'variable') {

      const variationId = popupElement.dataset.variationId;

      if (variationId) {

        const variation = popupData.variations.find(v => v.id == variationId);

        if (variation && variation.weight) {

          // Variation weight is already in the correct units (from PHP)

          unitWeight = parseFloat(variation.weight);

          console.log('Using variation weight:', unitWeight, 'from variation:', variationId);

        }

      }

    } else {

      // Normal flow: get from radio button or default

      const unitWeightRadio = popupElement.querySelector('input[name="popup_unit_weight"]:checked');

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

        qtyInput = popupElement.querySelector('#popup-quantity-units');

        quantity = qtyInput ? parseFloat(qtyInput.value) : 1;

        quantityInUnits = quantity;

        quantityInWeightUnits = 0;

      } else {

        qtyInput = popupElement.querySelector('#popup-quantity-weight');

        quantity = qtyInput ? parseFloat(qtyInput.value) : (ocwsu.min_weight || 0.5);

        quantityInUnits = 0;

        quantityInWeightUnits = quantity;

      }

    } else {

      // Product has only one mode

      qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');

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

    popupElement.dataset.ocwsuUnit = (canToggle && currentMode === 'units') || ocwsu.sold_by_units ? 'unit' : (ocwsu.product_weight_units || 'kg');

    popupElement.dataset.ocwsuUnitWeight = unitWeight || 0;

    popupElement.dataset.ocwsuQuantityInUnits = quantityInUnits;

    popupElement.dataset.ocwsuQuantityInWeightUnits = quantityInWeightUnits;

    popupElement.dataset.quantityInKg = quantityInKg;

  }



  /**

   * Validate and enable/disable add to cart button

   */

  function validateAddToCartButton() {

    if (!popupElement) return;

    

    const addBtn = popupElement.querySelector('#popup-add-to-cart');

    if (!addBtn) return;

    

    // Check if product is in stock first

    if (!popupData || !popupData.in_stock) {

      addBtn.disabled = true;

      addBtn.classList.add('is-disabled');

      const btnText = addBtn.querySelector('.ed-product-popup__add-btn-text');

      if (btnText) btnText.textContent = 'אזל מהמלאי';

      return;

    }

    

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

      if (btnText) btnText.textContent = popupData.isEditMode ? 'עדכן בסל' : 'הוסף לסל';

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

    

    // Check stock before sending request

    if (!popupData || !popupData.in_stock) {

      const productName = popupData?.name || 'המוצר';

      showPopupError(`לא ניתן להוסיף את "${productName}" לסל הקניות - המוצר אזל מהמלאי.`);

      return;

    }

    

    // For variable products, MUST have variation selected

    if (popupData.type === 'variable') {

      console.group('🔍 [DEBUG] handleAddToCart - Variable Product Check');

      console.log('popupData.type:', popupData.type);

      console.log('popupData.attributes:', popupData.attributes);

      

      const variationId = popupElement.dataset.variationId;

      console.log('Current variationId:', variationId);

      

      // Check selected attributes

      const selectedAttributes = popupElement.querySelectorAll('input[name^="attribute_"]:checked');

      console.log('Selected attributes count:', selectedAttributes.length);

      selectedAttributes.forEach(radio => {

        console.log(`  ${radio.name} = ${radio.value}`);

      });

      

      // Check if variation is selected

      if (!variationId) {

        console.log('⚠️ No variationId, trying to update...');

        // Try to update variation selection one more time

        updateVariationSelection();

        const updatedVariationId = popupElement.dataset.variationId;

        console.log('Updated variationId:', updatedVariationId);

        

        if (!updatedVariationId) {

          console.error('❌ Still no variationId after update');

          console.groupEnd();

          showPopupError('נא לבחור את כל האפשרויות הנדרשות');

          return;

        }

      }

      

      console.log('✅ Variation ID found:', variationId || popupElement.dataset.variationId);

      console.groupEnd();

      

      // Check variation stock

      if (variationId) {

        const variation = popupData.variations.find(v => v.id == variationId);

        if (variation && !variation.in_stock) {

          const productName = popupData.name || 'המוצר';

          showPopupError(`לא ניתן להוסיף את "${productName}" לסל הקניות - המוצר אזל מהמלאי.`);

          return;

        }

      }

    }

    

    // Get form data

    const formData = new FormData();

    

    // Product ID or Variation ID

    const variationId = popupElement.dataset.variationId;

    if (popupData.type === 'variable') {

      // For variable products, MUST send variation_id and attributes

      if (!variationId) {

        showPopupError('נא לבחור את כל האפשרויות הנדרשות');

        return;

      }

      

      formData.append('variation_id', variationId);

      formData.append('product_id', popupData.id);

      

      // Add selected attributes (WooCommerce format: attribute_pa_xxx for taxonomy, attribute_xxx for custom)

      const selectedAttributes = popupElement.querySelectorAll('input[name^="attribute_"]:checked');

      

      if (selectedAttributes.length === 0) {

        showPopupError('נא לבחור את כל האפשרויות הנדרשות');

        return;

      }

      

      // Attributes that should be ignored for this variation (e.g. size ANY with empty value)

      let ignoredAttrs = [];

      if (popupElement.dataset.ignoredAttributes) {

        try {

          ignoredAttrs = JSON.parse(popupElement.dataset.ignoredAttributes) || [];

        } catch (e) {

          ignoredAttrs = [];

        }

      }

      

      selectedAttributes.forEach(radio => {

        // Base attribute name, e.g. 'pa_גודל'

        const baseName = radio.name.replace(/^attribute_/, '');

        if (ignoredAttrs.includes(baseName)) {

          // Don't send attributes that the chosen variation leaves empty / ANY.

          // This prevents server-side validation like \"גודל הוא שדה חובה\" כשאין ערך אמיתי בווריאציה.

          return;

        }

        // The name is already in format "attribute_pa_xxx" or "attribute_xxx" from the HTML

        // WooCommerce expects exactly this format

        formData.append(radio.name, radio.value);

      });

    } else {

      // Simple product

      formData.append('product_id', popupData.id);

    }

    

    // Add WooCommerce nonce if available (for security)

    if (window.wc_add_to_cart_params?.wc_add_to_cart_nonce) {

      formData.append('wc_add_to_cart_nonce', window.wc_add_to_cart_params.wc_add_to_cart_nonce);

    }

    

    // ocwsu fields - update before using (must be called first)

    updateOcwsuHiddenFields();

    

    // Check if product can toggle between units and weight

    const canToggle = popupData.ocwsu?.weighable && popupData.ocwsu?.sold_by_units && popupData.ocwsu?.sold_by_weight;

    const currentMode = popupElement.dataset.quantityMode || (popupData.ocwsu?.sold_by_units ? 'units' : 'weight');

    

    // Get quantity from active input based on mode

    let qtyInput = null;

    if (canToggle) {

      // Get quantity from active input based on mode

      qtyInput = currentMode === 'units' 

        ? popupElement.querySelector('#popup-quantity-units')

        : popupElement.querySelector('#popup-quantity-weight');

    } else {

      qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');

    }

    

    const quantity = qtyInput ? parseFloat(qtyInput.value) : 1;

    

    // For oc-woo-sale-units plugin, quantity should be in the base unit (kg for weighable products)

    // Always use quantityInKg for weighable products (backend expects kg)

    const ocwsu = popupData.ocwsu || {};

    let quantityToSend = quantity;

    

    if (ocwsu.weighable) {

      // For weighable products, always send quantity in kg

      quantityToSend = parseFloat(popupElement.dataset.quantityInKg || quantity);

    }

    

    // Send quantity (WooCommerce expects this)

    formData.append('quantity', quantityToSend);

    

    // ocwsu fields (for the plugin)

    formData.append('ocwsu_unit', popupElement.dataset.ocwsuUnit || 'unit');

    formData.append('ocwsu_unit_weight', popupElement.dataset.ocwsuUnitWeight || '0');

    formData.append('ocwsu_quantity_in_units', popupElement.dataset.ocwsuQuantityInUnits || '0');

    formData.append('ocwsu_quantity_in_weight_units', popupElement.dataset.ocwsuQuantityInWeightUnits || '0');

    

    // Product note (always send if field exists and has value)

    const productNote = popupElement.querySelector('#popup-product-note');

    if (productNote && productNote.value.trim()) {

      formData.append('product_note', productNote.value.trim());

    }

    

    // Debug: Log all FormData entries

    console.group('🔍 [DEBUG] Add to Cart Request');

    console.log('📦 Product Data:', {

      productId: popupData.id,

      productType: popupData.type,

      variationId: popupElement.dataset.variationId || 'none',

      quantity: quantity,

      ocwsu: {

        unit: popupElement.dataset.ocwsuUnit,

        unitWeight: popupElement.dataset.ocwsuUnitWeight,

        quantityInUnits: popupElement.dataset.ocwsuQuantityInUnits,

        quantityInWeightUnits: popupElement.dataset.ocwsuQuantityInWeightUnits,

        quantityInKg: popupElement.dataset.quantityInKg

      }

    });

    

    // Log FormData entries

    const formDataEntries = {};

    for (const [key, value] of formData.entries()) {

      formDataEntries[key] = value;

    }

    console.log('📋 FormData:', formDataEntries);

    

    // Check if we're in edit mode (updating existing cart item)

    const isEditMode = popupData.isEditMode && popupData.cartItemKey;

    

    // If editing, remove the old cart item first

    if (isEditMode) {

      try {

        // Remove old cart item via WooCommerce AJAX

        const removeUrl = window.wc_add_to_cart_params?.wc_ajax_url?.toString().replace('%%endpoint%%', 'remove_from_cart') || 

                         '/?wc-ajax=remove_from_cart';

        

        const removeFormData = new FormData();

        removeFormData.append('cart_item_key', popupData.cartItemKey);

        

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

      console.groupEnd();

      console.error('❌ [ERROR] Add to Cart Failed:', {

        error: error,

        message: error.message,

        stack: error.stack

      });

      

      // Show error in popup (if not already shown)

      if (!popupElement.querySelector('.ed-product-popup__error-message')) {

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

    if (!popupElement) return;

    

    // Remove existing error messages

    const existingErrors = popupElement.querySelectorAll('.ed-product-popup__error-message');

    existingErrors.forEach(el => el.remove());

    

    // Create error message element

    const errorEl = document.createElement('div');

    errorEl.className = 'ed-product-popup__error-message';

    errorEl.textContent = message;

    

    // Insert before add to cart button

    const addBtn = popupElement.querySelector('#popup-add-to-cart');

    if (addBtn && addBtn.parentElement) {

      addBtn.parentElement.insertBefore(errorEl, addBtn);

    } else {

      // Fallback: insert in footer

      const footer = popupElement.querySelector('.ed-product-popup__footer');

      if (footer) {

        footer.insertBefore(errorEl, footer.firstChild);

      }

    }

    

    // Scroll to error

    errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    

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

    if (!popupElement) return;

    

    const image = popupElement.querySelector('#popup-product-image');

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

    

    // Ensure URL is clean (no product slug) after closing

    const url = new URL(window.location.href);

    const pathname = url.pathname;

    

    // If URL still contains product, clean it up

    if (pathname.match(/\/product\//)) {

      // Try to get category from current URL or default to home

      const catMatch = pathname.match(/^\/cat\/([^\/]+)\//);

      if (catMatch) {

        const categoryUrl = window.location.origin + '/cat/' + catMatch[1] + '/';

        history.replaceState({category: catMatch[1]}, '', categoryUrl);

      } else {

        const homeUrl = window.location.origin + '/';

        history.replaceState({}, '', homeUrl);

      }

    }

  }



  // Initialize on DOM ready

  if (document.readyState === 'loading') {

    document.addEventListener('DOMContentLoaded', init);

  } else {

    init();

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

      

      popupData = await response.json();

      

      // Store cart item data for later use

      popupElement = null;

      popupData.cartItemKey = cartItemData.cart_item_key || null;

      popupData.cartItemData = cartItemData;

      popupData.isEditMode = true;

      

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

      

      // Pre-fill with cart item data

      prefillPopupWithCartData(cartItemData);

      

      // Initialize variation selection if variable product

      if (popupData.type === 'variable' && popupData.attributes.length > 0) {

        setTimeout(() => {

          updateVariationSelection();

          validateAddToCartButton();

        }, 50);

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

      

    } catch (error) {

      console.error('Error opening popup for edit:', error);

    }

  }

  

  /**

   * Pre-fill popup with cart item data

   */

  function prefillPopupWithCartData(cartItemData) {

    if (!popupElement || !cartItemData) return;

    

    // Set quantity

    const qtyInput = popupElement.querySelector('#popup-quantity-units, #popup-quantity-weight, #popup-quantity');

    if (qtyInput && cartItemData.quantity) {

      qtyInput.value = cartItemData.quantity;

      qtyInput.dispatchEvent(new Event('change', { bubbles: true }));

    }

    

    // Set variation if exists

    if (cartItemData.variation_id && popupData.type === 'variable') {

      popupElement.dataset.variationId = cartItemData.variation_id;

      

      // Set variation attributes

      if (cartItemData.variation && typeof cartItemData.variation === 'object') {

        Object.keys(cartItemData.variation).forEach(attrKey => {

          const attrValue = cartItemData.variation[attrKey];

          const radio = popupElement.querySelector(`input[name="attribute_${attrKey}"][value="${attrValue}"]`);

          if (radio) {

            radio.checked = true;

            radio.dispatchEvent(new Event('change', { bubbles: true }));

          }

        });

      }

    }

    

    // Set product note

    const noteInput = popupElement.querySelector('#popup-product-note');

    if (noteInput && cartItemData.product_note) {

      noteInput.value = cartItemData.product_note;

    }

    

    // Set ocwsu data if exists
    if (cartItemData.ocwsu_quantity_in_units > 0 || cartItemData.ocwsu_quantity_in_weight_units > 0) {
      // Store ocwsu data in popup element for later use
      popupElement.dataset.ocwsuQuantityInUnits = cartItemData.ocwsu_quantity_in_units || 0;
      popupElement.dataset.ocwsuQuantityInWeightUnits = cartItemData.ocwsu_quantity_in_weight_units || 0;
      
      // If product has ocwsu toggle, set the correct mode
      const ocwsu = popupData.ocwsu || {};
      if (ocwsu.weighable && ocwsu.sold_by_units && ocwsu.sold_by_weight) {
        // Determine mode based on which quantity is set
        if (cartItemData.ocwsu_quantity_in_units > 0) {
          popupElement.dataset.quantityMode = 'units';
          const unitsInput = popupElement.querySelector('#popup-quantity-units');
          if (unitsInput) {
            unitsInput.value = cartItemData.ocwsu_quantity_in_units;
            unitsInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        } else if (cartItemData.ocwsu_quantity_in_weight_units > 0) {
          popupElement.dataset.quantityMode = 'weight';
          const weightInput = popupElement.querySelector('#popup-quantity-weight');
          if (weightInput) {
            weightInput.value = cartItemData.ocwsu_quantity_in_weight_units;
            weightInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      }
    }

    // Sync unit label from toggle (יח' / ק"ג)
    syncQuantityLabelsFromToggle();

    // Update ocwsu fields

    updateOcwsuHiddenFields();

    validateAddToCartButton();

  }

  

  // Expose for external use

  window.EDProductPopup = {

    open: openPopup,

    close: closePopup

  };



})();



