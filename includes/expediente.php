<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function mxli_expediente_external_url(string $clave): string
{
    $clave = strtoupper(preg_replace('/\s+/', '', $clave) ?? "");
    return "https://www.mexicali.gob.mx/webpub/consultacatastro/documentacion.aspx?" . rawurlencode($clave);
}

function mxli_normalize_expediente_clave(string $clave): string
{
    $clave = strtoupper(preg_replace('/\s+/', '', $clave) ?? "");
    if ($clave === "" || !preg_match('/^[A-Z0-9]{4,32}$/', $clave)) {
        return "";
    }
    return $clave;
}

/**
 * Flags de marca de agua / descarga / impresión (estilo sqsistema).
 *
 * - activa: mostrar marca de agua
 * - puedeQuitar: usuario puede desactivar marca (tiene map.expediente.nowatermark)
 * - forzada: marca siempre on (no tiene nowatermark)
 * - puedeDescargar / puedeImprimir
 */
function mxli_expediente_flags(?array $user = null): array
{
    $user = $user ?? mxli_current_user();
    $canView = mxli_can("map.expediente", $user);
    $canClean = mxli_can("map.expediente.nowatermark", $user);
    $canDownload = mxli_can("map.expediente.download", $user);
    $canPrint = mxli_can("map.expediente.print", $user);

    $forzada = $canView && !$canClean;
    $off = !empty($_SESSION["mxli_expediente_wm_off"]);
    $activa = $canView && ($forzada || !$off);

    return [
        "puedeVer" => $canView,
        "modo" => !$canView ? 0 : ($forzada ? 2 : 1),
        "activa" => $activa,
        "puedeQuitar" => $canView && $canClean,
        "forzada" => $forzada,
        "puedeDescargar" => $canView && $canDownload,
        "puedeImprimir" => $canView && $canPrint,
    ];
}

function mxli_expediente_fetch_pdf(string $clave): ?string
{
    $clave = mxli_normalize_expediente_clave($clave);
    if ($clave === "") {
        return null;
    }

    $url = mxli_expediente_external_url($clave);
    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => 45,
            "ignore_errors" => true,
            "header" => "Accept: application/pdf,*/*\r\nUser-Agent: Geomexicali-Localizacion/1.0\r\n",
        ],
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false || $body === "") {
        return null;
    }

    // PDF magic or loose check (IIS sometimes wraps)
    $trim = ltrim($body);
    if (str_starts_with($trim, "%PDF")) {
        return $body;
    }
    // Some endpoints may return HTML error
    if (stripos($trim, "<html") !== false || stripos($trim, "<!DOCTYPE") !== false) {
        return null;
    }
    // Accept if Content-Type suggested PDF via headers
    $statusOk = false;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('/^HTTP\/\S+\s+200/i', $line)) {
            $statusOk = true;
        }
        if (stripos($line, "Content-Type:") === 0 && stripos($line, "pdf") !== false) {
            return $body;
        }
    }
    return $statusOk && strlen($body) > 500 ? $body : null;
}
