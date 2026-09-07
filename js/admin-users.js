(function () {
  "use strict";

  var tbody = document.querySelector("#users-table tbody");
  var createForm = document.getElementById("user-create-form");
  var createMsg = document.getElementById("create-msg");
  var listMsg = document.getElementById("list-msg");
  var dialog = document.getElementById("edit-dialog");
  var editForm = document.getElementById("user-edit-form");
  var editMsg = document.getElementById("edit-msg");
  var filterText = document.getElementById("filter-text");
  var filterRole = document.getElementById("filter-role");
  var filterActivo = document.getElementById("filter-activo");
  var filterPadron = document.getElementById("filter-padron");
  var filterCount = document.getElementById("filter-count");
  var usersCache = [];
  var filteredUsers = [];
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

  function getBulkRoleSelect() {
    return (
      document.getElementById("bulk-role") ||
      document.getElementById("import-role") ||
      document.querySelector("#user-import-form select[name='role']") ||
      document.querySelector("#bulk-import-panel select[name='role']") ||
      document.querySelector("#user-import-form select")
    );
  }

  /** Opciones reales (value no vacío); ignora placeholder "Cargando…" */
  function selectHasRealOptions(sel) {
    if (!sel || !sel.options || !sel.options.length) return false;
    for (var i = 0; i < sel.options.length; i++) {
      if (String(sel.options[i].value || "").trim()) return true;
    }
    return false;
  }

  function setRoleSelectLoading(sel) {
    if (!sel) return;
    sel.disabled = true;
    sel.innerHTML = '<option value="" disabled selected>Cargando roles…</option>';
  }

  function fillOneRoleSelect(sel, preferred) {
    if (!sel) return;
    var isBulk =
      sel.id === "bulk-role" ||
      sel.id === "import-role" ||
      (sel.form && sel.form.id === "user-import-form");
    if (!rolesCache.length) {
      if (isBulk) {
        setRoleSelectLoading(sel);
      } else {
        sel.innerHTML = "";
      }
      return;
    }
    var current = preferred || sel.value || "consulta";
    if (!String(current || "").trim()) current = "consulta";
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
    sel.innerHTML = opts;
    if (isBulk) sel.disabled = false;
    if (rolesCache.some(function (r) { return r.slug === current; })) {
      sel.value = current;
    } else if (rolesCache.some(function (r) { return r.slug === "consulta"; })) {
      sel.value = "consulta";
    } else {
      sel.value = rolesCache[0].slug;
    }
  }

  function cloneRoleOptions(fromSel, toSel) {
    if (!fromSel || !toSel || !selectHasRealOptions(fromSel)) return false;
    var preferred = toSel.value || fromSel.value || "consulta";
    toSel.innerHTML = fromSel.innerHTML;
    toSel.disabled = false;
    if (preferred && Array.prototype.some.call(toSel.options, function (o) {
      return o.value === preferred;
    })) {
      toSel.value = preferred;
    } else if (fromSel.value) {
      toSel.value = fromSel.value;
    } else if (toSel.options.length) {
      toSel.selectedIndex = 0;
    }
    return selectHasRealOptions(toSel);
  }

  function fillRoleSelects(preferredEdit) {
    var createSel = document.getElementById("create-role");
    var editSel = document.getElementById("edit-role");
    var bulkSel = getBulkRoleSelect();
    var currentCreate = (createSel && createSel.value) || "consulta";
    var currentBulk = (bulkSel && bulkSel.value) || currentCreate;

    fillOneRoleSelect(createSel, currentCreate);
    fillOneRoleSelect(bulkSel, currentBulk);

    // Si alta masiva sigue sin opciones reales, clonar desde «Nuevo usuario»
    if (bulkSel && !selectHasRealOptions(bulkSel) && selectHasRealOptions(createSel)) {
      cloneRoleOptions(createSel, bulkSel);
    }
    // Inverso: si por timing create quedó vacío y bulk ya tiene lista
    if (createSel && !selectHasRealOptions(createSel) && selectHasRealOptions(bulkSel)) {
      cloneRoleOptions(bulkSel, createSel);
    }

    if (editSel) {
      fillOneRoleSelect(editSel, preferredEdit || editSel.value);
    }
  }

  // Alias usado por llamadas / depuración
  function fillRoles(preferredEdit) {
    fillRoleSelects(preferredEdit);
  }

  function ensureBulkRoleFilled() {
    fillRoleSelects();
    var bulkSel = getBulkRoleSelect();
    var createSel = document.getElementById("create-role");
    if (bulkSel && !selectHasRealOptions(bulkSel) && selectHasRealOptions(createSel)) {
      cloneRoleOptions(createSel, bulkSel);
    }
  }

  function fillFilterRoleSelect() {
    if (!filterRole) return;
    var current = filterRole.value || "";
    var opts =
      '<option value="">Todos</option>' +
      rolesCache
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
    filterRole.innerHTML = opts;
    if (
      current &&
      Array.prototype.some.call(filterRole.options, function (o) {
        return o.value === current;
      })
    ) {
      filterRole.value = current;
    } else {
      filterRole.value = "";
    }
  }

  function userHasPadron(user) {
    return !!(
      user.can_view_padron ||
      (user.permissions || []).indexOf("map.padron") !== -1
    );
  }

  function getFilterState() {
    return {
      text: String((filterText && filterText.value) || "")
        .trim()
        .toLowerCase(),
      role: String((filterRole && filterRole.value) || "").trim(),
      activo: String((filterActivo && filterActivo.value) || "").trim(),
      padron: String((filterPadron && filterPadron.value) || "").trim(),
    };
  }

  function applyFilters() {
    var f = getFilterState();
    filteredUsers = (usersCache || []).filter(function (user) {
      var role = user.role === "user" ? "consulta" : user.role;
      if (f.role && role !== f.role) return false;
      if (f.activo === "1" && !user.is_active) return false;
      if (f.activo === "0" && user.is_active) return false;
      if (f.padron === "1" && !userHasPadron(user)) return false;
      if (f.padron === "0" && userHasPadron(user)) return false;
      if (f.text) {
        var hay =
          String(user.username || "").toLowerCase() +
          " " +
          String(user.full_name || "").toLowerCase() +
          " " +
          String(user.email || "").toLowerCase();
        if (hay.indexOf(f.text) === -1) return false;
      }
      return true;
    });
    renderUsersTable(filteredUsers);
    updateFilterCount();
  }

  function updateFilterCount() {
    if (!filterCount) return;
    var total = usersCache.length;
    var shown = filteredUsers.length;
    if (!total) {
      filterCount.textContent = "Sin usuarios";
      return;
    }
    if (shown === total) {
      filterCount.textContent = total + " usuario" + (total === 1 ? "" : "s");
    } else {
      filterCount.textContent =
        "Mostrando " + shown + " de " + total;
    }
  }

  function csvEscape(value) {
    var s = String(value == null ? "" : value);
    if (/[",\r\n]/.test(s)) {
      return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  function exportFilteredCsv() {
    var rows = filteredUsers;
    var header = [
      "usuario",
      "nombre_completo",
      "correo",
      "rol",
      "can_view_padron",
      "activo",
      "vence",
      "ultimo_acceso",
    ];
    var lines = [header.join(",")];
    rows.forEach(function (user) {
      var role = user.role === "user" ? "consulta" : user.role || "";
      lines.push(
        [
          csvEscape(user.username),
          csvEscape(user.full_name || ""),
          csvEscape(user.email || ""),
          csvEscape(role),
          csvEscape(user.can_view_padron ? "1" : "0"),
          csvEscape(user.is_active ? "1" : "0"),
          csvEscape(user.expires_at || ""),
          csvEscape(user.last_login_at || ""),
        ].join(",")
      );
    });
    var bom = "\uFEFF";
    var blob = new Blob([bom + lines.join("\r\n")], {
      type: "text/csv;charset=utf-8;",
    });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    var stamp = new Date();
    var pad = function (n) {
      return n < 10 ? "0" + n : String(n);
    };
    a.href = url;
    a.download =
      "usuarios_" +
      stamp.getFullYear() +
      pad(stamp.getMonth() + 1) +
      pad(stamp.getDate()) +
      "_" +
      pad(stamp.getHours()) +
      pad(stamp.getMinutes()) +
      pad(stamp.getSeconds()) +
      ".csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showMsg(
      listMsg,
      "Exportados " + rows.length + " usuario" + (rows.length === 1 ? "" : "s"),
      true
    );
  }

  function renderUsers(users) {
    usersCache = users || [];
    applyFilters();
  }

  function renderUsersTable(users) {
    tbody.innerHTML = "";

    (users || []).forEach(function (user) {
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
        (userHasPadron(user) ? "badge-on" : "badge-off") +
        "\">" +
        (user.can_view_padron
          ? "Usuario"
          : (user.permissions || []).indexOf("map.padron") !== -1
            ? "Rol"
            : "No") +
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
        ensureBulkRoleFilled();
        fillFilterRoleSelect();
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
      if (tab === "users") {
        ensureBulkRoleFilled();
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
      can_view_padron: !!(document.getElementById("create-padron") && document.getElementById("create-padron").checked),
      expires_at: expires || null,
    };

    api("../api/admin/users.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function () {
        createForm.reset();
        fillRoleSelects();
        var createRole = document.getElementById("create-role");
        if (createRole && rolesCache.some(function (r) { return r.slug === "consulta"; })) {
          createRole.value = "consulta";
        }
        var createPadron = document.getElementById("create-padron");
        if (createPadron) createPadron.checked = false;
        showMsg(createMsg, "Usuario creado correctamente", true);
        return loadUsers();
      })
      .catch(function (err) {
        showMsg(createMsg, err.message, false);
      });
  });

  var importForm = document.getElementById("user-import-form");
  var importMsg = document.getElementById("import-msg");
  var importResults = document.getElementById("import-results");
  var importSummary = document.getElementById("import-summary");
  var importSkippedList = document.getElementById("import-skipped-list");
  var importSubmit = document.getElementById("import-submit");

  function renderImportResults(data) {
    if (!importResults || !importSummary || !importSkippedList) return;
    var created = Number(data.created || 0);
    var skippedCount = Number(data.skipped_count || 0);
    var parts = [
      "Creados: " + created,
      "Omitidos: " + skippedCount,
    ];
    if (data.role) {
      parts.push("Rol: " + roleLabel(data.role));
    }
    if (data.truncated) {
      parts.push(data.truncated);
    }
    importSummary.textContent = parts.join(" · ");
    importSkippedList.innerHTML = "";
    var skipped = data.skipped || [];
    if (skipped.length === 0) {
      importResults.hidden = false;
      return;
    }
    skipped.slice(0, 100).forEach(function (item) {
      var li = document.createElement("li");
      var who = item.username ? " «" + item.username + "»" : "";
      li.textContent =
        "Línea " + (item.line || "?") + who + ": " + (item.reason || "Omitido");
      importSkippedList.appendChild(li);
    });
    if (skipped.length > 100) {
      var more = document.createElement("li");
      more.textContent = "… y " + (skipped.length - 100) + " más";
      importSkippedList.appendChild(more);
    }
    importResults.hidden = false;
  }

  // Al enfocar / abrir Alta masiva, asegurar que el combo tenga roles
  var bulkPanel = document.getElementById("bulk-import-panel");
  if (bulkPanel) {
    ["focusin", "click", "mouseenter"].forEach(function (evt) {
      bulkPanel.addEventListener(evt, function () {
        ensureBulkRoleFilled();
      });
    });
  }
  var bulkRoleEarly = getBulkRoleSelect();
  if (bulkRoleEarly) {
    ["focus", "mousedown", "touchstart"].forEach(function (evt) {
      bulkRoleEarly.addEventListener(evt, function () {
        ensureBulkRoleFilled();
      });
    });
  }

  if (importForm) {
    importForm.addEventListener("submit", function (event) {
      event.preventDefault();
      showMsg(importMsg, "", true);
      if (importResults) importResults.hidden = true;

      var fileInput = document.getElementById("import-file");
      var file = fileInput && fileInput.files && fileInput.files[0];
      if (!file) {
        showMsg(importMsg, "Seleccione un archivo CSV o XLSX", false);
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        showMsg(importMsg, "El archivo supera 2 MB", false);
        return;
      }

      var roleEl = getBulkRoleSelect();
      var role = String(roleEl && roleEl.value ? roleEl.value : "").trim();
      if (!role) {
        showMsg(importMsg, "Seleccione un rol para todos los usuarios", false);
        return;
      }

      var fd = new FormData();
      fd.append("file", file);
      fd.append("role", role);
      var expires = String(
        (document.getElementById("import-expires") &&
          document.getElementById("import-expires").value) ||
          ""
      ).trim();
      if (expires) fd.append("expires_at", expires);

      if (importSubmit) {
        importSubmit.disabled = true;
        importSubmit.textContent = "Importando…";
      }

      fetch("../api/admin/users-import.php", {
        method: "POST",
        body: fd,
      })
        .then(function (res) {
          return res.text().then(function (text) {
            var data = null;
            var raw = String(text || "");
            try {
              data = raw ? JSON.parse(raw) : null;
            } catch (parseErr) {
              var snippet = raw.replace(/\s+/g, " ").trim().slice(0, 280);
              throw new Error(
                "Respuesta no JSON del servidor (HTTP " +
                  res.status +
                  "). " +
                  (snippet
                    ? "Vista previa: " + snippet
                    : "Cuerpo vacío. Revise Network → users-import.php → Response.")
              );
            }
            if (!data || typeof data !== "object") {
              throw new Error("Respuesta vacía o inválida al importar");
            }
            if (!res.ok || data.ok === false) {
              var errMsg = (data && data.error) || "Error al importar";
              if (data && data.detail) {
                errMsg = errMsg + " — " + data.detail;
              }
              throw new Error(errMsg);
            }
            return data;
          });
        })
        .then(function (data) {
          showMsg(
            importMsg,
            data.message ||
              ("Importación finalizada: " +
                (data.created || 0) +
                " creados, " +
                (data.skipped_count || 0) +
                " omitidos."),
            true
          );
          renderImportResults(data);
          importForm.reset();
          // reset() vuelve al placeholder disabled del HTML; rellenar de nuevo
          ensureBulkRoleFilled();
          var bulkAfter = getBulkRoleSelect();
          if (bulkAfter && rolesCache.some(function (r) { return r.slug === role; })) {
            bulkAfter.value = role;
          }
          return loadUsers();
        })
        .catch(function (err) {
          showMsg(importMsg, err.message, false);
        })
        .finally(function () {
          if (importSubmit) {
            importSubmit.disabled = false;
            importSubmit.textContent = "Importar";
          }
        });
    });
  }

  document.getElementById("btn-refresh").addEventListener("click", loadUsers);

  var btnExport = document.getElementById("btn-export");
  if (btnExport) {
    btnExport.addEventListener("click", function () {
      exportFilteredCsv();
    });
  }

  function bindFilter(el, evt) {
    if (!el) return;
    el.addEventListener(evt || "change", applyFilters);
  }
  bindFilter(filterText, "input");
  bindFilter(filterRole, "change");
  bindFilter(filterActivo, "change");
  bindFilter(filterPadron, "change");

  tbody.addEventListener("click", function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.dataset.edit) {
      var id = Number(target.dataset.edit);
      var user = usersCache.find(function (u) {
        return Number(u.id) === id;
      });
      if (!user) return;

      var currentRole = user.role === "user" ? "consulta" : user.role || "consulta";
      fillRoleSelects(currentRole);
      document.getElementById("edit-id").value = String(user.id);
      document.getElementById("edit-username").textContent = user.username;
      document.getElementById("edit-full-name").value = user.full_name || "";
      document.getElementById("edit-email").value = user.email || "";
      document.getElementById("edit-password").value = "";
      var editRole = document.getElementById("edit-role");
      if (rolesCache.some(function (r) { return r.slug === currentRole; })) {
        editRole.value = currentRole;
      } else if (rolesCache.length) {
        // Rol en BD no listado: agregar opción temporal para no perder el valor
        var opt = document.createElement("option");
        opt.value = currentRole;
        opt.textContent = (user.role_label || currentRole) + " (actual)";
        editRole.appendChild(opt);
        editRole.value = currentRole;
      }
      document.getElementById("edit-expires").value = dateInputValue(user.expires_at);
      document.getElementById("edit-active").checked = !!user.is_active;
      var editPadron = document.getElementById("edit-padron");
      if (editPadron) editPadron.checked = !!user.can_view_padron;
      showMsg(editMsg, "", true);
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

  var editCancel = document.getElementById("edit-cancel");
  if (editCancel) {
    editCancel.addEventListener("click", function () {
      dialog.close();
    });
  }

  editForm.addEventListener("submit", function (event) {
    event.preventDefault();
    showMsg(editMsg, "", true);

    var roleEl = document.getElementById("edit-role");
    var role = String(roleEl && roleEl.value ? roleEl.value : "").trim();
    if (!role) {
      showMsg(editMsg, "Seleccione un rol", false);
      return;
    }

    var password = document.getElementById("edit-password").value;
    if (password && password.length < 6) {
      showMsg(editMsg, "La contraseña debe tener al menos 6 caracteres", false);
      return;
    }

    var expires = document.getElementById("edit-expires").value.trim();
    var payload = {
      id: Number(document.getElementById("edit-id").value),
      full_name: document.getElementById("edit-full-name").value.trim(),
      email: document.getElementById("edit-email").value.trim(),
      role: role,
      is_active: document.getElementById("edit-active").checked,
      can_view_padron: !!(
        document.getElementById("edit-padron") &&
        document.getElementById("edit-padron").checked
      ),
      expires_at: expires || null,
    };

    if (password) payload.password = password;

    api("../api/admin/users.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function () {
        dialog.close();
        showMsg(listMsg, "Usuario actualizado", true);
        return loadUsers();
      })
      .catch(function (err) {
        showMsg(editMsg, err.message, false);
        showMsg(listMsg, err.message, false);
      });
  });

  window.mxliReloadUserRoles = loadUsers;
  loadUsers();
})();

