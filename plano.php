<?php
declare(strict_types=1);

require_once __DIR__ . "/includes/init.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/planos.php";

mxli_require_login();

if (!mxli_can("map.planos")) {
    http_response_code(403);
    echo "Sin permiso para consultar planos.";
    exit;
}

$rel = mxli_planos_safe_rel((string)($_GET["f"] ?? ""));
if ($rel === "") {
    http_response_code(400);
    echo "Falta el archivo del plano.";
    exit;
}

$flags = mxli_planos_flags();
$embed = isset($_GET["embed"]) && (string)$_GET["embed"] === "1";
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, "UTF-8");
$name = basename($rel);
$ext = mxli_planos_ext($name);
$kind = in_array($ext, ["jpg", "jpeg"], true) ? "image"
    : (in_array($ext, ["dwg", "dxf"], true) ? "cad" : "pdf");
$fileKey = mxli_planos_rel_key($rel);
$fileUrl = "api/planos-file.php?f=" . rawurlencode($rel) . "&k=" . $fileKey;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Plano <?= $h($name) ?></title>
  <link rel="stylesheet" href="css/expediente-visor.css?v=9">
</head>
<body class="<?= !empty($flags["activa"]) ? "wm-on" : "" ?><?= $embed ? " exp-embed" : "" ?>" oncontextmenu="return false;">
  <header class="exp-bar">
    <div class="exp-bar__left">
      <?php if ($embed): ?>
        <span class="exp-bar__back" aria-hidden="true"></span>
      <?php else: ?>
        <a class="exp-bar__back" href="index.php">← Mapa</a>
      <?php endif; ?>
      <strong title="<?= $h($name) ?>"><?= $h($name) ?></strong>
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
    <div id="exp-pages"><p class="exp-msg">Cargando plano…</p></div>
  </div>
  <div id="exp-busy">Preparando…</div>
  <script>
  window.MXLI_PLANO = {
    name: <?= json_encode($name, JSON_UNESCAPED_UNICODE) ?>,
    rel: <?= json_encode($rel, JSON_UNESCAPED_UNICODE) ?>,
    ext: <?= json_encode($ext, JSON_UNESCAPED_UNICODE) ?>,
    kind: <?= json_encode($kind, JSON_UNESCAPED_UNICODE) ?>,
    fileUrl: <?= json_encode($fileUrl, JSON_UNESCAPED_UNICODE) ?>,
    wm: <?= !empty($flags["activa"]) ? "true" : "false" ?>,
    canToggle: <?= !empty($flags["puedeQuitar"]) ? "true" : "false" ?>,
    canDownload: <?= !empty($flags["puedeDescargar"]) ? "true" : "false" ?>,
    canPrint: <?= !empty($flags["puedeImprimir"]) ? "true" : "false" ?>,
    watermarkUrl: "img/marca_agua_expediente.png"
  };
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <?php if ($kind === "cad"): ?>
    <script src="js/cad/dxfPreview.js?v=2"></script>
    <?php if ($ext === "dwg"): ?>
      <script type="module" src="js/cad/mxli-libredwg.js?v=2"></script>
    <?php endif; ?>
  <?php endif; ?>
  <script src="js/planoVisor.js?v=4"></script>
</body>
</html>
