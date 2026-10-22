<?php
declare(strict_types=1);

/**
 * Consulta de deslindes en el portal de padrón catastral del Ayuntamiento.
 *
 * Fuente: https://www.mexicali.gob.mx/webpub/consultacatastro/
 * Pantalla Deslindes → No. de Trámite (folio).
 * Columnas: FOLIO, CLAVECATASTRAL, ESTATUS, FECHA, TRAMITADOR, PERITO, FECHA_TERMINO.
 */

function mxli_deslindes_portal_url(): string
{
    $url = trim(mxli_config(
        "catastro_portal_url",
        "https://www.mexicali.gob.mx/webpub/consultacatastro/"
    ));
    if ($url === "") {
        $url = "https://www.mexicali.gob.mx/webpub/consultacatastro/";
    }
    return rtrim($url, "/") . "/";
}

function mxli_deslindes_cfg(string $key, string $default = ""): string
{
    $aliases = [
        "catastro_portal_user" => ["deslindes_portal_user", "catastro_portal_user"],
        "catastro_portal_pass" => ["deslindes_portal_pass", "catastro_portal_pass"],
        "catastro_portal_url" => ["deslindes_portal_url", "catastro_portal_url"],
        "deslindes_portal_user" => ["deslindes_portal_user", "catastro_portal_user"],
        "deslindes_portal_pass" => ["deslindes_portal_pass", "catastro_portal_pass"],
        "deslindes_portal_url" => ["deslindes_portal_url", "catastro_portal_url"],
    ];
    $keys = $aliases[$key] ?? [$key];
    foreach ($keys as $name) {
        $value = mxli_config($name, "");
        if ($value !== "") {
            return $value;
        }
    }
    return $default;
}

function mxli_deslindes_normalize_folio(string $raw): string
{
    $raw = trim($raw);
    $raw = preg_replace("/\s+/", "", $raw) ?? "";
    return $raw;
}

function mxli_deslindes_valid_folio(string $folio): bool
{
    return (bool)preg_match('/^[0-9]{4,12}$/', $folio);
}

function mxli_deslindes_fold(string $value): string
{
    $value = function_exists("mb_strtolower")
        ? mb_strtolower($value, "UTF-8")
        : strtolower($value);
    $from = ["á", "é", "í", "ó", "ú", "ü", "ñ", "Á", "É", "Í", "Ó", "Ú", "Ü", "Ñ"];
    $to = ["a", "e", "i", "o", "u", "u", "n", "a", "e", "i", "o", "u", "u", "n"];
    return str_replace($from, $to, $value);
}

function mxli_deslindes_decode_body(string $html): string
{
    if ($html === "") {
        return "";
    }
    if (preg_match('/charset\s*=\s*["\']?iso-8859-1/i', $html)
        || preg_match('/charset\s*=\s*["\']?windows-1252/i', $html)
    ) {
        $converted = @mb_convert_encoding($html, "UTF-8", "ISO-8859-1");
        return is_string($converted) ? $converted : $html;
    }
    if (!mb_check_encoding($html, "UTF-8")) {
        $converted = @mb_convert_encoding($html, "UTF-8", "ISO-8859-1");
        return is_string($converted) ? $converted : $html;
    }
    return $html;
}

function mxli_deslindes_cookie_file(): string
{
    $dir = rtrim(sys_get_temp_dir(), "/\\");
    return $dir . DIRECTORY_SEPARATOR . "mxli_catastro_deslindes.cookies.json";
}

/**
 * @return array<string,string>
 */
function mxli_deslindes_cookies_load(): array
{
    $path = mxli_deslindes_cookie_file();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    $data = json_decode(is_string($raw) ? $raw : "", true);
    return is_array($data) ? $data : [];
}

/**
 * @param array<string,string> $cookies
 */
function mxli_deslindes_cookies_save(array $cookies): void
{
    @file_put_contents(mxli_deslindes_cookie_file(), json_encode($cookies, JSON_UNESCAPED_SLASHES));
}

/**
 * @param array<string,string> $cookies
 * @param list<string> $headers
 * @return array<string,string>
 */
