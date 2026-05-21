(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Preloader ---------- */
  var preloader = document.getElementById("preloader");
  function hidePreloader() {
    document.body.classList.add("loaded");
    if (preloader) preloader.classList.add("hide");
  }
  if (preloader && !reduceMotion) {
    var start = Date.now();
    var MIN = 1100; // ロゴを見せる最低表示時間
    window.addEventListener("load", function () {
      var wait = Math.max(0, MIN - (Date.now() - start));
      setTimeout(hidePreloader, wait);
    });
    // フォールバック（load が来なくても必ず解除）
    setTimeout(hidePreloader, 4000);
  } else {
    hidePreloader();
  }

  /* ---------- Mobile nav ---------- */
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", String(open));
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("open");
        toggle.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Header shadow on scroll ---------- */
  var header = document.querySelector(".site-header");
  var toTop = document.getElementById("toTop");
  window.addEventListener("scroll", function () {
    var y = window.scrollY;
    if (header) header.classList.toggle("scrolled", y > 10);
    if (toTop) toTop.classList.toggle("show", y > 600);
  }, { passive: true });

  /* ---------- Scroll reveal ---------- */
  var targets = document.querySelectorAll(".reveal, .reveal-group");
  if (targets.length) {
    if (reduceMotion || !("IntersectionObserver" in window)) {
      targets.forEach(function (el) { el.classList.add("in"); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("in");
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -8% 0px" });
      targets.forEach(function (el) { io.observe(el); });
    }
  }
})();
