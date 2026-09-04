(function () {
  "use strict";

  function ensureDialog() {
    var dlg = document.getElementById("mxli-expediente-dialog");
    if (dlg) return dlg;

    dlg = document.createElement("dialog");
    dlg.id = "mxli-expediente-dialog";
    dlg.className = "mxli-expediente-dialog";
    dlg.innerHTML =
      '<div class="mxli-expediente-dialog__bar">' +
      '<strong id="mxli-expediente-dialog-title">Expediente</strong>' +
      '<button type="button" class="mxli-expediente-dialog__close" id="mxli-expediente-dialog-close" title="Cerrar">×</button>' +
      "</div>" +
      '<iframe id="mxli-expediente-frame" class="mxli-expediente-dialog__frame" title="Expediente digital"></iframe>';
    document.body.appendChild(dlg);

    document
      .getElementById("mxli-expediente-dialog-close")
      .addEventListener("click", closeExpedienteDialog);

    dlg.addEventListener("cancel", function (e) {
      e.preventDefault();
      closeExpedienteDialog();
    });

    window.addEventListener("message", function (ev) {
      if (!ev || !ev.data || ev.data.type !== "mxli-expediente-close") return;
      closeExpedienteDialog();
    });

    return dlg;
  }

  function closeExpedienteDialog() {
    var dlg = document.getElementById("mxli-expediente-dialog");
    var frame = document.getElementById("mxli-expediente-frame");
    if (frame) frame.src = "about:blank";
    if (!dlg) return;
    if (typeof dlg.close === "function") dlg.close();
    else dlg.removeAttribute("open");
    if (typeof map2d !== "undefined" && map2d && map2d.invalidateSize) {
      setTimeout(function () {
        map2d.invalidateSize();
      }, 50);
    }
  }

  function openExpediente(clave) {
    var key = String(clave || "")
      .trim()
      .replace(/\s+/g, "")
      .toUpperCase();
    if (key.length < 4) {
      window.alert("Clave catastral no válida para expediente.");
      return;
    }
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.expediente")) {
      window.alert("Su rol no tiene permiso para consultar expedientes.");
      return;
    }

    if (typeof window.mxliSaveMapState === "function") {
      window.mxliSaveMapState();
    }

    var dlg = ensureDialog();
    var title = document.getElementById("mxli-expediente-dialog-title");
    var frame = document.getElementById("mxli-expediente-frame");
    if (title) title.textContent = "Expediente " + key;
    if (frame) {
      frame.src = "expediente.php?clave=" + encodeURIComponent(key) + "&embed=1";
    }

    if (typeof dlg.showModal === "function") dlg.showModal();
    else dlg.setAttribute("open", "");
  }

  document.addEventListener("click", function (event) {
    var btn = event.target && event.target.closest
      ? event.target.closest("[data-expediente-clave]")
      : null;
    if (!btn) return;
    event.preventDefault();
    event.stopPropagation();
    openExpediente(btn.getAttribute("data-expediente-clave") || "");
  });

  window.mxliOpenExpediente = openExpediente;
  window.mxliCloseExpedienteDialog = closeExpedienteDialog;
})();
