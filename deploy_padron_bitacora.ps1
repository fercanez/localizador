# Deploy bitácora de consultas al padrón
$ErrorActionPreference = "Stop"
$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$Server = "${ServerUser}@${ServerHost}"
$Port = "3839"
$Remote = "/var/www/html/localizacion"
$Root = $PSScriptRoot
$UseTmp = $ServerUser -ne "root"

$files = @(
    @{ Local = "includes/padron-log.php";           Remote = "includes/padron-log.php";           Tmp = "mxli_padron_log.php" },
    @{ Local = "api/padron.php";                    Remote = "api/padron.php";                    Tmp = "mxli_api_padron.php" },
    @{ Local = "api/admin/padron-bitacora.php";     Remote = "api/admin/padron-bitacora.php";     Tmp = "mxli_api_padron_bitacora.php" },
    @{ Local = "admin/usuarios.php";                Remote = "admin/usuarios.php";                Tmp = "mxli_admin_usuarios.php" },
    @{ Local = "js/admin-users.js";                 Remote = "js/admin-users.js";                 Tmp = "mxli_admin_users.js" },
    @{ Local = "js/admin-padron-bitacora.js";       Remote = "js/admin-padron-bitacora.js";       Tmp = "mxli_admin_padron_bitacora.js" },
    @{ Local = "css/admin-users.css";               Remote = "css/admin-users.css";               Tmp = "mxli_admin_users.css" },
    @{ Local = "sql/app_padron_consultas.sql";      Remote = "sql/app_padron_consultas.sql";      Tmp = "mxli_app_padron_consultas.sql" },
    @{ Local = "scripts/migrate_padron_bitacora.php"; Remote = "scripts/migrate_padron_bitacora.php"; Tmp = "mxli_migrate_padron_bitacora.php" }
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
        $parent = ($_.Remote -replace '\\', '/') -replace '/[^/]+$', ''
        if (-not $parent) { $parent = "." }
        "sudo mkdir -p $Remote/$parent; sudo cp /tmp/$($_.Tmp) $Remote/$($_.Remote)"
    }) -join "; "
    & ssh -p $Port $Server $copies
    if ($LASTEXITCODE -ne 0) { throw "sudo cp remoto fallo" }
}

Write-Host @"

==> En el servidor, crear la tabla (una vez):

ssh -p $Port $Server
cd $Remote
sudo php scripts/migrate_padron_bitacora.php

# La tabla también se crea sola en la primera consulta al padrón.

"@ -ForegroundColor Yellow
Write-Host "==> Listo. Recargue Usuarios y roles → pestaña Bitácora padrón." -ForegroundColor Green
