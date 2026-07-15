(function () {
  "use strict";

  const STORAGE_KEY = "mxli-map-toolbar-pos";

  function restorePosition(toolbar) {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      if (!saved || typeof saved.top !== "number" || typeof saved.left !== "number") {
        return;
      }

      toolbar.style.top = saved.top + "px";
      toolbar.style.left = saved.left + "px";
      toolbar.style.right = "auto";
    } catch (error) {
      /* ignore */
    }
  }

  function savePosition(toolbar) {
    const rect = toolbar.getBoundingClientRect();
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        top: Math.round(rect.top),
        left: Math.round(rect.left),
      })
    );
  }

  function clampPosition(toolbar) {
    const rect = toolbar.getBoundingClientRect();
    const maxLeft = Math.max(8, window.innerWidth - rect.width - 8);
    const maxTop = Math.max(88, window.innerHeight - rect.height - 8);
    const left = Math.min(Math.max(8, rect.left), maxLeft);
    const top = Math.min(Math.max(88, rect.top), maxTop);

    toolbar.style.left = left + "px";
    toolbar.style.top = top + "px";
    toolbar.style.right = "auto";
  }

  function stopMapInteraction(event, keepDefault) {
    event.stopPropagation();
    if (typeof L !== "undefined" && L.DomEvent) {
      L.DomEvent.stopPropagation(event);
    }
    if (!keepDefault) {
      event.preventDefault();
      if (typeof L !== "undefined" && L.DomEvent) {
        L.DomEvent.preventDefault(event);
      }
    }
  }

  function isToolbarInteractiveTarget(target) {
    return !!target.closest(
      "input, button, textarea, select, a, .control-icon, .buscador-predio-btn, .buscador-predio-input"
    );
  }

  function disableMapDrag() {
    if (typeof map2d !== "undefined" && map2d.dragging) {
      map2d.dragging.disable();
    }
  }

  function enableMapDrag() {
    if (typeof map2d !== "undefined" && map2d.dragging) {
      map2d.dragging.enable();
    }
  }

  function makeDraggable(toolbar, handle) {
    let dragging = false;
    let offsetX = 0;
    let offsetY = 0;

    function onPointerDown(event) {
      if (event.button !== undefined && event.button !== 0) return;

      dragging = true;
      const rect = toolbar.getBoundingClientRect();
      toolbar.style.left = rect.left + "px";
      toolbar.style.top = rect.top + "px";
      toolbar.style.right = "auto";
      offsetX = event.clientX - rect.left;
      offsetY = event.clientY - rect.top;
      toolbar.classList.add("is-dragging");
      disableMapDrag();
      stopMapInteraction(event, false);

      if (handle.setPointerCapture && event.pointerId !== undefined) {
        handle.setPointerCapture(event.pointerId);
      }
    }

    function onPointerMove(event) {
      if (!dragging) return;

      stopMapInteraction(event, false);
      toolbar.style.left = event.clientX - offsetX + "px";
      toolbar.style.top = event.clientY - offsetY + "px";
      toolbar.style.right = "auto";
    }

    function onPointerUp(event) {
      if (!dragging) return;

      dragging = false;
      toolbar.classList.remove("is-dragging");
      enableMapDrag();
      clampPosition(toolbar);
      savePosition(toolbar);
      stopMapInteraction(event, false);

      if (handle.releasePointerCapture && event.pointerId !== undefined) {
        try {
          handle.releasePointerCapture(event.pointerId);
        } catch (error) {
          /* ignore */
        }
      }
    }

    handle.addEventListener("mousedown", onPointerDown);
    handle.addEventListener("pointerdown", onPointerDown);
    window.addEventListener("mousemove", onPointerMove);
    window.addEventListener("pointermove", onPointerMove);
    window.addEventListener("mouseup", onPointerUp);
    window.addEventListener("pointerup", onPointerUp);
    window.addEventListener("pointercancel", onPointerUp);
  }

  function mountToolbarItems() {
    const toolbar = document.getElementById("map-top-toolbar");
    const actions = document.getElementById("map-top-toolbar-actions");
    const tools = document.getElementById("tools");
    const zoom = document.querySelector(".leaflet-control-zoom");

    if (!toolbar || !actions) return false;

    if (tools && tools.parentElement !== actions) {
      tools.classList.add("map-top-toolbar__tools");
      actions.appendChild(tools);
    }

    if (zoom && zoom.parentElement !== actions) {
      zoom.classList.add("map-top-toolbar__zoom");
      actions.appendChild(zoom);
    }

    return !!(tools && zoom);
  }

  function initMapTopToolbar() {
    const toolbar = document.getElementById("map-top-toolbar");
    const handle = toolbar && toolbar.querySelector(".map-top-toolbar__handle");

    if (!toolbar || !handle) {
      setTimeout(initMapTopToolbar, 300);
      return;
    }

    if (!mountToolbarItems()) {
      setTimeout(initMapTopToolbar, 300);
      return;
    }

    restorePosition(toolbar);
    clampPosition(toolbar);
    makeDraggable(toolbar, handle);

    ["mousedown", "pointerdown", "click", "dblclick", "wheel", "touchstart"].forEach(
      function (eventName) {
        toolbar.addEventListener(eventName, function (event) {
          stopMapInteraction(event, isToolbarInteractiveTarget(event.target));
        });
      }
    );

    handle.addEventListener("click", function (event) {
      stopMapInteraction(event, false);
    });

    if (typeof L !== "undefined" && L.DomEvent) {
      L.DomEvent.disableClickPropagation(toolbar);
      L.DomEvent.disableScrollPropagation(toolbar);
    }

    window.addEventListener("resize", function () {
      clampPosition(toolbar);
    });

    initPrintMenu();
  }

  function initPrintMenu() {
    const printBtn = document.getElementById("printMapBtn");
    const modeDialog = document.getElementById("printMode-dialog");
    const cancelBtn = document.getElementById("printModeCancel");

    if (!printBtn || !modeDialog || printBtn.dataset.printMenuReady === "1") {
      return;
    }
    printBtn.dataset.printMenuReady = "1";

    function openModeDialog(event) {
      if (typeof window.mxliCan === "function" && !window.mxliCan("map.print")) {
        if (event) stopMapInteraction(event, false);
        window.alert("Su rol no tiene permiso para imprimir.");
        return;
      }
      if (event) stopMapInteraction(event, false);
      if (typeof modeDialog.showModal === "function") {
        modeDialog.showModal();
      } else {
        modeDialog.setAttribute("open", "");
      }
    }

    function closeModeDialog() {
      if (typeof modeDialog.close === "function") {
        modeDialog.close();
      } else {
        modeDialog.removeAttribute("open");
      }
    }

    printBtn.addEventListener("click", openModeDialog);
    printBtn.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        openModeDialog(event);
      }
    });

    if (cancelBtn) {
      cancelBtn.addEventListener("click", function (event) {
        event.preventDefault();
        closeModeDialog();
      });
    }

    modeDialog.querySelectorAll("[data-print-mode]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        const mode = button.getAttribute("data-print-mode");
        closeModeDialog();
        if (typeof window.setMapTitle === "function") {
          window.setMapTitle(mode);
        }
      });
    });

    modeDialog.addEventListener("cancel", function (event) {
      event.preventDefault();
      closeModeDialog();
    });
  }

  window.initMapTopToolbar = initMapTopToolbar;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      setTimeout(initMapTopToolbar, 500);
    });
  } else {
    setTimeout(initMapTopToolbar, 500);
  }
})();
