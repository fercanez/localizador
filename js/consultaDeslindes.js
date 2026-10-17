(function () {
  "use strict";

  if (window.MxliConsultaDeslindesInit) return;
  window.MxliConsultaDeslindesInit = true;

  var busy = false;

  function canDeslindes() {
    return typeof window.mxliCan === "function" && window.mxliCan("map.deslindes");
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
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
      "<div><h3>Trámites de deslinde por ventanilla</h3>" +
      "<p>Peritos adscritos · Consulta padrón catastral, Ayuntamiento de Mexicali</p></div>" +
      '<button type="button" class="planos-close" id="mxli-deslindes-close" title="Cerrar" aria-label="Cerrar">×</button>' +
      "</div>" +
      '<div class="planos-toolbar">' +
      '<form class="planos-search" id="mxli-deslindes-form">' +
      '<label for="mxli-deslindes-q">No. de Trámite</label>' +
      '<input id="mxli-deslindes-q" name="tramite" inputmode="numeric" maxlength="12" autocomplete="off" placeholder="575617">' +
      '<button type="submit" id="mxli-deslindes-submit">Consultar</button>' +
      "</form>" +
      '<p class="planos-meta" id="mxli-deslindes-meta">Capture el número de trámite. Haga clic en la clave catastral para ubicar el predio en el mapa.</p>' +
      "</div>" +
      '<div class="planos-list-wrap deslindes-list" id="mxli-deslindes-list">' +
      '<div class="planos-empty">Sin datos para mostrar.</div>' +
      "</div></div>";
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
      var btn = ev.target.closest("[data-deslinde-clave]");
      if (!btn) return;
      ev.preventDefault();
      ubicar(btn.getAttribute("data-deslinde-clave") || "");
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
    if (!canDeslindes()) {
      window.alert("Su rol no tiene permiso para consultar trámites de deslinde.");
      return;
    }
    var dlg = ensureDialog();
    var input = document.getElementById("mxli-deslindes-q");
    if (preset && input) input.value = String(preset);
    if (!dlg.open) {
      if (typeof dlg.showModal === "function") dlg.showModal();
      else dlg.setAttribute("open", "");
    }
    if (input) input.focus();
    if (preset) consultar();
  }

  function ubicar(clave) {
    clave = String(clave || "").replace(/\s+/g, "").toUpperCase();
    if (!clave) return;
    if (!window.BuscarPredioMxli || typeof window.BuscarPredioMxli.buscar !== "function") {
      window.alert("El buscador de predios no está disponible.");
      return;
    }
    closeDialog();
    var input = document.getElementById("buscarPredioClave");
    if (input) input.value = clave;
    window.BuscarPredioMxli.buscar(clave);
  }

  function setMessage(html) {
    var wrap = document.getElementById("mxli-deslindes-list");
    if (wrap) wrap.innerHTML = '<div class="planos-empty">' + html + "</div>";
  }

  function render(data) {
    var wrap = document.getElementById("mxli-deslindes-list");
    var meta = document.getElementById("mxli-deslindes-meta");
    var rows = Array.isArray(data.rows) ? data.rows : [];
    if (meta) {
      meta.innerHTML =
        "Trámite <b>" +
        escapeHtml(data.tramite || "") +
        "</b> — " +
        rows.length +
        " registro" +
        (rows.length === 1 ? "" : "s") +
        (data.cache ? " · consultado hace unos minutos" : "");
    }
    if (!wrap) return;
    if (!rows.length) {
      setMessage(escapeHtml(data.mensaje || "Sin trámites para ese número."));
      return;
    }
    var canLocate = typeof window.mxliCan !== "function" || window.mxliCan("map.search");
    var body = rows
      .map(function (r) {
        var clave = String(r.clave_catastral || "");
        var claveHtml =
          clave && canLocate
            ? '<button type="button" class="deslindes-clave" data-deslinde-clave="' +
              escapeHtml(clave) +
              '" title="Ubicar predio en el mapa">' +
              escapeHtml(clave) +
              "</button>"
            : escapeHtml(clave || "—");
        return (
          "<tr>" +
          '<td data-label="Folio">' + escapeHtml(r.folio || "") + "</td>" +
          '<td data-label="Clave catastral">' + claveHtml + "</td>" +
          '<td data-label="Estatus"><span class="deslindes-estatus">' + escapeHtml(r.estatus || "") + "</span></td>" +
          '<td data-label="Fecha">' + escapeHtml(r.fecha || "") + "</td>" +
          '<td data-label="Tramitador">' + escapeHtml(r.tramitador || "") + "</td>" +
          '<td data-label="Perito">' + escapeHtml(r.perito || "") + "</td>" +
          '<td data-label="Fecha término">' + escapeHtml(r.fecha_termino || "") + "</td>" +
          "</tr>"
        );
      })
      .join("");
    wrap.innerHTML =
      "<table class='planos-table deslindes-table'><thead><tr>" +
      "<th>Folio</th><th>Clave catastral</th><th>Estatus</th><th>Fecha</th>" +
      "<th>Tramitador</th><th>Perito</th><th>Fecha término</th>" +
      "</tr></thead><tbody>" +
      body +
      "</tbody></table>";
  }

  function consultar() {
    if (!canDeslindes() || busy) return;
    var input = document.getElementById("mxli-deslindes-q");
    var tramite = String((input && input.value) || "").replace(/\D+/g, "");
    if (!tramite) {
      window.alert("Capture el No. de Trámite (solo números).");
      return;
    }
    if (input) input.value = tramite;
    busy = true;
    var submit = document.getElementById("mxli-deslindes-submit");
    if (submit) submit.disabled = true;
    setMessage("Consultando el portal del Ayuntamiento…");

    fetch("api/deslindes.php?tramite=" + encodeURIComponent(tramite), {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || data.ok === false) {
            throw new Error((data && data.error) || "No se pudo consultar el trámite.");
          }
          return data;
        });
      })
      .then(render)
      .catch(function (err) {
        setMessage(escapeHtml(err.message || "No se pudo consultar el trámite."));
      })
      .then(function () {
        busy = false;
        if (submit) submit.disabled = false;
      });
  }

  // La barra del mapa detiene la propagación de clics: enlazar el icono directamente.
  function bindToolbarButton() {
    var btn = document.getElementById("consultaDeslindesBtn");
    if (!btn || btn.getAttribute("data-deslindes-bound") === "1") return;
    btn.setAttribute("data-deslindes-bound", "1");
    btn.addEventListener("click", function (ev) {
      ev.preventDefault();
      openDialog();
    });
    btn.addEventListener("keydown", function (ev) {
      if (ev.key === "Enter" || ev.key === " ") {
        ev.preventDefault();
        openDialog();
      }
    });
  }

  document.addEventListener("click", function (ev) {
    var trigger = ev.target.closest("#consultaDeslindesHeaderBtn, [data-open-deslindes]");
    if (trigger) {
      ev.preventDefault();
      openDialog(trigger.getAttribute("data-open-deslindes") || "");
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindToolbarButton);
  } else {
    bindToolbarButton();
  }

  window.mxliOpenDeslindes = openDialog;
})();
