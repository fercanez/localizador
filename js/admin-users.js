(function () {
  "use strict";

  var tbody = document.querySelector("#users-table tbody");
  var createForm = document.getElementById("user-create-form");
  var createMsg = document.getElementById("create-msg");
  var listMsg = document.getElementById("list-msg");
  var dialog = document.getElementById("edit-dialog");
  var editForm = document.getElementById("user-edit-form");
  var usersCache = [];

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
          throw new Error((data && data.error) || "Error de servidor");
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

  function renderUsers(users) {
    usersCache = users || [];
    tbody.innerHTML = "";

    usersCache.forEach(function (user) {
      var tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" +
        escapeHtml(user.username) +
        "</td>" +
        "<td>" +
        escapeHtml(user.full_name || "") +
        "</td>" +
        "<td><span class=\"badge badge-" +
        (user.role === "admin" ? "admin" : "user") +
        "\">" +
        escapeHtml(user.role) +
        "</span></td>" +
        "<td><span class=\"badge " +
        (user.is_active ? "badge-on" : "badge-off") +
        "\">" +
        (user.is_active ? "Sí" : "No") +
        "</span></td>" +
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
        renderUsers(data.users || []);
      })
      .catch(function (err) {
        showMsg(listMsg, err.message, false);
      });
  }

  createForm.addEventListener("submit", function (event) {
    event.preventDefault();
    var fd = new FormData(createForm);
    var payload = {
      username: String(fd.get("username") || "").trim(),
      full_name: String(fd.get("full_name") || "").trim(),
      email: String(fd.get("email") || "").trim(),
      password: String(fd.get("password") || ""),
      role: String(fd.get("role") || "user"),
      is_active: true,
    };

    api("../api/admin/users.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function () {
        createForm.reset();
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

      document.getElementById("edit-id").value = String(user.id);
      document.getElementById("edit-username").textContent = user.username;
      document.getElementById("edit-full-name").value = user.full_name || "";
      document.getElementById("edit-email").value = user.email || "";
      document.getElementById("edit-password").value = "";
      document.getElementById("edit-role").value = user.role || "user";
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
    var payload = {
      id: Number(document.getElementById("edit-id").value),
      full_name: document.getElementById("edit-full-name").value.trim(),
      email: document.getElementById("edit-email").value.trim(),
      role: document.getElementById("edit-role").value,
      is_active: document.getElementById("edit-active").checked,
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

  loadUsers();
})();
