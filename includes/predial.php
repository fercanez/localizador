<?php
declare(strict_types=1);

/**
 * Consulta en vivo del adeudo predial en el portal del Ayuntamiento de Mexicali.
 *
 * Misma fuente que SGC-Web (predial_mexicali.py):
 *   POST https://www.mexicali.gob.mx/portalmexicali/predial/presupuesto
 *   Body: claveCatastral=<CLAVE>
 *
 * Regla:
 *   total_a_pagar > 0            → con_adeudo (rojo, predial)
 *   multa de aseo público        → adeudo_aseo (naranja)
 *   total_a_pagar = 0 / sin deuda → sin_adeudo (verde)
 */

const MXLI_PREDIAL_URL = "https://www.mexicali.gob.mx/portalmexicali/predial/presupuesto";
const MXLI_PREDIAL_CACHE_TTL = 300;

function mxli_predial_normalize_clave(string $clave): string
{
    return strtoupper(preg_replace("/\s+/", "", $clave) ?? "");
}

function mxli_predial_parse_money(string $value): float
{
    $value = trim($value);
    $value = str_replace(["$", " ", "\xc2\xa0"], "", $value);
    $value = str_replace(",", "", $value);
    if ($value === "" || !is_numeric($value)) {
        return 0.0;
    }
    return (float)$value;
}

function mxli_predial_fold(string $value): string
{
    $value = function_exists("mb_strtolower")
        ? mb_strtolower($value, "UTF-8")
        : strtolower($value);
    if (!is_string($value)) {
        $value = strtolower((string)$value);
    }
    $from = ["á", "é", "í", "ó", "ú", "ü", "ñ"];
    $to = ["a", "e", "i", "o", "u", "u", "n"];
    return str_replace($from, $to, $value);
}

function mxli_predial_label_match(string $haystack, string $label): bool
{
    return str_contains(mxli_predial_fold($haystack), mxli_predial_fold($label));
}

/**
 * @return array<string, mixed>
 */
function mxli_predial_empty_result(string $clave): array
{
    return [
        "clave_catastral" => $clave,
        "tiene_adeudo" => false,
        "tiene_adeudo_aseo" => false,
        "estatus_consulta" => "no_encontrado",
        "periodo" => null,
        "subtotal_importes" => 0.0,
        "sobretasa_seguridad_publica" => 0.0,
        "fomento_deportivo" => 0.0,
        "rezago_fomento_deportivo" => 0.0,
        "servicio_alumbrado" => 0.0,
        "recargos" => 0.0,
        "multas" => 0.0,
        "gastos_ejecucion" => 0.0,
        "descuentos" => 0.0,
        "donativo_cruz_roja" => 0.0,
        "donativo_bomberos" => 0.0,
        "total_a_pagar" => 0.0,
        "total_aseo" => 0.0,
        "consultado_en" => gmdate("c"),
        "fuente" => "portal_mexicali",
    ];
}

function mxli_predial_cache_path(string $clave): string
{
    $dir = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . "mxli_predial";
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . DIRECTORY_SEPARATOR . "v6-" . hash("sha256", $clave) . ".json";
}

/**
 * @return array<string, mixed>|null
 */
