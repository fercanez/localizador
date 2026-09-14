# Deploy consulta de adeudo predial (portal Mexicali + capa temporal)
$ErrorActionPreference = "Stop"
$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$Server = "${ServerUser}@${ServerHost}"
$Port = "3839"
$Remote = "/var/www/html/localizacion"
$Root = $PSScriptRoot
$UseTmp = $ServerUser -ne "root"

$files = @(
    @{ Local = "includes/predial.php";                 Remote = "includes/predial.php";                 Tmp = "mxli_predial.php" },
    @{ Local = "api/predial-adeudo.php";               Remote = "api/predial-adeudo.php";               Tmp = "mxli_api_predial_adeudo.php" },
    @{ Local = "api/predial-adeudos.php";              Remote = "api/predial-adeudos.php";              Tmp = "mxli_api_predial_adeudos.php" },
    @{ Local = "api/wfs-bbox.php";                     Remote = "api/wfs-bbox.php";                     Tmp = "mxli_api_wfs_bbox.php" },
    @{ Local = "js/predialAdeudo.js";                  Remote = "js/predialAdeudo.js";                  Tmp = "mxli_predialAdeudo.js" },
    @{ Local = "js/predialCapa.js";                    Remote = "js/predialCapa.js";                    Tmp = "mxli_predialCapa.js" },
    @{ Local = "js/padronPredio.js";                   Remote = "js/padronPredio.js";                   Tmp = "mxli_padronPredio.js" },
    @{ Local = "js/buscadorPredios.js";                Remote = "js/buscadorPredios.js";                Tmp = "mxli_buscadorPredios.js" },
    @{ Local = "js/leaflet.js";                        Remote = "js/leaflet.js";                        Tmp = "mxli_leaflet.js" },
    @{ Local = "js/listaCapas.js";                     Remote = "js/listaCapas.js";                     Tmp = "mxli_listaCapas.js" },
    @{ Local = "js/wmsLayerPanel.js";                  Remote = "js/wmsLayerPanel.js";                  Tmp = "mxli_wmsLayerPanel.js" },
    @{ Local = "includes/sidePanels/listadoCapas.php"; Remote = "includes/sidePanels/listadoCapas.php"; Tmp = "mxli_listadoCapas.php" },
    @{ Local = "includes/head.php";                    Remote = "includes/head.php";                    Tmp = "mxli_head.php" },
    @{ Local = "index.php";                            Remote = "index.php";                            Tmp = "mxli_index.php" },
    @{ Local = "css/index.css";                        Remote = "css/index.css";                        Tmp = "mxli_index.css" },
    @{ Local = "css/mobile-map.css";                   Remote = "css/mobile-map.css";                   Tmp = "mxli_mobile_map.css" }
)

Write-Host "==> Verificando archivos locales..." -ForegroundColor Cyan
foreach ($f in $files) {
    $path = Join-Path $Root $f.Local
    if (-not (Test-Path $path)) { throw "Archivo faltante: $path" }
    Write-Host "  OK $($f.Local)"
}

Write-Host "==> Subiendo (scp -P $Port) a $Server ..." -ForegroundColor Cyan
foreach ($f in $files) {
    $local = Join-Path $Root $f.Local
    if ($UseTmp) {
        $dest = "${Server}:/tmp/$($f.Tmp)"
    } else {
        $dest = "${Server}:${Remote}/$($f.Remote)"
    }
    Write-Host "  scp $($f.Local) -> $dest"
    & scp -P $Port $local $dest
    if ($LASTEXITCODE -ne 0) { throw "scp fallo para $($f.Local)" }
}

if ($UseTmp) {
    Write-Host "==> Copiando con sudo en el servidor..." -ForegroundColor Cyan
    $copies = ($files | ForEach-Object {
        "sudo mkdir -p $Remote/$(Split-Path $_.Remote -Parent); sudo cp /tmp/$($_.Tmp) $Remote/$($_.Remote)"
    }) -join "; "
    & ssh -p $Port $Server $copies
    if ($LASTEXITCODE -ne 0) { throw "sudo cp remoto fallo" }
}

Write-Host "==> Listo. Recargue https://localizacion.geomexicali.info (Ctrl+F5)." -ForegroundColor Green