function mxli_deslindes_cookies_merge(array $cookies, array $headers): array
{
    foreach ($headers as $line) {
        if (stripos($line, "Set-Cookie:") !== 0) {
            continue;
        }
        $part = trim(substr($line, 11));
        $nv = explode(";", $part, 2)[0];
        $eq = strpos($nv, "=");
        if ($eq === false) {
            continue;
        }
        $name = trim(substr($nv, 0, $eq));
        $value = trim(substr($nv, $eq + 1));
        if ($name !== "") {
            $cookies[$name] = $value;
        }
    }
    return $cookies;
}

/**
 * @param array<string,string> $fields
 * @param array{referer?:string,xhr?:bool} $extra
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http(string $method, string $url, array $fields = [], array $extra = []): array
{
    if (function_exists("curl_init")) {
        return mxli_deslindes_http_curl($method, $url, $fields, $extra);
    }
    return mxli_deslindes_http_stream($method, $url, $fields, $extra);
}

/**
 * @param array<string,string> $fields
 * @param array{referer?:string,xhr?:bool} $extra
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http_curl(string $method, string $url, array $fields = [], array $extra = []): array
{
    $cookie = mxli_deslindes_cookie_file() . ".curl";
    $ch = curl_init($url);
    $referer = (string)($extra["referer"] ?? (mxli_deslindes_portal_url() . "Login.aspx"));
    $headers = [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Origin: https://www.mexicali.gob.mx",
        "Referer: " . $referer,
    ];
    if (!empty($extra["xhr"])) {
        $headers[] = "X-Requested-With: XMLHttpRequest";
        $headers[] = "X-DevExpress-Callback: yes";
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => "",
    ];
    if (strtoupper($method) === "POST") {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
        $headers[] = "Content-Type: application/x-www-form-urlencoded";
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $html = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($html) || $html === "") {
        $detail = $err !== "" ? $err : "HTTP {$status}";
        throw new RuntimeException("No se pudo consultar el portal de Catastro. " . $detail);
    }

    return [
        "body" => mxli_deslindes_decode_body($html),
        "status" => $status > 0 ? $status : 200,
        "url" => $finalUrl,
    ];
}

/**
 * @param array<string,string> $fields
 * @param array{referer?:string,xhr?:bool} $extra
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http_stream(string $method, string $url, array $fields = [], array $extra = []): array
{
    $cookies = mxli_deslindes_cookies_load();
    $current = $url;
    $method = strtoupper($method);
    $body = $method === "POST" ? http_build_query($fields) : "";
    $lastHtml = "";
    $status = 0;
    $referer = (string)($extra["referer"] ?? (mxli_deslindes_portal_url() . "Login.aspx"));

    for ($i = 0; $i < 8; $i++) {
        $headerLines = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Origin: https://www.mexicali.gob.mx",
            "Referer: " . $referer,
        ];
        if (!empty($extra["xhr"])) {
            $headerLines[] = "X-Requested-With: XMLHttpRequest";
            $headerLines[] = "X-DevExpress-Callback: yes";
        }
        if ($cookies !== []) {
            $parts = [];
            foreach ($cookies as $name => $value) {
                $parts[] = $name . "=" . $value;
            }
            $headerLines[] = "Cookie: " . implode("; ", $parts);
        }
        if ($method === "POST") {
            $headerLines[] = "Content-Type: application/x-www-form-urlencoded";
        }
        $context = stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headerLines) . "\r\n",
                "content" => $body,
                "timeout" => 45,
                "ignore_errors" => true,
                "follow_location" => 0,
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ],
        ]);
        $html = @file_get_contents($current, false, $context);
        $respHeaders = is_array($http_response_header ?? null) ? $http_response_header : [];
        $statusLine = (string)($respHeaders[0] ?? "");
        preg_match("/\s(\d{3})\s/", $statusLine, $m);
        $status = isset($m[1]) ? (int)$m[1] : 0;
        $cookies = mxli_deslindes_cookies_merge($cookies, $respHeaders);
        mxli_deslindes_cookies_save($cookies);

        $location = "";
        foreach ($respHeaders as $line) {
            if (stripos($line, "Location:") === 0) {
                $location = trim(substr($line, 9));
                break;
            }
        }
        if ($location !== "" && $status >= 300 && $status < 400) {
            if (!preg_match("#^https?://#i", $location)) {
                $parts = parse_url($current);
                $origin = ($parts["scheme"] ?? "https") . "://" . ($parts["host"] ?? "www.mexicali.gob.mx");
                $location = str_starts_with($location, "/")
                    ? $origin . $location
                    : $origin . "/" . ltrim($location, "/");
            }
            $current = $location;
            $method = "GET";
            $body = "";
            continue;
        }

        if (!is_string($html) || $html === "") {
            throw new RuntimeException("No se pudo consultar el portal de Catastro (HTTP {$status}).");
        }
        $lastHtml = $html;
        break;
    }

    if ($lastHtml === "") {
        throw new RuntimeException("No se pudo consultar el portal de Catastro.");
    }

    return [
        "body" => mxli_deslindes_decode_body($lastHtml),
        "status" => $status > 0 ? $status : 200,
        "url" => $current,
    ];
}

/**
 * @return array<string,string>
 */
