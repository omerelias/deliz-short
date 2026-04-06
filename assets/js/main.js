
jQuery(function ($) {
  $('.top-header-inner').slick({
    slidesToShow: 1,
    arrows: false,
    dots: false,
    //adaptiveHeight: false,
    //fade: true, 
    rtl:true, 
    //cssEase: 'linear', 
    autoplay: true,
    autoplaySpeed: 3000,
  });

  //סליק לסליידר ראשי
  $('.js-ed-main-slider').slick({
    slidesToShow: 1,
    arrows: false,
    dots: true,
    rtl:true,
    adaptiveHeight: false,
    autoplay: true,
    autoplaySpeed: 4000,
  });

  //login click
  $("body:not(.logged-in) .header-user-inner a").click(function(event) {
    event.preventDefault(); 
    $('body').addClass('auth-active');
  });

  $("a.login-panel").click(function(event) {
    event.preventDefault(); 
    $('body').addClass('auth-active');
  });

  function closeOverlays() {
    $('body').removeClass('auth-active');
    $('body').removeClass('basket-open');
    $('.site-overlay').removeClass('active');
  }

  $(".site-overlay,button.auth__close,button.cart-close").click(function(event) {
    closeOverlays();
  });

  $(document).on('keyup', function(e) {
    if (e.key === 'Escape' && ($('body').hasClass('basket-open') || $('body').hasClass('auth-active'))) {
      closeOverlays();
    }
  });

    // Coupon "copy" form (used in checkout and float cart)
    $(document).on('keyup', '.coupon-form.copy-form input.input-text', function () {
        const code = $(this).val();
        const $real = $('form.checkout_coupon input.input-text');
        if ($real.length) {
            $real.val(code);
        }
    });

    // Enter key should behave like WooCommerce coupon form submit
    $(document).on('keydown', '.coupon-form.copy-form input.input-text', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const $wrap = $(this).closest('.coupon-form.copy-form');
            $wrap.find('.apply-coupon-copy').trigger('click');
        }
    });

    function extractWooNotices(html) {
        if (!html || typeof html !== 'string') return '';

        // Prefer the standard Woo wrapper if present
        const wrapperMatch = html.match(/<div[^>]*class="[^"]*woocommerce-notices-wrapper[^"]*"[^>]*>[\s\S]*?<\/div>/i);
        if (wrapperMatch) return wrapperMatch[0];

        // Otherwise, pick common notice blocks (Woo can return <div> or <ul>)
        const blocks = [];
        const reDiv = /<div[^>]*class="[^"]*woocommerce-(?:message|error|info)[^"]*"[^>]*>[\s\S]*?<\/div>/gi;
        const reUl = /<ul[^>]*class="[^"]*woocommerce-(?:message|error|info)[^"]*"[^>]*>[\s\S]*?<\/ul>/gi;
        let m;
        while ((m = reDiv.exec(html))) blocks.push(m[0]);
        while ((m = reUl.exec(html))) blocks.push(m[0]);
        return blocks.join('');
    }

    function noticesLookLikeError(html) {
        if (!html || typeof html !== 'string') return false;
        return /woocommerce-error/i.test(html);
    }

    function setFloatCartCouponNotices(html, opts) {
        const el = document.querySelector('#ed-float-cart .ed-float-cart__coupon-notices');
        if (!el) return;
        const isError = !!(opts && opts.isError);
        el.innerHTML = html || '';
        clearTimeout(el._hideTimeout);
        // Keep success/info short-lived; leave errors until the next action or manual clear
        if (html && !isError) {
            el._hideTimeout = setTimeout(function () {
                el.innerHTML = '';
            }, 8000);
        }
    }

    function getFloatCartAjaxParams() {
        return window.wc_cart_params || window.wc_checkout_params || window.ED_COUPON_PARAMS;
    } 

    function parseWooCouponResponse(text) {
        let noticesHtml = '';
        let jsonSuccess = null;
        let fragments = null;
        let cartHash = null;
        try {
            const json = JSON.parse(text);
            fragments = json?.data?.fragments ?? json?.fragments ?? null;
            cartHash = json?.data?.cart_hash ?? json?.cart_hash ?? null;
            noticesHtml =
                json?.data?.messages ||
                json?.messages ||
                json?.notices ||
                json?.data?.notices ||
                '';
            if (typeof json?.success === 'boolean') {
                jsonSuccess = json.success;
            }
        } catch (_) {
            noticesHtml = extractWooNotices(text);
        }
        if (!noticesHtml) {
            noticesHtml = extractWooNotices(text);
        }
        return { noticesHtml, jsonSuccess, fragments, cartHash };
    }

    /**
     * Same idea as WooCommerce cart-fragments.js: replace each selector with server HTML.
     * Response already includes #ed-float-cart (see woocommerce_add_to_cart_fragments in theme).
     */
    function applyWooCartFragments(fragments) {
        if (!fragments || typeof fragments !== 'object' || typeof jQuery === 'undefined') {
            return false;
        }
        var appliedAny = false;
        jQuery.each(fragments, function (selector, html) {
            if (!selector || typeof html !== 'string' || !html.length) {
                return;
            }
            var $targets = jQuery(selector);
            if ($targets.length) {
                $targets.replaceWith(html);
                appliedAny = true;
            }
        });
        return appliedAny;
    }

    function syncWcCartHashFromResponse(cartHash) {
        if (!cartHash || typeof cartHash !== 'string') {
            return;
        }
        const p = window.wc_cart_params || window.wc_checkout_params;
        if (p) {
            p.cart_hash = cartHash;
        }
    }

    /**
     * Some stores/plugins make apply_coupon / remove_coupon return HTML notices only (not JSON fragments).
     * WooCommerce always exposes JSON fragments via get_refreshed_fragments.
     */
    async function fetchWcRefreshedFragments() {
        var cartParams = getFloatCartAjaxParams();
        if (!cartParams || !cartParams.wc_ajax_url) {
            return null;
        }
        var url = cartParams.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
        var body = new URLSearchParams();
        body.set('time', String(Date.now()));
        try {
            var res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            });
            var text = await res.text();
            var json = JSON.parse(text);
            var fragments = json?.data?.fragments ?? json?.fragments ?? null;
            var cartHash = json?.data?.cart_hash ?? json?.cart_hash ?? null;
            var keys = fragments && typeof fragments === 'object' ? Object.keys(fragments) : [];
            if (!fragments || !keys.length) {
                return null;
            }
            return { fragments: fragments, cartHash: cartHash };
        } catch (e) {
            return null;
        }
    }

    async function refreshFloatCartAfterCouponChange(result) {
        if (!result || !result.ok || typeof jQuery === 'undefined') {
            return;
        }

        var applied = false;
        if (result.fragments && Object.keys(result.fragments).length) {
            applied = applyWooCartFragments(result.fragments);
        }
        syncWcCartHashFromResponse(result.cartHash);

        if (!applied) {
            var refreshed = await fetchWcRefreshedFragments();
            if (refreshed && refreshed.fragments && Object.keys(refreshed.fragments).length) {
                applied = applyWooCartFragments(refreshed.fragments);
                syncWcCartHashFromResponse(refreshed.cartHash);
            }
        }

        jQuery('body').trigger('updated_wc_div');

        if (!applied) {
            jQuery('body').trigger('wc_fragment_refresh');
        }
    }

    async function applyCouponAjax(code) {
        const cartParams = getFloatCartAjaxParams();
        if (!cartParams || !cartParams.wc_ajax_url) {
            return { ok: false, noticesHtml: '', fragments: null, cartHash: null };
        }

        const url = cartParams.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon');
        const body = new URLSearchParams();
        body.set('coupon_code', code || '');
        if (cartParams.apply_coupon_nonce) {
            body.set('security', cartParams.apply_coupon_nonce);
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            });
            const text = await res.text();
            const parsed = parseWooCouponResponse(text);
            let applyOk = res.ok;
            if (parsed.jsonSuccess === false) {
                applyOk = false;
            } else if (parsed.jsonSuccess === true) {
                applyOk = true;
            }
            if (noticesLookLikeError(parsed.noticesHtml)) {
                applyOk = false;
            }

            return {
                ok: applyOk,
                noticesHtml: parsed.noticesHtml,
                fragments: parsed.fragments,
                cartHash: parsed.cartHash,
            };
        } catch (err) {
            return { ok: false, noticesHtml: '', fragments: null, cartHash: null };
        }
    }

    async function removeCouponAjax(couponCode) {
        const cartParams = getFloatCartAjaxParams();
        if (!cartParams || !cartParams.wc_ajax_url) {
            return { ok: false, noticesHtml: '', fragments: null, cartHash: null };
        }
        const url = cartParams.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_coupon');
        const body = new URLSearchParams();
        body.set('coupon', couponCode || '');
        if (cartParams.remove_coupon_nonce) {
            body.set('security', cartParams.remove_coupon_nonce);
        }
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            });
            const text = await res.text();
            const parsed = parseWooCouponResponse(text);
            let ok = res.ok;
            if (parsed.jsonSuccess === false) {
                ok = false;
            } else if (parsed.jsonSuccess === true) {
                ok = true;
            }
            if (noticesLookLikeError(parsed.noticesHtml)) {
                ok = false;
            }
            return {
                ok: ok,
                noticesHtml: parsed.noticesHtml,
                fragments: parsed.fragments,
                cartHash: parsed.cartHash,
            };
        } catch (err) {
            return { ok: false, noticesHtml: '', fragments: null, cartHash: null };
        }
    }

    document.addEventListener(
        'click',
        function (e) {
            var t = e.target;
            var el = t && t.closest ? t.closest('#ed-float-cart a.woocommerce-remove-coupon') : null;
            if (!el) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var code = (el.getAttribute('data-coupon') || '').trim();
            if (!code) return;
            removeCouponAjax(code).then(async function (result) {
                if (result.ok) {
                    setFloatCartCouponNotices('', {});
                } else if (result.noticesHtml) {
                    setFloatCartCouponNotices(result.noticesHtml, { isError: true });
                }
                await refreshFloatCartAfterCouponChange(result);
            });
        },
        true
    );

    $(document).on('click', '.coupon-form.copy-form .apply-coupon-copy', async function (e) {
        e.preventDefault();

        const $wrap = $(this).closest('.coupon-form.copy-form');
        const code = $wrap.find('input.input-text').val() || '';

        // If we're on checkout, keep using the real checkout form behavior
        const $checkoutBtn = $('form.checkout_coupon .button');
        if ($checkoutBtn.length) {
            $('form.checkout_coupon input.input-text').val(code);
            $checkoutBtn.trigger('click');
        } else {
            const result = await applyCouponAjax(code);
            if (result?.noticesHtml) {
                setFloatCartCouponNotices(result.noticesHtml, { isError: !result?.ok });
            } else if (result?.ok) {
                setFloatCartCouponNotices('', {});
            }
            if (result?.ok) {
                await refreshFloatCartAfterCouponChange(result);
            }
        }
    });


	$(document).on( 'click', 'button.auth-btn', function(e){
		let val 		= $(this).val();
		let parent 	= $(this).closest( '#customer_login' );
		let target;
		// console.log( val, 'val' );
		// console.log( parent, 'parent' );

		if ( val == 'register' ){
			parent.addClass( 'register-show' );
			target = $('.authorization-panel--container .col-2');
		} else {
			target = $('.authorization-panel--container .col-1');
			parent.removeClass( 'register-show' );
		}
	});

  //popup modal
  $(document).on( 'click', '.cart-custom-notice a', function(e){
      $('.modal#lastPricePop').addClass( 'show' );
  });

  $(document).on( 'click', '.modal-header button.close,.modal-ovelay', function(e){
      $('.modal').removeClass( 'show' );
  });  

  // Scroll to top when clicking category links in the sidebar slider
    $(document).on('click', '.ed-mp__link', function () {
        var topOffset = 120;
        var targetTop = 0;

        try {
            window.scrollTo({
            top: targetTop + topOffset,
            behavior: 'auto'
            });
        } catch (e) {
            window.scrollTo(0, topOffset);
        }
    });

});

