(function (window) {
  "use strict";

  var cache = {};
  var inflight = {};

  function canConsultarAdeudo() {
    if (typeof window.mxliCanLayer === "function" && window.mxliCanLayer("predios_adeudo")) {
      return true;
    }
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.search") || window.mxliCan("map.padron") || window.mxliCan("users.manage"))
    );
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function normalizeClave(clave) {
    return String(clave || "")
      .trim()
      .replace(/\s+/g, "")
      .toUpperCase();
  }

  function apiBase() {
    var base = "";
    try {
      var scripts = document.getElementsByTagName("script");
      for (var i = scripts.length - 1; i >= 0; i--) {
        var src = scripts[i].src || "";
        var m = src.match(/^(.*\/)?js\/predialAdeudo\.js/i);
        if (m) {
          base = src.replace(/js\/predialAdeudo\.js.*$/i, "");
          break;
        }
      }
    } catch (e) {}
    return base || "";
  }

  function formatMoney(n) {
    var num = Number(n);
    if (!isFinite(num)) num = 0;
    return num.toLocaleString("es-MX", {
      style: "currency",
      currency: "MXN",
    });
  }

  function estatusDe(data) {
    if (!data) return "pendiente";
    var est = String(data.estatus_consulta || "");
    if (est === "con_adeudo" || data.tiene_adeudo === true) {
      return "con_adeudo";
    }
    if (est === "adeudo_aseo" || data.tiene_adeudo_aseo === true) {
      return "adeudo_aseo";
    }
    if (Number(data.total_a_pagar || 0) > 0) {
      return "con_adeudo";
    }
    if (est === "sin_adeudo") return "sin_adeudo";
    if (est === "no_encontrado") return "no_encontrado";
    if (est === "error") return "error";
    return "pendiente";
  }

  function emitAdeudo(data) {
    try {
      window.dispatchEvent(new CustomEvent("mxli-predial-adeudo", { detail: data }));
    } catch (e) {}
  }

  function consultarPredialAdeudo(clave, opts) {
    var key = normalizeClave(clave);
    var force = !!(opts && opts.force);
    if (!key || key.length < 6) {
      return Promise.resolve(null);
    }
    if (!force && cache[key]) {
      return Promise.resolve(cache[key]);
    }
    if (force) {
      delete cache[key];
    }
    if (!force && inflight[key]) {
      return inflight[key];
    }
    if (!canConsultarAdeudo()) {
      return Promise.resolve(null);
    }

    inflight[key] = fetch(apiBase() + "api/predial-adeudo.php?clave=" + encodeURIComponent(key), {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = {};
          try {
            data = text ? JSON.parse(text) : {};
          } catch (e) {
            data = { error: "El servidor de predial no respondió JSON." };
          }
          return { res: res, data: data };
        });
      })
      .then(function (payload) {
        var adeudo =
          payload.data && payload.data.adeudo
            ? payload.data.adeudo
            : {
                clave_catastral: key,
                tiene_adeudo: false,
                estatus_consulta: "error",
                fuente: "portal_mexicali",
                mensaje: (payload.data && (payload.data.error || payload.data.detail)) || "No disponible",
              };
        if (
          adeudo.estatus_consulta === "con_adeudo" ||
          adeudo.estatus_consulta === "sin_adeudo" ||
          adeudo.estatus_consulta === "adeudo_aseo"
        ) {
          cache[key] = adeudo;
        }
        emitAdeudo(adeudo);
        return adeudo;
      })
      .catch(function () {
        return {
          clave_catastral: key,
          tiene_adeudo: false,
          estatus_consulta: "error",
          fuente: "portal_mexicali",
          mensaje: "No se pudo consultar el portal predial",
        };
      })
      .then(function (adeudo) {
        delete inflight[key];
        return adeudo;
      });

    return inflight[key];
  }

  function badgeHtml(data, loading) {
    if (loading) {
      return '<span class="mxli-predial-badge mxli-predial-badge--loading">Consultando adeudo…</span>';
    }
    var est = estatusDe(data);
    if (est === "con_adeudo") {
      return (
        '<span class="mxli-predial-badge mxli-predial-badge--debt">Con adeudo: ' +
        escapeHtml(formatMoney(data && data.total_a_pagar)) +
        "</span>"
      );
    }
    if (est === "adeudo_aseo") {
      return (
        '<span class="mxli-predial-badge mxli-predial-badge--aseo">Adeudo aseo público: ' +
        escapeHtml(formatMoney(data && data.total_aseo)) +
        "</span>"
      );
    }
    if (est === "sin_adeudo") {
      return '<span class="mxli-predial-badge mxli-predial-badge--ok">Sin adeudo</span>';
    }
    if (est === "no_encontrado") {
      return '<span class="mxli-predial-badge mxli-predial-badge--unknown">No encontrado en portal</span>';
    }
    if (est === "error") {
      var why = data && data.mensaje ? String(data.mensaje) : "";
      return (
        '<span class="mxli-predial-badge mxli-predial-badge--unknown" title="' +
        escapeHtml(why) +
        '">Adeudo no disponible</span>'
      );
    }
    return '<span class="mxli-predial-badge mxli-predial-badge--loading">Consultando adeudo…</span>';
  }

  function setBadgeNode(el, data, loading) {
    if (!el || !el.querySelector) return;
    var node = el.querySelector("[data-field='estado_predial'] .mxli-padron-value") ||
      el.querySelector(".mxli-predial-badge-host");
    if (!node) return;
    node.innerHTML = badgeHtml(data, !!loading);
  }

  function applyToPadronBlock(el, data) {
    if (!el || !el.classList) return;
    el.classList.remove(
      "mxli-padron-block--ok",
      "mxli-padron-block--debt",
      "mxli-padron-block--aseo",
      "mxli-padron-block--unknown"
    );
    var claveRow = el.querySelector('[data-field="clave"]');
    if (claveRow) {
      claveRow.classList.remove("mxli-padron-clave-ok", "mxli-padron-clave-debt", "mxli-padron-clave-aseo");
    }
    var claveLine = el.querySelector(".mxli-predial-clave-line");
    if (claveLine) {
      claveLine.classList.remove("mxli-predial-clave-ok", "mxli-predial-clave-debt", "mxli-predial-clave-aseo");
    }
    var est = estatusDe(data);
    if (est === "con_adeudo") {
      el.classList.add("mxli-padron-block--debt");
      if (claveRow) claveRow.classList.add("mxli-padron-clave-debt");
      if (claveLine) claveLine.classList.add("mxli-predial-clave-debt");
    } else if (est === "adeudo_aseo") {
      el.classList.add("mxli-padron-block--aseo");
      if (claveRow) claveRow.classList.add("mxli-padron-clave-aseo");
      if (claveLine) claveLine.classList.add("mxli-predial-clave-aseo");
    } else if (est === "sin_adeudo") {
      el.classList.add("mxli-padron-block--ok");
      if (claveRow) claveRow.classList.add("mxli-padron-clave-ok");
      if (claveLine) claveLine.classList.add("mxli-predial-clave-ok");
    } else {
      el.classList.add("mxli-padron-block--unknown");
    }
    setBadgeNode(el, data, false);
    try {
      var popup = window.map2d && window.map2d._popup;
      if (popup && typeof popup._updateLayout === "function") {
        popup._updateLayout();
        if (typeof popup._updatePosition === "function") popup._updatePosition();
        if (typeof popup._adjustPan === "function") popup._adjustPan();
      }
    } catch (e) {}
  }

  function applyToSearchItem(btn, data) {
    if (!btn || !btn.classList) return;
    btn.classList.remove(
      "buscador-predio-results__item--ok",
      "buscador-predio-results__item--debt",
      "buscador-predio-results__item--aseo",
      "buscador-predio-results__item--unknown"
    );
    var main = btn.querySelector(".buscador-predio-results__item-main") || btn;
    main.classList.remove(
      "buscador-predio-results__item-main--ok",
      "buscador-predio-results__item-main--debt",
      "buscador-predio-results__item-main--aseo"
    );
    var chip = btn.querySelector(".buscador-predio-results__fiscal");
    if (!chip) {
      chip = document.createElement("span");
      chip.className = "buscador-predio-results__fiscal";
      btn.appendChild(chip);
    }
    var est = estatusDe(data);
    if (est === "con_adeudo") {
      btn.classList.add("buscador-predio-results__item--debt");
      main.classList.add("buscador-predio-results__item-main--debt");
      chip.textContent =
        "CON ADEUDO" +
        (data && data.total_a_pagar > 0 ? " · " + formatMoney(data.total_a_pagar) : "");
    } else if (est === "adeudo_aseo") {
      btn.classList.add("buscador-predio-results__item--aseo");
      main.classList.add("buscador-predio-results__item-main--aseo");
      chip.textContent =
        "ASEO PÚBLICO" +
        (data && data.total_aseo > 0 ? " · " + formatMoney(data.total_aseo) : "");
    } else if (est === "sin_adeudo") {
      btn.classList.add("buscador-predio-results__item--ok");
      main.classList.add("buscador-predio-results__item-main--ok");
      chip.textContent = "SIN ADEUDO";
    } else {
      btn.classList.add("buscador-predio-results__item--unknown");
      chip.textContent = est === "pendiente" ? "CONSULTANDO…" : "SIN DATO PREDIAL";
    }
  }

  function fillPredialAdeudo(root, clave) {
    if (!canConsultarAdeudo()) return;
    var key = normalizeClave(clave);
    var el = null;
    if (root && root.getAttribute && (root.getAttribute("data-mxli-padron") || root.getAttribute("data-mxli-predial"))) {
      el = root;
    } else if (root && root.querySelector) {
      el = root.querySelector("[data-mxli-padron], [data-mxli-predial], .mxli-predial-badge-host");
      if (!el && root.querySelector) {
        el = root;
      }
    }
    if (!el) {
      el = document.querySelector(".leaflet-popup-content [data-mxli-padron]");
    }
    if (!el || !key) return;

    setBadgeNode(el, null, true);
    consultarPredialAdeudo(key).then(function (data) {
      applyToPadronBlock(el, data);
      if (el !== root && root && root.classList) {
        applyToPadronBlock(root, data);
      }
    });
  }

  window.mxliPredialEstatus = estatusDe;
  window.mxliPredialBadgeHtml = badgeHtml;
  window.mxliConsultarPredialAdeudo = consultarPredialAdeudo;
  window.mxliAplicarPredialAdeudo = applyToPadronBlock;
  window.mxliPintarResultadoPredial = applyToSearchItem;
  window.mxliFillPredialAdeudo = fillPredialAdeudo;
})(window);