function mxli_deslindes_hidden_fields(string $html): array
{
    $fields = [];
    if (!preg_match_all('/<input\b[^>]*>/i', $html, $tags)) {
        return $fields;
    }
    foreach ($tags[0] as $tag) {
        if (!preg_match('/\bname=(["\'])([^"\']+)\1/i', $tag, $nm)) {
            continue;
        }
        $name = html_entity_decode($nm[2], ENT_QUOTES, "UTF-8");
        $value = "";
        if (preg_match('/\bvalue=(["\'])([^"\']*)\1/i', $tag, $vm)) {
            $value = html_entity_decode($vm[2], ENT_QUOTES, "UTF-8");
        }
        $fields[$name] = $value;
    }
    return $fields;
}

function mxli_deslindes_is_login_page(string $html, string $url): bool
{
    if (stripos($url, "Login.aspx") !== false) {
        return true;
    }
    $fold = mxli_deslindes_fold($html);
    return str_contains($fold, 'name="usuario"')
        && str_contains($fold, 'name="contrasena"')
        && str_contains($fold, "entrar");
}

function mxli_deslindes_plain(string $html): string
{
    $text = preg_replace("/<script\b[^>]*>.*?<\/script>/is", " ", $html) ?? $html;
    $text = preg_replace("/<style\b[^>]*>.*?<\/style>/is", " ", $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, "UTF-8");
    return trim(preg_replace("/\s+/", " ", $text) ?? $text);
}

function mxli_deslindes_login(): void
{
    $user = trim(mxli_deslindes_cfg("catastro_portal_user"));
    $pass = mxli_deslindes_cfg("catastro_portal_pass");
    if ($user === "" || $pass === "") {
        throw new RuntimeException(
            "Falta la cuenta del portal de padrón. Defina deslindes_portal_user y deslindes_portal_pass en includes/config.local.php."
        );
    }

    $base = mxli_deslindes_portal_url();
    $loginUrl = $base . "Login.aspx";
    $get = mxli_deslindes_http("GET", $loginUrl);
    $fields = mxli_deslindes_hidden_fields($get["body"]);
    $fields["Usuario"] = $user;
    $fields["Contrasena"] = $pass;
    $fields["__EVENTTARGET"] = "Entrar";
    $fields["__EVENTARGUMENT"] = $fields["__EVENTARGUMENT"] ?? "";
    $fields["Entrar"] = "";
    if (!isset($fields["DXScript"])) {
        $fields["DXScript"] = "1_225,1_164,1_130,1_162,1_170,1_218,1_151";
    }
    if (!isset($fields["DXCss"])) {
        $fields["DXCss"] = "1_32,1_18,1_16,1_17";
    }

    $post = mxli_deslindes_http("POST", $loginUrl, $fields);
    if (mxli_deslindes_is_login_page($post["body"], $post["url"])) {
        $msg = mxli_deslindes_plain($post["body"]);
        if (preg_match("/(usuario|contrase|inv[aá]lid|incorrect)/i", $msg)) {
            throw new RuntimeException("El portal de Catastro rechazó el usuario o la contraseña.");
        }
        throw new RuntimeException("No se pudo iniciar sesión en el portal de padrón catastral.");
    }
}

