/**
 * Product Popup Core Functions
 * Handles initialization, opening/closing popup, and product click handling
 */

(function () {
    'use strict';

    const state = window.EDProductPopupState;

    /**
     * Initialize popup
     */
    function init() {
        // Listen for product clicks
        document.addEventListener('click', handleProductClick);

        // Listen for close button clicks
        document.addEventListener('click', window.EDProductPopupEvents?.handleCloseClick);

        // Listen for overlay clicks
        document.addEventListener('click', window.EDProductPopupEvents?.handleOverlayClick);

        // Listen for ESC key
        document.addEventListener('keydown', window.EDProductPopupEvents?.handleKeyDown);

        // Listen for add to cart button
        document.addEventListener('click', window.EDProductPopupCart?.handleAddToCart);

        // Listen for attribute/variation changes
        document.addEventListener('change', window.EDProductPopupVariations?.handleAttributeChange);

        // Check if URL contains product slug - open popup on page load
        checkUrlForProduct();

        // Listen for mini cart quantity controls
        document.addEventListener('click', window.EDProductPopupMiniCart?.handleMiniCartQuantityClick);

        // Listen for mini cart quantity input changes
        document.addEventListener('change', window.EDProductPopupMiniCart?.handleMiniCartQuantityChange);

        // Listen for edit button clicks in mini cart
        document.addEventListener('click', window.EDProductPopupMiniCart?.handleMiniCartEditClick);
    }

    /**
     * Check URL for product slug and open popup if found
     */
    async function checkUrlForProduct() {
        // Don't check if popup is already open
        if (state.isOpen || state.popupElement) return;

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

            state.popupData = await response.json();

            // Debug: log popup data to check ocwsu.weighable
            console.log('Popup Data:', state.popupData);
            console.log('ocwsu.weighable:', state.popupData.ocwsu?.weighable);

            // Check if product is in stock - if not, don't open popup
            if (!state.popupData.in_stock) {
                return;
            }

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

            // Initialize variation selection if variable product
            if (state.popupData.type === 'variable' && state.popupData.attributes.length > 0) {
                // Trigger updateVariationSelection after a short delay to ensure DOM is ready
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

            // Store trigger element for animation
            if (triggerElement) {
                state.popupElement.dataset.triggerElement = triggerElement.getBoundingClientRect();
            }

        } catch (error) {
            console.error('Error opening popup:', error);
        }
    }

    /**
     * Close popup
     */
    function closePopup() {
        if (!state.popupElement || !state.isOpen) return;

        state.popupElement.classList.remove('is-open');
        document.body.classList.remove('popup-open');

        setTimeout(() => {
            if (state.popupElement) {
                state.popupElement.remove();
                state.popupElement = null;
                state.popupData = null;
                state.isOpen = false;
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

    // Expose functions
    window.EDProductPopupCore = {
        init,
        openPopup,
        closePopup,
        handleProductClick,
        checkUrlForProduct
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

