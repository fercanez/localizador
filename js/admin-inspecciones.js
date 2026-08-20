(function () {
  "use strict";

  var API = "../api/inspections.php";
  var grid = document.getElementById("inspect-grid");
  var listMsg = document.getElementById("inspect-list-msg");
  var filterInput = document.getElementById("filter-clave");
  var dialog = document.getElementById("inspect-edit-dialog");
  var editForm = document.getElementById("inspect-edit-form");
  var itemsCache = [];
  var filterTimer = null;

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
      .replace(/>/g, "&gt;");
  }

  function formatDate(v) {
    if (!v) return "—";
    try {
      return new Date(v).toLocaleString("es-MX");
    } catch (e) {
      return v;
    }
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

  function renderItems(items) {
    itemsCache = items || [];
    grid.innerHTML = "";

    if (!itemsCache.length) {
      grid.innerHTML = '<p class="inspect-empty">No hay registros de inspección.</p>';
      return;
    }

    itemsCache.forEach(function (item) {
      var card = document.createElement("article");
      card.className = "inspect-card";
      card.innerHTML =
        '<img class="inspect-card__img" src="' +
        escapeHtml(item.photo_url) +
        '" alt="Foto ' +
        escapeHtml(item.clavecatas) +
        '">' +
        '<div class="inspect-card__body">' +
        '<div class="inspect-card__clave">' +
        escapeHtml(item.clavecatas) +
        "</div>" +
        '<div class="inspect-card__meta">' +
        escapeHtml(formatDate(item.captured_at)) +
        (item.created_by_name ? " · " + escapeHtml(item.created_by_name) : "") +
        "</div>" +
        '<div class="inspect-card__notes">' +
        escapeHtml(item.notes || "—") +
        "</div>" +
        '<div class="inspect-card__actions">' +
        '<button type="button" data-edit="' +
        item.id +
        '">Editar</button>' +
        '<button type="button" data-delete="' +
        item.id +
        '">Eliminar</button>' +
        "</div>" +
        "</div>";
      grid.appendChild(card);
    });
  }

  function loadItems() {
    showMsg(listMsg, "", true);
    var clave = String(filterInput.value || "").trim().replace(/\s+/g, "").toUpperCase();
    var url = API + (clave ? "?clave=" + encodeURIComponent(clave) + "&limit=120" : "?limit=120");
    return api(url)
      .then(function (data) {
        renderItems(data.items || []);
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  }

  grid.addEventListener("click", function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.dataset.edit) {
      var id = Number(target.dataset.edit);
      var item = itemsCache.find(function (x) {
        return Number(x.id) === id;
      });
      if (!item) return;
      document.getElementById("edit-inspect-id").value = String(item.id);
      document.getElementById("edit-inspect-clave").value = item.clavecatas;
      document.getElementById("edit-inspect-notes").value = item.notes || "";
      document.getElementById("edit-inspect-preview").src = item.photo_url;
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

    api(API, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: Number(document.getElementById("edit-inspect-id").value),
        clavecatas: document.getElementById("edit-inspect-clave").value.trim(),
        notes: document.getElementById("edit-inspect-notes").value.trim(),
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

  document.getElementById("btn-refresh-inspect").addEventListener("click", loadItems);

  filterInput.addEventListener("input", function () {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(loadItems, 300);
  });

  loadItems();
})();
