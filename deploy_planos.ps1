# Deploy consulta de planos (perito deslindador)
$ErrorActionPreference = "Stop"
$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$Server = "${ServerUser}@${ServerHost}"
$Port = "3839"
$Remote = "/var/www/html/localizacion"
$Root = $PSScriptRoot
$UseTmp = $ServerUser -ne "root"

$files = @(
    @{ Local = "includes/planos.php";              Remote = "includes/planos.php";              Tmp = "mxli_planos.php" },
    @{ Local = "includes/layer-catalog.php";       Remote = "includes/layer-catalog.php";       Tmp = "mxli_layer_catalog.php" },
    @{ Local = "includes/auth.php";                Remote = "includes/auth.php";                Tmp = "mxli_auth.php" },
    @{ Local = "includes/head.php";                Remote = "includes/head.php";                Tmp = "mxli_head.php" },
    @{ Local = "includes/layoutPublicMapa.php";    Remote = "includes/layoutPublicMapa.php";    Tmp = "mxli_layoutPublicMapa.php" },
    @{ Local = "includes/config.example.php";      Remote = "includes/config.example.php";      Tmp = "mxli_config_example.php" },
    @{ Local = "api/planos.php";                   Remote = "api/planos.php";                   Tmp = "mxli_api_planos.php" },
    @{ Local = "api/planos-file.php";              Remote = "api/planos-file.php";              Tmp = "mxli_api_planos_file.php" },
    @{ Local = "api/planos-meta.php";              Remote = "api/planos-meta.php";              Tmp = "mxli_api_planos_meta.php" },
    @{ Local = "api/cad-wasm.php";                 Remote = "api/cad-wasm.php";                 Tmp = "mxli_cad_wasm.php" },
    @{ Local = "plano.php";                        Remote = "plano.php";                        Tmp = "mxli_plano.php" },
    @{ Local = "js/planoVisor.js";                 Remote = "js/planoVisor.js";                 Tmp = "mxli_planoVisor.js" },
    @{ Local = "css/expediente-visor.css";         Remote = "css/expediente-visor.css";         Tmp = "mxli_expediente_visor.css" },
    @{ Local = "api/admin/roles.php";              Remote = "api/admin/roles.php";              Tmp = "mxli_api_admin_roles.php" },
    @{ Local = "js/consultaPlanos.js";             Remote = "js/consultaPlanos.js";             Tmp = "mxli_consultaPlanos.js" },
    @{ Local = "js/admin-roles.js";                Remote = "js/admin-roles.js";                Tmp = "mxli_admin_roles.js" },
    @{ Local = "admin/usuarios.php";               Remote = "admin/usuarios.php";               Tmp = "mxli_admin_usuarios.php" },
    @{ Local = "css/admin-users.css";              Remote = "css/admin-users.css";              Tmp = "mxli_admin_users.css" },
    @{ Local = "js/permissions.js";                Remote = "js/permissions.js";                Tmp = "mxli_permissions.js" },
    @{ Local = "css/consulta-planos.css";          Remote = "css/consulta-planos.css";          Tmp = "mxli_consulta_planos.css" },
    @{ Local = "index.php";                        Remote = "index.php";                        Tmp = "mxli_index.php" },
    @{ Local = "scripts/migrate_planos.php";       Remote = "scripts/migrate_planos.php";       Tmp = "mxli_migrate_planos.php" },
    @{ Local = "scripts/index_planos.ps1";         Remote = "scripts/index_planos.ps1";         Tmp = "mxli_index_planos.ps1" }
)

Write-Host "==> Verificando archivos locales..." -ForegroundColor Cyan
foreach ($f in $files) {
    $path = Join-Path $Root $f.Local
    if (-not (Test-Path $path)) { throw "Archivo faltante: $path" }
    Write-Host "  OK $($f.Local)"
}

$indexDir = Join-Path $Root "data\planos"
if (-not (Test-Path (Join-Path $indexDir "SP.json"))) {
    throw "Falta data/planos/SP.json. Ejecute scripts/index_planos.ps1"
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

$zip = Join-Path $env:TEMP "mxli_planos_index.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $indexDir "*.json") -DestinationPath $zip -Force
Write-Host "  scp data/planos (zip) -> /tmp/mxli_planos_index.zip"
& scp -P $Port $zip "${Server}:/tmp/mxli_planos_index.zip"
if ($LASTEXITCODE -ne 0) { throw "scp fallo para el indice de planos" }

$filesDir = Join-Path $indexDir "files"
$hasPlanosFiles = Test-Path $filesDir
if ($hasPlanosFiles) {
    $filesZip = Join-Path $env:TEMP "mxli_planos_files.zip"
    if (Test-Path $filesZip) { Remove-Item $filesZip -Force }
    Compress-Archive -Path (Join-Path $filesDir "*") -DestinationPath $filesZip -Force
    Write-Host "  scp data/planos/files (zip) -> /tmp/mxli_planos_files.zip"
    & scp -P $Port $filesZip "${Server}:/tmp/mxli_planos_files.zip"
    if ($LASTEXITCODE -ne 0) { throw "scp fallo para los archivos de planos" }
}

$cadDir = Join-Path $Root "js\cad"
if (-not (Test-Path (Join-Path $cadDir "wasm\libredwg-web.wasm"))) {
    throw "Falta js/cad/wasm/libredwg-web.wasm (motor de previsualizacion DWG)"
}
$cadZip = Join-Path $env:TEMP "mxli_cad.zip"
if (Test-Path $cadZip) { Remove-Item $cadZip -Force }
Compress-Archive -Path $cadDir -DestinationPath $cadZip -Force
Write-Host "  scp js/cad (zip) -> /tmp/mxli_cad.zip"
& scp -P $Port $cadZip "${Server}:/tmp/mxli_cad.zip"
if ($LASTEXITCODE -ne 0) { throw "scp fallo para el visor CAD" }

if ($UseTmp) {
    Write-Host "==> Copiando con sudo en el servidor..." -ForegroundColor Cyan
    $copies = ($files | ForEach-Object {
        "sudo mkdir -p $Remote/$(Split-Path $_.Remote -Parent); sudo cp /tmp/$($_.Tmp) $Remote/$($_.Remote)"
    }) -join "; "
    $copies += "; sudo mkdir -p $Remote/data/planos; sudo unzip -o /tmp/mxli_planos_index.zip -d $Remote/data/planos; sudo chown -R www-data:www-data $Remote/data/planos"
    if ($hasPlanosFiles) {
        $copies += "; sudo mkdir -p $Remote/data/planos/files; sudo unzip -o /tmp/mxli_planos_files.zip -d $Remote/data/planos/files; sudo chown -R www-data:www-data $Remote/data/planos/files"
    }
    $copies += "; sudo mkdir -p $Remote/js; sudo unzip -o /tmp/mxli_cad.zip -d $Remote/js; sudo chown -R www-data:www-data $Remote/js/cad"
    & ssh -p $Port $Server $copies
    if ($LASTEXITCODE -ne 0) { throw "sudo cp / unzip remoto fallo" }
}

Write-Host "==> Ejecutando migracion de permisos..." -ForegroundColor Cyan
& ssh -p $Port $Server "sudo php $Remote/scripts/migrate_planos.php"
if ($LASTEXITCODE -ne 0) {
    Write-Host "WARN: migrate_planos.php no se ejecuto. Corralo en el servidor." -ForegroundColor Yellow
}

Write-Host "==> Listo. Recargue https://localizacion.geomexicali.info (Ctrl+F5)." -ForegroundColor Green
Write-Host "    Busque SP: deben aparecer los planos de San Pedro Residencial." -ForegroundColor Green
