/**
 * Checkout Upsells JavaScript
 * Handles popup display, product interactions, and checkout flow
 */
(function($) {
  'use strict';

  const config = window.ED_CHECKOUT_UPSELLS || {};
  if (!config.ajaxUrl || !config.nonce) {
    return;
  }

  let $popup = $('#ed-checkout-upsells-popup');
  let isPopupOpen = false;
  let checkoutForm = null;

  /**
   * Ensure popup exists in DOM
   */
  function ensurePopup() {
    if ($popup.length) {
      return;
    }

    // Create popup dynamically if it doesn't exist
    const popupHtml = `
      <div class="ed-checkout-upsells-popup" id="ed-checkout-upsells-popup" style="display: none;">
        <div class="ed-checkout-upsells-popup__overlay"></div>
        <div class="ed-checkout-upsells-popup__content">
          <div class="ed-checkout-upsells-popup__header">
            <h3 class="ed-checkout-upsells-popup__title">${config.title || 'מוצרי קופה'}</h3>
            <button type="button" class="ed-checkout-upsells-popup__close" aria-label="סגור">×</button>
          </div>
          <div class="ed-checkout-upsells-popup__body">
            <div class="ed-checkout-upsells-popup__products" id="ed-checkout-upsells-products">
              <div class="ed-checkout-upsells-popup__loading">טוען מוצרים...</div>
            </div>
          </div>
          <div class="ed-checkout-upsells-popup__footer">
            <button type="button" class="ed-checkout-upsells-popup__skip button" id="ed-checkout-upsells-skip">
              דלג והמשך לתשלום
            </button>
          </div>
        </div>
      </div>
    `;
    
    $('body').append(popupHtml);
    $popup = $('#ed-checkout-upsells-popup');
  }

  /**
   * Initialize on page load
   */
  function init() {
    console.log('🔵 ED Checkout Upsells initialized');
    console.log('🔵 Config:', config);
    console.log('🔵 Popup element exists:', $('#ed-checkout-upsells-popup').length > 0);
    
    // Bind events first
    bindEvents();
    
    console.log('🔵 Events bound');
  }

  /**
   * Load upsell products via AJAX
   */
  function loadProducts() {
    const $container = $('#ed-checkout-upsells-products');
    if (!$container.length) return;

    $.ajax({
      url: config.ajaxUrl,
      type: 'POST',
      data: {
        action: 'ed_get_checkout_upsells',
        nonce: config.nonce,
      },
      success: function(response) {
        if (response.success && response.data && response.data.html) {
          $container.html(response.data.html);
          
          // Ensure all products have data-product-id attribute for popup handler
          setTimeout(function() {
            $container.find('li.product').each(function() {
              const $product = $(this);
              if (!$product.attr('data-product-id') && !$product.attr('data-product_id')) {
                // Try to get from link
                const $link = $product.find('a[href*="/product/"]').first();
                if ($link.length) {
                  const href = $link.attr('href');
                  const match = href.match(/\/product\/([^\/\?]+)/);
                  if (match) {
                    const slug = match[1];
                    // Try to extract ID from slug or use slug directly
                    // The popup handler can handle slugs too
                    $product.attr('data-product-id', slug);
                  }
                }
              }
            });
          }, 50);
          
          // Re-initialize WooCommerce scripts for the new products
          if (typeof $(document.body).trigger === 'function') {
            $(document.body).trigger('wc_fragment_refresh');
          }
        } else {
          $container.html('<p>' + (config.i18n.error || 'שגיאה בטעינת מוצרים') + '</p>');
        }
      },
      error: function() {
        $container.html('<p>' + (config.i18n.error || 'שגיאה בטעינת מוצרים') + '</p>');
      },
    });
  }

  /**
   * Show popup
   */
  function showPopup() {
    if (isPopupOpen) return;
    
    // Ensure popup exists
    ensurePopup();
    
    isPopupOpen = true;
    $popup.fadeIn(300);
    $('body').addClass('ed-checkout-upsells-popup-open');
    
    // Store checkout form reference
    checkoutForm = $('form.checkout');
  }

  /**
   * Hide popup
   */
  function hidePopup() {
    if (!isPopupOpen) return;
    
    isPopupOpen = false;
    $popup.fadeOut(300);
    $('body').removeClass('ed-checkout-upsells-popup-open');
  }

  /**
   * After upsell step (or when no upsells): SMS for guests, else go to checkout.
   */
  function proceedToCheckoutOrSms(checkoutUrl) {
    if (checkoutUrl) {
      window.edCheckoutUrl = checkoutUrl;
    }
    if (typeof oc_sms_auth !== 'undefined' && oc_sms_auth.is_logged_in == 0 &&
        typeof window.CheckoutSMSFlow !== 'undefined' && typeof window.CheckoutSMSFlow.showPopup === 'function') {
      window.CheckoutSMSFlow.showPopup();
      return;
    }
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
  }

  /**
   * Skip upsells and continue checkout
   */
  function skipUpsells() {
    hidePopup();
    
    // If we have a stored checkout URL (from floating cart link), SMS first for guests then redirect
    if (window.edCheckoutUrl) {
      proceedToCheckoutOrSms(window.edCheckoutUrl);
      return;
    }
    
    // Otherwise, submit the stored form (if on checkout page)
    if (checkoutForm && checkoutForm.length) {
      checkoutForm.off('submit', interceptSubmit);
      checkoutForm.submit();
    }
  }

  /**
   * Intercept form submission
   */
  function interceptSubmit(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    console.log('🔵 Checkout form submit - intercepting...');

    // Store form reference
    checkoutForm = $(this);

    // Check if upsells exist
    $.ajax({
      url: config.ajaxUrl,
      type: 'POST',
      data: {
        action: 'ed_check_checkout_upsells',
        nonce: config.nonce,
      },
      success: function(response) {
        console.log('✅ AJAX Response:', response);
        console.log('✅ Response success:', response.success);
        console.log('✅ Response data:', response.data);
        console.log('✅ Has upsells:', response.data && response.data.has_upsells);
        
        if (response.success && response.data && response.data.has_upsells) {
          console.log('✅ Showing popup with upsells');
          // Show popup and load products
          showPopup();
          loadProducts();
        } else {
          console.log('❌ No upsells, continuing with checkout');
          // No upsells, continue with checkout (SMS flow applies to floating-cart path, not form submit here)
          checkoutForm.off('submit', interceptSubmit);
          checkoutForm.submit();
        }
      },
      error: function(xhr, status, error) {
        console.error('❌ AJAX Error:', error);
        console.error('❌ Status:', status);
        console.error('❌ XHR:', xhr);
        // On error, continue with checkout
        checkoutForm.off('submit', interceptSubmit);
        checkoutForm.submit();
      },
    });

    return false;
  }

  /**
   * Add product to cart
   */
  function addToCart(productId, $btn) {
    if ($btn.is(':disabled')) return;

    const $product = $btn.closest('li.product');
    const originalText = $btn.text();
    
    // Disable button and show loading
    $btn.prop('disabled', true);
    $btn.addClass('loading');
    $btn.text(config.i18n.adding || 'מוסיף...');
    $product.addClass('is-loading');

    // Use product popup endpoint if available, otherwise use WooCommerce AJAX
    if (config.popupEnabled && config.popupConfig) {
      // Use product popup REST API
      $.ajax({
        url: config.popupConfig.addToCartUrl,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          product_id: productId,
          quantity: 1,
        }),
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', config.popupConfig.restNonce);
        },
        success: function(response) {
          if (response.success || !response.error) {
            handleAddToCartSuccess($btn, response);
          } else {
            handleAddToCartError($btn, response.errorMessage || config.i18n.error);
          }
        },
        error: function() {
          handleAddToCartError($btn, config.i18n.error);
        },
        complete: function() {
          $product.removeClass('is-loading');
        },
      });
    } else {
      // Fallback to WooCommerce AJAX
      $.ajax({
        url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
        type: 'POST',
        data: {
          product_id: productId,
          quantity: 1,
        },
        success: function(response) {
          if (response.error) {
            handleAddToCartError($btn, response.error_message || config.i18n.error);
          } else {
            handleAddToCartSuccess($btn, response);
          }
        },
        error: function() {
          handleAddToCartError($btn, config.i18n.error);
        },
        complete: function() {
          $product.removeClass('is-loading');
        },
      });
    }
  }

  /**
   * Handle successful add to cart
   */
  function handleAddToCartSuccess($btn, response) {
    // Show success state
    $btn.removeClass('loading').addClass('added');
    $btn.text(config.i18n.added || 'נוסף לסל!');

    // Update cart fragments if provided
    if (response.fragments && typeof response.fragments === 'object') {
      updateCartFragments(response.fragments);
    }

    // Trigger WooCommerce cart update event
    $(document.body).trigger('added_to_cart', [response.fragments || {}, $btn, response.cart_hash || '']);

    // Reset button after 2 seconds
    setTimeout(function() {
      $btn.removeClass('added');
      $btn.text('הוסף לסל');
      $btn.prop('disabled', false);
    }, 2000);
  }

  /**
   * Handle add to cart error
   */
  function handleAddToCartError($btn, message) {
    alert(message);
    $btn.prop('disabled', false);
    $btn.removeClass('loading');
    $btn.text('הוסף לסל');
  }

  /**
   * Open product popup
   */
  function openProductPopup(productId) {
    console.log('🔵 openProductPopup called with:', productId);
    
    if (!config.popupEnabled || !config.popupConfig) {
      console.log('⚠️ Popup not enabled, opening in new tab');
      // Fallback: open product page in new tab
      if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.product_url) {
        window.open(wc_add_to_cart_params.product_url.replace('%product_id%', productId), '_blank');
      } else {
        window.open('/product/' + productId, '_blank');
      }
      return;
    }

    // Try to use the REST API to open popup
    if (config.popupConfig && config.popupConfig.endpoint) {
      console.log('🔵 Using REST API to open popup');
      $.ajax({
        url: config.popupConfig.endpoint,
        method: 'GET',
        data: { id: productId },
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', config.popupConfig.restNonce);
        },
        success: function(response) {
          console.log('✅ Popup data received:', response);
          // The popup should handle this automatically via the product-popup.js handler
          // But we can also trigger it manually if needed
          if (window.ED_POPUP_CONFIG && typeof window.openProductPopup === 'function') {
            window.openProductPopup(productId);
          } else {
            // Try to find and click the product element to trigger the popup
            const $product = $('#ed-checkout-upsells-products li.product[data-product-id="' + productId + '"]');
            if ($product.length) {
              $product.trigger('click');
            } else {
              // Create a temporary element to trigger the popup
              const $temp = $('<div>').attr('data-product-id', productId).addClass('product');
              $('body').append($temp);
              $temp.trigger('click');
              $temp.remove();
            }
          }
        },
        error: function(xhr, status, error) {
          console.error('❌ Error loading popup data:', error);
          // Fallback: try to trigger via click
          const $product = $('#ed-checkout-upsells-products li.product[data-product-id="' + productId + '"]');
          if ($product.length) {
            $product.trigger('click');
          }
        },
      });
    } else {
      // Fallback: try to trigger via click on product element
      console.log('🔵 Trying to trigger popup via click');
      const $product = $('#ed-checkout-upsells-products li.product[data-product-id="' + productId + '"]');
      if ($product.length) {
        $product.trigger('click');
      } else {
        // Create a temporary element to trigger the popup
        const $temp = $('<div>').attr('data-product-id', productId).addClass('product');
        $('body').append($temp);
        $temp.trigger('click');
        $temp.remove();
      }
    }
  }

  /**
   * Update cart fragments
   */
  function updateCartFragments(fragments) {
    if (!fragments || typeof fragments !== 'object') {
      return;
    }

    $.each(fragments, function(selector, html) {
      const $element = $(selector);
      if ($element.length) {
        $element.replaceWith(html);
      }
    });

    // Trigger WooCommerce fragment refresh
    $(document.body).trigger('wc_fragment_refresh');
  }

  /**
   * Bind event handlers
   */
  function bindEvents() {
    // Intercept checkout form submission (on checkout page)
    $(document).on('submit', 'form.checkout', interceptSubmit);

    // Intercept checkout link click (from floating cart)
    $(document).on('click', '.ed-float-cart__btn--checkout', function(e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      console.log('🔵 Checkout button clicked - intercepting...'); 
      
      const $link = $(this); 
      const checkoutUrl = $link.attr('href');
      window.edCheckoutUrl = checkoutUrl;
      
      console.log('🔵 Checkout URL:', checkoutUrl);
      console.log('🔵 Config:', config);

      function continueUpsellFlow() {
        // Upsells first; SMS only after upsell step or when there are no upsells (see proceedToCheckoutOrSms)
        $.ajax({
          url: config.ajaxUrl,
          type: 'POST',
          data: {
            action: 'ed_check_checkout_upsells',
            nonce: config.nonce,
          },
          success: function(response) {
            console.log('✅ AJAX Response:', response);
            console.log('✅ Response success:', response.success);
            console.log('✅ Response data:', response.data);
            console.log('✅ Has upsells:', response.data && response.data.has_upsells);
            if (response.success && response.data && response.data.has_upsells) {
              console.log('✅ Showing popup with upsells');
              showPopup();
              loadProducts();
            } else {
              console.log('❌ No upsells, going to checkout');
              proceedToCheckoutOrSms(checkoutUrl);
            }
          },
          error: function(xhr, status, error) {
            console.error('❌ AJAX Error:', error);
            console.error('❌ Status:', status);
            console.error('❌ XHR:', xhr);
            proceedToCheckoutOrSms(checkoutUrl);
          },
        });
      }

      if (typeof window.delizOcwsCheckoutGateRun === 'function') {
        window.delizOcwsCheckoutGateRun(function(confirmed) {
          if (!confirmed) {
            if (typeof window.delizOpenOcwsDeliveryPopup === 'function') {
              window.delizOpenOcwsDeliveryPopup();
            }
            return;
          }
          continueUpsellFlow();
        });
        return false;
      }

      continueUpsellFlow();
      
      return false;
    });

    // Close popup button (X) - just close, don't proceed to checkout
    $(document).on('click', '.ed-checkout-upsells-popup__close', function(e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      hidePopup();
      return false;
    });

    // Overlay click - also just close
    $(document).on('click', '.ed-checkout-upsells-popup__overlay', function(e) {
      e.preventDefault();
      e.stopPropagation();
      hidePopup();
    });

    // Skip button
    $(document).on('click', '#ed-checkout-upsells-skip', function(e) {
      e.preventDefault();
      skipUpsells();
    });

    // Handle "Add to cart" buttons from WooCommerce product template
    // This works for products loaded via AJAX in the popup
    $(document).on('click', '#ed-checkout-upsells-products .add_to_cart_button', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $btn = $(this);
      const $product = $btn.closest('li.product');
      let productId = $btn.data('product_id') || $product.data('product-id') || $product.find('[data-product-id]').first().data('product-id');
      
      if (!productId) {
        // Try to get from href or other attributes
        const href = $btn.attr('href');
        if (href) {
          const match = href.match(/add-to-cart=(\d+)/);
          if (match) {
            productId = parseInt(match[1]);
          }
        }
      }
      
      if (productId) {
        // Check if product needs popup (variable product, etc.)
        const productType = $btn.data('product-type') || $product.data('product-type');
        
        if (productType === 'variable' || productType === 'grouped' || $btn.hasClass('product_type_variable')) {
          // Open product popup for variable/grouped products
          openProductPopup(productId);
        } else {
          // Simple product - add directly to cart
          addToCart(productId, $btn);
        }
      }
    });

    // The existing product-popup.js handler should work automatically
    // We just need to ensure products have data-product-id after they load
    // No need for custom click handler - let product-popup.js handle it

    // ESC key to skip
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && isPopupOpen) {
        // ESC = skip upsells
        skipUpsells();
      }
    });
  }

  // Initialize when DOM is ready
  $(document).ready(function() {
    init();
  });

  // Expose functions to global scope for use by other scripts
  window.showCheckoutUpsellsPopup = function() {
    const checkoutUrl = window.edCheckoutUrl;
    if (checkoutUrl) {
      window.edCheckoutUrl = checkoutUrl;
    }
    showPopup();
    loadProducts();
  };


})(jQuery);
