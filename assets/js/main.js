
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

  $(".site-overlay,button.auth__close,button.cart-close").click(function(event) {
    $('body').removeClass('auth-active');
    $('body').removeClass('basket-open');
    $('.site-overlay').removeClass('active');
  });

    $(document).on( 'keyup', 'table.woocommerce-checkout-review-order-table .coupon-form input.input-text', function(e){
        let code = $(this).val();
        console.log( code, 'code' );
        $('form.checkout_coupon input.input-text').val( code );
    });  

    $(document).on( 'click', 'table.woocommerce-checkout-review-order-table .apply-coupon-copy', function(e){
        $('form.checkout_coupon .button').trigger( 'click' )
        e.preventDefault();
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
