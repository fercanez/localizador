(function () {
  "use strict";

  var tbody = document.querySelector("#roles-table tbody");
  var rolesMsg = document.getElementById("roles-msg");
  var dialog = document.getElementById("role-dialog");
  var form = document.getElementById("role-form");
  var rolesCache = [];
  var ALL_LAYER_KEYS = Array.prototype.map.call(
    document.querySelectorAll(".role-layer"),
    function (el) {
      return el.value;
    }
  );

  function showMsg(el, text, ok) {
    if (!el) return;
    el.hidden = !text;
    el.textContent = text || "";
    el.classList.toggle("is-ok", !!ok);
    el.classList.toggle("is-error", !ok && !!text);
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

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;");
  }

  function formatPerms(list) {
    var toolLabels = {
      "map.view": "Ver",
      "map.search": "Buscar",
      "map.info": "Info",
      "map.print": "Imprimir",
      "map.locate": "GPS",
      "map.measure": "Medir",
      "map.upload": "Subir",
      "users.manage": "Usuarios",
    };
    return (list || [])
      .filter(function (p) {
        return p !== "map.view";
      })
      .map(function (p) {
        return toolLabels[p] || p;
      })
      .join(", ") || "Solo mapa";
  }

  function formatLayers(list) {
    if (!list || list.indexOf("*") !== -1) return "Todas";
    return list.length + " de " + ALL_LAYER_KEYS.length;
  }

  function renderRoles(roles) {
    rolesCache = roles || [];
    if (!tbody) return;
    tbody.innerHTML = "";
    rolesCache.forEach(function (role) {
      var tr = document.createElement("tr");
      var canDelete = !role.is_system;
      tr.innerHTML =
        "<td><strong>" +
        escapeHtml(role.name) +
        "</strong><br><code>" +
        escapeHtml(role.slug) +
        "</code>" +
        (role.is_system ? ' <span class="badge badge-admin">Sistema</span>' : "") +
        "</td>" +
        "<td>" +
        escapeHtml(role.description || "—") +
        "</td>" +
        "<td>" +
        escapeHtml(formatPerms(role.permissions)) +
        "</td>" +
        "<td>" +
        escapeHtml(formatLayers(role.allowed_layers)) +
        "</td>" +
        '<td class="admin-actions">' +
        '<button type="button" data-edit-role="' +
        escapeHtml(role.slug) +
        '">Editar</button>' +
        (canDelete
          ? '<button type="button" data-delete-role="' +
            escapeHtml(role.slug) +
            '">Eliminar</button>'
          : "") +
        "</td>";
      tbody.appendChild(tr);
    });
  }

  function setToolChecks(perms, isAdmin) {
    var set = {};
    (perms || []).forEach(function (p) {
      set[p] = true;
    });
    document.querySelectorAll(".role-tool").forEach(function (el) {
      var perm = el.value;
      if (perm === "map.view") {
        el.checked = true;
        el.disabled = true;
        return;
      }
      if (isAdmin && perm === "users.manage") {
        el.checked = true;
        el.disabled = true;
        return;
      }
      el.disabled = false;
      el.checked = !!set[perm];
    });
  }

  function setLayerChecks(layers, isAdmin) {
    var all = isAdmin || !layers || layers.indexOf("*") !== -1;
    var allBox = document.getElementById("role-layers-all");
    if (allBox) {
      allBox.checked = all;
      allBox.disabled = !!isAdmin;
    }
    var set = {};
    (layers || []).forEach(function (k) {
      set[k] = true;
    });
    document.querySelectorAll(".role-layer").forEach(function (el) {
      el.disabled = !!isAdmin || all;
      el.checked = all || !!set[el.value];
    });
  }

  function collectPermissions(isAdmin) {
    var list = ["map.view"];
    document.querySelectorAll(".role-tool").forEach(function (el) {
      if (el.value === "map.view") return;
      if (el.checked) list.push(el.value);
    });
    if (isAdmin && list.indexOf("users.manage") === -1) {
      list.push("users.manage");
    }
    return list;
  }

  function collectLayers(isAdmin) {
    if (isAdmin) return ["*"];
    var allBox = document.getElementById("role-layers-all");
    if (allBox && allBox.checked) return ["*"];
    var list = [];
    document.querySelectorAll(".role-layer:checked").forEach(function (el) {
      list.push(el.value);
    });
    return list.length ? list : ["*"];
  }

  function openCreate() {
    document.getElementById("role-mode").value = "create";
    document.getElementById("role-dialog-title").textContent = "Nuevo rol";
    document.getElementById("role-slug").value = "";
    document.getElementById("role-name").value = "";
    document.getElementById("role-description").value = "";
    document.getElementById("role-name").disabled = false;
    setToolChecks(
      ["map.view", "map.search", "map.info", "map.print", "map.locate", "map.measure"],
      false
    );
    setLayerChecks(["*"], false);
    dialog.showModal();
  }

  function openEdit(slug) {
    var role = rolesCache.find(function (r) {
      return r.slug === slug;
    });
    if (!role) return;
    var isAdmin = role.slug === "admin";
    document.getElementById("role-mode").value = "edit";
    document.getElementById("role-dialog-title").textContent = "Editar rol";
    document.getElementById("role-slug").value = role.slug;
    document.getElementById("role-name").value = role.name;
    document.getElementById("role-description").value = role.description || "";
    document.getElementById("role-name").disabled = isAdmin;
    setToolChecks(role.permissions, isAdmin);
    setLayerChecks(role.allowed_layers, isAdmin);
    dialog.showModal();
  }

  function loadRoles() {
    showMsg(rolesMsg, "", true);
    return api("../api/admin/roles.php")
      .then(function (data) {
        renderRoles(data.roles || []);
        if (typeof window.mxliReloadUserRoles === "function") {
          window.mxliReloadUserRoles();
        }
      })
      .catch(function (err) {
        showMsg(rolesMsg, err.message, false);
      });
  }

  var allBox = document.getElementById("role-layers-all");
  if (allBox) {
    allBox.addEventListener("change", function () {
      var on = allBox.checked;
      document.querySelectorAll(".role-layer").forEach(function (el) {
        el.checked = on;
        el.disabled = on;
      });
    });
  }

  document.querySelectorAll(".role-layer").forEach(function (el) {
    el.addEventListener("change", function () {
      if (!el.checked && allBox) allBox.checked = false;
      if (
        allBox &&
        ALL_LAYER_KEYS.every(function (k) {
          var box = document.querySelector('.role-layer[value="' + k + '"]');
          return box && box.checked;
        })
      ) {
        allBox.checked = true;
        document.querySelectorAll(".role-layer").forEach(function (layerEl) {
          layerEl.disabled = true;
        });
      }
    });
  });

  var btnNew = document.getElementById("btn-new-role");
  if (btnNew) btnNew.addEventListener("click", openCreate);

  if (tbody) {
    tbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!(target instanceof HTMLElement)) return;
      if (target.dataset.editRole) {
        openEdit(target.dataset.editRole);
        return;
      }
      if (target.dataset.deleteRole) {
        var slug = target.dataset.deleteRole;
        if (!window.confirm("¿Eliminar el rol «" + slug + "»?")) return;
        api("../api/admin/roles.php", {
          method: "DELETE",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ slug: slug }),
        })
          .then(function () {
            showMsg(rolesMsg, "Rol eliminado", true);
            return loadRoles();
          })
          .catch(function (err) {
            showMsg(rolesMsg, err.message, false);
          });
      }
    });
  }

  if (form) {
    form.addEventListener("submit", function (event) {
      var submitter = event.submitter;
      if (submitter && submitter.value === "cancel") return;

      event.preventDefault();
      var mode = document.getElementById("role-mode").value;
      var slug = document.getElementById("role-slug").value.trim();
      var name = document.getElementById("role-name").value.trim();
      var description = document.getElementById("role-description").value.trim();
      var isAdmin = slug === "admin";
      var payload = {
        name: name,
        description: description,
        permissions: collectPermissions(isAdmin),
        allowed_layers: collectLayers(isAdmin),
      };

      var req;
      if (mode === "create") {
        req = api("../api/admin/roles.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
      } else {
        payload.slug = slug;
        req = api("../api/admin/roles.php", {
          method: "PUT",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
      }

      req
        .then(function () {
          dialog.close();
          showMsg(rolesMsg, "Rol guardado", true);
          return loadRoles();
        })
        .catch(function (err) {
          showMsg(rolesMsg, err.message, false);
        });
    });
  }

  document.addEventListener("mxli:roles-tab-shown", loadRoles);
  loadRoles();
})();