//mobile menu
(function () {
  const menu = document.querySelector('.main-content .menu-block ul.ed-mp__menu');
  if (!menu) return;

  const canScrollX = () => (menu.scrollWidth - menu.clientWidth) > 1;

  function align(li, side, smooth = true) {
    if (!li || !canScrollX()) return;
    li.scrollIntoView({
      behavior: smooth ? 'smooth' : 'auto',
      block: 'nearest',
      inline: side, // RTL: start=ימין, end=שמאל
    });
  }

  // טעינה: active מוצמד לימין
  function alignActiveOnLoad() {
    const activeLi = menu.querySelector('a.ed-mp__link.is-active')?.closest('li');
    if (!activeLi) return;
    requestAnimationFrame(() => requestAnimationFrame(() => align(activeLi, 'start', false)));
  }

  document.addEventListener('DOMContentLoaded', alignActiveOnLoad);
  window.addEventListener('load', alignActiveOnLoad);

  menu.addEventListener('click', (e) => {
    const link = e.target.closest('a.ed-mp__link');
    if (!link || !menu.contains(link)) return;

    const clickedLi = link.closest('li');
    if (!clickedLi || !canScrollX()) return;

    const activeLi = menu.querySelector('a.ed-mp__link.is-active')?.closest('li');

    // אם אין active כרגע – נצמיד את הנלחץ לימין
    if (!activeLi) {
      align(clickedLi, 'start', true);
      return;
    }

    // קליק על active עצמו – לא מזיזים
    if (activeLi === clickedLi) return;

    // RTL: clicked משמאל ל-active => clicked אחרי active ב-DOM
    const clickedIsLeftOfActive =
      !!(activeLi.compareDocumentPosition(clickedLi) & Node.DOCUMENT_POSITION_FOLLOWING);

    // משמאל ל-active => להצמיד לימין
    // מימין ל-active => להצמיד לשמאל
    align(clickedLi, clickedIsLeftOfActive ? 'start' : 'end', true);
  });
})();