function mxli_deslindes_find_folio_field(string $html): string
{
    $candidates = [];
    if (preg_match_all('/<input\b[^>]*>/i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            if (preg_match('/\btype=(["\'])hidden\1/i', $tag)) {
                continue;
            }
            if (preg_match('/\btype=(["\'])(submit|button|image|checkbox|radio|file)\1/i', $tag)) {
                continue;
            }
            if (!preg_match('/\bname=(["\'])([^"\']+)\1/i', $tag, $nm)) {
                continue;
            }
            $name = html_entity_decode($nm[2], ENT_QUOTES, "UTF-8");
            if ($name === "" || preg_match('/^(__|DX)/i', $name)) {
                continue;
            }
            if (in_array($name, ["Usuario", "Contrasena", "DXScript", "DXCss"], true)) {
                continue;
            }
            $candidates[] = $name;
        }
    }

    foreach ($candidates as $name) {
        $fold = mxli_deslindes_fold($name);
        if (str_contains($fold, "folio") || str_contains($fold, "tramite") || str_contains($fold, "numero")) {
            return $name;
        }
    }

    if (preg_match('/for=(["\'])([^"\']+)\1[^>]*>[^<]*(tr[aá]mite|folio)/i', $html, $m)) {
        $for = preg_replace("/_I$/", "", $m[2]);
        if (is_string($for) && $for !== "") {
            return $for;
        }
    }

    return "Folio";
}

function mxli_deslindes_to_unique_id(string $id): string
{
    $id = trim($id);
    if ($id === "") {
        return $id;
    }
    if (str_contains($id, "$")) {
        return $id;
    }
    if (str_starts_with($id, "ctl00_")) {
        return str_replace("_", "$", $id);
    }
    return $id;
}

function mxli_deslindes_find_consult_target(string $html): string
{
    if (preg_match('/WebForm_PostBackOptions\(\s*"([^"]*btnConsultar[^"]*)"/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
    }
    if (preg_match('/WebForm_PostBackOptions\(\s*"([^"]*Consultar[^"]*)"/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
    }
    if (preg_match("/btnConsultar\\.uniqueID\\s*=\\s*['\"]([^'\"]+)/i", $html, $m)) {
        return $m[1];
    }
    if (preg_match("/new ASPxClientButton\\('([^']*Consultar[^']*)'\\)/i", $html, $m)) {
        return mxli_deslindes_to_unique_id($m[1]);
    }
    if (preg_match('/id=(["\'])([^"\']*Consultar[^"\']*)\\1/i', $html, $m)) {
        return mxli_deslindes_to_unique_id(html_entity_decode($m[2], ENT_QUOTES, "UTF-8"));
    }
    return "ctl00\$ContentPlaceHolder1\$btnConsultar";
}

/**
 * @return list<string>
 */
function mxli_deslindes_map_headers(array $headers): array
{
    $map = [];
    foreach ($headers as $i => $raw) {
        $fold = preg_replace("/[^a-z0-9]/", "", mxli_deslindes_fold($raw)) ?? "";
        if ($fold === "folio" || $fold === "notramite" || $fold === "tramite") {
            $map[$i] = "folio";
        } elseif (str_contains($fold, "clavecatastral") || $fold === "clave" || str_contains($fold, "clavecatas")) {
            $map[$i] = "clave_catastral";
        } elseif (str_contains($fold, "estatus") || $fold === "estado" || $fold === "status") {
            $map[$i] = "estatus";
        } elseif (str_contains($fold, "fechatermino") || str_contains($fold, "fechafinal") || str_contains($fold, "termino")) {
            $map[$i] = "fecha_termino";
        } elseif ($fold === "fecha" || str_contains($fold, "fechainicio") || str_contains($fold, "fechaalta")) {
            $map[$i] = "fecha";
        } elseif (str_contains($fold, "tramitador")) {
            $map[$i] = "tramitador";
        } elseif (str_contains($fold, "perito")) {
            $map[$i] = "perito";
        }
    }
    return $map;
}

/**
 * Extrae bloques HTML de un tag sin php-xml / DOMDocument.
 *
 * @return list<array{attrs:string,inner:string}>
 */
function mxli_deslindes_extract_tags(string $html, string $tag): array
{
    $tag = strtolower($tag);
    $out = [];
    $len = strlen($html);
    $offset = 0;
    $openRe = "/<{$tag}\\b([^>]*)>/i";
    $anyRe = "/<\\/?{$tag}\\b[^>]*>/i";
    while ($offset < $len && preg_match($openRe, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $start = (int)$m[0][1];
        $open = $m[0][0];
        $attrs = $m[1][0];
        $innerStart = $start + strlen($open);
        if (preg_match("/\\/\\s*>$/", $open)) {
            $out[] = ["attrs" => $attrs, "inner" => ""];
            $offset = $innerStart;
            continue;
        }
        $depth = 1;
        $pos = $innerStart;
        $innerEnd = $len;
        while ($depth > 0 && preg_match($anyRe, $html, $n, PREG_OFFSET_CAPTURE, $pos)) {
            $tok = $n[0][0];
            $at = (int)$n[0][1];
            $isClose = isset($tok[1]) && $tok[1] === "/";
            $depth += $isClose ? -1 : 1;
            $pos = $at + strlen($tok);
            if ($depth === 0) {
                $innerEnd = $at;
                break;
            }
        }
        if ($depth !== 0) {
            break;
        }
        $out[] = ["attrs" => $attrs, "inner" => substr($html, $innerStart, $innerEnd - $innerStart)];
        $offset = $pos;
    }
    return $out;
}

function mxli_deslindes_attr(string $attrs, string $name): string
{
    if (preg_match('/\b' . preg_quote($name, "/") . '=(["\'])([^"\']*)\1/i', $attrs, $m)) {
        return html_entity_decode($m[2], ENT_QUOTES, "UTF-8");
    }
    return "";
}

function mxli_deslindes_cell_text(string $html): string
{
    $html = preg_replace("/<script\\b[^>]*>.*?<\\/script>/is", " ", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, "UTF-8");
    return trim(preg_replace("/\\s+/", " ", $text) ?? $text);
}

/**
 * @return list<string>
 */
function mxli_deslindes_row_cells(string $trInner): array
{
    $cells = [];
    foreach (mxli_deslindes_extract_tags($trInner, "th") as $th) {
        $cells[] = mxli_deslindes_cell_text($th["inner"]);
    }
    if ($cells !== []) {
        return $cells;
    }
    foreach (mxli_deslindes_extract_tags($trInner, "td") as $td) {
        $cells[] = mxli_deslindes_cell_text($td["inner"]);
    }
    return $cells;
}

function mxli_deslindes_unwrap_callback(string $body): string
{
    if (preg_match("/\\/\\*DX\\*\\/\\((\\{.*\\})\\)\\s*$/s", $body, $m)) {
        $json = json_decode(str_replace("'", "\"", $m[1]), true);
        if (is_array($json)) {
            foreach (["result", "html", "Result"] as $k) {
                if (!empty($json[$k]) && is_string($json[$k])) {
                    return $json[$k];
                }
            }
        }
    }
    if (preg_match("/['\"]result['\"]\\s*:\\s*['\"]([\\s\\S]*?)['\"]\\s*[,}]/", $body, $m)) {
        return stripcslashes($m[1]);
    }
    return $body;
}

/**
 * @return array{id:string,unique:string}
 */
function mxli_deslindes_callback_target(string $html): array
{
    $id = "";
    $unique = "";
    if (preg_match("/new ASPxClientGridView\\('([^']+)'\\)/", $html, $m)) {
        $id = $m[1];
    } elseif (preg_match("/([A-Za-z0-9_]+)\\.PerformCallback\\s*\\(/", $html, $m) && stripos($m[1], "Menu") === false) {
        $id = $m[1];
    }
    if (stripos($id, "Menu") !== false) {
        $id = "";
    }
    if ($id !== "" && preg_match("/" . preg_quote($id, "/") . "\\.uniqueID\\s*=\\s*['\"]([^'\"]+)['\"]/", $html, $m)) {
        $unique = $m[1];
    }
    if ($unique === "" && $id !== "") {
        $unique = mxli_deslindes_to_unique_id($id);
    }
    if ($unique === "" && preg_match("/new ASPxClientCallbackPanel\\('([^']+)'\\)/", $html, $m) && stripos($m[1], "Menu") === false) {
        $id = $m[1];
        $unique = mxli_deslindes_to_unique_id($m[1]);
        if (preg_match("/" . preg_quote($id, "/") . "\\.uniqueID\\s*=\\s*['\"]([^'\"]+)['\"]/", $html, $um)) {
            $unique = $um[1];
        }
    }
    if (stripos($unique, "Menu") !== false) {
        $unique = "";
        $id = "";
    }
    return ["id" => $id, "unique" => $unique];
}

/**
 * @return list<array<string,string>>
 */
function mxli_deslindes_parse_loose(string $html, string $folio): array
{
    $plain = mxli_deslindes_plain($html);
    if ($folio !== "" && !str_contains($plain, $folio)) {
        return [];
    }
    if (!preg_match("/\\b([A-Z]{2,4}\\d{5,10})\\b/", $plain, $claveM)) {
        return [];
    }
    $estatus = "";
    if (preg_match("/(VISTO BUENO[^,]{0,40}|RECHAZAD[OA][^,]{0,20}|PENDIENTE[^,]{0,20}|EN PROCESO[^,]{0,20}|TERMINADO[^,]{0,20})/i", $plain, $estM)) {
        $estatus = trim($estM[1]);
    }
    $fechas = [];
    if (preg_match_all("/\\b(\\d{2}\\/\\d{2}\\/\\d{4})\\b/", $plain, $feM)) {
        $fechas = $feM[1];
    }
    $item = [
        "folio" => $folio,
        "clave_catastral" => $claveM[1],
        "estatus" => $estatus,
        "fecha" => $fechas[0] ?? "",
        "tramitador" => "",
        "perito" => "",
        "fecha_termino" => $fechas[1] ?? "",
    ];
    if (preg_match("/tramitador\\s*[:\\s]+([A-ZÁÉÍÓÚÑ ]{5,80})/i", $plain, $tM)) {
        $item["tramitador"] = trim($tM[1]);
    }
    if (preg_match("/perito\\s*[:\\s]+([A-ZÁÉÍÓÚÑ ]{5,80})/i", $plain, $pM)) {
        $item["perito"] = trim($pM[1]);
    }
    return [$item];
}

/**
 * @return list<array<string,string>>
 */
function mxli_deslindes_item_usable(array $item): bool
{
    $clave = strtoupper(preg_replace("/\s+/", "", (string)($item["clave_catastral"] ?? "")) ?? "");
    if (!preg_match('/^[A-Z]{2,4}[0-9]{4,10}$/', $clave)) {
        return false;
    }
    $joined = mxli_deslindes_fold(implode(" ", [
        (string)($item["estatus"] ?? ""),
        (string)($item["tramitador"] ?? ""),
        (string)($item["perito"] ?? ""),
    ]));
    if (str_contains($joined, "clavecatastral") || str_contains($joined, "tramitador")) {
        return false;
    }
    return true;
}

/**
 * @param list<array<string,string>> $items
 * @return list<array<string,string>>
 */
function mxli_deslindes_clean_items(array $items): array
{
    $out = [];
    $seen = [];
    foreach ($items as $item) {
        if (!mxli_deslindes_item_usable($item)) {
            continue;
        }
        $item["clave_catastral"] = strtoupper(preg_replace("/\s+/", "", $item["clave_catastral"]) ?? "");
        $key = $item["folio"] . "|" . $item["clave_catastral"];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $item;
    }
    return $out;
}

function mxli_deslindes_items_from_html(string $html, string $folio = ""): array
{
    $html = mxli_deslindes_unwrap_callback($html);
    $items = mxli_deslindes_clean_items(mxli_deslindes_parse_table($html));
    if ($items === [] && $folio !== "") {
        $items = mxli_deslindes_clean_items(mxli_deslindes_parse_loose($html, $folio));
    }
    return $items;
}

/**
 * @return list<array<string,string>>
 */
function mxli_deslindes_parse_table(string $html): array
{
    $html = preg_replace("/<script\\b[^>]*>.*?<\\/script>/is", " ", $html) ?? $html;
    $found = [];
    foreach (mxli_deslindes_extract_tags($html, "table") as $table) {
        $class = mxli_deslindes_attr($table["attrs"], "class");
        $id = mxli_deslindes_attr($table["attrs"], "id");
        $plain = mxli_deslindes_fold(mxli_deslindes_cell_text($table["inner"]));
        $looksGrid = str_contains($class, "dxgv")
            || str_contains($id, "DXMainTable")
            || str_contains($plain, "clavecatastral")
            || str_contains($plain, "tramitador");
        if (!$looksGrid) {
            continue;
        }

        $headerCells = [];
        $bodyRows = [];
        $rowSource = $table["inner"];
        foreach (["thead", "tbody"] as $sec) {
            $secs = mxli_deslindes_extract_tags($table["inner"], $sec);
            if ($secs !== []) {
                $rowSource = "";
                foreach ($secs as $s) {
                    $rowSource .= $s["inner"];
                }
                break;
            }
        }
        foreach (mxli_deslindes_extract_tags($rowSource, "tr") as $tr) {
            $trClass = mxli_deslindes_attr($tr["attrs"], "class");
            $trId = mxli_deslindes_attr($tr["attrs"], "id");
            $cells = mxli_deslindes_row_cells($tr["inner"]);
            if ($cells === [] || count($cells) < 2) {
                continue;
            }
            $upper = array_map("strtoupper", $cells);
            $skipClass = $trClass . " " . $trId;
            if (preg_match("/dxgvHeader|DXHeaders|FilterRow|dxgvFilter|dxgvFooter|DXFooter|dxgvPager|GroupRow|EmptyData/i", $skipClass)) {
                $isNamedHeader = in_array("FOLIO", $upper, true) || in_array("CLAVECATASTRAL", $upper, true);
                if ($headerCells === [] && $isNamedHeader && count($cells) >= 3) {
                    $headerCells = $cells;
                }
                continue;
            }
            $isHeader = in_array("FOLIO", $upper, true)
                || in_array("CLAVECATASTRAL", $upper, true);
            if ($isHeader) {
                if ($headerCells === [] && count($cells) >= 3) {
                    $headerCells = $cells;
                }
                continue;
            }
            if (str_contains($trClass, "Empty") || str_contains($trId, "Empty")) {
                continue;
            }
            if (str_contains($trClass, "dxgvDataRow") || str_contains($trId, "DXDataRow") || $headerCells !== []) {
                $bodyRows[] = $cells;
            }
        }

        if ($headerCells === [] || $bodyRows === []) {
            continue;
        }
        $map = mxli_deslindes_map_headers($headerCells);
        if ($map === []) {
            continue;
        }
        foreach ($bodyRows as $cells) {
            $item = [
                "folio" => "",
                "clave_catastral" => "",
                "estatus" => "",
                "fecha" => "",
                "tramitador" => "",
                "perito" => "",
                "fecha_termino" => "",
            ];
            foreach ($map as $i => $key) {
                $item[$key] = trim((string)($cells[$i] ?? ""));
            }
            if (!preg_match('/^[A-Z]{2,4}[0-9]{4,10}$/', strtoupper(preg_replace("/\s+/", "", $item["clave_catastral"]) ?? ""))) {
                foreach ($cells as $cell) {
                    $try = strtoupper(preg_replace("/\s+/", "", $cell) ?? "");
                    if (preg_match('/^[A-Z]{2,4}[0-9]{4,10}$/', $try)) {
                        $item["clave_catastral"] = $try;
                        break;
                    }
                }
            }
            if ($item["folio"] === "" && $item["clave_catastral"] === "") {
                continue;
            }
            $found[] = $item;
        }
        if ($found !== []) {
            break;
        }
    }

    return $found;
}

/**
 * @return array{ok:bool,folio:string,count:int,items:list<array<string,string>>,fuente:string}
 */
function mxli_deslindes_consultar(string $folio): array
{
    $folio = mxli_deslindes_normalize_folio($folio);
    if (!mxli_deslindes_valid_folio($folio)) {
        throw new InvalidArgumentException("Indique el No. de Trámite (folio numérico).");
    }
    if (trim(mxli_deslindes_cfg("catastro_portal_user")) === ""
        || mxli_deslindes_cfg("catastro_portal_pass") === ""
    ) {
        throw new RuntimeException(
            "Falta la cuenta del portal de padrón. Defina deslindes_portal_user y deslindes_portal_pass en includes/config.local.php."
        );
    }

    $base = mxli_deslindes_portal_url();
    $pageUrl = $base . "Deslindes.aspx";
    $extra = ["referer" => $base . "Login.aspx"];

    $page = mxli_deslindes_http("GET", $pageUrl, [], $extra);
    if (mxli_deslindes_is_login_page($page["body"], $page["url"])) {
        mxli_deslindes_login();
        $page = mxli_deslindes_http("GET", $pageUrl, [], $extra);
        if (mxli_deslindes_is_login_page($page["body"], $page["url"])) {
            throw new RuntimeException("El portal de padrón exige sesión y no se pudo autenticar.");
        }
    }

    if (preg_match('/href=(["\'])([^"\']*Deslindes[^"\']*)\\1/i', $page["body"], $hm)) {
        $href = html_entity_decode($hm[2], ENT_QUOTES, "UTF-8");
        if ($href !== "" && stripos($href, "javascript") === false) {
            if (!preg_match("#^https?://#i", $href)) {
                $href = $base . ltrim($href, "./");
            }
            if (stripos($href, "Deslindes") !== false && $href !== $pageUrl) {
                $pageUrl = $href;
                $page = mxli_deslindes_http("GET", $pageUrl, [], $extra);
            }
        }
    }

    $extra["referer"] = $pageUrl;
    $folioField = mxli_deslindes_to_unique_id(mxli_deslindes_find_folio_field($page["body"]));
    $target = mxli_deslindes_to_unique_id(mxli_deslindes_find_consult_target($page["body"]));
    $lastBody = $page["body"];
    $items = [];

    $tryPost = static function (string $html) use ($folio, $folioField, $target, $pageUrl, $extra): array {
        $fields = mxli_deslindes_hidden_fields($html);
        foreach (array_keys($fields) as $name) {
            if (preg_match('/ASPxTxtTramite\\$State$/i', $name) || preg_match('/Tramite\\$State$/i', $name)) {
                unset($fields[$name]);
            }
        }
        $fields[$folioField] = $folio;
        $fields["__EVENTTARGET"] = $target;
        $fields["__EVENTARGUMENT"] = "";
        return mxli_deslindes_http("POST", $pageUrl, $fields, $extra);
    };

    $post = $tryPost($page["body"]);
    $lastBody = $post["body"];
    $items = mxli_deslindes_items_from_html($lastBody, $folio);

    if ($items === [] && !str_contains($lastBody, $folio)) {
        mxli_deslindes_login();
        $page = mxli_deslindes_http("GET", $pageUrl, [], $extra);
        $post = $tryPost($page["body"]);
        $lastBody = $post["body"];
        $items = mxli_deslindes_items_from_html($lastBody, $folio);
    }

    @file_put_contents(
        rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . "mxli_deslindes_last.html",
        $lastBody
    );

    $payload = [
        "ok" => true,
        "folio" => $folio,
        "count" => count($items),
        "items" => $items,
        "fuente" => "portal_consultacatastro",
    ];
    if ($items === []) {
        $plain = mxli_deslindes_plain($lastBody);
        $payload["aviso"] = "El portal respondió sin filas. Campo=" . $folioField
            . " botón=" . $target
            . " sesion=" . (str_contains(mxli_deslindes_fold($plain), "cerrar sesion") ? "si" : "no")
            . " folio_en_pagina=" . (str_contains($plain, $folio) ? "si" : "no");
    }
    return $payload;
}
