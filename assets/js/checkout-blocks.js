jQuery(function($) {
    'use strict';
    console.log('[Checkout Blocks] Script loaded');

    const ORDER_COMMENTS_STORAGE_KEY = 'deliz_checkout_order_comments';

    // Initialize checkout blocks
    function initCheckoutBlocks() {  
        console.log('[Checkout Blocks] initCheckoutBlocks called');
        
        const $blocks = $('.checkout-block');
        const $headers = $('.checkout-block__header');
        const $editButtons = $('.checkout-block__edit');
        

        // Log each block's state
        $blocks.each(function(index) {
            const $block = $(this);
            const blockType = $block.data('block') || $block.attr('class');
            const isOpen = $block.hasClass('is-open');
            const isClosed = $block.hasClass('is-closed');
            console.log(`[Checkout Blocks] Block ${index + 1}: type=${blockType}, is-open=${isOpen}, is-closed=${isClosed}`);
        });
        
        // Remove existing handlers to prevent duplicates
        $('.checkout-block__header, .checkout-block__summary-row').off('click.checkoutBlocks');
        $('.checkout-block__edit').off('click.checkoutBlocks');
        
        // Summary row or header click - open popup or toggle block
        $(document).on('click.checkoutBlocks', '.checkout-block__summary-row, .checkout-block__header', function(e) {
            if ($(e.target).closest('.checkout-block__edit').length) return;
            
            const $block = $(this).closest('.checkout-block');
            if ($block.hasClass('checkout-block--order')) return;
            
            const popupId = $block.data('popup-id');
            if (popupId) {
                e.preventDefault();
                openBlockPopup($block);
            } else {
                toggleBlock($block);
            }
        });
        
        // Edit button click - open popup or block
        $('.checkout-block__edit').on('click.checkoutBlocks', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $block = $(this).closest('.checkout-block');
            const popupId = $block.data('popup-id');
            if (popupId) {
                openBlockPopup($block);
            } else {
                openBlock($block);
            }
        });
        
        // Popup close: overlay, close button, and confirm button (shipping block)
        $(document).on('click.checkoutBlocks', '.checkout-block-popup__overlay, .checkout-block-popup__close, .checkout-block-popup__confirm', function(e) {
            e.preventDefault();
            const $popup = $(this).closest('.checkout-block-popup');
            if (!$popup.length) return;
            const popupId = $popup.attr('id');
            const $block = $('.checkout-block[data-popup-id="' + popupId + '"]');
            if ($block.length) {
                closeBlockPopup($block);
                if (!$block.hasClass('checkout-block--order')) {
                    updateBlockSummary($block);
                }
                if ($block.hasClass('checkout-block--shipping')) {
                    $(document.body).trigger('update_checkout');
                }
            }
        });
        
        // Order block: "X מוצרים" opens order details popup
        $(document).on('click.checkoutBlocks', '.checkout-order-compact__products-link', function(e) {
            e.preventDefault();
            const popupId = $(this).data('popup-id');
            const $block = $('.checkout-block[data-popup-id="' + popupId + '"]');
            if ($block.length) openBlockPopup($block);
        });
        
        // Initialize summaries for all blocks
        $('.checkout-block').each(function() {
            const $block = $(this);
            if (!$block.hasClass('is-open')) {
                updateBlockSummary($block);
            }
        });
        
        console.log('[Checkout Blocks] Initialization complete');
    }
    
    function toggleBlock($block) {
        const isOpen = $block.hasClass('is-open');
        const blockType = $block.data('block') || 'unknown';
        
        console.log('[Checkout Blocks] toggleBlock called for:', blockType, 'isOpen:', isOpen);
        
        if (isOpen) {
            console.log('[Checkout Blocks] Closing block:', blockType);
            closeBlock($block);
        } else {
            console.log('[Checkout Blocks] Opening block:', blockType);
            openBlock($block);
        }
    }
    
    function openBlockPopup($block) {
        const popupId = $block.data('popup-id');
        if (!popupId) return;
        const $popup = $('#' + popupId);
        if (!$popup.length) return;
        $popup.attr('aria-hidden', 'false').addClass('is-open').fadeIn(200);
        $('body').addClass('checkout-block-popup-open');
    }
    
    function closeBlockPopup($block) {
        const popupId = $block.data('popup-id');
        if (!popupId) return;
        const $popup = $('#' + popupId);
        if (!$popup.length) return;
        $popup.attr('aria-hidden', 'true').removeClass('is-open').fadeOut(200);
        $('body').removeClass('checkout-block-popup-open');
    }
    
    function openBlock($block) {
        const popupId = $block.data('popup-id');
        if (popupId) {
            openBlockPopup($block);
            return;
        }
        const blockType = $block.data('block') || 'unknown';
        $block.removeClass('is-closed').addClass('is-open');
        $block.attr('aria-expanded', 'true');
        $block.find('.checkout-block__edit').hide();
        try {
            $('html, body').animate({ scrollTop: $block.offset().top - 100 }, 300);
        } catch (e) {}
    }
    
    function closeBlock($block) {
        const popupId = $block.data('popup-id');
        if (popupId) {
            closeBlockPopup($block);
            updateBlockSummary($block);
            return;
        }
        $block.removeClass('is-open').addClass('is-closed');
        $block.attr('aria-expanded', 'false');
        $block.find('.checkout-block__edit').show();
        updateBlockSummary($block);
    }

    /**
     * City select options use value = location code; label may repeat the code (e.g. "שם … code").
     * For home delivery summary we only want the readable city name, not the code.
     */
    function ocwsStripCityCodeFromSummaryLabel($citySelect, label) {
        if (!$citySelect || !$citySelect.length) {
            return (label || '').trim();
        }
        let raw = (label || '').trim();
        if (!raw || !$citySelect.is('select')) {
            return raw;
        }
        let code = $citySelect.val();
        if (code === undefined || code === null || code === '') {
            return raw;
        }
        code = String(code).trim();
        if (!code) {
            return raw;
        }
        if (raw.length > code.length && raw.slice(-code.length) === code) {
            return raw.slice(0, -code.length).replace(/[\s,\-|]+$/g, '').trim();
        }
        const esc = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const paren = new RegExp('\\s*\\(' + esc + '\\)\\s*$');
        if (paren.test(raw)) {
            return raw.replace(paren, '').trim();
        }
        const suffix = new RegExp('[\\s\\-|]+' + esc + '\\s*$');
        if (suffix.test(raw)) {
            return raw.replace(suffix, '').trim();
        }
        return raw;
    }

    function updateBlockSummary($block) {
        const blockType = $block.data('block');
        const $summary = $block.find('.checkout-block__summary');

        if (!$summary.length) return;

        let summaryText = '';

        try {
            switch(blockType) {
                case 'billing': {
                    const $firstName = $('#billing_first_name');
                    const $lastName = $('#billing_last_name');
                    const $phone = $('#billing_phone');
                    const $city = $('#billing_city');
                    const $street = $('#billing_street');
                    const $houseNum = $('#billing_house_num');
                    const $address1 = $('#billing_address_1');
                    const $otherRecipientCheckbox = $('input[name=\"ocws_other_recipient\"]');
                    const $recipientFirstName = $('#ocws_recipient_firstname');
                    const $recipientLastName = $('#ocws_recipient_lastname');
                    const $recipientPhone = $('#ocws_recipient_phone');

                    let firstName = ($firstName.length && $firstName.is('input, select, textarea')) ? $firstName.val() : '';
                    let lastName = ($lastName.length && $lastName.is('input, select, textarea')) ? $lastName.val() : '';
                    let phone = ($phone.length && $phone.is('input, select, textarea')) ? $phone.val() : '';

                    const isOtherRecipient = $otherRecipientCheckbox.length && $otherRecipientCheckbox.is(':checked');
                    if (isOtherRecipient) {
                        const otherFirstName = ($recipientFirstName.length && $recipientFirstName.is('input, select, textarea')) ? $recipientFirstName.val() : '';
                        const otherLastName = ($recipientLastName.length && $recipientLastName.is('input, select, textarea')) ? $recipientLastName.val() : '';
                        const otherPhone = ($recipientPhone.length && $recipientPhone.is('input, select, textarea')) ? $recipientPhone.val() : '';

                        if (otherFirstName || otherLastName || otherPhone) {
                            firstName = otherFirstName || firstName;
                            lastName = otherLastName || lastName;
                            phone = otherPhone || phone;
                        }
                    }

                    // Get city - try to get Hebrew name from select option text
                    let city = '';
                    if ($city.length) {
                        if ($city.is('select')) {
                            city = $city.find('option:selected').text() || $city.val() || '';
                        } else if ($city.is('input, textarea')) {
                            city = $city.val() || '';
                        }
                    }

                    // Build address - use street and house_num if available
                    const street = ($street.length && $street.is('input, select, textarea')) ? $street.val() : '';
                    const houseNum = ($houseNum.length && $houseNum.is('input, select, textarea')) ? $houseNum.val() : '';
                    let address1 = ($address1.length && $address1.is('input, select, textarea')) ? $address1.val() : '';

                    // Remove English city names from address_1
                    if (address1 && typeof address1 === 'string') {
                        const englishCities = ['Rishon LeZion', 'Rishon LeZiyyon', 'Tel Aviv', 'Jerusalem', 'Haifa'];
                        englishCities.forEach(function(engCity) {
                            address1 = address1.replace(new RegExp(engCity, 'gi'), '').trim();
                        });
                    }

                    const addressParts = [];
                    if (street) addressParts.push(street);
                    if (houseNum) addressParts.push(houseNum);
                    if (address1 && !street) addressParts.push(address1);
                    if (city) addressParts.push(city);
                    const address = addressParts.join(' ');

                    let mainLine = '';
                    if (firstName || lastName) {
                        mainLine = (firstName + ' ' + lastName).trim();
                    }
                    if (!mainLine) {
                        mainLine = 'לא הוזנו פרטים';
                    }

                    summaryText = '<strong>עבור:</strong> ' + mainLine;

                    // Update unified summary line under order block
                    $('.js-checkout-summary-billing').html(mainLine);
                    break;
                }

                case 'shipping': {
                    // Get shipping method
                    const $shippingMethodInput = $('input[name^="shipping_method"]:checked');
                    let shippingMethod = '';
                    let shippingMethodValue = '';
                    if ($shippingMethodInput.length) {
                        const $label = $shippingMethodInput.closest('label');
                        shippingMethod = $label.length ? $label.text().trim() : '';
                        shippingMethodValue = $shippingMethodInput.val() || '';
                    }

                    // Check if pickup or shipping
                    const isPickup = shippingMethodValue && (shippingMethodValue.indexOf('local_pickup') !== -1 || shippingMethodValue.indexOf('oc_woo_local_pickup_method') !== -1);
                    const isShippingMethod = shippingMethodValue && shippingMethodValue.indexOf('oc_woo_advanced_shipping_method') !== -1;

                    // Get address - check if shipping to different address
                    const $shipToOtherCheckbox = $('#ship-to-different-address-checkbox');
                    const isShippingToOther = $shipToOtherCheckbox.length && $shipToOtherCheckbox.is(':checked');

                    const $billingGoogleAuto = $('form.checkout input[name="billing_google_autocomplete"]');
                    const billingGoogleAutocomplete = ($billingGoogleAuto.length && $billingGoogleAuto.is('input'))
                        ? String($billingGoogleAuto.val() || '').trim()
                        : '';

                    let addressString = '';
                    if (!isPickup && billingGoogleAutocomplete) {
                        // Home delivery: use full Google autocomplete line (same as user sees in the field)
                        addressString = billingGoogleAutocomplete;
                    } else {
                        let addressParts = [];
                        if (isShippingToOther) {
                            // Shipping address
                            const $shippingStreet = $('#shipping_street');
                            const $shippingHouseNum = $('#shipping_house_num');
                            const $shippingAddress1 = $('#shipping_address_1');
                            const $shippingCity = $('#shipping_city');

                            const street = ($shippingStreet.length && $shippingStreet.is('input, select, textarea')) ? $shippingStreet.val() : '';
                            const houseNum = ($shippingHouseNum.length && $shippingHouseNum.is('input, select, textarea')) ? $shippingHouseNum.val() : '';
                            let address1 = ($shippingAddress1.length && $shippingAddress1.is('input, select, textarea')) ? $shippingAddress1.val() : '';

                            // Remove English city names from address_1
                            if (address1 && typeof address1 === 'string') {
                                const englishCities = ['Rishon LeZion', 'Rishon LeZiyyon', 'Tel Aviv', 'Jerusalem', 'Haifa'];
                                englishCities.forEach(function(engCity) {
                                    address1 = address1.replace(new RegExp(engCity, 'gi'), '').trim();
                                });
                            }

                            // Get city - try to get Hebrew name from select option text
                            let city = '';
                            if ($shippingCity.length) {
                                if ($shippingCity.is('select')) {
                                    city = $shippingCity.find('option:selected').text() || $shippingCity.val() || '';
                                } else if ($shippingCity.is('input, textarea')) {
                                    city = $shippingCity.val() || '';
                                }
                            }
                            if (city && !isPickup) {
                                city = ocwsStripCityCodeFromSummaryLabel($shippingCity, city);
                            }

                            if (street) addressParts.push(street);
                            if (houseNum) addressParts.push(houseNum);
                            if (address1 && !street) addressParts.push(address1);
                            if (city) addressParts.push(city);
                        } else {
                            // Billing address
                            const $billingStreet = $('#billing_street');
                            const $billingHouseNum = $('#billing_house_num');
                            const $billingAddress1 = $('#billing_address_1');
                            const $billingCity = $('#billing_city');

                            const street = ($billingStreet.length && $billingStreet.is('input, select, textarea')) ? $billingStreet.val() : '';
                            const houseNum = ($billingHouseNum.length && $billingHouseNum.is('input, select, textarea')) ? $billingHouseNum.val() : '';
                            let address1 = ($billingAddress1.length && $billingAddress1.is('input, select, textarea')) ? $billingAddress1.val() : '';

                            // Remove English city names from address_1
                            if (address1 && typeof address1 === 'string') {
                                const englishCities = ['Rishon LeZion', 'Rishon LeZiyyon', 'Tel Aviv', 'Jerusalem', 'Haifa'];
                                englishCities.forEach(function(engCity) { 
                                    address1 = address1.replace(new RegExp(engCity, 'gi'), '').trim();
                                });
                            }

                            // Get city - try to get Hebrew name from select option text
                            let city = '';
                            if ($billingCity.length) {
                                if ($billingCity.is('select')) {
                                    city = $billingCity.find('option:selected').text() || $billingCity.val() || '';
                                } else if ($billingCity.is('input, textarea')) {
                                    city = $billingCity.val() || '';
                                }
                            }
                            if (city && !isPickup) {
                                city = ocwsStripCityCodeFromSummaryLabel($billingCity, city);
                            }

                            if (street) addressParts.push(street);
                            if (houseNum) addressParts.push(houseNum);
                            if (address1 && !street) addressParts.push(address1);
                            if (city) addressParts.push(city);
                        }
                        addressString = addressParts.join(' ');
                    }

                    // Get date/time info
                    let dateTimeInfo = '';
                    let pickupBranch = '';
                    if (isPickup) {
                        // Pickup: branch, date, time
                        const $pickupAffId = $('#ocws_lp_pickup_aff_id');
                        const $pickupAffName = $('input[name="ocws_lp_pickup_aff_name"]');
                        const $pickupDate = $('#ocws_lp_pickup_date');
                        const $pickupSlotStart = $('#ocws_lp_pickup_slot_start');
                        const $pickupSlotEnd = $('#ocws_lp_pickup_slot_end');

                        if ($pickupAffId.length && $pickupAffId.is('select')) {
                            pickupBranch = $pickupAffId.find('option:selected').text() || '';
                        }
                        if (!pickupBranch && $pickupAffName.length && $pickupAffName.is('input')) {
                            pickupBranch = $pickupAffName.val() || '';
                        }

                        let pickupDate = '';
                        if ($pickupDate.length && $pickupDate.is('input')) {
                            pickupDate = $pickupDate.val() || '';
                        }
                        if (!pickupDate) {
                            const $pickupDateInput = $('input[name="ocws_lp_pickup_date"]');
                            if ($pickupDateInput.length && $pickupDateInput.is('input')) {
                                pickupDate = $pickupDateInput.val() || '';
                            }
                        }

                        let pickupSlotStart = '';
                        if ($pickupSlotStart.length && $pickupSlotStart.is('input')) {
                            pickupSlotStart = $pickupSlotStart.val() || '';
                        }
                        if (!pickupSlotStart) {
                            const $pickupSlotStartInput = $('input[name="ocws_lp_pickup_slot_start"]');
                            if ($pickupSlotStartInput.length && $pickupSlotStartInput.is('input')) {
                                pickupSlotStart = $pickupSlotStartInput.val() || '';
                            }
                        }

                        let pickupSlotEnd = '';
                        if ($pickupSlotEnd.length && $pickupSlotEnd.is('input')) {
                            pickupSlotEnd = $pickupSlotEnd.val() || '';
                        }
                        if (!pickupSlotEnd) {
                            const $pickupSlotEndInput = $('input[name="ocws_lp_pickup_slot_end"]');
                            if ($pickupSlotEndInput.length && $pickupSlotEndInput.is('input')) {
                                pickupSlotEnd = $pickupSlotEndInput.val() || '';
                            }
                        }

                        if (pickupDate) {
                            let line = pickupDate;
                            let timeRange = '';
                            if (pickupSlotStart) {
                                timeRange = pickupSlotStart;
                                if (pickupSlotEnd && pickupSlotEnd !== pickupSlotStart) {
                                    timeRange += ' - ' + pickupSlotEnd;
                                }
                            }
                            if (timeRange) {
                                line += ' | ' + timeRange;
                            }
                            dateTimeInfo = '<br>' + line;
                        }
                    } else if (isShippingMethod) {
                        // Shipping: date, time
                        const $shippingDate = $('#order_expedition_date');
                        const $shippingSlotStart = $('#order_expedition_slot_start');
                        const $shippingSlotEnd = $('#order_expedition_slot_end');

                        let shippingDate = '';
                        if ($shippingDate.length && $shippingDate.is('input')) {
                            shippingDate = $shippingDate.val() || '';
                        }
                        if (!shippingDate) {
                            const $shippingDateInput = $('input[name="order_expedition_date"]');
                            if ($shippingDateInput.length && $shippingDateInput.is('input')) {
                                shippingDate = $shippingDateInput.val() || '';
                            }
                        }

                        let shippingSlotStart = '';
                        if ($shippingSlotStart.length && $shippingSlotStart.is('input')) {
                            shippingSlotStart = $shippingSlotStart.val() || '';
                        }
                        if (!shippingSlotStart) {
                            const $shippingSlotStartInput = $('input[name="order_expedition_slot_start"]');
                            if ($shippingSlotStartInput.length && $shippingSlotStartInput.is('input')) {
                                shippingSlotStart = $shippingSlotStartInput.val() || '';
                            }
                        }

                        let shippingSlotEnd = '';
                        if ($shippingSlotEnd.length && $shippingSlotEnd.is('input')) {
                            shippingSlotEnd = $shippingSlotEnd.val() || '';
                        }
                        if (!shippingSlotEnd) {
                            const $shippingSlotEndInput = $('input[name="order_expedition_slot_end"]');
                            if ($shippingSlotEndInput.length && $shippingSlotEndInput.is('input')) {
                                shippingSlotEnd = $shippingSlotEndInput.val() || '';
                            }
                        }

                        if (shippingDate) {
                            let line = shippingDate;
                            let timeRange = '';
                            if (shippingSlotStart) {
                                timeRange = shippingSlotStart;
                                if (shippingSlotEnd && shippingSlotEnd !== shippingSlotStart) {
                                    timeRange += ' - ' + shippingSlotEnd;
                                }
                            }
                            if (timeRange) {
                                line += ' | ' + timeRange;
                            }
                            dateTimeInfo = '<br>' + line;
                        }
                    }

                    // Build summary
                    let mainLine = '';
                    if (isPickup && pickupBranch) {
                        mainLine = pickupBranch;
                    } else if (addressString) {

                        mainLine = addressString;
                    } else if (shippingMethod && shippingMethod !== 'משלוח') {
                        mainLine = shippingMethod;
                    }
                    if (!mainLine) {
                        mainLine = 'לא נבחר משלוח';
                    }

                    const shippingLabel = isPickup ? 'איסוף מ:' : 'משלוח ל:';
                    summaryText = '<strong>' + shippingLabel + '</strong> ' + mainLine + (dateTimeInfo || '');

                    // Update unified summary line under order block
                    $('.js-checkout-summary-shipping-label').text(shippingLabel);
                    $('.js-checkout-summary-shipping').html(mainLine + (dateTimeInfo || ''));
                    break;
                }

                case 'notes': {
                    const $orderComments = $('#order_comments');
                    const notes = ($orderComments.length && $orderComments.is('input, textarea')) ? $orderComments.val() : '';
                    let shortNotes, unifiedText;
                    const $unifiedLabel = $('.checkout-summary-line--notes .checkout-summary-line__label');
                    const $unifiedValue = $('.js-checkout-summary-notes');
                    const $unifiedEdit  = $('.checkout-summary-line--notes .checkout-summary-line__edit');
                    if (notes) {
                        shortNotes  = notes.length > 50 ? notes.substring(0, 50) + '...' : notes;
                        if ($unifiedLabel.length) {
                            $unifiedLabel.text('הערות להזמנה');
                        }
                        if ($unifiedEdit.length) {
                            $unifiedEdit.show();
                        }
                    } else {
                        shortNotes  = 'אין הערות';
                        if ($unifiedLabel.length) {
                            $unifiedLabel.text('');
                        }
                        if ($unifiedEdit.length) {
                            $unifiedEdit.hide();
                        }
                    }
                    summaryText = '<strong>הערות להזמנה (אופציונלי): </strong> ' + shortNotes;

                    // Update unified summary line under order block
                    if ($unifiedValue.length) {
                        if (notes) {
                            $unifiedValue.empty().text(shortNotes);
                            $unifiedValue.removeClass('is-add-note');
                        } else {
                            unifiedText = 'הוספת הערה להזמנה &gt;';
                            $unifiedValue
                                .empty()
                                .html(unifiedText)
                                .addClass('is-add-note');
                        }
                    }
                    break;
                }
            }
        } catch (e) {
            console.error('Error updating block summary:', e);
            summaryText = '';
        }

        $summary.html(summaryText);
    }

    // Update summaries on field change
    $(document).on('change', '#billing_first_name, #billing_last_name, #billing_phone, #billing_email, #billing_city, #billing_address_1, #ocws_recipient_firstname, #ocws_recipient_lastname, #ocws_recipient_phone, input[name=\"ocws_other_recipient\"]', function() {
        const $block = $('.checkout-block--billing');
        if (!$block.hasClass('is-open')) {
            updateBlockSummary($block);
        }
    });

    // Update shipping summary on any address or shipping method change
    $(document).on('change', 'input[name^="shipping_method"], #ship-to-different-address-checkbox, #billing_city, #billing_street, #billing_house_num, #billing_address_1, #billing_enter_code, #billing_google_autocomplete, input[name="billing_google_autocomplete"], #shipping_city, #shipping_street, #shipping_house_num, #shipping_address_1, #order_expedition_date, #order_expedition_slot_start, #order_expedition_slot_end, #ocws_lp_pickup_aff_id, #ocws_lp_pickup_date, #ocws_lp_pickup_slot_start, #ocws_lp_pickup_slot_end, input[name="ocws_lp_pickup_aff_id"], input[name="ocws_lp_pickup_date"], input[name="ocws_lp_pickup_slot_start"], input[name="ocws_lp_pickup_slot_end"]', function() {
        const $block = $('.checkout-block--shipping');
        if (!$block.hasClass('is-open')) {
            updateBlockSummary($block);
        }
    });

    $(document).on('input', '#billing_google_autocomplete, input[name="billing_google_autocomplete"]', function() {
        const $block = $('.checkout-block--shipping');
        if (!$block.hasClass('is-open')) {
            updateBlockSummary($block);
        }
    });

    // Also listen to WooCommerce checkout updates
    $(document.body).on('updated_checkout', function() {
        $('.checkout-block').each(function() {
            const $block = $(this);
            if (!$block.hasClass('is-open')) {
                updateBlockSummary($block);
            }
        });
    });

    $(document).on('change', '#order_comments', function() {
        const $block = $('.checkout-block--notes');
        if (!$block.hasClass('is-open')) {
            updateBlockSummary($block);
        }
        syncUnifiedNotesLine();
    });

    // Set initial state for blocks
    function setInitialBlockStates() {
        console.log('[Checkout Blocks] setInitialBlockStates called');

        $('.checkout-block').each(function(index) {
            const $block = $(this);
            const blockType = $block.data('block') || $block.attr('class');

            // Skip order block
            if ($block.hasClass('checkout-block--order')) {
                return;
            }

            const hasIsOpen = $block.hasClass('is-open');
            const hasIsClosed = $block.hasClass('is-closed');


            // If block doesn't have is-open or is-closed, set default state
            if (!hasIsOpen && !hasIsClosed) {
                // Billing block starts open, others start closed
                if ($block.hasClass('checkout-block--billing')) {
                    $block.addClass('is-open').attr('aria-expanded', 'true');
                } else {
                    $block.addClass('is-closed').attr('aria-expanded', 'false');
                }
            } else {
            }
        });

        console.log('[Checkout Blocks] setInitialBlockStates complete');
    }

    // Hide payment methods list when only one method is available
    function updatePaymentMethodsVisibility() {
        const $list = $('.wc_payment_methods.payment_methods.methods');
        if (!$list.length) return;
        const $items = $list.children('li.wc_payment_method');
        if ($items.length === 1) {
            $list.addClass('single-payment-method');
        } else {
            $list.removeClass('single-payment-method');
        }
    }

    // Update place order button text from selected payment method (data-order_button_text)
    function updatePlaceOrderButtonText() {
        const $checked = $('input[name="payment_method"]:checked');
        const $btn = $('#place_order');

        console.log('[Checkout Blocks] updatePlaceOrderButtonText called', {
            hasButton: !!$btn.length,
            hasChecked: !!$checked.length
        });

        if (!$btn.length || !$checked.length) {
            return;
        }

        const id = $checked.attr('id');
        const gateway = $checked.val();
        const dataText = $checked.attr('data-order_button_text');
        const currentVal = $btn.val();
        const currentText = $btn.text();
        const text = dataText || currentVal || currentText;

        console.log('[Checkout Blocks] payment method -> button text', {
            id,
            gateway,
            dataText,
            currentVal,
            currentText,
            finalText: text
        });

        if (text) {
            $btn.val(text).text(text);
        }
    }

    // Inline single-line order notes input (replaces notes popup)
    function beginInlineNotesInput(initialValue) {
        const $value = $('.checkout-summary-line--notes .js-checkout-summary-notes');
        const $comments = $('#order_comments');
        if (!$value.length || !$comments.length) return;

        // Replace value with input
        $value
            .empty()
            .append('<input type="text" class="checkout-notes-inline-input" placeholder="הוספת הערה להזמנה" autocomplete="off" />')
            .addClass('is-add-note');

        const $input = $value.find('.checkout-notes-inline-input');
        $input.val(initialValue || '');

        // Hide edit while editing (clean)
        $('.checkout-summary-line--notes .checkout-summary-line__edit').hide();

        try {
            $input.trigger('focus');
            $input[0] && $input[0].setSelectionRange && $input[0].setSelectionRange($input.val().length, $input.val().length);
        } catch (e) {}
    }

    function commitInlineNotesInput($input) {
        const $comments = $('#order_comments');
        if (!$comments.length || !$input || !$input.length) return;
        const val = ($input.val() || '').trim();
        try {
            if (val) {
                window.localStorage.setItem(ORDER_COMMENTS_STORAGE_KEY, val);
            } else {
                window.localStorage.removeItem(ORDER_COMMENTS_STORAGE_KEY);
            }
        } catch (e) {}

        $comments.val(val).trigger('change');
        $(document.body).trigger('update_checkout');
    }

    // Keep unified notes line always in sync (even if default PHP markup is shown first)
    function syncUnifiedNotesLine() {
        const $comments = $('#order_comments');
        const $unifiedLabel = $('.checkout-summary-line--notes .checkout-summary-line__label');
        const $unifiedValue = $('.checkout-summary-line--notes .js-checkout-summary-notes');
        const $unifiedEdit  = $('.checkout-summary-line--notes .checkout-summary-line__edit');
        if (!$comments.length || !$unifiedValue.length) return;

        // If user is currently typing, don't overwrite the input UI.
        if ($unifiedValue.find('input.checkout-notes-inline-input').length) return;

        let notes = ($comments.is('input, textarea')) ? (($comments.val() || '').trim()) : '';
        if (!notes) {
            try { notes = (window.localStorage.getItem(ORDER_COMMENTS_STORAGE_KEY) || '').trim(); } catch (e) { notes = ''; }
            if (notes) {
                $comments.val(notes);
            }
        }

        if (notes) {
            const shortNotes = notes.length > 50 ? notes.substring(0, 50) + '...' : notes;
            if ($unifiedLabel.length) $unifiedLabel.text('הערות להזמנה');
            if ($unifiedEdit.length) $unifiedEdit.show();
            $unifiedValue.removeClass('is-add-note').empty().text(shortNotes);
        } else {
            if ($unifiedLabel.length) $unifiedLabel.text('');
            if ($unifiedEdit.length) $unifiedEdit.hide();
            $unifiedValue
                .addClass('is-add-note')
                .empty()
                .html('הוספת הערה להזמנה &gt;');
        }
    }

    // Initialize on page load
    console.log('[Checkout Blocks] Starting initialization...');
    setInitialBlockStates();
    initCheckoutBlocks(); 
    updatePaymentMethodsVisibility();
    updatePlaceOrderButtonText();
    syncUnifiedNotesLine();
    // Initialize unified summary lines once on load
    $('.checkout-block--billing, .checkout-block--shipping, .checkout-block--notes').each(function() {
        updateBlockSummary($(this));
    });

    // Handle WooCommerce update_order_review -1 issue after AJAX login:
    // if we get -1 for the checkout update request, reload once so nonces match the logged-in user.
    let updateOrderReviewReloaded = false;
    $(document).ajaxError(function(event, jqXHR, settings) {
        if (
            !updateOrderReviewReloaded &&
            settings &&
            typeof settings.url === 'string' &&
            settings.url.indexOf('update_order_review') !== -1 &&
            jqXHR &&
            (jqXHR.status === 0 || jqXHR.responseText === '-1')
        ) {
            updateOrderReviewReloaded = true;
            console.warn('[Checkout Blocks] update_order_review returned -1; reloading checkout once to refresh nonces.');
            window.location.reload();
        }
    });

    console.log('[Checkout Blocks] Initial page load complete');

    // Re-initialize after WooCommerce updates (fragments)
    $(document.body).on('updated_checkout', function() {
        setInitialBlockStates();
        initCheckoutBlocks();
        updatePaymentMethodsVisibility();
        updatePlaceOrderButtonText();
        syncUnifiedNotesLine();
        $('.checkout-block--billing, .checkout-block--shipping, .checkout-block--notes').each(function() {
            updateBlockSummary($(this));
        });
    });

    // When payment method changes, update place order button text (with logging)
    $(document.body).on('change', 'input[name="payment_method"]', function() {
        console.log('[Checkout Blocks] payment_method changed', {
            id: this.id,
            value: this.value,
            dataText: $(this).attr('data-order_button_text')
        });
        updatePlaceOrderButtonText();
    });

    /**
     * Toggle products list inside order review ("ההזמנה שלי")
     */
    $(document).on('click', '.order-items-toggle-btn', function(e) {
        e.preventDefault();
        const $tbody = $(this).closest('tbody.checkout-order-items-body');
        if ($tbody.hasClass('is-closed')) {
            $tbody.removeClass('is-closed').addClass('is-open');
        } else {
            $tbody.removeClass('is-open').addClass('is-closed'); 
        }
    });
 
    // Open popups from unified summary "שינוי" buttons
    $(document).on('click', '.checkout-summary-line__edit', function(e) {
        e.preventDefault();
        const target = $(this).data('checkout-block-target');
        if (!target) return;
        if (target === 'notes') {
            const $comments = $('#order_comments');
            beginInlineNotesInput($comments.length ? ($comments.val() || '') : '');
            return;
        }
        const $block = $('.checkout-block--' + target);
        if ($block.length) { 
            openBlockPopup($block); 
        }
    }); 

    // Click the "add note" area to start typing inline
    $(document).on('click', '.checkout-summary-line--notes .js-checkout-summary-notes.is-add-note', function(e) {
        // If already an input, let it focus naturally
        if ($(e.target).is('input')) return;
        const $comments = $('#order_comments');
        let val = $comments.length ? ($comments.val() || '') : '';
        if (!val) {
            try { val = window.localStorage.getItem(ORDER_COMMENTS_STORAGE_KEY) || ''; } catch (err) {}
        }
        beginInlineNotesInput(val);
    });

    // Commit notes on blur / Enter
    $(document).on('keydown', '.checkout-summary-line--notes .checkout-notes-inline-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).trigger('blur');
        }
    });
    $(document).on('blur', '.checkout-summary-line--notes .checkout-notes-inline-input', function() {
        commitInlineNotesInput($(this));
        // After saving, return to unified display (text or add-link)
        syncUnifiedNotesLine();
    });
});