//סליידר במובייל
(function ($) {
  var mq = window.matchMedia('(max-width: 991px)');
  var $slider, $main, $ph;

  function run() {
    $slider = $('body .slick-dotted.slick-slider').first();
    $main   = $('.main-content').first();
    if (!$slider.length || !$main.length) return;

    if (mq.matches) {
      if (!$slider.data('edPh')) {
        $ph = $('<span class="ed-slick-ph" style="display:none!important"></span>');
        $slider.before($ph);
        $slider.data('edPh', $ph);
      } else {
        $ph = $slider.data('edPh');
      }

      $main.prepend($slider);

      if ($slider.hasClass('slick-initialized')) {
        $slider.slick('setPosition');
      }
    } else {
      $ph = $slider.data('edPh');
      if ($ph && $ph.length) {
        $ph.before($slider);
        $ph.remove();
        $slider.removeData('edPh');

        if ($slider.hasClass('slick-initialized')) {
          $slider.slick('setPosition');
        }
      }
    }
  }

  $(run);
  if (mq.addEventListener) mq.addEventListener('change', run);
  else mq.addListener(run);

  // אם נטען דינמית
  setTimeout(run, 300);
})(jQuery);

//footer mobile
jQuery('.site-footer .fmain .fcol-menu .title').click(function ($) {
	jQuery(this).parent().toggleClass('active');
});

