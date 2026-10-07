<?php
declare(strict_types=1);

require_once __DIR__ . "/includes/init.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/expediente.php";

mxli_require_login();

if (!mxli_can("map.expediente")) {
    http_response_code(403);
    echo "Sin permiso para consultar expedientes.";
    exit;
}

$clave = mxli_normalize_expediente_clave((string)($_GET["clave"] ?? ""));
if ($clave === "") {
    http_response_code(400);
    echo "Falta la clave catastral.";
    exit;
}

$flags = mxli_expediente_flags();
$embed = isset($_GET["embed"]) && (string)$_GET["embed"] === "1";
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Expediente <?= $h($clave) ?></title>
  <link rel="stylesheet" href="css/expediente-visor.css?v=9">
</head>
<body class="<?= !empty($flags["activa"]) ? "wm-on" : "" ?><?= $embed ? " exp-embed" : "" ?>" oncontextmenu="return false;">
  <header class="exp-bar">
    <div class="exp-bar__left">
      <?php if ($embed): ?>
        <button type="button" class="exp-bar__back" id="exp-back-btn">← Cerrar</button>
      <?php else: ?>
        <a class="exp-bar__back" href="index.php" id="exp-back-btn">← Mapa</a>
      <?php endif; ?>
      <strong>Expediente <?= $h($clave) ?></strong>
    </div>
    <div class="exp-bar__actions">
      <div class="exp-zoom" role="group" aria-label="Zoom">
        <button type="button" id="exp-zoom-out" class="exp-btn exp-btn--icon" title="Alejar">−</button>
        <span id="exp-zoom-pct" class="exp-zoom__pct">100%</span>
        <button type="button" id="exp-zoom-in" class="exp-btn exp-btn--icon" title="Acercar">+</button>
        <button type="button" id="exp-zoom-fitw" class="exp-btn exp-btn--icon" title="Ajustar al ancho">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v2H4V7zm0 8h16v2H4v-2zm-1-5h2v4H3v-4zm18 0h2v4h-2v-4z"/></svg>
        </button>
        <button type="button" id="exp-zoom-fith" class="exp-btn exp-btn--icon" title="Ajustar al alto">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16h2V4H7zm8 0v16h2V4h-2zM9 3h6v2H9V3zm0 16h6v2H9v-2z"/></svg>
        </button>
      </div>
      <?php if (!empty($flags["puedeQuitar"])): ?>
        <button type="button" id="exp-toggle-wm" class="exp-btn">
          <?= !empty($flags["activa"]) ? "Quitar marca de agua" : "Poner marca de agua" ?>
        </button>
      <?php endif; ?>
      <?php if (!empty($flags["puedeDescargar"])): ?>
        <button type="button" id="exp-download" class="exp-btn exp-btn--primary">Descargar</button>
      <?php endif; ?>
      <?php if (!empty($flags["puedeImprimir"])): ?>
        <button type="button" id="exp-print" class="exp-btn exp-btn--primary">Imprimir</button>
      <?php endif; ?>
    </div>
  </header>
  <div id="exp-scroll">
    <div id="exp-pages"><p class="exp-msg">Cargando expediente…</p></div>
  </div>
  <div id="exp-busy">Preparando…</div>
  <script>
  window.MXLI_EXPEDIENTE = {
    clave: <?= json_encode($clave, JSON_UNESCAPED_UNICODE) ?>,
    pdfUrl: <?= json_encode("api/expediente-pdf.php?clave=" . rawurlencode($clave), JSON_UNESCAPED_UNICODE) ?>,
    wm: <?= !empty($flags["activa"]) ? "true" : "false" ?>,
    canToggle: <?= !empty($flags["puedeQuitar"]) ? "true" : "false" ?>,
    canDownload: <?= !empty($flags["puedeDescargar"]) ? "true" : "false" ?>,
    canPrint: <?= !empty($flags["puedeImprimir"]) ? "true" : "false" ?>,
    watermarkUrl: "img/marca_agua_expediente.png"
  };
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="js/expedienteVisor.js?v=8"></script>
  <script>
  (function () {
    var back = document.getElementById("exp-back-btn");
    if (!back) return;
    back.addEventListener("click", function (e) {
      e.preventDefault();
      if (window.parent && window.parent !== window) {
        try {
          window.parent.postMessage({ type: "mxli-expediente-close" }, "*");
          return;
        } catch (err) {}
      }
      if (window.opener && !window.opener.closed) {
        try { window.opener.focus(); } catch (e2) {}
        window.close();
        return;
      }
      window.close();
      setTimeout(function () {
        if (!window.closed) window.location.href = "index.php";
      }, 250);
    });
  })();
  </script>
</body>
</html>
