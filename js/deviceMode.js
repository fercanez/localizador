/*
 * Detección de celular / tableta / táctil: clases en <html> y <body> para CSS y JS.
 * Se basa en matchMedia (ancho, pointer, hover) y capacidad táctil, no en el user-agent.
 */
(function () {
  "use strict";

  var root = document.documentElement;
  var COMPACT_MAX_WIDTH = 900;
  var PHONE_MAX_WIDTH = 767;
  var PHONE_MAX_SHORT_SIDE = 500;
  var TABLET_MAX_LONG_SIDE = 1400;

  function mq(query) {
    return window.matchMedia ? window.matchMedia(query).matches : false;
  }

  function detect() {
    var w = window.innerWidth || root.clientWidth || 0;
    var h = window.innerHeight || root.clientHeight || 0;
    var shortSide = Math.min(w, h);
    var longSide = Math.max(w, h);
    var coarse = mq("(pointer: coarse)");
    var noHover = mq("(hover: none)");
    var touch = coarse || "ontouchstart" in window || (navigator.maxTouchPoints || 0) > 0;
    var touchPrimary = coarse || (touch && noHover);

    var mobile = w <= PHONE_MAX_WIDTH || (touchPrimary && shortSide <= PHONE_MAX_SHORT_SIDE);
    var tablet = !mobile && touchPrimary && longSide <= TABLET_MAX_LONG_SIDE;

    return {
      width: w,
      height: h,
      touch: touchPrimary,
      mobile: mobile,
      tablet: tablet,
      desktop: !mobile && !tablet,
      compact: mobile || tablet || w <= COMPACT_MAX_WIDTH,
      portrait: h >= w,
    };
  }

  var CLASS_MAP = {
    touch: "is-touch",
    mobile: "is-mobile",
    tablet: "is-tablet",
    desktop: "is-desktop",
    compact: "mxli-compact",
    portrait: "is-portrait",
  };

  var current = null;

  function applyClasses(target, state) {
    if (!target) return;
    Object.keys(CLASS_MAP).forEach(function (key) {
      target.classList.toggle(CLASS_MAP[key], !!state[key]);
    });
    target.classList.toggle("is-landscape", !state.portrait);
  }

  function update() {
    var next = detect();
    var changed =
      !current ||
      Object.keys(CLASS_MAP).some(function (key) {
        return current[key] !== next[key];
      });
    current = next;
    applyClasses(root, next);
    applyClasses(document.body, next);
    if (changed) {
      try {
        window.dispatchEvent(new CustomEvent("mxli:devicechange", { detail: next }));
      } catch (e) {}
    }
    return next;
  }

  window.MXLI_DEVICE = {
    state: function () {
      return current || update();
    },
    isMobile: function () {
      return this.state().mobile;
    },
    isTablet: function () {
      return this.state().tablet;
    },
    isTouch: function () {
      return this.state().touch;
    },
    isCompact: function () {
      return this.state().compact;
    },
    refresh: update,
  };

  update();

  var resizeTimer = null;
  function onResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(update, 120);
  }
  window.addEventListener("resize", onResize);
  window.addEventListener("orientationchange", onResize);

  /* ---- Menú del encabezado (hamburguesa) ---- */
  function initHeaderMenu() {
    var btn = document.getElementById("mxliHeaderMenuBtn");
    var menu = document.getElementById("mxliHeaderMenu");
    if (!btn || !menu || btn.dataset.ready === "1") return;
    btn.dataset.ready = "1";

    function setOpen(open) {
      menu.classList.toggle("is-open", open);
      btn.classList.toggle("is-open", open);
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      document.body.classList.toggle("mxli-header-menu-open", open);
    }

    btn.addEventListener("click", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      setOpen(!menu.classList.contains("is-open"));
    });

    menu.addEventListener("click", function (ev) {
      if (ev.target.closest("a, button")) {
        setOpen(false);
      }
    });

    document.addEventListener("click", function (ev) {
      if (!menu.classList.contains("is-open")) return;
      if (menu.contains(ev.target) || btn.contains(ev.target)) return;
      setOpen(false);
    });

    document.addEventListener("keydown", function (ev) {
      if (ev.key === "Escape" && menu.classList.contains("is-open")) {
        setOpen(false);
        btn.focus();
      }
    });

    window.addEventListener("mxli:devicechange", function (ev) {
      if (!ev.detail || !ev.detail.compact) setOpen(false);
    });
  }

  /* ---- Panel de capas como cajón en celular ---- */
  function closeLayerPanels() {
    var open = document.querySelectorAll(".map-sideElement.map-open");
    for (var i = 0; i < open.length; i++) {
      open[i].classList.remove("map-open");
    }
  }

  function initLayerDrawer() {
    var panels = document.querySelectorAll(".map-sideElement");
    if (!panels.length || document.getElementById("mxliDrawerBackdrop")) return;

    var backdrop = document.createElement("div");
    backdrop.id = "mxliDrawerBackdrop";
    backdrop.className = "mxli-drawer-backdrop";
    backdrop.setAttribute("aria-hidden", "true");
    document.body.appendChild(backdrop);

    function sync() {
      var isOpen = !!document.querySelector(".map-sideElement.map-open");
      document.body.classList.toggle("mxli-drawer-open", isOpen && current && current.mobile);
    }

    backdrop.addEventListener("click", function (ev) {
      ev.preventDefault();
      closeLayerPanels();
      sync();
    });

    var capasBtn = document.getElementById("mxliCapasBtn");
    var railBtn = document.getElementById("mapMenu-btn");
    if (capasBtn && railBtn) {
      var toggleFromToolbar = function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        railBtn.click();
      };
      capasBtn.addEventListener("click", toggleFromToolbar);
      capasBtn.addEventListener("keydown", function (ev) {
        if (ev.key === "Enter" || ev.key === " ") toggleFromToolbar(ev);
      });
    }

    var observer = new MutationObserver(sync);
    for (var i = 0; i < panels.length; i++) {
      observer.observe(panels[i], { attributes: true, attributeFilter: ["class"] });
    }

    window.addEventListener("mxli:devicechange", sync);

    if (current && current.mobile) closeLayerPanels();
    sync();
  }

  /* ---- Popups de Leaflet: que no queden debajo de la barra de búsqueda ---- */
  function toolbarBottom() {
    var tb = document.getElementById("map-top-toolbar");
    if (!tb) return 0;
    var rect = tb.getBoundingClientRect();
    return rect.bottom > 0 ? Math.round(rect.bottom) : 0;
  }

  function tunePopups() {
    if (typeof L === "undefined" || !L.Popup || !L.point) return;
    var opts = L.Popup.prototype.options;
    if (!opts.__mxliDefaults) {
      opts.__mxliDefaults = {
        topLeft: opts.autoPanPaddingTopLeft,
        bottomRight: opts.autoPanPaddingBottomRight,
      };
    }
    if (current && current.compact) {
      opts.autoPanPaddingTopLeft = L.point(10, toolbarBottom() + 10);
      opts.autoPanPaddingBottomRight = L.point(10, 16);
    } else {
      opts.autoPanPaddingTopLeft = opts.__mxliDefaults.topLeft;
      opts.autoPanPaddingBottomRight = opts.__mxliDefaults.bottomRight;
    }
  }

  function onReady() {
    update();
    initHeaderMenu();
    initLayerDrawer();
    tunePopups();
    setTimeout(tunePopups, 1500);
    window.addEventListener("mxli:devicechange", function () {
      tunePopups();
      if (typeof window.mxliFixMapSize === "function") {
        setTimeout(window.mxliFixMapSize, 150);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", onReady);
  } else {
    onReady();
  }
})();