//יוסט שאלות ותשובות
(function () {
  function initYoastFaqToggle() {
    const questions = document.querySelectorAll('.schema-faq-question');
    if (!questions.length) return;

    questions.forEach((q) => {
      if (q.dataset.faqInit === '1') return;
      q.dataset.faqInit = '1';

      // Yoast בדרך כלל עוטף כל Q/A בתוך .schema-faq-section
      const section = q.closest('.schema-faq-section') || q.parentElement;
      if (!section) return;

      // התשובה יכולה להיות לא הסיבלינג הישיר, אז מחפשים בתוך ה-section
      const answer = section.querySelector('.schema-faq-answer');
      if (!answer) return;

      // start closed
      answer.style.display = 'none';

      q.style.cursor = 'pointer';
      q.setAttribute('role', 'button');
      q.setAttribute('tabindex', '0');
      q.setAttribute('aria-expanded', 'false');

      const toggle = () => {
        const isOpen = q.getAttribute('aria-expanded') === 'true';
        q.setAttribute('aria-expanded', String(!isOpen));
        answer.style.display = isOpen ? 'none' : '';
      };

      q.addEventListener('click', toggle);
      q.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggle();
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', initYoastFaqToggle);

  // אם תוכן נטען דינמית (Elementor וכו')
  new MutationObserver(initYoastFaqToggle).observe(document.documentElement, {
    childList: true,
    subtree: true,
  });

  //קופון
(function ($) {
    $(document).on('click', '.ed-float-cart__coupon > label', function () {
        $(this).closest('.ed-float-cart__coupon').toggleClass('active');
    });
})(jQuery);

})();
