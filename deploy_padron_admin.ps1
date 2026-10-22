# Deploy pestaña Admin → Actualizar padrón
$ErrorActionPreference = "Stop"
$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$Server = "${ServerUser}@${ServerHost}"
$Port = "3839"
$Remote = "/var/www/html/localizacion"
$Root = $PSScriptRoot
$UseTmp = $ServerUser -ne "root"

$files = @(
    @{ Local = "includes/padron-import.php";     Remote = "includes/padron-import.php";     Tmp = "mxli_padron_import_inc.php" },
    @{ Local = "api/admin/padron-import.php";    Remote = "api/admin/padron-import.php";    Tmp = "mxli_padron_import_api.php" },
    @{ Local = "admin/usuarios.php";             Remote = "admin/usuarios.php";             Tmp = "mxli_admin_usuarios.php" },
    @{ Local = "js/admin-users.js";              Remote = "js/admin-users.js";              Tmp = "mxli_admin_users.js" },
    @{ Local = "js/admin-padron.js";             Remote = "js/admin-padron.js";             Tmp = "mxli_admin_padron.js" },
    @{ Local = "css/admin-users.css";            Remote = "css/admin-users.css";            Tmp = "mxli_admin_users.css" },
    @{ Local = "sql/grant_padron_loader.sql";    Remote = "sql/grant_padron_loader.sql";    Tmp = "mxli_grant_padron_loader.sql" },
    @{ Local = "sql/app_padron_import_meta.sql"; Remote = "sql/app_padron_import_meta.sql"; Tmp = "mxli_app_padron_meta.sql" }
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

Write-Host @"

==> En el servidor, aplicar GRANT (una vez):

ssh -p $Port $Server
sudo -u postgres psql -d geonode_data -v ON_ERROR_STOP=1 -f $Remote/sql/grant_padron_loader.sql

# Opcional: subir límites PHP para CSV ~80MB
#   upload_max_filesize = 100M
#   post_max_size = 110M
#   max_execution_time = 0

"@ -ForegroundColor Yellow
Write-Host "==> Listo." -ForegroundColor Green
