(function () {
  "use strict";

  const toggle = document.getElementById("navToggle");
  const nav = document.getElementById("nav");

  toggle.addEventListener("click", function () {
    const open = nav.classList.toggle("open");
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

  const toTop = document.getElementById("toTop");
  window.addEventListener("scroll", function () {
    toTop.classList.toggle("show", window.scrollY > 600);
  }, { passive: true });
})();
