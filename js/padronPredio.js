(function (window) {
  "use strict";

  function canViewPadron() {
    if (window.MXLI_USER && window.MXLI_USER.can_view_padron) return true;
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.padron") || window.mxliCan("users.manage"))
    );
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function toUpperDisplay(value) {
    return String(value == null ? "" : value).trim().toUpperCase();
  }

  function parseNumber(value) {
    if (value == null || value === "") return NaN;
    if (typeof value === "number") return value;
    var s = String(value).trim().replace(/\s/g, "");
    if (s.indexOf(",") >= 0 && s.indexOf(".") >= 0) {
      s = s.replace(/\./g, "").replace(",", ".");
    } else if (s.indexOf(",") >= 0) {
      s = s.replace(",", ".");
    }
    return Number(s);
  }

  function formatAreaM2(value) {
    var n = parseNumber(value);
    if (!isFinite(n)) return "";
    return (
      n.toLocaleString("es-MX", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }) + " M²"
    );
  }

  /** Fila de tabla Excel: etiqueta (sin negrita) | valor (negrita); todo mayúsculas. */
  function tableRowHtml(field, label, valueText) {
    if (field === "estado_predial") {
      var badge =
        typeof window.mxliPredialBadgeHtml === "function"
          ? window.mxliPredialBadgeHtml(null, true)
          : escapeHtml("Consultando…");
      return (
        '<tr data-field="estado_predial">' +
        '<td class="mxli-padron-label">ESTADO PREDIAL</td>' +
        '<td class="mxli-padron-value">' +
        badge +
        "</td></tr>"
      );
    }
    var val = toUpperDisplay(valueText);
    if (!val) val = "—";
    var rowClass = field === "clave" ? " mxli-padron-clave-row" : "";
    var attrs =
      (field ? ' data-field="' + escapeHtml(field) + '"' : "") +
      (rowClass ? ' class="' + rowClass.trim() + '"' : "");
    return (
      "<tr" +
      attrs +
      ">" +
      '<td class="mxli-padron-label">' +
      escapeHtml(toUpperDisplay(label)) +
      "</td>" +
      '<td class="mxli-padron-value"><b>' +
      escapeHtml(val) +
      "</b></td>" +
      "</tr>"
    );
  }

  function setField(el, field, label, valueText) {
    var node = el.querySelector('[data-field="' + field + '"]');
    if (!node) return;
    var val = toUpperDisplay(valueText);
    if (!val) val = "—";
    var labelCell = node.querySelector(".mxli-padron-label");
    var valueCell = node.querySelector(".mxli-padron-value");
    if (labelCell) {
      labelCell.textContent = toUpperDisplay(label);
    }
    if (valueCell) {
      valueCell.innerHTML = "<b>" + escapeHtml(val) + "</b>";
      return;
    }
    // Compatibilidad si el DOM aún tiene el formato viejo.
    node.innerHTML =
      escapeHtml(toUpperDisplay(label)) +
      ": <b>" +
      escapeHtml(val) +
      "</b>";
  }

  /** Lee valor con aliases (API puede mandar propietario o nombredespliegue, etc.). */
  function pickPadronValue(padron, keys) {
    if (!padron) return "";
    for (var i = 0; i < keys.length; i++) {
      var v = padron[keys[i]];
      if (v != null && String(v).trim() !== "") return String(v).trim();
    }
    var lower = {};
    Object.keys(padron).forEach(function (k) {
      lower[String(k).toLowerCase()] = padron[k];
    });
    for (var j = 0; j < keys.length; j++) {
      var v2 = lower[String(keys[j]).toLowerCase()];
      if (v2 != null && String(v2).trim() !== "") return String(v2).trim();
    }
    return "";
  }

  function applyPadronFields(el, padron) {
    if (!padron) {
      setField(el, "propietario", "Registrado a", "Sin datos en padrón");
      setField(el, "colonia", "Colonia", "—");
      setField(el, "calle", "Calle", "—");
      setField(el, "numof", "Número oficial", "—");
      setField(el, "sup_doc", "Sup. Documental", "—");
      setField(el, "uso_predial", "Uso Predial", "—");
      return;
    }
    var propietario = pickPadronValue(padron, [
      "propietario",
      "nombre_completo",
      "nombredespliegue",
      "NOMBREDESPLIEGUE",
      "razon_social",
      "nombre",
    ]);
    var colonia = pickPadronValue(padron, ["colonia", "COLONIA"]);
    var calle = pickPadronValue(padron, ["calle", "CALLE"]);
    var numof = pickPadronValue(padron, [
      "numero_oficial",
      "numof",
      "numero",
      "NUMOF",
    ]);
    var letra = pickPadronValue(padron, ["letra", "LETRA"]);
    if (letra && numof && String(numof).indexOf(letra) < 0) {
      numof = String(numof).trim() + " " + String(letra).trim();
    } else if (letra && !numof) {
      numof = letra;
    }
    var supRaw = pickPadronValue(padron, [
      "superficie_documental",
      "superficiedocumental",
      "sup_registrada",
      "sup_regitrada",
      "SUPERFICIEDOCUMENTAL",
      "sup",
    ]);
    var usoPredial = pickPadronValue(padron, [
      "uso_predial",
      "usopredial",
      "descripcion",
      "DESCRIPCION",
    ]);
    setField(el, "propietario", "Registrado a", propietario || "—");
    setField(el, "colonia", "Colonia", colonia || "—");
    setField(el, "calle", "Calle", calle || "—");
    setField(el, "numof", "Número oficial", numof || "—");
    var supDoc = formatAreaM2(supRaw);
    setField(
      el,
      "sup_doc",
      "Sup. Documental",
      supDoc || (supRaw ? String(supRaw) + " M²" : "—")
    );
    setField(el, "uso_predial", "Uso Predial", usoPredial || "—");
  }

  window.mxliCanViewPadron = canViewPadron;

  function expedienteLinkHtml(claveU) {
    if (typeof window.mxliCan !== "function" || !window.mxliCan("map.expediente")) {
      return "";
    }
    return (
      '<a href="#" class="mxli-expediente-link" data-expediente-clave="' +
      escapeHtml(claveU) +
      '" role="button">' +
      '<span class="mxli-expediente-link__icon" aria-hidden="true">' +
      '<svg viewBox="0 0 48 40" width="34" height="28" xmlns="http://www.w3.org/2000/svg">' +
      '<path d="M2 10h14l3 4h27v20a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V10z" fill="#e8b84a"/>' +
      '<path d="M2 14h44v20a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V14z" fill="#f0c95a"/>' +
      '<rect x="14" y="8" width="16" height="20" rx="1.5" fill="#f7fbff" stroke="#7eb6e8" stroke-width="1.2"/>' +
      '<path d="M17 13h10M17 17h10M17 21h7" stroke="#7eb6e8" stroke-width="1.4" stroke-linecap="round"/>' +
      "</svg>" +
      "</span>" +
      '<span class="mxli-expediente-link__text">Ver Expediente</span>' +
      "</a>"
    );
  }

  window.mxliExpedienteLinkHtml = expedienteLinkHtml;

  /**
   * Cuerpo del popup con permiso map.padron.
   * Orden: clave, registrado a, colonia, calle, núm. oficial,
   *        sup. documental, sup. cartográfica, uso predial.
   */
  window.mxliPredioPadronBodyHtml = function (clave, superficieCartografica) {
    var claveU = toUpperDisplay(clave);
    var cart = formatAreaM2(superficieCartografica);
    if (!cart) {
      cart =
        superficieCartografica != null && String(superficieCartografica).trim() !== ""
          ? String(superficieCartografica) + " M²"
          : "—";
    }
    return (
      '<div class="mxli-padron-block" data-mxli-padron="' +
      escapeHtml(claveU) +
      '">' +
      '<table class="mxli-padron-table" role="presentation">' +
      "<tbody>" +
      tableRowHtml("clave", "Clave Catastral", claveU || "—") +
      tableRowHtml("estado_predial", "Estado predial", "Consultando…") +
      tableRowHtml("propietario", "Registrado a", "…") +
      tableRowHtml("colonia", "Colonia", "…") +
      tableRowHtml("calle", "Calle", "…") +
      tableRowHtml("numof", "Número oficial", "…") +
      tableRowHtml("sup_doc", "Sup. Documental", "…") +
      tableRowHtml(null, "Sup. Cartografica", cart) +
      tableRowHtml("uso_predial", "Uso Predial", "…") +
      "</tbody>" +
      "</table>" +
      expedienteLinkHtml(claveU) +
      "</div>"
    );
  };

  /** Compatibilidad: ya no se usa como bloque suelto bajo el popup simple. */
  window.mxliPadronPlaceholderHtml = function (clave) {
    if (!canViewPadron()) {
      return "";
    }
    return window.mxliPredioPadronBodyHtml(clave, "");
  };

  /**
   * Recalcula tamaño/pan del popup SIN reinyectar _content.
   * Si _content es string, popup.update() hace innerHTML = string original
   * y borra los campos ya rellenados (dejaba "…" para siempre).
   */
  function refreshOpenPopupSize() {
    try {
      var map = window.map2d;
      var popup = map && map._popup;
      if (!popup) return;
      // Si el contenido era HTML string, anclar al nodo vivo ya actualizado.
      if (
        typeof popup._content === "string" &&
        popup._contentNode &&
        popup._contentNode.firstChild
      ) {
        popup._content = popup._contentNode.firstChild;
      }
      if (typeof popup._updateLayout === "function") {
        popup._updateLayout();
        if (typeof popup._updatePosition === "function") {
          popup._updatePosition();
        }
        if (typeof popup._adjustPan === "function") {
          popup._adjustPan();
        }
        return;
      }
      if (typeof popup.update === "function") {
        popup.update();
      }
    } catch (e) {}
  }

  function showPadronError(el, message) {
    setField(el, "propietario", "Registrado a", message || "No disponible");
    setField(el, "colonia", "Colonia", "—");
    setField(el, "calle", "Calle", "—");
    setField(el, "numof", "Número oficial", "—");
    setField(el, "sup_doc", "Sup. Documental", "—");
    setField(el, "uso_predial", "Uso Predial", "—");
    refreshOpenPopupSize();
  }

  function apiPadronUrl(clave) {
    var base = "";
    try {
      var scripts = document.getElementsByTagName("script");
      for (var i = scripts.length - 1; i >= 0; i--) {
        var src = scripts[i].src || "";
        var m = src.match(/^(.*\/)?js\/padronPredio\.js/i);
        if (m) {
          base = src.replace(/js\/padronPredio\.js.*$/i, "");
          break;
        }
      }
    } catch (e) {}
    return (base || "") + "api/padron.php?clave=" + encodeURIComponent(clave);
  }

  window.mxliFillPadronBlock = function (root, clave) {
    if (!canViewPadron()) {
      var deniedRoot = null;
      if (root && root.querySelector) {
        deniedRoot = root.querySelector("[data-mxli-padron]") || root;
      }
      if (!deniedRoot) {
        deniedRoot = document.querySelector(".leaflet-popup-content [data-mxli-padron]");
      }
      if (deniedRoot && deniedRoot.querySelector) {
        showPadronError(deniedRoot, "Sin permiso para consultar el padrón");
      }
      return;
    }
    var el = null;
    if (root && root.getAttribute && root.getAttribute("data-mxli-padron")) {
      el = root;
    } else if (root && root.querySelector) {
      el = root.querySelector("[data-mxli-padron]");
    }
    if (!el) {
      el = document.querySelector(
        '[data-mxli-padron="' + String(clave || "").toUpperCase() + '"]'
      );
    }
    if (!el) {
      el = document.querySelector(".leaflet-popup-content [data-mxli-padron]");
    }
    if (!el) {
      // Leaflet a veces monta el contenido un tick después
      setTimeout(function () {
        var again =
          document.querySelector(".leaflet-popup-content [data-mxli-padron]") ||
          document.querySelector(
            '[data-mxli-padron="' + String(clave || "").toUpperCase() + '"]'
          );
        if (again) {
          window.mxliFillPadronBlock(again, clave);
        }
      }, 80);
      return;
    }
    var key = toUpperDisplay(clave || el.getAttribute("data-mxli-padron") || "");
    if (!key) {
      applyPadronFields(el, null);
      refreshOpenPopupSize();
      return;
    }

    // Evitar peticiones duplicadas al mismo bloque
    var reqId = String(Date.now()) + "-" + key;
    el.setAttribute("data-mxli-padron-req", reqId);

    if (typeof window.mxliFillPredialAdeudo === "function") {
      window.mxliFillPredialAdeudo(el, key);
    }

    var ctrl = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = null;
    if (ctrl) {
      timer = setTimeout(function () {
        try {
          ctrl.abort();
        } catch (e) {}
      }, 20000);
    }

    fetch(apiPadronUrl(key), {
      credentials: "same-origin",
      cache: "no-store",
      signal: ctrl ? ctrl.signal : undefined,
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = null;
          try {
            data = text ? JSON.parse(text) : null;
          } catch (parseErr) {
            data = {
              ok: false,
              error: "Respuesta inválida del padrón",
              detail: String(text || "").slice(0, 180),
            };
          }
          return { res: res, data: data };
        });
      })
      .then(function (payload) {
        if (timer) clearTimeout(timer);
        if (el.getAttribute("data-mxli-padron-req") !== reqId) return;
        // Asegurar que un update posterior no reinstale el HTML string vacío.
        refreshOpenPopupSize();
        var data = payload.data;
        if (payload.res.status === 401) {
          showPadronError(el, "Sesión expirada; vuelva a iniciar sesión");
          return;
        }
        if (payload.res.status === 403) {
          showPadronError(
            el,
            (data && (data.detail || data.error)) ||
              "Sin permiso para consultar el padrón"
          );
          return;
        }
        if (!payload.res.ok || !data || data.ok === false) {
          showPadronError(
            el,
            (data && (data.detail || data.error)) || "No disponible"
          );
          return;
        }
        if (!data.found || !data.padron) {
          applyPadronFields(el, null);
          // Mensaje más claro que el genérico de applyPadronFields
          setField(el, "propietario", "Registrado a", "Sin datos en padrón");
          refreshOpenPopupSize();
          return;
        }
        applyPadronFields(el, data.padron);
        refreshOpenPopupSize();
      })
      .catch(function (err) {
        if (timer) clearTimeout(timer);
        if (el.getAttribute("data-mxli-padron-req") !== reqId) return;
        var msg =
          err && err.name === "AbortError"
            ? "Tiempo agotado al consultar padrón"
            : "No disponible";
        showPadronError(el, msg);
      });
  };
})(window);
