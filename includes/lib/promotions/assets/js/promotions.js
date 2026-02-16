(function($) {
  'use strict';

  $(document).ready(function() {
    // Update promotion messages when cart is updated
    if (typeof wc_add_to_cart_params !== 'undefined') {
      $(document.body).on('added_to_cart', function() {
        updatePromotionMessages();
      });
    }

    // Update on cart page load
    if ($('body').hasClass('woocommerce-cart')) {
      updatePromotionMessages();
    }

    // Add badge to product popup
    addBadgeToProductPopup();
  });

  /**
   * Update promotion messages in cart
   */
  function updatePromotionMessages() {
    // This will be handled server-side via the hooks
    // This is just a placeholder for any client-side updates needed
  }

  /**
   * Add promotion badge to product popup
   */
  function addBadgeToProductPopup() {
    // Watch for popup opening and add badge
    const observer = new MutationObserver(function(mutations) {
      const popup = document.getElementById('ed-product-popup');
      if (popup && popup.classList.contains('is-open')) {
        const productId = popup.querySelector('[data-product-id]')?.getAttribute('data-product-id') ||
                         popup.querySelector('#popup-add-to-cart')?.getAttribute('data-product-id');
        
        if (productId && !popup.querySelector('.ed-promotion-badge')) {
          // Fetch promotion badge via AJAX
          $.ajax({
            url: ED_PROMOTIONS_FRONT.ajaxUrl,
            type: 'POST',
            data: {
              action: 'ed_get_product_promotion_badge',
              nonce: ED_PROMOTIONS_FRONT.nonce,
              product_id: productId,
            },
            success: function(response) {
              if (response.success && response.data.badge_html) {
                const imageContainer = popup.querySelector('.ed-product-popup__image');
                if (imageContainer) {
                  imageContainer.insertAdjacentHTML('afterbegin', response.data.badge_html);
                }
              }
            },
          });
        }
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class'],
    });
  }

})(jQuery);

