
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
        try {
            const json = JSON.parse(text);
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
        return { noticesHtml, jsonSuccess };
    }

    async function applyCouponAjax(code) {
        const cartParams = getFloatCartAjaxParams();
        console.log('[coupon] applyCouponAjax:start', { code, hasCartParams: !!cartParams });
        if (!cartParams || !cartParams.wc_ajax_url) {
            console.warn('[coupon] missing wc_cart_params/wc_checkout_params or wc_ajax_url', { cartParams });
            return { ok: false, noticesHtml: '' };
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

            console.log('[coupon] wc_ajax apply_coupon response', {
                applyOk: applyOk,
                status: res.status,
                bodyPreview: text?.slice?.(0, 500),
            });

            return { ok: applyOk, noticesHtml: parsed.noticesHtml };
        } catch (err) {
            console.error('[coupon] wc_ajax apply_coupon failed', err);
            return { ok: false, noticesHtml: '' };
        }
    }

    async function removeCouponAjax(couponCode) {
        const cartParams = getFloatCartAjaxParams();
        if (!cartParams || !cartParams.wc_ajax_url) {
            return { ok: false, noticesHtml: '' };
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
            return { ok: ok, noticesHtml: parsed.noticesHtml };
        } catch (err) {
            console.error('[coupon] remove_coupon failed', err);
            return { ok: false, noticesHtml: '' };
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
            removeCouponAjax(code).then(function (result) {
                if (result.ok) {
                    setFloatCartCouponNotices('', {});
                } else if (result.noticesHtml) {
                    setFloatCartCouponNotices(result.noticesHtml, { isError: true });
                }
                if (result.ok && typeof jQuery !== 'undefined') {
                    jQuery('body').trigger('wc_fragment_refresh');
                    jQuery('body').trigger('updated_wc_div');
                }
            });
        },
        true
    );

    $(document).on('click', '.coupon-form.copy-form .apply-coupon-copy', async function (e) {
        e.preventDefault();

        const $wrap = $(this).closest('.coupon-form.copy-form');
        const code = $wrap.find('input.input-text').val() || '';
        console.log('[coupon] click apply-coupon-copy', {
            code,
            inFloatCart: $wrap.closest('#ed-float-cart').length > 0,
        });

        // If we're on checkout, keep using the real checkout form behavior
        const $checkoutBtn = $('form.checkout_coupon .button');
        if ($checkoutBtn.length) {
            console.log('[coupon] using checkout_coupon form submit');
            $('form.checkout_coupon input.input-text').val(code);
            $checkoutBtn.trigger('click');
        } else {
            const result = await applyCouponAjax(code);
            console.log('[coupon] applyCouponAjax result', result);
            if (result?.noticesHtml) {
                setFloatCartCouponNotices(result.noticesHtml, { isError: !result?.ok });
            } else if (result?.ok) {
                setFloatCartCouponNotices('', {});
            }
            if (result?.ok && typeof jQuery !== 'undefined') {
                console.log('[coupon] triggering wc_fragment_refresh + updated_wc_div');
                jQuery('body').trigger('wc_fragment_refresh');
                jQuery('body').trigger('updated_wc_div');
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
    try {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
      window.scrollTo(0, 0);
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
})();
