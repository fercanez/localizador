(function () {
  "use strict";

  var API = "../api/admin/padron-import.php";
  var form = document.getElementById("padron-import-form");
  var msg = document.getElementById("padron-msg");
  var progress = document.getElementById("padron-progress");
  var submitBtn = document.getElementById("padron-submit");
  var refreshBtn = document.getElementById("padron-refresh-status");
  var loadedOnce = false;
  var EMPTY_META_MSG = "aún no se ha cargado desde este panel";

  function showMsg(text, ok) {
    if (!msg) return;
    msg.hidden = !text;
    msg.textContent = text || "";
    msg.classList.toggle("is-ok", !!ok);
    msg.classList.toggle("is-error", !ok && !!text);
  }

  function asNum(n) {
    if (n === null || n === undefined || n === "") return null;
    var x = Number(n);
    return Number.isFinite(x) ? x : null;
  }

  function fmtNum(n) {
    var x = asNum(n);
    if (x === null) return "—";
    try {
      return x.toLocaleString("es-MX");
    } catch (e) {
      return String(x);
    }
  }

  function fmtDate(value) {
    if (!value) return EMPTY_META_MSG;
    try {
      return new Date(value).toLocaleString("es-MX");
    } catch (e) {
      return String(value);
    }
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function parseJsonResponse(res) {
    return res.text().then(function (text) {
      var data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (e) {
        var snippet = (text || "").replace(/\s+/g, " ").slice(0, 180);
        throw new Error(
          "Respuesta no JSON del servidor (" + res.status + "): " + snippet
        );
      }
      if (!res.ok || !data || data.ok === false) {
        var err =
          (data && data.error) ||
          "Error al consultar el padrón (HTTP " + res.status + ")";
        if (data && data.detail) {
          err += " (" + data.detail + ")";
        }
        if (data && data.hint) {
          err += " — " + data.hint;
        }
        throw new Error(err);
      }
      return data;
    });
  }

  function renderMeta(meta, rowCount) {
    var n = asNum(rowCount);
    setText("padron-row-count", n !== null ? fmtNum(n) : "—");

    var hasMeta = !!(meta && meta.imported_at);
    if (!hasMeta) {
      setText("padron-imported-at", EMPTY_META_MSG);
      setText("padron-imported-by", "—");
      setText("padron-filename", "—");
      var box = document.getElementById("padron-last-compare");
      if (box) box.hidden = true;
      return;
    }

    setText("padron-imported-at", fmtDate(meta.imported_at));
    setText("padron-imported-by", meta.imported_by || "—");
    setText("padron-filename", meta.filename || "—");

    var hasCompare =
      meta.rows_before != null ||
      meta.rows_after != null ||
      meta.claves_nuevas != null;
    var compare = document.getElementById("padron-last-compare");
    if (compare) {
      compare.hidden = !hasCompare;
    }
    if (hasCompare) {
      setText("padron-rows-before", fmtNum(meta.rows_before));
      setText("padron-rows-after", fmtNum(meta.rows_after));
      setText("padron-claves-nuevas", fmtNum(meta.claves_nuevas));
      setText("padron-claves-cambiadas", fmtNum(meta.claves_cambiadas));
      setText("padron-claves-eliminadas", fmtNum(meta.claves_eliminadas));
      setText("padron-claves-iguales", fmtNum(meta.claves_iguales));
    }
  }

  function renderCompareFromResult(data) {
    var after = asNum(data.rows_after != null ? data.rows_after : data.row_count);
    var comp = data.comparativo || data;
    setText("padron-row-count", fmtNum(after));
    setText("padron-rows-before", fmtNum(comp.rows_before));
    setText("padron-rows-after", fmtNum(comp.rows_after != null ? comp.rows_after : after));
    setText("padron-claves-nuevas", fmtNum(comp.claves_nuevas));
    setText("padron-claves-cambiadas", fmtNum(comp.claves_cambiadas));
    setText("padron-claves-eliminadas", fmtNum(comp.claves_eliminadas));
    setText("padron-claves-iguales", fmtNum(comp.claves_iguales));
    var compare = document.getElementById("padron-last-compare");
    if (compare) compare.hidden = false;

    if (data.meta && data.meta.imported_at) {
      renderMeta(data.meta, after);
    } else {
      setText("padron-imported-at", fmtDate(new Date().toISOString()));
      setText("padron-imported-by", data.imported_by || "—");
      setText("padron-filename", data.filename || "—");
    }
  }

  function applyPermHint(data) {
    var hint = document.getElementById("padron-perm-hint");
    if (!hint) return;
    if (data.hint) {
      hint.hidden = false;
      hint.textContent = data.hint;
    } else if (!data.can_insert || !data.can_truncate) {
      hint.hidden = false;
      hint.textContent =
        "Faltan permisos INSERT/TRUNCATE. Ejecute sql/grant_padron_loader.sql como postgres.";
    } else if (data.meta_error) {
      hint.hidden = false;
      hint.textContent =
        "Meta de importación no disponible: " + data.meta_error;
    } else {
      hint.hidden = true;
      hint.textContent = "";
    }
  }

  function loadStatus() {
    return fetch(API + "?action=status", {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(parseJsonResponse)
      .then(function (data) {
        renderMeta(data.meta, data.row_count);
        applyPermHint(data);
        if (data.count_error) {
          showMsg("Aviso de conteo: " + data.count_error, false);
        }
        loadedOnce = true;
        return data;
      })
      .catch(function (err) {
        showMsg(err.message || "Error de estado", false);
      });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      showMsg("", true);
      refreshBtn.disabled = true;
      loadStatus().finally(function () {
        refreshBtn.disabled = false;
      });
    });
  }

  document.addEventListener("mxli:padron-tab-shown", function () {
    loadStatus();
  });

  // Si la pestaña ya está visible al cargar (p.ej. hash / deep-link futuro)
  var panel = document.getElementById("tab-padron");
  if (panel && !panel.hidden && panel.classList.contains("is-active")) {
    loadStatus();
  }

  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var fileInput = document.getElementById("padron-file");
      var haveBackup = document.getElementById("padron-have-backup");
      var makeBackup = document.getElementById("padron-make-backup");

      if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        showMsg("Seleccione un archivo CSV o XLSX.", false);
        return;
      }
      if (!(haveBackup && haveBackup.checked) && !(makeBackup && makeBackup.checked)) {
        showMsg(
          "Marque «Ya tengo respaldo» o «Crear respaldo en BD» antes de continuar.",
          false
        );
        return;
      }

      var ok = window.confirm(
        "Esta operación REEMPLAZA todo el padrón2026.\n\n" +
          "¿Desea continuar con la carga?"
      );
      if (!ok) return;

      var fd = new FormData();
      fd.append("file", fileInput.files[0]);
      if (haveBackup && haveBackup.checked) fd.append("have_backup", "1");
      if (makeBackup && makeBackup.checked) fd.append("make_backup", "1");

      showMsg("", true);
      if (progress) progress.hidden = false;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Procesando…";
      }

      fetch(API, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
        cache: "no-store",
      })
        .then(parseJsonResponse)
        .then(function (data) {
          renderCompareFromResult(data);
          var summary =
            (data.message || "Padrón actualizado.") +
            " Nuevas: " +
            fmtNum(data.claves_nuevas) +
            ", cambiadas: " +
            fmtNum(data.claves_cambiadas) +
            ", eliminadas: " +
            fmtNum(data.claves_eliminadas) +
            ".";
          if (data.meta_error) {
            summary += " (Aviso meta: " + data.meta_error + ")";
          }
          showMsg(summary, true);
          form.reset();
          // Refrescar panel desde el servidor (fecha, filas, archivo)
          return loadStatus().then(function () {
            // Conservar el mensaje verde de éxito tras el refresh
            showMsg(summary, true);
            return data;
          });
        })
        .catch(function (err) {
          showMsg(err.message || "Error", false);
        })
        .finally(function () {
          if (progress) progress.hidden = true;
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Subir y reemplazar padrón";
          }
        });
    });
  }
})();
