# Genera data/planos/{HOMO}.json desde \\SCATASTRO\GROMA
$ErrorActionPreference = "Stop"
$RootShare = if ($env:MXLI_PLANOS_SHARE) { $env:MXLI_PLANOS_SHARE } else { "\\SCATASTRO\GROMA" }
$OutDir = Join-Path (Split-Path $PSScriptRoot -Parent) "data\planos"
$ExtOk = @{ ".pdf" = 1; ".dwg" = 1; ".dxf" = 1; ".jpg" = 1; ".jpeg" = 1 }

if (-not (Test-Path -LiteralPath $RootShare)) {
    throw "No se alcanza $RootShare"
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$groups = @{}

Get-ChildItem -LiteralPath $RootShare -Directory -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.Name -notmatch '^([A-Za-z0-9]{2,4})(\b|$)') { return }
    $homo = $Matches[1].ToUpperInvariant()
    if ($_.Name.Length -gt 4 -and $_.Name -notmatch '^[A-Za-z0-9]{2,4}[\s-]') { return }
    $files = @(Get-ChildItem -LiteralPath $_.FullName -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $ExtOk.ContainsKey($_.Extension.ToLowerInvariant()) })
    if ($files.Count -eq 0) { return }
    if (-not $groups.ContainsKey($homo)) {
        $groups[$homo] = New-Object System.Collections.Generic.List[object]
    }
    foreach ($f in $files) {
        $fromRoot = $f.FullName.Substring($RootShare.Length).TrimStart("\").Replace("\", "/")
        $groups[$homo].Add([ordered]@{
            name  = $f.Name
            rel   = $fromRoot
            ext   = $f.Extension.TrimStart(".").ToLowerInvariant()
            size  = [int64]$f.Length
            mtime = [int][DateTimeOffset]::new($f.LastWriteTimeUtc).ToUnixTimeSeconds()
        })
    }
}

foreach ($k in ($groups.Keys | Sort-Object)) {
    $arr = @($groups[$k] | Sort-Object name)
    $json = $arr | ConvertTo-Json -Compress -Depth 4
    if ($arr.Count -eq 1) { $json = "[$json]" }
    $dest = Join-Path $OutDir ($k + ".json")
    [System.IO.File]::WriteAllText($dest, $json, [System.Text.UTF8Encoding]::new($false))
}

Write-Host ("Listo: {0} homoclaves en {1}" -f $groups.Count, $OutDir)
