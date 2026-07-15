<?php
declare(strict_types=1);

require_once __DIR__ . "/includes/init.php";
require_once __DIR__ . "/includes/auth.php";

if (mxli_is_logged_in()) {
    header("Location: index.php");
    exit;
}

$next = (string)($_GET["next"] ?? "index.php");
if ($next === "" || str_contains($next, "://") || str_starts_with($next, "//")) {
    $next = "index.php";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Ingreso | Catastro Mexicali</title>
  <link rel="stylesheet" href="css/auth-app.css">
</head>
<body class="auth-body">
  <main class="auth-card">
    <div class="auth-brand">
      <img src="escudo-mexicali.png" alt="Escudo de Mexicali" class="auth-logo">
      <div>
        <p class="auth-eyebrow">Dirección de Administración Urbana</p>
        <h1>Jefatura de Catastro</h1>
        <p class="auth-sub">Mexicali, Baja California</p>
      </div>
    </div>

    <form id="auth-login-form" class="auth-form" autocomplete="on">
      <h2>Iniciar sesión</h2>
      <p class="auth-hint">Ingrese con su usuario asignado por el administrador.</p>

      <label>
        Usuario
        <input type="text" name="username" id="username" required autocomplete="username" autofocus>
      </label>

      <label>
        Contraseña
        <input type="password" name="password" id="password" required autocomplete="current-password">
      </label>

      <p id="auth-error" class="auth-error" hidden></p>

      <button type="submit" class="auth-btn" id="auth-submit">Entrar</button>
    </form>
  </main>

  <script>
  (function () {
    var form = document.getElementById("auth-login-form");
    var errorEl = document.getElementById("auth-error");
    var submitBtn = document.getElementById("auth-submit");
    var nextUrl = <?= json_encode($next, JSON_UNESCAPED_SLASHES) ?>;

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      errorEl.hidden = true;
      submitBtn.disabled = true;
      submitBtn.textContent = "Entrando...";

      fetch("api/auth/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({
          username: document.getElementById("username").value.trim(),
          password: document.getElementById("password").value
        })
      })
        .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
        .then(function (result) {
          if (!result.res.ok || !result.data.ok) {
            throw new Error((result.data && result.data.error) || "No se pudo iniciar sesión");
          }
          window.location.href = nextUrl || "index.php";
        })
        .catch(function (err) {
          errorEl.textContent = err.message || "Error de acceso";
          errorEl.hidden = false;
          submitBtn.disabled = false;
          submitBtn.textContent = "Entrar";
        });
    });
  })();
  </script>
</body>
</html>
