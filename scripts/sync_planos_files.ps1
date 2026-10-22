# Copia planos de \\SCATASTRO\GROMA\{HOMO} a data/planos/files con nombres ASCII (hash)
# para evitar problemas de acentos en Linux, y arma el zip para el servidor.
param(
    [string]$Homoclave = "SP"
)

$ErrorActionPreference = "Stop"
$Homo = $Homoclave.Trim().ToUpperInvariant()
if ($Homo -notmatch '^[A-Z][A-Z0-9]{1,3}$') {
    throw "Homoclave invalida: $Homoclave"
}

$RootShare = if ($env:MXLI_PLANOS_SHARE) { $env:MXLI_PLANOS_SHARE } else { "\\SCATASTRO\GROMA" }
$Src = Join-Path $RootShare $Homo
$Project = Split-Path $PSScriptRoot -Parent
$Dest = Join-Path $Project "data\planos\files\$Homo"

if (-not (Test-Path -LiteralPath $Src)) {
    throw "No se alcanza $Src. Debe ejecutarse en una PC que vea el share GROMA."
}

Write-Host "==> Copiando $Src -> $Dest (nombres hash)"
if (Test-Path -LiteralPath $Dest) {
    Remove-Item -LiteralPath $Dest -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $Dest | Out-Null

$sha = [System.Security.Cryptography.SHA1]::Create()
$copied = 0
$bytes = [int64]0
Get-ChildItem -LiteralPath $Src -Recurse -File -ErrorAction Stop | ForEach-Object {
    $fromRoot = $_.FullName.Substring($RootShare.Length).TrimStart("\").Replace("\", "/")
    $ext = $_.Extension.TrimStart(".").ToLowerInvariant()
    if ($ext -notin @("pdf", "dwg", "dxf", "jpg", "jpeg")) { return }
    $relNorm = $fromRoot.ToLower()
    $hash = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($relNorm)))).Replace("-", "").ToLowerInvariant()
    $out = Join-Path $Dest ($hash + "." + $ext)
    Copy-Item -LiteralPath $_.FullName -Destination $out -Force
    $copied++
    $bytes += $_.Length
}
Write-Host ("    {0} archivos, {1:N1} MB" -f $copied, ($bytes / 1MB))
if ($copied -eq 0) { throw "No se copio ningun archivo de $Src" }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = Join-Path $env:TEMP "mxli_planos_files_$Homo.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
$filesRoot = Join-Path $Project "data\planos\files"
$utf8 = New-Object System.Text.UTF8Encoding $false
[IO.Compression.ZipFile]::CreateFromDirectory($filesRoot, $zip, [IO.Compression.CompressionLevel]::Fastest, $false, $utf8)
Write-Host "    Zip: $zip ($([math]::Round((Get-Item $zip).Length / 1MB, 1)) MB)"

$ServerUser = if ($env:MXLI_SCP_USER) { $env:MXLI_SCP_USER } else { "canez" }
$ServerHost = if ($env:MXLI_SCP_HOST) { $env:MXLI_SCP_HOST } else { "5.78.201.11" }
$phpLocal = Join-Path $Project "includes\planos.php"
Write-Host ""
Write-Host "Ahora suba PHP + zip (le pedira la clave SSH):"
Write-Host ("scp -P 3839 `"$phpLocal`" {0}@{1}:/tmp/mxli_planos.php" -f $ServerUser, $ServerHost)
Write-Host ("scp -P 3839 `"$zip`" {0}@{1}:/tmp/mxli_planos_files.zip" -f $ServerUser, $ServerHost)
Write-Host ("ssh -p 3839 {0}@{1} `"sudo cp /tmp/mxli_planos.php /var/www/html/localizacion/includes/planos.php; sudo mkdir -p /var/www/html/localizacion/data/planos/files; sudo unzip -o /tmp/mxli_planos_files.zip -d /var/www/html/localizacion/data/planos/files; sudo chown -R www-data:www-data /var/www/html/localizacion/includes/planos.php /var/www/html/localizacion/data/planos/files`"" -f $ServerUser, $ServerHost)
