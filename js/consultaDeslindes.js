(function () {
  "use strict";

  if (window.MxliConsultaDeslindesInit) return;
  window.MxliConsultaDeslindesInit = true;

  var lastItems = [];
  var lastFolio = "";

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

  function estatusClass(estatus) {
    var t = String(estatus || "").toUpperCase();
    if (t.indexOf("VISTO BUENO") !== -1 || t.indexOf("TERMINADO") !== -1 || t.indexOf("AUTORIZ") !== -1) {
      return "deslindes-status--ok";
    }
    if (t.indexOf("RECHAZ") !== -1 || t.indexOf("CANCEL") !== -1) {
      return "deslindes-status--bad";
    }
    if (t.indexOf("PEND") !== -1 || t.indexOf("PROCESO") !== -1 || t.indexOf("REVISION") !== -1) {
      return "deslindes-status--wait";
    }
    return "";
  }

  function ensureDialog() {
    var dlg = document.getElementById("mxli-deslindes-dialog");
    if (dlg) return dlg;

    dlg = document.createElement("dialog");
    dlg.id = "mxli-deslindes-dialog";
    dlg.className = "planos-dialog deslindes-dialog";
    dlg.innerHTML =
      '<div class="planos-shell">' +
      '<div class="planos-bar">' +
      "<div><h3>Consulta de deslindes</h3>" +
      "<p>Padrón catastral · No. de Trámite (folio)</p></div>" +
      '<button type="button" class="planos-close" id="mxli-deslindes-close" title="Cerrar" aria-label="Cerrar">×</button>' +
      "</div>" +
      '<div class="planos-toolbar">' +
      '<form class="planos-search" id="mxli-deslindes-form">' +
      '<label for="mxli-deslindes-q">No. de Trámite</label>' +
      '<input id="mxli-deslindes-q" name="folio" maxlength="12" inputmode="numeric" autocomplete="off" placeholder="1881356" aria-label="No. de Trámite">' +
      '<button type="submit">Consultar</button>' +
      "</form>" +
      '<p class="planos-meta" id="mxli-deslindes-meta">Capture el folio del trámite para ver estatus, tramitador y perito.</p>' +
      "</div>" +
      '<div class="planos-main deslindes-main">' +
      '<div class="planos-list-wrap deslindes-table-wrap" id="mxli-deslindes-list">' +
      '<div class="planos-empty">Sin datos para mostrar.</div>' +
      "</div></div></div>";
    document.body.appendChild(dlg);

    document.getElementById("mxli-deslindes-close").addEventListener("click", closeDialog);
    dlg.addEventListener("cancel", function (ev) {
      ev.preventDefault();
      closeDialog();
    });
    document.getElementById("mxli-deslindes-form").addEventListener("submit", function (ev) {
      ev.preventDefault();
      consultar();
    });
    document.getElementById("mxli-deslindes-list").addEventListener("click", function (ev) {
      var row = ev.target.closest("[data-clave]");
      if (!row) return;
      ev.preventDefault();
      localizarClave(row.getAttribute("data-clave") || "");
    });
    return dlg;
  }

  function closeDialog() {
    var dlg = document.getElementById("mxli-deslindes-dialog");
    if (!dlg) return;
    if (typeof dlg.close === "function") dlg.close();
    else dlg.removeAttribute("open");
  }

  function openDialog(preset) {
    if (!canPlanos()) {
      window.alert("Su rol no tiene permiso para consultar deslindes.");
      return;
    }
    var dlg = ensureDialog();
    var input = document.getElementById("mxli-deslindes-q");
    if (preset && input) {
      input.value = String(preset).replace(/\D/g, "");
    }
    if (typeof dlg.showModal === "function") dlg.showModal();
    else dlg.setAttribute("open", "");
    if (input) input.focus();
  }

  function localizarClave(clave) {
    if (!clave) return;
    var input = document.getElementById("buscarPredioClave");
    var btn = document.getElementById("btnBuscarPredioClave");
    if (input) input.value = clave;
    if (btn) btn.click();
    closeDialog();
  }

  function render() {
    var wrap = document.getElementById("mxli-deslindes-list");
    var meta = document.getElementById("mxli-deslindes-meta");
    if (!wrap) return;

    var total = lastItems.length;
    if (meta) {
      meta.innerHTML = lastFolio
        ? "Folio <b>" + escapeHtml(lastFolio) + "</b> — " + total + " registro" + (total === 1 ? "" : "s")
        : "Capture el folio del trámite para ver estatus, tramitador y perito.";
    }

    if (!total) {
      wrap.innerHTML =
        '<div class="planos-empty">No se encontró trámite para ese folio.</div>';
      return;
    }

    var rows = lastItems
      .map(function (item) {
        var clave = String(item.clave_catastral || "");
        var est = String(item.estatus || "");
        return (
          "<tr class='planos-row' data-clave='" +
          escapeHtml(clave) +
          "' title='Localizar predio en el mapa'>" +
          "<td>" +
          escapeHtml(item.folio || lastFolio) +
          "</td><td><b>" +
          escapeHtml(clave) +
          "</b></td><td><span class='deslindes-status " +
          estatusClass(est) +
          "'>" +
          escapeHtml(est) +
          "</span></td><td>" +
          escapeHtml(item.fecha || "") +
          "</td><td>" +
          escapeHtml(item.tramitador || "") +
          "</td><td>" +
          escapeHtml(item.perito || "") +
          "</td><td>" +
          escapeHtml(item.fecha_termino || "") +
          "</td></tr>"
        );
      })
      .join("");

    wrap.innerHTML =
      "<table class='planos-table deslindes-table'><thead><tr>" +
      "<th>FOLIO</th><th>CLAVE CATASTRAL</th><th>ESTATUS</th><th>FECHA</th>" +
      "<th>TRAMITADOR</th><th>PERITO</th><th>FECHA TÉRMINO</th>" +
      "</tr></thead><tbody>" +
      rows +
      "</tbody></table>";
  }

  function consultar() {
    if (!canPlanos()) return;
    var input = document.getElementById("mxli-deslindes-q");
    var q = String((input && input.value) || "")
      .trim()
      .replace(/\s+/g, "");
    if (!/^[0-9]{4,12}$/.test(q)) {
      window.alert("Indique el No. de Trámite (folio numérico). Ejemplo: 1881356");
      return;
    }
    var wrap = document.getElementById("mxli-deslindes-list");
    if (wrap) wrap.innerHTML = '<div class="planos-empty">Consultando folio…</div>';

    fetch("api/deslindes.php?folio=" + encodeURIComponent(q), { credentials: "same-origin" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || data.ok === false) {
            throw new Error((data && data.error) || "No se pudo consultar el deslinde.");
          }
          return data;
        });
      })
      .then(function (data) {
        lastFolio = data.folio || q;
        lastItems = Array.isArray(data.items) ? data.items : [];
        render();
        if (!lastItems.length && data.aviso) {
          var wrapEl = document.getElementById("mxli-deslindes-list");
          if (wrapEl) {
            wrapEl.innerHTML =
              '<div class="planos-empty">No se encontró trámite para ese folio.<br><small>' +
              escapeHtml(data.aviso) +
              "</small></div>";
          }
        }
      })
      .catch(function (err) {
        lastItems = [];
        lastFolio = q;
        render();
        var wrapEl = document.getElementById("mxli-deslindes-list");
        if (wrapEl) {
          wrapEl.innerHTML =
            '<div class="planos-empty">' +
            escapeHtml(err.message || "No se pudo consultar el deslinde.") +
            "</div>";
        }
      });
  }

  document.addEventListener("click", function (ev) {
    if (ev.target.closest("#consultaDeslindesHeaderBtn, [data-open-deslindes]")) {
      ev.preventDefault();
      openDialog();
    }
  });

  window.mxliOpenDeslindes = openDialog;
})();
