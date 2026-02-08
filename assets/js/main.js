
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

  $(".site-overlay,button.auth__close,button.cart-close").click(function(event) {
    $('body').removeClass('auth-active');
    $('body').removeClass('basket-open');
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