# Deploy padron field-mapping fix (canez@ via /tmp + sudo cp, or root@ directo)
$ErrorActionPreference = "Stop"
$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$Server = "${ServerUser}@${ServerHost}"
$Port = "3839"
$Remote = "/var/www/html/localizacion"
$Root = $PSScriptRoot
$UseTmp = $ServerUser -ne "root"

$files = @(
    @{ Local = "includes/padron.php";            Remote = "includes/padron.php";            Tmp = "mxli_padron.php" },
    @{ Local = "api/padron.php";                 Remote = "api/padron.php";                 Tmp = "mxli_api_padron.php" },
    @{ Local = "js/padronPredio.js";             Remote = "js/padronPredio.js";             Tmp = "mxli_padronPredio.js" },
    @{ Local = "scripts/test_padron_lookup.php"; Remote = "scripts/test_padron_lookup.php"; Tmp = "mxli_test_padron_lookup.php" }
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
        "sudo cp /tmp/$($_.Tmp) $Remote/$($_.Remote)"
    }) -join "; "
    & ssh -p $Port $Server $copies
    if ($LASTEXITCODE -ne 0) { throw "sudo cp remoto fallo" }
}

Write-Host "==> Probando lookup SP001001..." -ForegroundColor Cyan
& ssh -p $Port $Server "cd $Remote && php scripts/test_padron_lookup.php SP001001"
Write-Host "==> Listo." -ForegroundColor Green
