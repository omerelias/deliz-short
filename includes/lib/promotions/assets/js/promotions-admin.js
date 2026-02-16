(function($) {
  'use strict';

  let currentStep = 1;
  let searchTimeout = null;

  $(document).ready(function() {
    initPromotionForm();
    initSearchFields();
    initDatePickers();
    initDeleteButtons();
    initToggleStatus();
  });

  /**
   * Initialize promotion form
   */
  function initPromotionForm() {
    const $form = $('#ed-promotion-form');
    if (!$form.length) {
      return;
    }

    // Show/hide type-specific fields
    $('input[name="promotion_type"]').on('change', function() {
      const type = $(this).val();
      $('.ed-promotion-type-fields').hide();
      if (type === 'discount') {
        $('.ed-promotion-type-discount').show();
      } else if (type === 'buy_x_pay_y') {
        $('.ed-promotion-type-buy_x_pay_y').show();
      }
    });

    // Trigger change on load if type is already selected
    const selectedType = $('input[name="promotion_type"]:checked').val();
    if (selectedType) {
      $('input[name="promotion_type"][value="' + selectedType + '"]').trigger('change');
    }

    // Next step
    $('.ed-next-step').on('click', function(e) {
      e.preventDefault();
      if (validateCurrentStep()) {
        goToStep(currentStep + 1);
      }
    });

    // Previous step
    $('.ed-prev-step').on('click', function(e) {
      e.preventDefault();
      goToStep(currentStep - 1);
    });

    // Form submit
    $form.on('submit', function(e) {
      e.preventDefault();
      savePromotion();
    });

    // End date checkbox
    $('#has_end_date').on('change', function() {
      if ($(this).is(':checked')) {
        $('.ed-end-date-field').show();
        $('#end_date').prop('required', true);
      } else {
        $('.ed-end-date-field').hide();
        $('#end_date').prop('required', false);
      }
    });
  }

  /**
   * Validate current step
   */
  function validateCurrentStep() {
    const $step = $('.ed-promotion-step[data-step="' + currentStep + '"]');
    
    if (currentStep === 1) {
      if (!$('input[name="promotion_type"]:checked').length) {
        alert('אנא בחר סוג מבצע');
        return false;
      }
    } else if (currentStep === 2) {
      if (!$('#promotion_name').val().trim()) {
        alert('אנא הזן שם מבצע');
        return false;
      }
      
      const type = $('input[name="promotion_type"]:checked').val();
      if (type === 'discount') {
        if (!$('#discount_percent').val() || parseFloat($('#discount_percent').val()) <= 0) {
          alert('אנא הזן אחוז הנחה תקין');
          return false;
        }
        if (!$('#target_id_discount').val()) {
          alert('אנא בחר מוצר או קטגוריה');
          return false;
        }
      } else if (type === 'buy_x_pay_y') {
        if (!$('#buy_kg').val() || parseFloat($('#buy_kg').val()) <= 0) {
          alert('אנא הזן כמות ק"ג תקינה');
          return false;
        }
        if (!$('#pay_amount').val() || parseFloat($('#pay_amount').val()) <= 0) {
          alert('אנא הזן סכום תשלום תקין');
          return false;
        }
        if (!$('#target_id_buy').val()) {
          alert('אנא בחר מוצר או קטגוריה');
          return false;
        }
      }
    } else if (currentStep === 3) {
      if (!$('#start_date').val()) {
        alert('אנא בחר תאריך התחלה');
        return false;
      }
      if ($('#has_end_date').is(':checked') && !$('#end_date').val()) {
        alert('אנא בחר תאריך סיום');
        return false;
      }
    }

    return true;
  }

  /**
   * Go to step
   */
  function goToStep(step) {
    if (step < 1 || step > 3) {
      return;
    }

    $('.ed-promotion-step').hide();
    $('.ed-promotion-step[data-step="' + step + '"]').show();
    currentStep = step;
  }

  /**
   * Save promotion
   */
  function savePromotion() {
    const $form = $('#ed-promotion-form');
    const $messages = $('.ed-promotion-form-messages');
    
    // Collect form data
    const type = $('input[name="promotion_type"]:checked').val();
    let targetType = '';
    let targetId = 0;

    if (type === 'discount') {
      targetType = $('#target_type_discount').val();
      targetId = parseInt($('#target_id_discount').val()) || 0;
    } else if (type === 'buy_x_pay_y') {
      targetType = $('#target_type_buy').val();
      targetId = parseInt($('#target_id_buy').val()) || 0;
    }

    const data = {
      promotion_id: parseInt($('input[name="promotion_id"]').val()) || 0,
      name: $('#promotion_name').val(),
      type: type,
      target_type: targetType,
      target_id: targetId,
      discount_percent: type === 'discount' ? parseFloat($('#discount_percent').val()) || 0 : 0,
      buy_kg: type === 'buy_x_pay_y' ? parseFloat($('#buy_kg').val()) || 0 : 0,
      pay_amount: type === 'buy_x_pay_y' ? parseFloat($('#pay_amount').val()) || 0 : 0,
      start_date: $('#start_date').val(),
      end_date: $('#end_date').val() || '',
      has_end_date: $('#has_end_date').is(':checked'),
      status: 'active',
    };

    // Show loading
    $messages.html('<div class="notice notice-info"><p>' + ED_PROMOTIONS.i18n.saving + '</p></div>');

    $.ajax({
      url: ED_PROMOTIONS.ajaxUrl,
      type: 'POST',
      data: {
        action: 'ed_promotion_save',
        nonce: ED_PROMOTIONS.nonce,
        data: data,
      },
      success: function(response) {
        if (response.success) {
          $messages.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
          setTimeout(function() {
            window.location.href = 'admin.php?page=ed-promotions';
          }, 1500);
        } else {
          $messages.html('<div class="notice notice-error"><p>' + (response.data?.message || ED_PROMOTIONS.i18n.error) + '</p></div>');
        }
      },
      error: function() {
        $messages.html('<div class="notice notice-error"><p>' + ED_PROMOTIONS.i18n.error + '</p></div>');
      },
    });
  }

  /**
   * Initialize search fields
   */
  function initSearchFields() {
    $('.ed-target-search').on('input', function() {
      const $field = $(this);
      const $results = $field.siblings('.ed-target-results');
      const $targetTypeField = $('#' + $field.data('target-type-field'));
      const $targetIdField = $field.siblings('input[type="hidden"]');
      const searchTerm = $field.val().trim();

      clearTimeout(searchTimeout);

      if (searchTerm.length < 2) {
        $results.html('').hide();
        return;
      }

      searchTimeout = setTimeout(function() {
        const targetType = $targetTypeField.val();
        const action = targetType === 'product' ? 'ed_promotion_search_products' : 'ed_promotion_search_categories';

        $.ajax({
          url: ED_PROMOTIONS.ajaxUrl,
          type: 'GET',
          data: {
            action: action,
            nonce: ED_PROMOTIONS.nonce,
            term: searchTerm,
          },
          success: function(response) {
            if (response.success && response.data.length > 0) {
              let html = '<ul class="ed-search-results">';
              response.data.forEach(function(item) {
                html += '<li data-id="' + item.id + '">' + item.text;
                if (item.price) {
                  html += ' - ' + item.price;
                }
                html += '</li>';
              });
              html += '</ul>';
              $results.html(html).show();
            } else {
              $results.html('<ul class="ed-search-results"><li>לא נמצאו תוצאות</li></ul>').show();
            }
          },
        });
      }, 300);
    });

    // Handle result selection
    $(document).on('click', '.ed-search-results li', function() {
      const $item = $(this);
      const $field = $item.closest('.ed-form-field').find('.ed-target-search');
      const $targetIdField = $field.siblings('input[type="hidden"]');
      const id = $item.data('id');
      const text = $item.text().trim();

      $targetIdField.val(id);
      $field.val(text);
      $item.closest('.ed-target-results').hide();
    });

    // Hide results on outside click
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.ed-form-field').length) {
        $('.ed-target-results').hide();
      }
    });

    // Update search when target type changes
    $('select[id^="target_type"]').on('change', function() {
      const $field = $(this).closest('.ed-form-field').siblings().find('.ed-target-search');
      $field.val('');
      $field.siblings('input[type="hidden"]').val('');
      $field.siblings('.ed-target-results').hide();
    });
  }

  /**
   * Initialize date pickers
   */
  function initDatePickers() {
    $('.ed-datepicker').datepicker({
      dateFormat: 'yy-mm-dd',
      changeMonth: true,
      changeYear: true,
      yearRange: '-0:+1',
    });

    $('.ed-select-date').on('click', function(e) {
      e.preventDefault();
      $(this).siblings('.ed-datepicker').datepicker('show');
    });
  }

  /**
   * Initialize delete buttons
   */
  function initDeleteButtons() {
    $('.ed-delete-promotion').on('click', function(e) {
      e.preventDefault();
      
      if (!confirm(ED_PROMOTIONS.i18n.confirmDelete)) {
        return;
      }

      const $button = $(this);
      const promotionId = $button.data('promotion-id');

      $.ajax({
        url: ED_PROMOTIONS.ajaxUrl,
        type: 'POST',
        data: {
          action: 'ed_promotion_delete',
          nonce: ED_PROMOTIONS.nonce,
          promotion_id: promotionId,
        },
        success: function(response) {
          if (response.success) {
            $button.closest('tr').fadeOut(function() {
              $(this).remove();
            });
          } else {
            alert(response.data?.message || ED_PROMOTIONS.i18n.error);
          }
        },
        error: function() {
          alert(ED_PROMOTIONS.i18n.error);
        },
      });
    });
  }

  /**
   * Initialize toggle status
   */
  function initToggleStatus() {
    $('.ed-toggle-status').on('click', function(e) {
      e.preventDefault();
      
      const $button = $(this);
      const promotionId = $button.data('promotion-id');
      const currentStatus = $button.data('current-status');
      const newStatus = currentStatus === 'disabled' ? 'active' : 'disabled';

      $.ajax({
        url: ED_PROMOTIONS.ajaxUrl,
        type: 'POST',
        data: {
          action: 'ed_promotion_toggle_status',
          nonce: ED_PROMOTIONS.nonce,
          promotion_id: promotionId,
          status: newStatus,
        },
        success: function(response) {
          if (response.success) {
            location.reload();
          } else {
            alert(response.data?.message || ED_PROMOTIONS.i18n.error);
          }
        },
        error: function() {
          alert(ED_PROMOTIONS.i18n.error);
        },
      });
    });
  }

})(jQuery);

