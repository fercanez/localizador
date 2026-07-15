(function () {
  "use strict";

  var tbody = document.querySelector("#users-table tbody");
  var createForm = document.getElementById("user-create-form");
  var createMsg = document.getElementById("create-msg");
  var listMsg = document.getElementById("list-msg");
  var dialog = document.getElementById("edit-dialog");
  var editForm = document.getElementById("user-edit-form");
  var usersCache = [];
  var rolesCache = [];

  function showMsg(el, text, ok) {
    el.hidden = !text;
    el.textContent = text || "";
    el.classList.toggle("is-ok", !!ok);
    el.classList.toggle("is-error", !ok && !!text);
  }

  function api(url, options) {
    return fetch(url, options).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || data.ok === false) {
          var msg = (data && data.error) || "Error de servidor";
          if (data && data.detail && msg.indexOf("servidor") !== -1) {
            msg = msg + " (" + data.detail + ")";
          }
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function formatDate(value) {
    if (!value) return "—";
    try {
      return new Date(value).toLocaleString("es-MX");
    } catch (e) {
      return value;
    }
  }

  function formatExpires(user) {
    if (!user.expires_at) {
      return '<span class="badge badge-on">Sin límite</span>';
    }
    if (user.is_expired) {
      return (
        '<span class="badge badge-off">Vencido ' +
        escapeHtml(user.expires_at) +
        "</span>"
      );
    }
    return (
      '<span class="badge badge-consulta">' +
      escapeHtml(user.expires_at) +
      "</span>"
    );
  }

  function roleMeta(slug) {
    var role = rolesCache.find(function (r) {
      return r.slug === slug;
    });
    return role || { slug: slug, name: slug };
  }

  function roleLabel(role) {
    if (role === "user") role = "consulta";
    var meta = roleMeta(role);
    return meta.name || role || "Consulta";
  }

  function roleBadgeClass(role) {
    if (role === "admin") return "badge-admin";
    if (role === "campo") return "badge-campo";
    if (role === "consulta" || role === "user") return "badge-consulta";
    return "badge-custom";
  }

  function dateInputValue(value) {
    if (!value) return "";
    var m = String(value).match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : "";
  }

  function fillRoleSelects(preferred) {
    var createSel = document.getElementById("create-role");
    var editSel = document.getElementById("edit-role");
    var currentCreate = preferred || (createSel && createSel.value) || "consulta";
    var opts = rolesCache
      .map(function (r) {
        return (
          '<option value="' +
          escapeHtml(r.slug) +
          '">' +
          escapeHtml(r.name) +
          "</option>"
        );
      })
      .join("");

    if (createSel) {
      createSel.innerHTML = opts;
      if (rolesCache.some(function (r) { return r.slug === currentCreate; })) {
        createSel.value = currentCreate;
      } else if (rolesCache.length) {
        createSel.value = rolesCache[0].slug;
      }
    }
    if (editSel) {
      editSel.innerHTML = opts;
    }
  }

  function renderUsers(users) {
    usersCache = users || [];
    tbody.innerHTML = "";

    usersCache.forEach(function (user) {
      var role = user.role === "user" ? "consulta" : user.role;
      var tr = document.createElement("tr");
      if (user.is_expired) {
        tr.classList.add("is-expired");
      }
      tr.innerHTML =
        "<td>" +
        escapeHtml(user.username) +
        "</td>" +
        "<td>" +
        escapeHtml(user.full_name || "") +
        "</td>" +
        "<td><span class=\"badge " +
        roleBadgeClass(role) +
        "\">" +
        escapeHtml(user.role_label || roleLabel(role)) +
        "</span></td>" +
        "<td><span class=\"badge " +
        (user.is_active ? "badge-on" : "badge-off") +
        "\">" +
        (user.is_active ? "Sí" : "No") +
        "</span></td>" +
        "<td>" +
        formatExpires(user) +
        "</td>" +
        "<td>" +
        escapeHtml(formatDate(user.last_login_at)) +
        "</td>" +
        "<td class=\"admin-actions\">" +
        "<button type=\"button\" data-edit=\"" +
        user.id +
        "\">Editar</button>" +
        "<button type=\"button\" data-toggle=\"" +
        user.id +
        "\">" +
        (user.is_active ? "Desactivar" : "Activar") +
        "</button>" +
        "</td>";
      tbody.appendChild(tr);
    });
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;");
  }

  function loadUsers() {
    showMsg(listMsg, "", true);
    return api("../api/admin/users.php")
      .then(function (data) {
        rolesCache = data.roles || [];
        fillRoleSelects();
        renderUsers(data.users || []);
        document.dispatchEvent(
          new CustomEvent("mxli:roles-updated", { detail: { roles: rolesCache } })
        );
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  }

  // Tabs Usuarios / Roles
  document.querySelectorAll(".admin-tab").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var tab = btn.getAttribute("data-tab");
      document.querySelectorAll(".admin-tab").forEach(function (b) {
        b.classList.toggle("is-active", b === btn);
      });
      document.querySelectorAll(".admin-tab-panel").forEach(function (panel) {
        var active = panel.getAttribute("data-panel") === tab;
        panel.classList.toggle("is-active", active);
        panel.hidden = !active;
      });
      if (tab === "roles") {
        document.dispatchEvent(new Event("mxli:roles-tab-shown"));
      }
    });
  });

  createForm.addEventListener("submit", function (event) {
    event.preventDefault();
    var fd = new FormData(createForm);
    var expires = String(fd.get("expires_at") || "").trim();
    var payload = {
      username: String(fd.get("username") || "").trim(),
      full_name: String(fd.get("full_name") || "").trim(),
      email: String(fd.get("email") || "").trim(),
      password: String(fd.get("password") || ""),
      role: String(fd.get("role") || "consulta"),
      is_active: true,
      expires_at: expires || null,
    };

    api("../api/admin/users.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function () {
        createForm.reset();
        fillRoleSelects("consulta");
        showMsg(createMsg, "Usuario creado correctamente", true);
        return loadUsers();
      })
      .catch(function (err) {
        showMsg(createMsg, err.message, false);
      });
  });

  document.getElementById("btn-refresh").addEventListener("click", loadUsers);

  tbody.addEventListener("click", function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.dataset.edit) {
      var id = Number(target.dataset.edit);
      var user = usersCache.find(function (u) {
        return Number(u.id) === id;
      });
      if (!user) return;

      fillRoleSelects();
      document.getElementById("edit-id").value = String(user.id);
      document.getElementById("edit-username").textContent = user.username;
      document.getElementById("edit-full-name").value = user.full_name || "";
      document.getElementById("edit-email").value = user.email || "";
      document.getElementById("edit-password").value = "";
      document.getElementById("edit-role").value =
        user.role === "user" ? "consulta" : user.role || "consulta";
      document.getElementById("edit-expires").value = dateInputValue(user.expires_at);
      document.getElementById("edit-active").checked = !!user.is_active;
      dialog.showModal();
      return;
    }

    if (target.dataset.toggle) {
      var toggleId = Number(target.dataset.toggle);
      var row = usersCache.find(function (u) {
        return Number(u.id) === toggleId;
      });
      if (!row) return;

      api("../api/admin/users.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: toggleId,
          is_active: !row.is_active,
        }),
      })
        .then(loadUsers)
        .catch(function (err) {
          showMsg(listMsg, err.message, false);
        });
    }
  });

  editForm.addEventListener("submit", function (event) {
    var submitter = event.submitter;
    if (submitter && submitter.value === "cancel") {
      return;
    }

    event.preventDefault();
    var expires = document.getElementById("edit-expires").value.trim();
    var payload = {
      id: Number(document.getElementById("edit-id").value),
      full_name: document.getElementById("edit-full-name").value.trim(),
      email: document.getElementById("edit-email").value.trim(),
      role: document.getElementById("edit-role").value,
      is_active: document.getElementById("edit-active").checked,
      expires_at: expires || null,
    };

    var password = document.getElementById("edit-password").value;
    if (password) payload.password = password;

    api("../api/admin/users.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function () {
        dialog.close();
        return loadUsers();
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  });

  window.mxliReloadUserRoles = loadUsers;
  loadUsers();
})();
