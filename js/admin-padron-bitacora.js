(function () {
  "use strict";

  var API = "../api/admin/padron-bitacora.php";
  var tbody = document.querySelector("#bitacora-table tbody");
  var form = document.getElementById("bitacora-filters");
  var msg = document.getElementById("bitacora-msg");
  var countEl = document.getElementById("bitacora-count");
  var pager = document.getElementById("bitacora-pager");
  var pageLabel = document.getElementById("bitacora-page-label");
  var btnPrev = document.getElementById("bitacora-prev");
  var btnNext = document.getElementById("bitacora-next");
  var btnRefresh = document.getElementById("bitacora-refresh");
  var btnExport = document.getElementById("bitacora-export");
  var page = 1;
  var pages = 1;
  var loadedOnce = false;

  function showMsg(text, ok) {
    if (!msg) return;
    msg.hidden = !text;
    msg.textContent = text || "";
    msg.classList.toggle("is-ok", !!ok);
    msg.classList.toggle("is-error", !ok && !!text);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function queryParams(extra) {
    var params = new URLSearchParams();
    var usuario = document.getElementById("bitacora-usuario");
    var clave = document.getElementById("bitacora-clave");
    var tipo = document.getElementById("bitacora-tipo");
    var desde = document.getElementById("bitacora-desde");
    var hasta = document.getElementById("bitacora-hasta");
    if (usuario && usuario.value.trim()) params.set("usuario", usuario.value.trim());
    if (clave && clave.value.trim()) params.set("clave", clave.value.trim());
    if (tipo && tipo.value) params.set("tipo", tipo.value);
    if (desde && desde.value) params.set("desde", desde.value);
    if (hasta && hasta.value) params.set("hasta", hasta.value);
    extra = extra || {};
    Object.keys(extra).forEach(function (k) {
      if (extra[k] != null && extra[k] !== "") params.set(k, String(extra[k]));
    });
    return params.toString();
  }

  function tipoLabel(tipo) {
    return tipo === "nombre" ? "Por nombre" : "Por clave";
  }

  function resultadoHtml(row) {
    if (row.tipo === "nombre") {
      var n = row.result_count != null ? Number(row.result_count) : 0;
      return n
        ? '<span class="badge badge-on">' + escapeHtml(String(n)) + " coincidencia(s)</span>"
        : '<span class="badge badge-off">Sin coincidencias</span>';
    }
    if (row.found === true) {
      return '<span class="badge badge-on">Encontrada</span>';
    }
    if (row.found === false) {
      return '<span class="badge badge-off">No encontrada</span>';
    }
    return "—";
  }

  function consultaHtml(row) {
    if (row.tipo === "nombre") {
      var q = String(row.query_text || "").trim() || "—";
      return (
        '<span class="badge badge-consulta">' +
        escapeHtml(tipoLabel(row.tipo)) +
        "</span> " +
        escapeHtml(q)
      );
    }
    var clave = String(row.clave || "").trim() || "—";
    return "<strong>" + escapeHtml(clave) + "</strong>";
  }

  function renderRows(rows) {
    if (!tbody) return;
    if (!rows || !rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="admin-empty">No hay consultas con esos filtros.</td></tr>';
      return;
    }
    tbody.innerHTML = rows
      .map(function (row) {
        return (
          "<tr>" +
          "<td>" +
          escapeHtml(row.consulted_at || "—") +
          "</td>" +
          "<td>" +
          escapeHtml(row.username || "—") +
          "</td>" +
          "<td>" +
          escapeHtml(row.full_name || "—") +
          "</td>" +
          "<td>" +
          consultaHtml(row) +
          "</td>" +
          "<td>" +
          resultadoHtml(row) +
          "</td>" +
          "<td>" +
          escapeHtml(row.ip || "—") +
          "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function renderPager() {
    if (!pager || !pageLabel || !btnPrev || !btnNext) return;
    pager.hidden = pages <= 1;
    pageLabel.textContent = "Página " + page + " de " + pages;
    btnPrev.disabled = page <= 1;
    btnNext.disabled = page >= pages;
  }

  function load(resetPage) {
    if (resetPage) page = 1;
    showMsg("", true);
    var qs = queryParams({ page: page, limit: 50 });
    return fetch(API + "?" + qs, { credentials: "same-origin", cache: "no-store" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data || data.ok === false) {
            var err = (data && data.error) || "Error al leer la bitácora";
            if (data && data.detail) err += " (" + data.detail + ")";
            throw new Error(err);
          }
          return data;
        });
      })
      .then(function (data) {
        page = Number(data.page || 1);
        pages = Number(data.pages || 1);
        renderRows(data.rows || []);
        renderPager();
        if (countEl) {
          var total = Number(data.total || 0);
          countEl.textContent =
            total === 1 ? "1 consulta" : total.toLocaleString("es-MX") + " consultas";
        }
        loadedOnce = true;
      })
      .catch(function (err) {
        renderRows([]);
        if (countEl) countEl.textContent = "";
        showMsg(err.message || "Error", false);
      });
  }

  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      load(true);
    });
  }

  if (btnPrev) {
    btnPrev.addEventListener("click", function () {
      if (page > 1) {
        page -= 1;
        load(false);
      }
    });
  }

  if (btnNext) {
    btnNext.addEventListener("click", function () {
      if (page < pages) {
        page += 1;
        load(false);
      }
    });
  }

  if (btnRefresh) {
    btnRefresh.addEventListener("click", function () {
      load(false);
    });
  }

  if (btnExport) {
    btnExport.addEventListener("click", function () {
      window.location.href = API + "?" + queryParams({ export: "csv" });
    });
  }

  document.addEventListener("mxli:bitacora-tab-shown", function () {
    load(!loadedOnce);
  });
})();
