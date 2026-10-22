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
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http(string $method, string $url, array $fields = []): array
{
    if (function_exists("curl_init")) {
        return mxli_deslindes_http_curl($method, $url, $fields);
    }
    return mxli_deslindes_http_stream($method, $url, $fields);
}

/**
 * @param array<string,string> $fields
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http_curl(string $method, string $url, array $fields = []): array
{
    $cookie = mxli_deslindes_cookie_file() . ".curl";
    $ch = curl_init($url);
    $headers = [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Origin: https://www.mexicali.gob.mx",
        "Referer: " . mxli_deslindes_portal_url() . "Login.aspx",
    ];
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
 * @return array{body:string,status:int,url:string}
 */
function mxli_deslindes_http_stream(string $method, string $url, array $fields = []): array
{
    $cookies = mxli_deslindes_cookies_load();
    $current = $url;
    $method = strtoupper($method);
    $body = $method === "POST" ? http_build_query($fields) : "";
    $lastHtml = "";
    $status = 0;

    for ($i = 0; $i < 8; $i++) {
        $headerLines = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Origin: https://www.mexicali.gob.mx",
            "Referer: " . mxli_deslindes_portal_url() . "Login.aspx",
        ];
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

    return $candidates[0] ?? "Folio";
}

function mxli_deslindes_find_consult_target(string $html): string
{
    if (preg_match('/id=(["\'])([^"\']+)\1[^>]*>[\s\S]{0,120}Consultar/i', $html, $m)) {
        return html_entity_decode($m[2], ENT_QUOTES, "UTF-8");
    }
    if (preg_match("/new ASPxClientButton\('([^']+)'\)[\s\S]{0,400}Consultar/i", $html, $m)) {
        return $m[1];
    }
    if (preg_match('/WebForm_PostBackOptions\(\s*"(Consultar|btnConsultar|ASPxButton1)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match("/window\['btnConsultar'\]/", $html)) {
        return "Consultar";
    }
    return "Consultar";
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
        foreach (mxli_deslindes_extract_tags($table["inner"], "tr") as $tr) {
            $trClass = mxli_deslindes_attr($tr["attrs"], "class");
            $trId = mxli_deslindes_attr($tr["attrs"], "id");
            $cells = mxli_deslindes_row_cells($tr["inner"]);
            if ($cells === []) {
                continue;
            }
            $upper = array_map("strtoupper", $cells);
            $isHeader = str_contains($trClass, "dxgvHeader")
                || str_contains($trId, "DXHeaders")
                || str_contains($trId, "Header")
                || in_array("FOLIO", $upper, true)
                || in_array("CLAVECATASTRAL", $upper, true);
            if ($isHeader && $headerCells === []) {
                $headerCells = $cells;
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

    $page = mxli_deslindes_http("GET", $pageUrl);
    if (mxli_deslindes_is_login_page($page["body"], $page["url"])) {
        mxli_deslindes_login();
        $page = mxli_deslindes_http("GET", $pageUrl);
        if (mxli_deslindes_is_login_page($page["body"], $page["url"])) {
            throw new RuntimeException("El portal de padrón exige sesión y no se pudo autenticar.");
        }
    }

    $fields = mxli_deslindes_hidden_fields($page["body"]);
    $folioField = mxli_deslindes_find_folio_field($page["body"]);
    $target = mxli_deslindes_find_consult_target($page["body"]);
    $fields[$folioField] = $folio;
    $fields["__EVENTTARGET"] = $target;
    $fields["__EVENTARGUMENT"] = $fields["__EVENTARGUMENT"] ?? "";

    $post = mxli_deslindes_http("POST", $pageUrl, $fields);
    if (mxli_deslindes_is_login_page($post["body"], $post["url"])) {
        mxli_deslindes_login();
        $page = mxli_deslindes_http("GET", $pageUrl);
        $fields = mxli_deslindes_hidden_fields($page["body"]);
        $folioField = mxli_deslindes_find_folio_field($page["body"]);
        $target = mxli_deslindes_find_consult_target($page["body"]);
        $fields[$folioField] = $folio;
        $fields["__EVENTTARGET"] = $target;
        $fields["__EVENTARGUMENT"] = $fields["__EVENTARGUMENT"] ?? "";
        $post = mxli_deslindes_http("POST", $pageUrl, $fields);
    }

    $items = mxli_deslindes_parse_table($post["body"]);
    if ($items === []) {
        $items = mxli_deslindes_parse_table($page["body"]);
    }

    return [
        "ok" => true,
        "folio" => $folio,
        "count" => count($items),
        "items" => $items,
        "fuente" => "portal_consultacatastro",
    ];
}