function mxli_predial_cache_get(string $clave): ?array
{
    $path = mxli_predial_cache_path($clave);
    if (!is_file($path)) {
        return null;
    }
    $mtime = @filemtime($path);
    if ($mtime === false || (time() - $mtime) > MXLI_PREDIAL_CACHE_TTL) {
        @unlink($path);
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === "") {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * @param array<string, mixed> $data
 */
function mxli_predial_cache_put(string $clave, array $data): void
{
    $estatus = (string)($data["estatus_consulta"] ?? "");
    if ($estatus === "error" || $estatus === "no_encontrado" || empty($data["parse_ok"])) {
        return;
    }
    @file_put_contents(
        mxli_predial_cache_path($clave),
        json_encode($data, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function mxli_predial_decode_body(string $html): string
{
    if ($html !== "" && strncmp($html, "\x1f\x8b", 2) === 0 && function_exists("gzdecode")) {
        $decoded = @gzdecode($html);
        if (is_string($decoded) && $decoded !== "") {
            return $decoded;
        }
    }
    return $html;
}

function mxli_predial_plain_text(string $html): string
{
    $html = str_replace(["\xc2\xa0", "&nbsp;", "&#160;", "&#36;"], [" ", " ", " ", "$"], $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, "UTF-8");
    $text = preg_replace("/\s+/", " ", $text) ?? $text;
    return mxli_predial_fold(trim($text));
}

function mxli_predial_first_table(string $html): string
{
    if (preg_match("/<table\\b[^>]*>.*?<\\/table>/is", $html, $m)) {
        return $m[0];
    }
    return substr($html, 0, 60000);
}

function mxli_predial_find_total(string $html): ?float
{
    $chunk = mxli_predial_first_table($html);
    $patterns = [
        "/Total a pagar.{0,2500}?\\$[\\s]*([0-9]{1,3}(?:,[0-9]{3})*\\.[0-9]{2})/is",
        "/Total a pagar.{0,2500}?([0-9]{1,3}(?:,[0-9]{3})*\\.[0-9]{2})/is",
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $chunk, $m)) {
            return mxli_predial_parse_money($m[1]);
        }
    }
    $plain = mxli_predial_plain_text($chunk);
    if (preg_match("/total a pagar.{0,80}?([0-9]{1,3}(?:,[0-9]{3})*\\.[0-9]{2})/i", $plain, $m)) {
        return mxli_predial_parse_money($m[1]);
    }
    return null;
}

function mxli_predial_find_aseo(string $html): ?float
{
    $plain = mxli_predial_plain_text($html);
    if (!str_contains($plain, "aseo publico")) {
        return null;
    }
    if (preg_match("/aseo publico.{0,60}?\\$?\\s*([0-9]{1,3}(?:,[0-9]{3})*\\.[0-9]{2})/i", $plain, $m)) {
        return mxli_predial_parse_money($m[1]);
    }
    if (preg_match("/\\$\\s*([0-9]{1,3}(?:,[0-9]{3})*\\.[0-9]{2})/", $html, $m)) {
        return mxli_predial_parse_money($m[1]);
    }
    return 0.0;
}

/**
 * @return array{body:string,status:int}
 */
function mxli_predial_http_post(string $clave, int $timeout = 30): array
{
    $body = http_build_query(["claveCatastral" => $clave]);
    $headerLines = [
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Origin: https://www.mexicali.gob.mx",
        "Referer: https://www.mexicali.gob.mx/portalmexicali/predial",
        "Accept: text/html, */*; q=0.01",
        "User-Agent: Mozilla/5.0",
    ];

    if (function_exists("curl_init")) {
        $ch = curl_init(MXLI_PREDIAL_URL);
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        curl_setopt_array($ch, $opts);
        $html = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (is_string($html) && $html !== "") {
            return ["body" => mxli_predial_decode_body($html), "status" => $status > 0 ? $status : 200];
        }
        $curlFail = $err !== "" ? $err : "cURL vacío (HTTP {$status}).";
    } else {
        $curlFail = "";
    }

    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => implode("\r\n", $headerLines) . "\r\n",
            "content" => $body,
            "timeout" => $timeout,
            "ignore_errors" => true,
        ],
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);
    $html = @file_get_contents(MXLI_PREDIAL_URL, false, $context);
    $statusLine = is_array($http_response_header ?? null) ? (string)($http_response_header[0] ?? "") : "";
    preg_match("/\s(\d{3})\s/", $statusLine, $m);
    $status = isset($m[1]) ? (int)$m[1] : 0;
    if (is_string($html) && $html !== "") {
        return ["body" => mxli_predial_decode_body($html), "status" => $status > 0 ? $status : 200];
    }

    $detail = $curlFail !== "" ? $curlFail : $statusLine;
    if ($detail === "") {
        $detail = "file_get_contents vacío. Revise allow_url_fopen / openssl.";
    }
    throw new RuntimeException("No se pudo consultar el portal predial de Mexicali. " . $detail);
}

/**
 * @return array<string, mixed>
 */
function mxli_predial_parse_html(string $html, string $clave): array
{
    $html = mxli_predial_decode_body($html);
    $result = mxli_predial_empty_result($clave);
    $result["mensaje"] = "No se pudo leer el total a pagar del portal.";

    $plain = mxli_predial_plain_text($html);
    $aseo = mxli_predial_find_aseo($html);
    $total = mxli_predial_find_total($html);

    $alertaSinAdeudo =
        str_contains($plain, "la clave no tiene adeudo")
        || (str_contains($plain, "no tiene adeudo") && !str_contains($plain, "total a pagar") && $aseo === null);

    if ($total !== null && $total > 0) {
        $result["total_a_pagar"] = $total;
        $result["tiene_adeudo"] = true;
        $result["estatus_consulta"] = "con_adeudo";
        $result["mensaje"] = "Total a pagar: " . number_format($total, 2, ".", ",");
        if ($aseo !== null) {
            $result["tiene_adeudo_aseo"] = true;
            $result["total_aseo"] = $aseo;
            $result["mensaje"] .= " · Aseo público: " . number_format($aseo, 2, ".", ",");
        }
        $result["parse_ok"] = true;
        return $result;
    }

    if ($aseo !== null) {
        $result["tiene_adeudo"] = false;
        $result["tiene_adeudo_aseo"] = true;
        $result["total_aseo"] = $aseo;
        $result["estatus_consulta"] = "adeudo_aseo";
        $result["mensaje"] = "Adeudo en aseo público: " . number_format($aseo, 2, ".", ",");
        $result["parse_ok"] = true;
        return $result;
    }

    if ($alertaSinAdeudo || ($total !== null && $total <= 0)) {
        $result["tiene_adeudo"] = false;
        $result["estatus_consulta"] = "sin_adeudo";
        $result["mensaje"] = "La clave no tiene adeudo.";
        $result["parse_ok"] = true;
        return $result;
    }

    $result["estatus_consulta"] = "no_encontrado";
    $result["mensaje"] = "El portal no devolvió tabla de adeudo.";
    return $result;
}

/**
 * @return array<string, mixed>
 */
function mxli_predial_consultar(string $claveCatastral, bool $useCache = true): array
{
    $clave = mxli_predial_normalize_clave($claveCatastral);
    if ($clave === "" || !preg_match("/^[A-Z0-9]{6,32}$/", $clave)) {
        $result = mxli_predial_empty_result($clave);
        $result["estatus_consulta"] = "error";
        $result["mensaje"] = "Clave catastral inválida.";
        return $result;
    }

    if ($useCache) {
        $cached = mxli_predial_cache_get($clave);
        if (is_array($cached)) {
            $cached["clave_catastral"] = $clave;
            return $cached;
        }
    }

    $lastError = null;
    $result = mxli_predial_empty_result($clave);
    @set_time_limit(45);
    try {
        $http = mxli_predial_http_post($clave);
        if ($http["status"] < 200 || $http["status"] >= 300) {
            throw new RuntimeException("El portal predial respondió HTTP " . $http["status"] . ".");
        }
        $result = mxli_predial_parse_html($http["body"], $clave);
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        $result = mxli_predial_empty_result($clave);
        $result["estatus_consulta"] = "error";
        $result["mensaje"] = "No se pudo consultar el portal de predial: " . $lastError;
    }

    mxli_predial_cache_put($clave, $result);
    return $result;
}

/**
 * @param list<string> $claves
 * @return list<array<string, mixed>>
 */
function mxli_predial_consultar_varias(array $claves, int $limit = 80): array
{
    $out = [];
    $seen = [];
    foreach ($claves as $raw) {
        $clave = mxli_predial_normalize_clave((string)$raw);
        if ($clave === "" || isset($seen[$clave]) || !preg_match("/^[A-Z0-9]{6,32}$/", $clave)) {
            continue;
        }
        $seen[$clave] = true;
        $out[] = $clave;
        if (count($out) >= $limit) {
            break;
        }
    }

    $resultados = [];
    $pending = [];
    foreach ($out as $clave) {
        $cached = mxli_predial_cache_get($clave);
        if (is_array($cached)) {
            $cached["clave_catastral"] = $clave;
            $resultados[$clave] = $cached;
        } else {
            $pending[] = $clave;
        }
    }

    if ($pending !== []) {
        if (function_exists("curl_multi_init")) {
            foreach (array_chunk($pending, 3) as $chunk) {
                foreach (mxli_predial_http_post_multi($chunk) as $clave => $parsed) {
                    $resultados[$clave] = $parsed;
                }
            }
            foreach ($pending as $clave) {
                $parsed = $resultados[$clave] ?? null;
                if (is_array($parsed) && !empty($parsed["parse_ok"])) {
                    continue;
                }
                $resultados[$clave] = mxli_predial_consultar($clave, false);
            }
        } else {
            foreach ($pending as $clave) {
                try {
                    $resultados[$clave] = mxli_predial_consultar($clave, false);
                } catch (Throwable $e) {
                    $err = mxli_predial_empty_result($clave);
                    $err["estatus_consulta"] = "error";
                    $err["mensaje"] = "No se pudo consultar el portal de predial: " . $e->getMessage();
                    $resultados[$clave] = $err;
                }
            }
        }
    }

    $ordered = [];
    foreach ($out as $clave) {
        if (isset($resultados[$clave])) {
            $ordered[] = $resultados[$clave];
        }
    }
    return $ordered;
}

/**
 * @param list<string> $claves
 * @return array<string, array<string, mixed>>
 */
function mxli_predial_http_post_multi(array $claves, int $timeout = 18): array
{
    $mh = curl_multi_init();
    $handles = [];
    $headers = [
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Origin: https://www.mexicali.gob.mx",
        "Referer: https://www.mexicali.gob.mx/portalmexicali/predial",
        "Accept: text/html, */*; q=0.01",
        "User-Agent: Mozilla/5.0",
    ];

    foreach ($claves as $clave) {
        $ch = curl_init(MXLI_PREDIAL_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(["claveCatastral" => $clave], "", "&", PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$clave] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $clave => $ch) {
        $html = curl_multi_getcontent($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if (!is_string($html) || $html === "" || $httpStatus < 200 || $httpStatus >= 300) {
            $fail = mxli_predial_empty_result($clave);
            $fail["estatus_consulta"] = "error";
            $fail["mensaje"] = $err !== "" ? $err : ("El portal predial respondió HTTP " . $httpStatus . ".");
            $out[$clave] = $fail;
            continue;
        }
        $parsed = mxli_predial_parse_html(mxli_predial_decode_body($html), $clave);
        mxli_predial_cache_put($clave, $parsed);
        $out[$clave] = $parsed;
    }
    curl_multi_close($mh);
    return $out;
}
