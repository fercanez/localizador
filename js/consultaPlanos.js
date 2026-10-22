(function () {
  "use strict";

  if (window.MxliConsultaPlanosInit) return;
  window.MxliConsultaPlanosInit = true;

  var lastItems = [];
  var lastQuery = "";
  var lastColonia = "";
  var selectedRel = "";

  function canPlanos() {
    return typeof window.mxliCan === "function" && window.mxliCan("map.planos");
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function ensureDialog() {
    var dlg = document.getElementById("mxli-planos-dialog");
    if (dlg) return dlg;

    dlg = document.createElement("dialog");
    dlg.id = "mxli-planos-dialog";
    dlg.className = "planos-dialog";
    dlg.innerHTML =
      '<div class="planos-shell">' +
      '<div class="planos-bar">' +
      "<div><h3>Consulta de planos</h3>" +
      "<p>Ayuntamiento de Mexicali · padrón catastral</p></div>" +
      '<button type="button" class="planos-close" id="mxli-planos-close" title="Cerrar" aria-label="Cerrar">×</button>' +
      "</div>" +
      '<div class="planos-toolbar">' +
      '<form class="planos-search" id="mxli-planos-form">' +
      "<label for=\"mxli-planos-q\">Colonia / homoclave</label>" +
      '<input id="mxli-planos-q" name="q" maxlength="4" autocomplete="off" placeholder="SP" aria-label="Homoclave">' +
      '<button type="submit">Buscar</button>' +
      "</form>" +
      '<p class="planos-meta" id="mxli-planos-meta">Las 2 o 3 primeras letras de la clave catastral (ej. ST = Santa Teresa).</p>' +
      "</div>" +
      '<div class="planos-main">' +
      '<div class="planos-list-pane">' +
      '<div class="planos-list-wrap" id="mxli-planos-list">' +
      '<div class="planos-empty">Sin datos para mostrar.</div>' +
      "</div></div>" +
      '<div class="planos-preview-pane">' +
      '<iframe id="mxli-planos-frame" class="planos-preview-frame" title="Vista previa del plano"></iframe>' +
      '<div class="planos-preview-placeholder" id="mxli-planos-placeholder">Seleccione un plano de la lista para previsualizarlo.</div>' +
      "</div></div></div>";
    document.body.appendChild(dlg);

    document.getElementById("mxli-planos-close").addEventListener("click", closeDialog);
    dlg.addEventListener("cancel", function (ev) {
      ev.preventDefault();
      closeDialog();
    });
    document.getElementById("mxli-planos-form").addEventListener("submit", function (ev) {
      ev.preventDefault();
      buscar();
    });
    document.getElementById("mxli-planos-list").addEventListener("click", function (ev) {
      var row = ev.target.closest("[data-planos-rel]");
      if (!row) return;
      ev.preventDefault();
      previewRel(row.getAttribute("data-planos-rel") || "", row.getAttribute("data-planos-name") || "");
    });
    return dlg;
  }

  function closeDialog() {
    var dlg = document.getElementById("mxli-planos-dialog");
    var frame = document.getElementById("mxli-planos-frame");
    if (frame) frame.src = "about:blank";
    selectedRel = "";
    if (!dlg) return;
    if (typeof dlg.close === "function") dlg.close();
    else dlg.removeAttribute("open");
  }

  function openDialog(preset) {
    if (!canPlanos()) {
      window.alert("Su rol no tiene permiso para consultar planos.");
      return;
    }
    var dlg = ensureDialog();
    var input = document.getElementById("mxli-planos-q");
    if (preset && input && !input.value) {
      input.value = String(preset).toUpperCase();
    }
    if (typeof dlg.showModal === "function") dlg.showModal();
    else dlg.setAttribute("open", "");
    if (input) input.focus();
  }

  function setPlaceholder(on, text) {
    var ph = document.getElementById("mxli-planos-placeholder");
    var frame = document.getElementById("mxli-planos-frame");
    if (ph) {
      if (text) ph.textContent = text;
      ph.hidden = !on;
    }
    if (frame) frame.classList.toggle("is-on", !on);
  }

  function previewRel(rel, name) {
    if (!rel) return;
    selectedRel = rel;
    var frame = document.getElementById("mxli-planos-frame");
    setPlaceholder(false);
    if (frame) {
      frame.src = "plano.php?f=" + encodeURIComponent(rel) + "&embed=1";
    }
    var rows = document.querySelectorAll("#mxli-planos-list [data-planos-rel]");
    for (var i = 0; i < rows.length; i++) {
      rows[i].classList.toggle("is-selected", rows[i].getAttribute("data-planos-rel") === rel);
    }
    if (name) {
      var meta = document.getElementById("mxli-planos-meta");
      if (meta && lastQuery) {
        meta.innerHTML =
          "Homoclave <b>" +
          escapeHtml(lastQuery) +
          "</b>" +
          (lastColonia ? " · " + escapeHtml(lastColonia) : "") +
          " — " +
          lastItems.length +
          " plano" +
          (lastItems.length === 1 ? "" : "s");
      }
    }
  }

  function render() {
    var wrap = document.getElementById("mxli-planos-list");
    var meta = document.getElementById("mxli-planos-meta");
    if (!wrap) return;

    var total = lastItems.length;
    var hint = lastQuery
      ? "Homoclave <b>" +
        escapeHtml(lastQuery) +
        "</b>" +
        (lastColonia ? " · " + escapeHtml(lastColonia) : "") +
        " — " +
        total +
        " plano" +
        (total === 1 ? "" : "s")
      : "Las 2 o 3 primeras letras de la clave catastral (ej. ST = Santa Teresa).";
    if (meta) meta.innerHTML = hint;

    if (!total) {
      wrap.innerHTML = '<div class="planos-empty">Sin datos para mostrar.</div>';
      selectedRel = "";
      var frame = document.getElementById("mxli-planos-frame");
      if (frame) frame.src = "about:blank";
      setPlaceholder(true, "Sin datos para mostrar.");
      return;
    }

    var rows = lastItems
      .map(function (item) {
        var ext = String(item.ext || "").toLowerCase();
        var rel = String(item.rel || "");
        var sel = rel === selectedRel ? " is-selected" : "";
        return (
          "<tr class='planos-row" +
          sel +
          "' data-planos-rel='" +
          escapeHtml(rel) +
          "' data-planos-name='" +
          escapeHtml(item.name || "") +
          "' role='button' tabindex='0'>" +
          "<td><span class='planos-badge planos-badge--" +
          escapeHtml(ext) +
          "'>" +
          escapeHtml(ext.toUpperCase()) +
          "</span></td><td>" +
          escapeHtml(item.name) +
          "</td></tr>"
        );
      })
      .join("");

    wrap.innerHTML =
      "<table class='planos-table'><thead><tr><th>Tipo</th><th>Planos</th></tr></thead><tbody>" +
      rows +
      "</tbody></table>";
  }

  function buscar() {
    if (!canPlanos()) return;
    var input = document.getElementById("mxli-planos-q");
    var q = String((input && input.value) || "")
      .trim()
      .replace(/\s+/g, "")
      .toUpperCase();
    if (!/^[A-Z][A-Z0-9]{1,3}$/.test(q)) {
      window.alert("Indique la homoclave (2 a 4 caracteres). Ejemplo: SP");
      return;
    }
    var wrap = document.getElementById("mxli-planos-list");
    if (wrap) wrap.innerHTML = '<div class="planos-empty">Buscando planos…</div>';
    setPlaceholder(true, "Buscando planos…");

    fetch("api/planos.php?q=" + encodeURIComponent(q), { credentials: "same-origin" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || data.ok === false) {
            throw new Error((data && data.error) || "No se pudieron cargar los planos.");
          }
          return data;
        });
      })
      .then(function (data) {
        lastQuery = data.q || q;
        lastColonia = data.colonia || "";
        lastItems = Array.isArray(data.items) ? data.items : [];
        selectedRel = "";
        render();
        if (lastItems.length) {
          previewRel(lastItems[0].rel || "", lastItems[0].name || "");
        }
      })
      .catch(function (err) {
        lastItems = [];
        lastQuery = q;
        lastColonia = "";
        selectedRel = "";
        render();
        var wrapEl = document.getElementById("mxli-planos-list");
        if (wrapEl) {
          wrapEl.innerHTML =
            '<div class="planos-empty">' +
            escapeHtml(err.message || "No se pudieron cargar los planos.") +
            "</div>";
        }
      });
  }

  function esBotonPlanos(target) {
    return !!(target && target.closest && target.closest("#consultaPlanosBtn, #consultaPlanosHeaderBtn, [data-open-planos]"));
  }

  function alAbrirPlanos(ev) {
    if (!esBotonPlanos(ev.target)) return;
    ev.preventDefault();
    ev.stopPropagation();
    openDialog();
  }

  // Captura: la barra del mapa detiene el clic y el listener normal no llega.
  document.addEventListener("click", alAbrirPlanos, true);
  document.addEventListener("keydown", function (ev) {
    if (ev.key !== "Enter" && ev.key !== " ") return;
    if (!esBotonPlanos(ev.target)) return;
    ev.preventDefault();
    openDialog();
  });

  document.addEventListener("keydown", function (ev) {
    var row = ev.target.closest && ev.target.closest("#mxli-planos-list [data-planos-rel]");
    if (!row) return;
    if (ev.key === "Enter" || ev.key === " ") {
      ev.preventDefault();
      previewRel(row.getAttribute("data-planos-rel") || "", row.getAttribute("data-planos-name") || "");
    }
  });

  window.mxliOpenPlanos = openDialog;
})();
