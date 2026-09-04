(function (window) {
  "use strict";

  function canViewPadron() {
    return typeof window.mxliCan === "function" && window.mxliCan("map.padron");
  }

  /** true si el usuario puede buscar/ver nombre de registro (padrón). */
  function canSearchByOwnerName() {
    if (canViewPadron()) return true;
    if (window.MXLI_USER && window.MXLI_USER.can_view_padron) return true;
    return typeof window.mxliCan === "function" && window.mxliCan("users.manage");
  }

  window.mxliCanViewPadron = canViewPadron;
  window.mxliCanSearchByOwnerName = canSearchByOwnerName;

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

  /** Fila: etiqueta corta Title Case | valor UPPERCASE negrita. */
  function tableRowHtml(field, label, valueText) {
    var val = toUpperDisplay(valueText);
    if (!val) val = "—";
    var attrs = field
      ? ' data-field="' + escapeHtml(field) + '"'
      : "";
    return (
      "<tr" +
      attrs +
      ">" +
      '<td class="mxli-padron-label">' +
      escapeHtml(label) +
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
      labelCell.textContent = label;
    }
    if (valueCell) {
      valueCell.innerHTML = "<b>" + escapeHtml(val) + "</b>";
      return;
    }
    // Compatibilidad si el DOM aún tiene el formato viejo.
    node.innerHTML =
      escapeHtml(label) +
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
      setField(el, "propietario", "Registrado", "Sin registro en padrón 2026");
      setField(el, "colonia", "Colonia", "—");
      setField(el, "calle", "Calle", "—");
      setField(el, "numof", "Número", "—");
      setField(el, "sup_doc", "Sup. doc.", "—");
      setField(el, "uso_predial", "Uso", "—");
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
    setField(el, "propietario", "Registrado", propietario || "—");
    setField(el, "colonia", "Colonia", colonia || "—");
    setField(el, "calle", "Calle", calle || "—");
    setField(el, "numof", "Número", numof || "—");
    var supDoc = formatAreaM2(supRaw);
    setField(
      el,
      "sup_doc",
      "Sup. doc.",
      supDoc || (supRaw ? String(supRaw) + " M²" : "—")
    );
    setField(el, "uso_predial", "Uso", usoPredial || "—");
  }

  window.mxliCanViewPadron = canViewPadron;
  window.mxliCanSearchByOwnerName = canSearchByOwnerName;

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
      tableRowHtml(null, "Clave", claveU || "—") +
      tableRowHtml("propietario", "Registrado", "…") +
      tableRowHtml("colonia", "Colonia", "…") +
      tableRowHtml("calle", "Calle", "…") +
      tableRowHtml("numof", "Número", "…") +
      tableRowHtml("sup_doc", "Sup. doc.", "…") +
      tableRowHtml(null, "Sup. cart.", cart) +
      tableRowHtml("uso_predial", "Uso", "…") +
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

  window.mxliFillPadronBlock = function (root, clave) {
    if (!canViewPadron()) {
      return;
    }
    var el =
      root && root.querySelector
        ? root.querySelector("[data-mxli-padron]")
        : document.querySelector('[data-mxli-padron="' + clave + '"]');
    if (!el) return;
    var key = toUpperDisplay(clave || el.getAttribute("data-mxli-padron") || "");
    if (!key) {
      applyPadronFields(el, null);
      return;
    }

    fetch("api/padron.php?clave=" + encodeURIComponent(key))
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data };
        });
      })
      .then(function (payload) {
        var data = payload.data;
        if (!payload.res.ok || !data || data.ok === false) {
          var err =
            (data && (data.detail || data.error)) || "No disponible";
          setField(el, "propietario", "Registrado", err);
          setField(el, "colonia", "Colonia", "—");
          setField(el, "calle", "Calle", "—");
          setField(el, "numof", "Número", "—");
          setField(el, "sup_doc", "Sup. doc.", "—");
          setField(el, "uso_predial", "Uso", "—");
          return;
        }
        applyPadronFields(el, data.found ? data.padron : null);
      })
      .catch(function () {
        setField(el, "propietario", "Registrado", "No disponible");
        setField(el, "colonia", "Colonia", "—");
        setField(el, "calle", "Calle", "—");
        setField(el, "numof", "Número", "—");
        setField(el, "sup_doc", "Sup. doc.", "—");
        setField(el, "uso_predial", "Uso", "—");
      });
  };
})(window);
