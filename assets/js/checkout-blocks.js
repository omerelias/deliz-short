jQuery(function($) {
    'use strict';
    console.log('[Checkout Blocks] Script loaded');

    // Initialize checkout blocks
    function initCheckoutBlocks() { 
        console.log('[Checkout Blocks] initCheckoutBlocks called');
        
        const $blocks = $('.checkout-block');
        const $headers = $('.checkout-block__header');
        const $editButtons = $('.checkout-block__edit');
        
        console.log('[Checkout Blocks] Found blocks:', $blocks.length); 
        console.log('[Checkout Blocks] Found headers:', $headers.length);
        console.log('[Checkout Blocks] Found edit buttons:', $editButtons.length);
        
        // Log each block's state
        $blocks.each(function(index) {
            const $block = $(this);
            const blockType = $block.data('block') || $block.attr('class');
            const isOpen = $block.hasClass('is-open');
            const isClosed = $block.hasClass('is-closed');
            console.log(`[Checkout Blocks] Block ${index + 1}: type=${blockType}, is-open=${isOpen}, is-closed=${isClosed}`);
        });
        
        // Remove existing handlers to prevent duplicates
        $('.checkout-block__header').off('click.checkoutBlocks');
        $('.checkout-block__edit').off('click.checkoutBlocks');
        
        // Header click - toggle block
        $('.checkout-block__header').on('click.checkoutBlocks', function(e) {
            console.log('[Checkout Blocks] Header clicked', e.target);
            
            // Don't toggle if clicking edit button
            if ($(e.target).closest('.checkout-block__edit').length) {
                console.log('[Checkout Blocks] Click was on edit button, ignoring');
                return;
            }
            
            const $block = $(this).closest('.checkout-block');
            const blockType = $block.data('block') || 'unknown';
            console.log('[Checkout Blocks] Toggling block:', blockType);
            
            // Don't toggle order block
            if ($block.hasClass('checkout-block--order')) {
                console.log('[Checkout Blocks] Order block, skipping toggle');
                return;
            }
            
            toggleBlock($block);
        });
        
        // Edit button click - open block
        $('.checkout-block__edit').on('click.checkoutBlocks', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $block = $(this).closest('.checkout-block');
            const blockType = $block.data('block') || 'unknown';
            console.log('[Checkout Blocks] Edit button clicked for block:', blockType);
            
            openBlock($block);
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
    
    function openBlock($block) {
        const blockType = $block.data('block') || 'unknown';
        console.log('[Checkout Blocks] openBlock called for:', blockType);
        console.log('[Checkout Blocks] Block before:', {
            hasIsOpen: $block.hasClass('is-open'),
            hasIsClosed: $block.hasClass('is-closed'),
            ariaExpanded: $block.attr('aria-expanded')
        });
        
        $block.removeClass('is-closed').addClass('is-open');
        $block.attr('aria-expanded', 'true');
        
        console.log('[Checkout Blocks] Block after:', {
            hasIsOpen: $block.hasClass('is-open'),
            hasIsClosed: $block.hasClass('is-closed'),
            ariaExpanded: $block.attr('aria-expanded')
        });
        
        // Hide edit button when open
        const $editBtn = $block.find('.checkout-block__edit');
        console.log('[Checkout Blocks] Edit button found:', $editBtn.length);
        $editBtn.hide();
        
        // Scroll to block if needed
        try {
            const offset = $block.offset().top - 100;
            $('html, body').animate({
                scrollTop: offset
            }, 300);
        } catch (e) {
            console.error('[Checkout Blocks] Error scrolling:', e);
        }
    }
    
    function closeBlock($block) {
        const blockType = $block.data('block') || 'unknown';
        console.log('[Checkout Blocks] closeBlock called for:', blockType);
        console.log('[Checkout Blocks] Block before:', {
            hasIsOpen: $block.hasClass('is-open'),
            hasIsClosed: $block.hasClass('is-closed'),
            ariaExpanded: $block.attr('aria-expanded')
        });
        
        $block.removeClass('is-open').addClass('is-closed');
        $block.attr('aria-expanded', 'false');
        
        console.log('[Checkout Blocks] Block after:', {
            hasIsOpen: $block.hasClass('is-open'),
            hasIsClosed: $block.hasClass('is-closed'),
            ariaExpanded: $block.attr('aria-expanded')
        });
        
        // Show edit button when closed
        const $editBtn = $block.find('.checkout-block__edit');
        console.log('[Checkout Blocks] Edit button found:', $editBtn.length);
        $editBtn.show();
        
        // Update summary when block is closed
        updateBlockSummary($block);
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
                    const $email = $('#billing_email');
                    const $city = $('#billing_city');
                    const $street = $('#billing_street');
                    const $houseNum = $('#billing_house_num');
                    const $address1 = $('#billing_address_1');

                    const firstName = ($firstName.length && $firstName.is('input, select, textarea')) ? $firstName.val() : '';
                    const lastName = ($lastName.length && $lastName.is('input, select, textarea')) ? $lastName.val() : '';
                    const phone = ($phone.length && $phone.is('input, select, textarea')) ? $phone.val() : '';
                    const email = ($email.length && $email.is('input, select, textarea')) ? $email.val() : '';

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

                    if (firstName || lastName) summaryText += '<strong>' + (firstName + ' ' + lastName).trim() + '</strong><br>';
                    if (phone) summaryText += phone + '<br>';
                    if (email) summaryText += email + '<br>';
                    if (address) summaryText += address;

                    if (!summaryText) summaryText = 'לא הוזנו פרטים';
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

                        if (street) addressParts.push(street);
                        if (houseNum) addressParts.push(houseNum);
                        if (address1 && !street) addressParts.push(address1);
                        if (city) addressParts.push(city);
                    }

                    const addressString = addressParts.join(' ');

                    // Get date/time info
                    let dateTimeInfo = '';
                    if (isPickup) {
                        // Pickup: branch, date, time
                        const $pickupAffId = $('#ocws_lp_pickup_aff_id');
                        const $pickupAffName = $('input[name="ocws_lp_pickup_aff_name"]');
                        const $pickupDate = $('#ocws_lp_pickup_date');
                        const $pickupSlotStart = $('#ocws_lp_pickup_slot_start');
                        const $pickupSlotEnd = $('#ocws_lp_pickup_slot_end');

                        let pickupBranch = '';
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
                            if (pickupBranch) {
                                dateTimeInfo += '<br><strong>סניף:</strong> ' + pickupBranch;
                            }
                            dateTimeInfo += '<br><strong>תאריך:</strong> ' + pickupDate;
                            if (pickupSlotStart) {
                                dateTimeInfo += '<br><strong>שעה:</strong> ' + pickupSlotStart;
                                if (pickupSlotEnd && pickupSlotEnd !== pickupSlotStart) {
                                    dateTimeInfo += ' - ' + pickupSlotEnd;
                                }
                            }
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
                            dateTimeInfo += '<br><strong>תאריך:</strong> ' + shippingDate;
                            if (shippingSlotStart) {
                                dateTimeInfo += '<br><strong>שעה:</strong> ' + shippingSlotStart;
                                if (shippingSlotEnd && shippingSlotEnd !== shippingSlotStart) {
                                    dateTimeInfo += ' - ' + shippingSlotEnd;
                                }
                            }
                        }
                    }

                    // Build summary
                    if (shippingMethod && shippingMethod !== 'משלוח') {
                        summaryText = '<strong>' + shippingMethod + '</strong>';
                        if (addressString) {
                            summaryText += '<br>' + addressString;
                        }
                        if (dateTimeInfo) {
                            summaryText += dateTimeInfo;
                        }
                    } else if (addressString) {
                        summaryText = addressString;
                        if (dateTimeInfo) {
                            summaryText += dateTimeInfo;
                        }
                    } else if (dateTimeInfo) {
                        summaryText = dateTimeInfo.replace(/^<br>/, '');
                    } else {
                        summaryText = 'לא נבחר משלוח';
                    }
                    break;
                }

                case 'notes': {
                    const $orderComments = $('#order_comments');
                    const notes = ($orderComments.length && $orderComments.is('input, textarea')) ? $orderComments.val() : '';
                    summaryText = notes ? (notes.length > 50 ? notes.substring(0, 50) + '...' : notes) : 'אין הערות';
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
    $(document).on('change', '#billing_first_name, #billing_last_name, #billing_phone, #billing_email, #billing_city, #billing_address_1', function() {
        const $block = $('.checkout-block--billing');
        if (!$block.hasClass('is-open')) {
            updateBlockSummary($block);
        }
    });

    // Update shipping summary on any address or shipping method change
    $(document).on('change', 'input[name^="shipping_method"], #ship-to-different-address-checkbox, #billing_city, #billing_street, #billing_house_num, #billing_address_1, #billing_enter_code, #shipping_city, #shipping_street, #shipping_house_num, #shipping_address_1, #order_expedition_date, #order_expedition_slot_start, #order_expedition_slot_end, #ocws_lp_pickup_aff_id, #ocws_lp_pickup_date, #ocws_lp_pickup_slot_start, #ocws_lp_pickup_slot_end, input[name="ocws_lp_pickup_aff_id"], input[name="ocws_lp_pickup_date"], input[name="ocws_lp_pickup_slot_start"], input[name="ocws_lp_pickup_slot_end"]', function() {
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
    });

    // Set initial state for blocks
    function setInitialBlockStates() {
        console.log('[Checkout Blocks] setInitialBlockStates called');

        $('.checkout-block').each(function(index) {
            const $block = $(this);
            const blockType = $block.data('block') || $block.attr('class');

            // Skip order block
            if ($block.hasClass('checkout-block--order')) {
                console.log(`[Checkout Blocks] Block ${index + 1} (${blockType}): Skipping order block`);
                return;
            }

            const hasIsOpen = $block.hasClass('is-open');
            const hasIsClosed = $block.hasClass('is-closed');

            console.log(`[Checkout Blocks] Block ${index + 1} (${blockType}): hasIsOpen=${hasIsOpen}, hasIsClosed=${hasIsClosed}`);

            // If block doesn't have is-open or is-closed, set default state
            if (!hasIsOpen && !hasIsClosed) {
                // Billing block starts open, others start closed
                if ($block.hasClass('checkout-block--billing')) {
                    console.log(`[Checkout Blocks] Block ${index + 1}: Setting to is-open (billing)`);
                    $block.addClass('is-open').attr('aria-expanded', 'true');
                } else {
                    console.log(`[Checkout Blocks] Block ${index + 1}: Setting to is-closed (default)`);
                    $block.addClass('is-closed').attr('aria-expanded', 'false');
                }
            } else {
                console.log(`[Checkout Blocks] Block ${index + 1}: Already has state, keeping as is`);
            }
        });

        console.log('[Checkout Blocks] setInitialBlockStates complete');
    }

    // Initialize on page load
    console.log('[Checkout Blocks] Starting initialization...');
    setInitialBlockStates();
    initCheckoutBlocks();
    console.log('[Checkout Blocks] Initial page load complete');

    // Re-initialize after WooCommerce updates (fragments)
    $(document.body).on('updated_checkout', function() {
        setInitialBlockStates();
        initCheckoutBlocks();
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
});

