(function () {
  "use strict";

  var API = "../api/inspections.php";
  var tbody = document.getElementById("inspect-grid");
  var listMsg = document.getElementById("inspect-list-msg");
  var filterInput = document.getElementById("filter-clave");
  var filterInspector = document.getElementById("filter-inspector");
  var dialog = document.getElementById("inspect-edit-dialog");
  var editForm = document.getElementById("inspect-edit-form");
  var itemsCache = [];
  var filteredCache = [];
  var filterTimer = null;
  var TYPE_LABELS = window.MXLI_INSPECT_TYPE_LABELS || {
    lic_construccion: "Lic. Construcción",
    imagen_urbana: "Imagen Urbana",
    uso_suelo: "Uso de Suelo",
    otro: "Otro",
  };

  function showMsg(el, text, ok) {
    if (!el) return;
    el.hidden = !text;
    el.textContent = text || "";
    el.classList.toggle("is-ok", !!ok);
    el.classList.toggle("is-error", !ok && !!text);
  }

  function escapeHtml(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function photoSrc(url) {
    var u = String(url || "");
    if (!u) return "";
    if (u.charAt(0) === "/" || /^https?:/i.test(u)) return u;
    return "../" + u;
  }

  function downloadSrc(url) {
    var src = photoSrc(url);
    if (!src) return "";
    return src + (src.indexOf("?") >= 0 ? "&" : "?") + "download=1";
  }

  function formatDate(v) {
    if (!v) return "—";
    try {
      return new Date(v).toLocaleString("es-MX");
    } catch (e) {
      return v;
    }
  }

  function formatGps(item) {
    if (item.lat == null || item.lng == null) return "—";
    return Number(item.lat).toFixed(5) + ", " + Number(item.lng).toFixed(5);
  }

  function selectedFilterTypes() {
    var keys = [];
    document.querySelectorAll('#filter-types input[type="checkbox"]:checked').forEach(function (inp) {
      if (inp.value) keys.push(inp.value);
    });
    return keys;
  }

  function selectedEditTypes() {
    var keys = [];
    document.querySelectorAll('#edit-inspect-types input[type="checkbox"][name="inspection_types"]:checked').forEach(function (inp) {
      if (inp.value) keys.push(inp.value);
    });
    return keys.join(",");
  }

  function setEditTypes(csv) {
    var selected = {};
    String(csv || "")
      .split(",")
      .forEach(function (k) {
        k = String(k || "").trim();
        if (k) selected[k] = true;
      });
    document.querySelectorAll('#edit-inspect-types input[type="checkbox"][name="inspection_types"]').forEach(function (inp) {
      inp.checked = !!selected[inp.value];
    });
  }

  function typesChipsHtml(item) {
    var types = item.types || [];
    if ((!types || !types.length) && item.inspection_types) {
      types = String(item.inspection_types).split(",").filter(Boolean);
    }
    if (!types.length) return "—";
    return types
      .map(function (k) {
        return (
          '<span class="inspect-type-chip">' +
          escapeHtml(TYPE_LABELS[k] || k) +
          "</span>"
        );
      })
      .join("");
  }

  function api(url, options) {
    return fetch(url, options).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || data.ok === false) {
          throw new Error((data && data.error) || "Error de servidor");
        }
        return data;
      });
    });
  }

  function applyClientFilters(items) {
    var out = items || [];
    var inspector = filterInspector ? String(filterInspector.value || "") : "";
    var typeKeys = selectedFilterTypes();
    if (inspector) {
      out = out.filter(function (it) {
        return String(it.created_by_name || "") === inspector;
      });
    }
    if (typeKeys.length) {
      out = out.filter(function (it) {
        var csv = "," + String(it.inspection_types || "") + ",";
        return typeKeys.some(function (k) {
          return csv.indexOf("," + k + ",") >= 0;
        });
      });
    }
    return out;
  }

  function refreshInspectorOptions(items) {
    if (!filterInspector) return;
    var current = filterInspector.value;
    var names = {};
    (items || []).forEach(function (it) {
      var n = String(it.created_by_name || "").trim();
      if (n) names[n] = true;
    });
    var sorted = Object.keys(names).sort();
    filterInspector.innerHTML = '<option value="">Todos los inspectores</option>';
    sorted.forEach(function (n) {
      var opt = document.createElement("option");
      opt.value = n;
      opt.textContent = n;
      filterInspector.appendChild(opt);
    });
    if (current && names[current]) filterInspector.value = current;
  }

  function renderItems(items) {
    filteredCache = items || [];
    tbody.innerHTML = "";

    if (!filteredCache.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="inspect-empty">No hay registros de inspección.</td></tr>';
      return;
    }

    filteredCache.forEach(function (item, index) {
      var tr = document.createElement("tr");
      tr.innerHTML =
        '<td class="inspect-table__num">' +
        (index + 1) +
        "</td>" +
        '<td><img class="inspect-table__thumb" src="' +
        escapeHtml(photoSrc(item.photo_url)) +
        '" alt="Foto ' +
        escapeHtml(item.clavecatas) +
        '" data-zoom="' +
        item.id +
        '" title="Ver foto ampliada"></td>' +
        '<td class="inspect-table__clave">' +
        escapeHtml(String(item.clavecatas || "").toUpperCase()) +
        "</td>" +
        '<td class="inspect-table__types">' +
        typesChipsHtml(item) +
        "</td>" +
        "<td>" +
        escapeHtml(formatDate(item.captured_at)) +
        "</td>" +
        "<td>" +
        escapeHtml(String(item.created_by_name || "—").toUpperCase()) +
        "</td>" +
        '<td class="inspect-table__notes" title="' +
        escapeHtml(item.notes || "") +
        '">' +
        escapeHtml(String(item.notes || "—").toUpperCase()) +
        "</td>" +
        '<td class="inspect-table__gps">' +
        escapeHtml(formatGps(item)) +
        "</td>" +
        '<td><div class="inspect-table__actions">' +
        '<a class="inspect-download" href="' +
        escapeHtml(downloadSrc(item.photo_url)) +
        '" download="inspeccion-' +
        escapeHtml(item.clavecatas) +
        '.jpg">Descargar</a>' +
        '<button type="button" data-edit="' +
        item.id +
        '">Editar</button>' +
        '<button type="button" data-delete="' +
        item.id +
        '">Eliminar</button>' +
        "</div></td>";
      tbody.appendChild(tr);
    });
  }

  function loadItems() {
    showMsg(listMsg, "", true);
    var clave = String(filterInput.value || "").trim().replace(/\s+/g, "").toUpperCase();
    var typeKeys = selectedFilterTypes();
    var url = API + "?limit=2000";
    if (clave) url += "&clave=" + encodeURIComponent(clave);
    if (typeKeys.length === 1) url += "&tipo=" + encodeURIComponent(typeKeys[0]);
    return api(url)
      .then(function (data) {
        itemsCache = data.items || [];
        refreshInspectorOptions(itemsCache);
        renderItems(applyClientFilters(itemsCache));
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  }

  function csvEscape(v) {
    var s = String(v == null ? "" : v);
    if (/[",\n\r]/.test(s)) {
      return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  function exportCsv() {
    if (!filteredCache.length) {
      showMsg(listMsg, "No hay registros para exportar.", false);
      return;
    }

    var rows = [
      ["#", "Clave", "Tipos", "Fecha", "Inspector", "Notas", "Latitud", "Longitud", "URL foto"].join(","),
    ];

    filteredCache.forEach(function (item, index) {
      rows.push(
        [
          index + 1,
          item.clavecatas,
          item.types_label || item.inspection_types || "",
          formatDate(item.captured_at),
          item.created_by_name || "",
          item.notes || "",
          item.lat != null ? item.lat : "",
          item.lng != null ? item.lng : "",
          photoSrc(item.photo_url),
        ]
          .map(csvEscape)
          .join(",")
      );
    });

    var blob = new Blob(["\uFEFF" + rows.join("\r\n")], {
      type: "text/csv;charset=utf-8;",
    });
    var a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "inspecciones_" + new Date().toISOString().slice(0, 10) + ".csv";
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () {
      URL.revokeObjectURL(a.href);
    }, 1000);
  }

  tbody.addEventListener("click", function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.dataset.zoom) {
      var zoomId = Number(target.dataset.zoom);
      var zoomItem = filteredCache.find(function (x) {
        return Number(x.id) === zoomId;
      });
      if (!zoomItem) return;
      openLightbox(photoSrc(zoomItem.photo_url), zoomItem.clavecatas);
      return;
    }

    if (target.dataset.edit) {
      var id = Number(target.dataset.edit);
      var item = filteredCache.find(function (x) {
        return Number(x.id) === id;
      });
      if (!item) return;
      document.getElementById("edit-inspect-id").value = String(item.id);
      document.getElementById("edit-inspect-clave").value = item.clavecatas;
      document.getElementById("edit-inspect-notes").value = item.notes || "";
      document.getElementById("edit-inspect-preview").src = photoSrc(item.photo_url);
      setEditTypes(item.inspection_types || "");
      dialog.showModal();
      return;
    }

    if (target.dataset.delete) {
      var delId = Number(target.dataset.delete);
      if (!window.confirm("¿Eliminar este registro y su fotografía?")) return;
      api(API, {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: delId }),
      })
        .then(loadItems)
        .catch(function (err) {
          showMsg(listMsg, err.message, false);
        });
    }
  });

  editForm.addEventListener("submit", function (event) {
    var submitter = event.submitter;
    if (submitter && submitter.value === "cancel") return;
    event.preventDefault();

    var typesCsv = selectedEditTypes();
    if (!typesCsv) {
      showMsg(listMsg, "Seleccione al menos un tipo de inspección.", false);
      return;
    }

    api(API, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: Number(document.getElementById("edit-inspect-id").value),
        clavecatas: document.getElementById("edit-inspect-clave").value.trim(),
        notes: String(document.getElementById("edit-inspect-notes").value || "")
          .trim()
          .toUpperCase(),
        inspection_types: typesCsv,
      }),
    })
      .then(function () {
        dialog.close();
        return loadItems();
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  });

  function openLightbox(src, clave) {
    var box = document.getElementById("inspect-lightbox");
    var img = document.getElementById("inspect-lightbox-img");
    var cap = document.getElementById("inspect-lightbox-caption");
    if (!box || !img) return;
    img.src = src;
    img.alt = "Foto " + (clave || "");
    if (cap) cap.textContent = String(clave || "").toUpperCase();
    if (typeof box.showModal === "function") {
      box.showModal();
    } else {
      box.setAttribute("open", "");
    }
  }

  function closeLightbox() {
    var box = document.getElementById("inspect-lightbox");
    if (!box) return;
    if (typeof box.close === "function") {
      box.close();
    } else {
      box.removeAttribute("open");
    }
  }

  var lightbox = document.getElementById("inspect-lightbox");
  var lightboxClose = document.getElementById("inspect-lightbox-close");
  if (lightboxClose) {
    lightboxClose.addEventListener("click", closeLightbox);
  }
  if (lightbox) {
    lightbox.addEventListener("click", function (event) {
      if (event.target === lightbox) closeLightbox();
    });
  }

  var editPreview = document.getElementById("edit-inspect-preview");
  if (editPreview) {
    editPreview.addEventListener("click", function () {
      var clave = document.getElementById("edit-inspect-clave");
      openLightbox(editPreview.src, clave ? clave.value : "");
    });
  }

  document.querySelectorAll('#filter-types input[type="checkbox"]').forEach(function (inp) {
    inp.addEventListener("change", loadItems);
  });

  document.getElementById("btn-refresh-inspect").addEventListener("click", loadItems);
  document.getElementById("btn-export-csv").addEventListener("click", exportCsv);

  filterInput.addEventListener("input", function () {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(loadItems, 300);
  });
  if (filterInspector) {
    filterInspector.addEventListener("change", function () {
      renderItems(applyClientFilters(itemsCache));
    });
  }

  loadItems();
})();