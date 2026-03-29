/**
 * אתחול אינטראקציות להיסטוריית רכישה (נקרא גם אחרי הזרקת HTML ב-AJAX).
 */
(function () {
  function animateValue(el, end, duration) {
    if (!el) return;
    var start = 0;
    var startTime = null;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      el.textContent = String(end);
      return;
    }
    function step(ts) {
      if (!startTime) startTime = ts;
      var p = Math.min((ts - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(start + (end - start) * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function bindSpotlight(hero) {
    if (!hero || hero.dataset.edPhSpotbound === "1") return;
    hero.dataset.edPhSpotbound = "1";

    function setSpot(clientX, clientY) {
      var r = hero.getBoundingClientRect();
      var x = ((clientX - r.left) / r.width) * 100;
      var y = ((clientY - r.top) / r.height) * 100;
      hero.style.setProperty(
        "--ed-ph-spot-x",
        Math.max(5, Math.min(95, x)) + "%"
      );
      hero.style.setProperty(
        "--ed-ph-spot-y",
        Math.max(5, Math.min(95, y)) + "%"
      );
    }

    hero.addEventListener("mousemove", function (e) {
      setSpot(e.clientX, e.clientY);
    });
    hero.addEventListener("touchmove", function (e) {
      if (e.touches && e.touches[0]) {
        setSpot(e.touches[0].clientX, e.touches[0].clientY);
      }
    }, { passive: true });
  }

  function observeSections(root) {
    var sections = root.querySelectorAll(".ed-ph-section");
    if (!sections.length) return;

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      sections.forEach(function (s) {
        s.classList.add("is-ed-ph-visible");
      });
      return;
    }

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) {
            en.target.classList.add("is-ed-ph-visible");
            io.unobserve(en.target);
          }
        });
      },
      { rootMargin: "0px 0px -60px 0px", threshold: 0.08 }
    );

    sections.forEach(function (s, i) {
      s.style.transitionDelay = Math.min(i * 70, 420) + "ms";
      io.observe(s);
    });
  }

  function runCountUps(root) {
    var stat = root.querySelector("[data-ed-ph-count-products]");
    if (!stat) return;
    var n = parseInt(stat.getAttribute("data-ed-ph-count-products"), 10);
    if (!isFinite(n) || n < 0) return;
    animateValue(stat, n, 900);
  }

  window.edProductHistoryInit = function (el) {
    if (!el || !el.querySelector) return;
    var root = el.getAttribute && el.getAttribute("data-ed-product-history") !== null
      ? el
      : el.querySelector("[data-ed-product-history]");
    if (!root) return;

    var hero = root.querySelector(".ed-ph-hero");
    bindSpotlight(hero);
    runCountUps(root);
    observeSections(root);
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-ed-product-history]").forEach(function (root) {
      window.edProductHistoryInit(root);
    });
  });
})();
