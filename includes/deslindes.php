<?php
declare(strict_types=1);

/**
 * Trámites de deslinde por ventanilla (peritos adscritos).
 *
 * Fuente: portal "Consulta padrón catastral" del Ayuntamiento de Mexicali
 * (ASP.NET 4 WebForms + DevExpress), con sesión:
 *   GET  Deslindes.aspx  → 302 Login.aspx? si no hay sesión
 *   POST Login.aspx      → __EVENTTARGET=Entrar, Usuario, Contrasena, __VIEWSTATE…
 *                          (error: span#Mensaje "El usuario o contraseña son incorrectos")
 *   POST Deslindes.aspx  → No. de Trámite + "Consultar" (postback completo)
 *   Grid: FOLIO, CLAVECATASTRAL, ESTATUS, FECHA, TRAMITADOR, PERITO, FECHA_TERMINO
 *
 * La cuenta del portal es una sola por servidor (config.local.php); las cookies
 * se guardan en deslindes_state_dir y se vuelve a iniciar sesión al expirar.
 * Producción no tiene ext-curl ni ext-dom: HTTP por streams y parseo por regex.
 */

const MXLI_DESLINDES_CACHE_TTL = 600;
const MXLI_DESLINDES_LOGIN_BACKOFF = 900;
const MXLI_DESLINDES_HTTP_TIMEOUT = 12;
const MXLI_DESLINDES_MSG_DOWN = "El portal de catastro del Ayuntamiento no está disponible en este momento (mantenimiento). Intente más tarde.";

final class MxliDeslindesError extends RuntimeException
{
    public string $kind;
    public ?string $debugPath;

    public function __construct(string $kind, string $message, ?string $debugPath = null)
    {
        parent::__construct($message);
        $this->kind = $kind;
        $this->debugPath = $debugPath;
    }
}

function mxli_deslindes_base_url(): string
{
    $url = trim(mxli_config("deslindes_portal_url", ""));
    if ($url === "") {
        $url = "https://www.mexicali.gob.mx/webpub/consultacatastro/";
    }
    return rtrim($url, "/") . "/";
}

function mxli_deslindes_configured(): bool
{
    return trim(mxli_config("deslindes_portal_user", "")) !== ""
        && mxli_config("deslindes_portal_pass", "") !== "";
}

function mxli_deslindes_state_dir(): string
{
    $dir = trim(mxli_config("deslindes_state_dir", ""));
    if ($dir === "") {
        $dir = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . "mxli_deslindes";
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return rtrim($dir, "/\\");
}

function mxli_deslindes_normalize_tramite(string $raw): string
{
    return preg_replace("/\D+/", "", $raw) ?? "";
}

/* ------------------------------------------------------------------ */
/* Cookies + HTTP (streams)                                            */
/* ------------------------------------------------------------------ */

/** @return array<string,string> */
function mxli_deslindes_jar_load(): array
{
    $raw = @file_get_contents(mxli_deslindes_state_dir() . "/cookies.json");
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $name => $value) {
        if (is_string($name) && is_string($value)) {
            $out[$name] = $value;
        }
    }
    return $out;
}

/** @param array<string,string> $jar */
function mxli_deslindes_jar_save(array $jar): void
{
    $path = mxli_deslindes_state_dir() . "/cookies.json";
    @file_put_contents($path, json_encode($jar), LOCK_EX);
    @chmod($path, 0600);
}

/** @param array<string,string> $jar */
function mxli_deslindes_jar_absorb(array &$jar, array $headers): void
{
    foreach ($headers as $line) {
        if (stripos((string)$line, "Set-Cookie:") !== 0) {
            continue;
        }
        $parts = explode(";", trim(substr((string)$line, 11)));
        $pair = explode("=", trim((string)array_shift($parts)), 2);
        $name = trim($pair[0]);
        if ($name === "") {
            continue;
        }
        $value = trim($pair[1] ?? "");
        $expired = $value === "";
        foreach ($parts as $attr) {
            $kv = explode("=", trim($attr), 2);
            $key = strtolower(trim($kv[0]));
            if ($key === "max-age" && (int)($kv[1] ?? 1) <= 0) {
                $expired = true;
            } elseif ($key === "expires") {
                $ts = strtotime(trim($kv[1] ?? ""));
                if ($ts !== false && $ts < time()) {
                    $expired = true;
                }
            }
        }
        if ($expired) {
            unset($jar[$name]);
        } else {
            $jar[$name] = $value;
        }
    }
}

function mxli_deslindes_resolve_url(string $base, string $location): string
{
    $location = trim($location);
    if ($location === "") {
        return $base;
    }
    if (preg_match("~^https?://~i", $location)) {
        return $location;
    }
    $p = parse_url($base);
    $origin = ($p["scheme"] ?? "https") . "://" . ($p["host"] ?? "") . (isset($p["port"]) ? ":" . $p["port"] : "");
    if ($location[0] === "/") {
        return $origin . $location;
    }
    $path = (string)($p["path"] ?? "/");
    $dir = substr($path, 0, (int)strrpos($path, "/") + 1);
    if (str_starts_with($location, "./")) {
        $location = substr($location, 2);
    }
    return $origin . $dir . $location;
}

function mxli_deslindes_to_utf8(string $html): string
{
    if ($html === "" || mb_check_encoding($html, "UTF-8")) {
        return $html;
    }
    return mb_convert_encoding($html, "UTF-8", "Windows-1252");
}

/**
 * @param array<string,string> $jar
 * @param array<string,string>|null $form
 * @return array{status:int,body:string,url:string}
 */
function mxli_deslindes_http(string $url, array &$jar, ?array $form = null, string $referer = ""): array
{
    $method = $form === null ? "GET" : "POST";
    for ($hop = 0; $hop < 6; $hop++) {
        $headers = [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Geomexicali-Localizacion/1.0",
            "Accept: text/html,application/xhtml+xml,*/*;q=0.8",
            "Accept-Language: es-MX,es;q=0.9",
        ];
        if ($jar !== []) {
            $pairs = [];
            foreach ($jar as $name => $value) {
                $pairs[] = $name . "=" . $value;
            }
            $headers[] = "Cookie: " . implode("; ", $pairs);
        }
        if ($referer !== "") {
            $headers[] = "Referer: " . $referer;
        }
        $content = "";
        if ($method === "POST") {
            $content = http_build_query($form ?? []);
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
            $headers[] = "Origin: " . preg_replace("~^(https?://[^/]+).*$~i", "$1", $url);
        }
        $context = stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers) . "\r\n",
                "content" => $content,
                "timeout" => MXLI_DESLINDES_HTTP_TIMEOUT,
                "ignore_errors" => true,
                "follow_location" => 0,
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ],
        ]);
        $http_response_header = [];
        $body = @file_get_contents($url, false, $context);
        $respHeaders = $http_response_header;
        if ($body === false && $respHeaders === []) {
            throw new MxliDeslindesError("portal_down", MXLI_DESLINDES_MSG_DOWN);
        }
        $status = 0;
        if (preg_match("~^HTTP/\S+\s+(\d{3})~", (string)($respHeaders[0] ?? ""), $m)) {
            $status = (int)$m[1];
        }
        mxli_deslindes_jar_absorb($jar, $respHeaders);

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = "";
            foreach ($respHeaders as $line) {
                if (stripos((string)$line, "Location:") === 0) {
                    $location = trim(substr((string)$line, 9));
                }
            }
            $referer = $url;
            $url = mxli_deslindes_resolve_url($url, $location);
            if ($status !== 307 && $status !== 308) {
                $method = "GET";
                $form = null;
            }
            continue;
        }
        $res = ["status" => $status, "body" => mxli_deslindes_to_utf8((string)$body), "url" => $url];
        if (mxli_deslindes_is_maintenance($res)) {
            throw new MxliDeslindesError("portal_down", MXLI_DESLINDES_MSG_DOWN);
        }
        return $res;
    }
    throw new MxliDeslindesError("portal_error", "El portal redirigió demasiadas veces.");
}

/* ------------------------------------------------------------------ */
/* HTML helpers (sin DOM)                                              */
/* ------------------------------------------------------------------ */

/** @return array<string,string> */
function mxli_deslindes_attrs(string $tag): array
{
    $out = [];
    if (preg_match_all('/([a-zA-Z_:][\w:.\-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $value = $a[2] !== "" ? $a[2] : (($a[3] ?? "") !== "" ? $a[3] : ($a[4] ?? ""));
            $out[strtolower($a[1])] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, "UTF-8");
        }
    }
    return $out;
}

function mxli_deslindes_text(string $html): string
{
    $html = preg_replace("~<(script|style)\b.*?</\\1>~is", " ", $html) ?? $html;
    $html = preg_replace("~<br\s*/?>~i", " ", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, "UTF-8");
    $text = str_replace("\xc2\xa0", " ", $text);
    return trim(preg_replace("/\s+/u", " ", $text) ?? $text);
}

function mxli_deslindes_fold(string $value): string
{
    $value = mb_strtoupper($value, "UTF-8");
    return strtr($value, ["Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U", "Ü" => "U", "Ñ" => "N"]);
}

function mxli_deslindes_url_path(string $url): string
{
    return strtolower((string)(parse_url($url, PHP_URL_PATH) ?? ""));
}

/** Solo la URL decide si es la pantalla de login (no la presencia de un campo password). */
function mxli_deslindes_is_login_page(string $html, string $url): bool
{
    return str_ends_with(mxli_deslindes_url_path($url), "/login.aspx");
}

function mxli_deslindes_has_password_input(string $html): bool
{
    return (bool)preg_match('/<input\b[^>]*type\s*=\s*["\']?password/i', $html);
}

/** @param array{status:int,body:string,url:string} $res */
function mxli_deslindes_is_maintenance(array $res): bool
{
    if ($res["status"] >= 500 || $res["status"] === 0) {
        return true;
    }
    if (preg_match('/name\s*=\s*["\']__VIEWSTATE/i', $res["body"])) {
        return false;
    }
    $text = mxli_deslindes_fold(mxli_deslindes_text($res["body"]));
    return (bool)preg_match("/MANTENIMIENTO|FUERA DE SERVICIO|SERVICE UNAVAILABLE|TEMPORALMENTE NO DISPONIBLE|RESPALDO EN PROCESO/", $text);
}

function mxli_deslindes_is_credentials_error(string $html): bool
{
    return str_contains(mxli_deslindes_fold(mxli_deslindes_text($html)), "USUARIO O CONTRASENA SON INCORRECTOS");
}

/** @param array{status:int,body:string,url:string} $res */
function mxli_deslindes_is_password_change(array $res): bool
{
    if (preg_match("/cambi|contrasen|password|expir/", mxli_deslindes_url_path($res["url"]))) {
        return true;
    }
    if (!mxli_deslindes_has_password_input($res["body"])) {
        return false;
    }
    $text = mxli_deslindes_fold(mxli_deslindes_text($res["body"]));
    return (bool)preg_match("/CAMBI\w* (DE |SU |LA )*CONTRASENA|NUEVA CONTRASENA|CONTRASENA\W+(HA )?EXPIR|CONFIRMAR CONTRASENA/", $text);
}

/**
 * Resultado del POST de login.
 * @param array{status:int,body:string,url:string} $res
 * @return string rejected | login_page | password_change | ok
 */
function mxli_deslindes_classify_login_response(array $res): string
{
    if (mxli_deslindes_is_credentials_error($res["body"])) {
        return "rejected";
    }
    if (mxli_deslindes_is_login_page($res["body"], $res["url"])) {
        return "login_page";
    }
    if (mxli_deslindes_is_password_change($res)) {
        return "password_change";
    }
    return "ok";
}

/** HTML para diagnóstico sin valores de password/hidden ni la contraseña configurada. */
function mxli_deslindes_sanitize_html(string $html): string
{
    $html = preg_replace_callback("/<input\b[^>]*>/i", static function (array $m): string {
        $a = mxli_deslindes_attrs($m[0]);
        $type = strtolower($a["type"] ?? "text");
        if (in_array($type, ["password", "hidden"], true) || preg_match("/usuario|user|contrase|pass/i", $a["name"] ?? "")) {
            return preg_replace('/\bvalue\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', 'value="[omitido]"', $m[0]) ?? "";
        }
        return $m[0];
    }, $html) ?? "";
    $pass = mxli_config("deslindes_portal_pass", "");
    if (strlen($pass) >= 3) {
        $html = str_replace([$pass, rawurlencode($pass), htmlspecialchars($pass, ENT_QUOTES)], "[omitido]", $html);
    }
    return $html;
}

/** @param array{status:int,body:string,url:string} $res */
function mxli_deslindes_save_debug(string $label, array $res): ?string
{
    $path = mxli_deslindes_state_dir() . "/debug-" . preg_replace("/[^a-z0-9_-]/i", "", $label) . ".html";
    $meta = "<!-- " . gmdate("c") . " HTTP " . $res["status"] . " " . str_replace("--", "", $res["url"]) . " -->\n";
    if (@file_put_contents($path, $meta . mxli_deslindes_sanitize_html($res["body"]), LOCK_EX) === false) {
        return null;
    }
    @chmod($path, 0600);
    return $path;
}

/**
 * Campos que el navegador enviaría en un postback (sin botones).
 * @return array<string,string>
 */
function mxli_deslindes_form_fields(string $html): array
{
    $fields = [];
    if (preg_match_all("/<input\b[^>]*>/i", $html, $m)) {
        foreach ($m[0] as $tag) {
            $a = mxli_deslindes_attrs($tag);
            $name = $a["name"] ?? "";
            if ($name === "" || isset($a["disabled"])) {
                continue;
            }
            $type = strtolower($a["type"] ?? "text");
            if (in_array($type, ["submit", "button", "image", "reset", "file"], true)) {
                continue;
            }
            if (($type === "checkbox" || $type === "radio") && !isset($a["checked"])) {
                continue;
            }
            $fields[$name] = $a["value"] ?? (($type === "checkbox" || $type === "radio") ? "on" : "");
        }
    }
    if (preg_match_all("~<select\b([^>]*)>(.*?)</select>~is", $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $sel) {
            $a = mxli_deslindes_attrs("<select " . $sel[1] . ">");
            $name = $a["name"] ?? "";
            if ($name === "" || isset($a["disabled"])) {
                continue;
            }
            $value = null;
            if (preg_match_all("~<option\b([^>]*)>(.*?)</option>~is", $sel[2], $opts, PREG_SET_ORDER)) {
                foreach ($opts as $i => $opt) {
                    $oa = mxli_deslindes_attrs("<option " . $opt[1] . ">");
                    $ov = $oa["value"] ?? mxli_deslindes_text($opt[2]);
                    if ($i === 0 || isset($oa["selected"])) {
                        $value = $ov;
                    }
                    if (isset($oa["selected"])) {
                        break;
                    }
                }
            }
            $fields[$name] = (string)($value ?? "");
        }
    }
    if (preg_match_all("~<textarea\b([^>]*)>(.*?)</textarea>~is", $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $ta) {
            $a = mxli_deslindes_attrs("<textarea " . $ta[1] . ">");
            if (($a["name"] ?? "") !== "") {
                $fields[$a["name"]] = html_entity_decode($ta[2], ENT_QUOTES | ENT_HTML5, "UTF-8");
            }
        }
    }
    foreach (mxli_deslindes_dx_client_states($html) as $name => $state) {
        if (!isset($fields[$name])) {
            $fields[$name] = $state;
        }
    }
    return $fields;
}

/**
 * Estado cliente de controles DevExpress (dxo.stateObject). El navegador lo
 * envía al hacer submit en un campo oculto con el uniqueID del control; sin él,
 * ASPxGridView se reconstruye vacío ("Sin datos para mostrar").
 * @return array<string,string>
 */
function mxli_deslindes_dx_client_states(string $html): array
{
    $out = [];
    if (!preg_match_all("~<script\b[^>]*>(.*?)</script>~is", $html, $m)) {
        return $out;
    }
    foreach ($m[1] as $js) {
        if (
            !preg_match("/\.uniqueID\s*=\s*'([^']+)'/", $js, $u)
            || !preg_match("/\.stateObject\s*=\s*\((\{.*?\})\);/s", $js, $s)
        ) {
            continue;
        }
        $json = mxli_deslindes_js_object_to_json($s[1]);
        if ($json !== null) {
            $out[$u[1]] = $json;
        }
    }
    return $out;
}

function mxli_deslindes_js_object_to_json(string $js): ?string
{
    $conv = preg_replace_callback(
        "/'((?:[^'\\\\]|\\\\.)*)'/s",
        static fn(array $m): string => (string)json_encode(stripcslashes($m[1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $js
    ) ?? "";
    $data = json_decode($conv);
    if ($data === null) {
        $data = json_decode(preg_replace('/([{,]\s*)([A-Za-z_$][\w$]*)\s*:/', '$1"$2":', $conv) ?? "");
    }
    return is_object($data) ? (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function mxli_deslindes_form_action(string $html, string $pageUrl): string
{
    if (preg_match("/<form\b[^>]*>/i", $html, $m)) {
        $action = mxli_deslindes_attrs($m[0])["action"] ?? "";
        if ($action !== "") {
            return mxli_deslindes_resolve_url($pageUrl, $action);
        }
    }
    return $pageUrl;
}

/** Inputs de texto visibles, excluyendo editores del filtro/paginador del grid. */
function mxli_deslindes_text_inputs(string $html): array
{
    $out = [];
    if (!preg_match_all("/<input\b[^>]*>/i", $html, $m)) {
        return $out;
    }
    foreach ($m[0] as $tag) {
        $a = mxli_deslindes_attrs($tag);
        $type = strtolower($a["type"] ?? "text");
        $name = $a["name"] ?? "";
        if ($name === "" || !in_array($type, ["text", "number", "tel", "search"], true)) {
            continue;
        }
        if (preg_match('/DXFREditor|DXFilterRow|DXPager|DXSE|DXCustomization|\$DDD\$/i', $name)) {
            continue;
        }
        $out[] = ["name" => $name, "id" => $a["id"] ?? ""];
    }
    return $out;
}

function mxli_deslindes_find_tramite_field(string $html): ?string
{
    $override = trim(mxli_config("deslindes_tramite_field", ""));
    if ($override !== "") {
        return $override;
    }
    $inputs = mxli_deslindes_text_inputs($html);
    if (preg_match_all("~<label\b([^>]*)>(.*?)</label>~is", $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $label) {
            $for = mxli_deslindes_attrs("<label " . $label[1] . ">")["for"] ?? "";
            if ($for === "" || !preg_match("/TRAMITE/", mxli_deslindes_fold(mxli_deslindes_text($label[2])))) {
                continue;
            }
            foreach ($inputs as $in) {
                if ($in["id"] === $for) {
                    return $in["name"];
                }
            }
        }
    }
    foreach ($inputs as $in) {
        if (preg_match("/tramite|tr[aá]mite|folio|numtra|notra/i", $in["name"] . " " . $in["id"])) {
            return $in["name"];
        }
    }
    return $inputs[0]["name"] ?? null;
}

/**
 * Control que dispara "Consultar": submit (name=value) o __EVENTTARGET.
 * @return array{target:string,extra:array<string,string>}
 */
function mxli_deslindes_find_submit(string $html, string $pattern = "/consultar|buscar/i", bool $useOverride = true): array
{
    $override = $useOverride ? trim(mxli_config("deslindes_submit_target", "")) : "";
    $submits = [];
    if (preg_match_all("/<input\b[^>]*>/i", $html, $m)) {
        foreach ($m[0] as $tag) {
            $a = mxli_deslindes_attrs($tag);
            $type = strtolower($a["type"] ?? "text");
            if (($a["name"] ?? "") !== "" && in_array($type, ["submit", "button", "image"], true)) {
                $submits[$a["name"]] = $a["value"] ?? "";
            }
        }
    }
    if ($override !== "") {
        if (isset($submits[$override])) {
            return ["target" => "", "extra" => [$override => $submits[$override]]];
        }
        return ["target" => $override, "extra" => []];
    }
    foreach ($submits as $name => $value) {
        if (preg_match($pattern, $value . " " . $name)) {
            return ["target" => "", "extra" => [$name => $value]];
        }
    }

    $targets = [];
    $regexes = [
        '/WebForm_PostBackOptions\(\s*(?:"|&quot;)([^"&]+)(?:"|&quot;)/i',
        "/__doPostBack\\(\\s*(?:'|&#39;|\\\\')([^'&\\\\]+)/i",
    ];
    foreach ($regexes as $re) {
        if (preg_match_all($re, $html, $m)) {
            foreach ($m[1] as $t) {
                $targets[$t] = true;
            }
        }
    }
    $candidates = [];
    foreach (array_keys($targets) as $target) {
        $id = str_replace("$", "_", $target);
        $pos = strpos($html, 'id="' . $id . '"');
        $near = $pos === false ? "" : mb_substr(mxli_deslindes_text(substr($html, $pos, 700)), 0, 60);
        if (preg_match("/menu|cerrar|salir|logout|contrase|DXPager|DXFR/i", $target . " " . $near)) {
            continue;
        }
        if (preg_match($pattern, $target) || ($near !== "" && preg_match($pattern, $near))) {
            return ["target" => $target, "extra" => []];
        }
        $candidates[] = $target;
    }
    $targets = $candidates;
    if (count($targets) === 1) {
        return ["target" => $targets[0], "extra" => []];
    }
    return ["target" => "", "extra" => []];
}

/** Resumen sin valores del formulario (diagnóstico para admin / CLI). */
function mxli_deslindes_describe_form(string $html): array
{
    $submit = mxli_deslindes_find_submit($html);
    return [
        "fields" => array_keys(mxli_deslindes_form_fields($html)),
        "text_inputs" => array_column(mxli_deslindes_text_inputs($html), "name"),
        "tramite_field" => mxli_deslindes_find_tramite_field($html),
        "submit_target" => $submit["target"],
        "submit_fields" => array_keys($submit["extra"]),
    ];
}

function mxli_deslindes_portal_message(string $html): string
{
    if (preg_match('~<span\b[^>]*\bid="[^"]*(?:Mensaje|Msg|Error|Aviso)[^"]*"[^>]*>(.*?)</span>~is', $html, $m)) {
        $text = mxli_deslindes_text($m[1]);
        if ($text !== "") {
            return $text;
        }
    }
    if (preg_match("~\balert\(\s*['\"]([^'\"]{3,240})['\"]\s*\)~", $html, $m)) {
        return mxli_deslindes_text($m[1]);
    }
    return "";
}

/* ------------------------------------------------------------------ */
/* Grid                                                                */
/* ------------------------------------------------------------------ */

function mxli_deslindes_column_key(string $header): string
{
    $h = preg_replace("/[^A-Z0-9]+/", "_", mxli_deslindes_fold(trim($header))) ?? "";
    $h = trim($h, "_");
    $map = [
        "FOLIO" => "folio",
        "NO_TRAMITE" => "folio",
        "TRAMITE" => "folio",
        "CLAVECATASTRAL" => "clave_catastral",
        "CLAVE_CATASTRAL" => "clave_catastral",
        "CLAVE" => "clave_catastral",
        "ESTATUS" => "estatus",
        "STATUS" => "estatus",
        "FECHA" => "fecha",
        "TRAMITADOR" => "tramitador",
        "PERITO" => "perito",
        "FECHA_TERMINO" => "fecha_termino",
        "FECHATERMINO" => "fecha_termino",
    ];
    return $map[$h] ?? strtolower($h);
}

/** @return list<string> */
function mxli_deslindes_default_columns(): array
{
    return ["folio", "clave_catastral", "estatus", "fecha", "tramitador", "perito", "fecha_termino"];
}

/**
 * @param list<string> $cellsHtml
 * @param list<string> $keys
 * @return array<string,string>|null
 */
function mxli_deslindes_row_from_cells(array $cellsHtml, array $keys): ?array
{
    $values = array_map("mxli_deslindes_text", $cellsHtml);
    if (count($values) !== count($keys)) {
        if (count($values) > count($keys) && count($keys) > 0) {
            $values = array_slice($values, count($values) - count($keys));
        } else {
            return null;
        }
    }
    $row = [];
    foreach ($keys as $i => $key) {
        if ($key === "") {
            continue;
        }
        $row[$key] = $values[$i];
    }
    if (implode("", $row) === "") {
        return null;
    }
    if (isset($row["clave_catastral"])) {
        $row["clave_catastral"] = strtoupper(preg_replace("/\s+/", "", $row["clave_catastral"]) ?? "");
    }
    return $row;
}

/**
 * @return array{found:bool,empty:bool,headers:list<string>,rows:list<array<string,string>>,message:string}
 */
function mxli_deslindes_parse_grid(string $html): array
{
    $result = ["found" => false, "empty" => false, "headers" => [], "rows" => [], "message" => ""];

    // DevExpress ASPxGridView
    if (preg_match_all('/<td\b[^>]*class="[^"]*\bdxgvHeader(?!Panel)[^"]*"[^>]*>/i', $html, $hm, PREG_OFFSET_CAPTURE)) {
        $starts = array_map(static fn($x) => (int)$x[1], $hm[0]);
        $headers = [];
        foreach ($starts as $i => $start) {
            $end = $starts[$i + 1] ?? ($start + 2000);
            $chunk = substr($html, $start, $end - $start);
            if (preg_match('/DXFilterRow|DXDataRow|DXEmptyRow|dxgvFilterRow|dxgvDataRow|dxgvEmptyDataRow/i', $chunk, $cut, PREG_OFFSET_CAPTURE)) {
                $chunk = substr($chunk, 0, (int)$cut[0][1]);
            }
            $headers[] = mxli_deslindes_text($chunk);
        }
        $result["headers"] = $headers;
        $keys = array_map(static fn($h) => $h === "" ? "" : mxli_deslindes_column_key($h), $headers);
        $result["found"] = true;

        if (preg_match_all('~<tr\b(?=[^>]*(?:DXDataRow|dxgvDataRow))[^>]*>(.*?)</tr>~is', $html, $rm)) {
            foreach ($rm[1] as $rowHtml) {
                $cells = [];
                if (preg_match_all('~<td\b([^>]*)>(.*?)</td>~is', $rowHtml, $cm, PREG_SET_ORDER)) {
                    foreach ($cm as $cell) {
                        if (preg_match('/dxgvIndentCell|dxgvDetailButton|dxgvGroupSpacer/i', $cell[1])) {
                            continue;
                        }
                        $cells[] = $cell[2];
                    }
                }
                $row = mxli_deslindes_row_from_cells($cells, $keys);
                if ($row !== null) {
                    $result["rows"][] = $row;
                }
            }
        }
        if (preg_match('~<tr\b(?=[^>]*(?:DXEmptyRow|dxgvEmptyDataRow))[^>]*>(.*?)</tr>~is', $html, $em)) {
            $result["empty"] = $result["rows"] === [];
            $result["message"] = mxli_deslindes_text($em[1]);
        }
        return $result;
    }

    // Tabla HTML simple (asp:GridView u otra) con encabezado CLAVECATASTRAL
    $pos = stripos($html, "CLAVECATASTRAL");
    if ($pos === false) {
        $pos = stripos($html, "CLAVE CATASTRAL");
    }
    if ($pos === false) {
        return $result;
    }
    $tableStart = strripos(substr($html, 0, $pos), "<table");
    if ($tableStart === false) {
        return $result;
    }
    $tableEnd = stripos($html, "</table>", $pos);
    $table = substr($html, $tableStart, $tableEnd === false ? null : $tableEnd - $tableStart);
    $result["found"] = true;
    $keys = [];
    if (preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is', $table, $rm)) {
        foreach ($rm[1] as $rowHtml) {
            preg_match_all('~<t([hd])\b[^>]*>(.*?)</t\1>~is', $rowHtml, $cm);
            $cells = $cm[2] ?? [];
            if ($keys === []) {
                $texts = array_map("mxli_deslindes_text", $cells);
                if (in_array("FOLIO", array_map("mxli_deslindes_fold", $texts), true)) {
                    $result["headers"] = $texts;
                    $keys = array_map("mxli_deslindes_column_key", $texts);
                }
                continue;
            }
            $row = mxli_deslindes_row_from_cells($cells, $keys);
            if ($row !== null) {
                $result["rows"][] = $row;
            }
        }
    }
    $result["empty"] = $result["rows"] === [];
    return $result;
}

/* ------------------------------------------------------------------ */
/* Sesión del portal                                                   */
/* ------------------------------------------------------------------ */

function mxli_deslindes_credentials_hash(): string
{
    return hash("sha256", mxli_config("deslindes_portal_user", "") . "\0" . mxli_config("deslindes_portal_pass", ""));
}

/** @param array<string,string> $jar */
function mxli_deslindes_login(array &$jar, string $loginHtml, string $loginUrl): void
{
    $failPath = mxli_deslindes_state_dir() . "/login-fail.json";
    $fail = json_decode((string)@file_get_contents($failPath), true);
    if (
        is_array($fail)
        && ($fail["hash"] ?? "") === mxli_deslindes_credentials_hash()
        && (time() - (int)($fail["at"] ?? 0)) < MXLI_DESLINDES_LOGIN_BACKOFF
    ) {
        $mins = (int)ceil((MXLI_DESLINDES_LOGIN_BACKOFF - (time() - (int)$fail["at"])) / 60);
        throw new MxliDeslindesError(
            "login_rejected",
            "El portal rechazó el usuario/contraseña configurados (" . ($fail["msg"] ?? "credenciales") . "). "
            . "Se reintentará en {$mins} min o al cambiar la contraseña en config.local.php."
        );
    }

    $fields = mxli_deslindes_form_fields($loginHtml);
    $userField = "Usuario";
    $passField = "Contrasena";
    if (preg_match_all("/<input\b[^>]*>/i", $loginHtml, $m)) {
        foreach ($m[0] as $tag) {
            $a = mxli_deslindes_attrs($tag);
            if (strtolower($a["type"] ?? "") === "password" && ($a["name"] ?? "") !== "") {
                $passField = $a["name"];
            }
        }
    }
    $texts = mxli_deslindes_text_inputs($loginHtml);
    foreach ($texts as $in) {
        if (preg_match("/usuario|user|login/i", $in["name"])) {
            $userField = $in["name"];
            break;
        }
    }
    $fields[$userField] = mxli_config("deslindes_portal_user", "");
    $fields[$passField] = mxli_config("deslindes_portal_pass", "");

    $submit = mxli_deslindes_find_submit($loginHtml, "/entrar|ingresar|login|acceder/i", false);
    $fields["__EVENTTARGET"] = $submit["target"] !== "" || $submit["extra"] !== [] ? $submit["target"] : "Entrar";
    $fields["__EVENTARGUMENT"] = "";
    $fields = array_merge($fields, $submit["extra"]);

    $res = mxli_deslindes_http(mxli_deslindes_form_action($loginHtml, $loginUrl), $jar, $fields, $loginUrl);
    switch (mxli_deslindes_classify_login_response($res)) {
        case "rejected":
            $msg = mxli_deslindes_portal_message($res["body"]) ?: "El usuario o contraseña son incorrectos";
            @file_put_contents(
                $failPath,
                json_encode(["hash" => mxli_deslindes_credentials_hash(), "at" => time(), "msg" => $msg], JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
            throw new MxliDeslindesError("login_rejected", "El portal rechazó el usuario/contraseña configurados: " . $msg);
        case "login_page":
            throw new MxliDeslindesError(
                "login_not_accepted",
                "El portal regresó a la pantalla de inicio de sesión sin indicar error.",
                mxli_deslindes_save_debug("login", $res)
            );
        case "password_change":
            throw new MxliDeslindesError(
                "password_change_required",
                "El portal pide cambiar la contraseña de la cuenta configurada. Entre al portal con esa cuenta, "
                . "cambie la contraseña y actualice deslindes_portal_pass en config.local.php.",
                mxli_deslindes_save_debug("login", $res)
            );
    }
    @unlink($failPath);
}

/**
 * Abre Deslindes.aspx con sesión (inicia sesión si hace falta).
 * @param array<string,string> $jar
 * @return array{status:int,body:string,url:string}
 */
function mxli_deslindes_open_page(array &$jar): array
{
    $pageUrl = mxli_deslindes_base_url() . "Deslindes.aspx";
    $res = mxli_deslindes_http($pageUrl, $jar);
    if (mxli_deslindes_is_login_page($res["body"], $res["url"])) {
        mxli_deslindes_login($jar, $res["body"], $res["url"]);
        $res = mxli_deslindes_http($pageUrl, $jar);
        if (mxli_deslindes_is_login_page($res["body"], $res["url"])) {
            throw new MxliDeslindesError(
                "session_lost",
                "El portal aceptó el inicio de sesión pero no conservó la sesión al abrir Deslindes.",
                mxli_deslindes_save_debug("deslindes", $res)
            );
        }
    }
    if (mxli_deslindes_is_password_change($res)) {
        throw new MxliDeslindesError(
            "password_change_required",
            "El portal pide cambiar la contraseña de la cuenta configurada. Entre al portal con esa cuenta, "
            . "cambie la contraseña y actualice deslindes_portal_pass en config.local.php.",
            mxli_deslindes_save_debug("deslindes", $res)
        );
    }
    if ($res["status"] < 200 || $res["status"] >= 300 || !str_ends_with(mxli_deslindes_url_path($res["url"]), "/deslindes.aspx")) {
        throw new MxliDeslindesError(
            "unexpected_page",
            "El portal mostró una página inesperada (HTTP " . $res["status"] . ", " . mxli_deslindes_url_path($res["url"]) . ") en lugar de Deslindes.",
            mxli_deslindes_save_debug("deslindes", $res)
        );
    }
    return $res;
}

/* ------------------------------------------------------------------ */
/* Consulta                                                            */
/* ------------------------------------------------------------------ */

function mxli_deslindes_cache_path(string $tramite): string
{
    return mxli_deslindes_state_dir() . "/t-" . hash("sha256", $tramite) . ".json";
}

/**
 * @return array<string,mixed>
 */
function mxli_deslindes_consultar(string $rawTramite, bool $useCache = true): array
{
    $tramite = mxli_deslindes_normalize_tramite($rawTramite);
    if ($tramite === "" || strlen($tramite) > 12) {
        throw new MxliDeslindesError("bad_request", "Indique un No. de Trámite numérico.");
    }
    if (!mxli_deslindes_configured()) {
        throw new MxliDeslindesError(
            "not_configured",
            "La consulta de deslindes no está configurada: faltan las credenciales del portal en config.local.php."
        );
    }

    $cachePath = mxli_deslindes_cache_path($tramite);
    if ($useCache && is_file($cachePath) && (time() - (int)@filemtime($cachePath)) < MXLI_DESLINDES_CACHE_TTL) {
        $cached = json_decode((string)@file_get_contents($cachePath), true);
        if (is_array($cached)) {
            $cached["cache"] = true;
            return $cached;
        }
    }

    $lock = @fopen(mxli_deslindes_state_dir() . "/portal.lock", "c");
    if ($lock !== false) {
        flock($lock, LOCK_EX);
    }
    try {
        $jar = mxli_deslindes_jar_load();
        $res = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $page = mxli_deslindes_open_page($jar);
            $field = mxli_deslindes_find_tramite_field($page["body"]);
            $submit = mxli_deslindes_find_submit($page["body"]);
            if ($field === null) {
                throw new MxliDeslindesError(
                    "form_unknown",
                    "No se encontró el campo No. de Trámite en Deslindes.aspx.",
                    mxli_deslindes_save_debug("deslindes", $page)
                );
            }
            if ($submit["target"] === "" && $submit["extra"] === []) {
                throw new MxliDeslindesError(
                    "form_unknown",
                    "No se encontró el botón Consultar en Deslindes.aspx.",
                    mxli_deslindes_save_debug("deslindes", $page)
                );
            }
            $fields = mxli_deslindes_form_fields($page["body"]);
            $fields[$field] = $tramite;
            $fields["__EVENTTARGET"] = $submit["target"];
            $fields["__EVENTARGUMENT"] = "";
            $fields = array_merge($fields, $submit["extra"]);

            $res = mxli_deslindes_http(
                mxli_deslindes_form_action($page["body"], $page["url"]),
                $jar,
                $fields,
                $page["url"]
            );
            if (!mxli_deslindes_is_login_page($res["body"], $res["url"])) {
                break;
            }
            $jar = [];
        }
    } finally {
        if (isset($jar)) {
            mxli_deslindes_jar_save($jar);
        }
        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    if ($res === null || mxli_deslindes_is_login_page($res["body"], $res["url"])) {
        throw new MxliDeslindesError(
            "session_lost",
            "La sesión del portal expiró y no se pudo renovar.",
            $res === null ? null : mxli_deslindes_save_debug("consulta", $res)
        );
    }
    if ($res["status"] < 200 || $res["status"] >= 300) {
        throw new MxliDeslindesError(
            "unexpected_page",
            "El portal respondió HTTP " . $res["status"] . " al consultar.",
            mxli_deslindes_save_debug("consulta", $res)
        );
    }

    $grid = mxli_deslindes_parse_grid($res["body"]);
    if (!$grid["found"]) {
        $msg = mxli_deslindes_portal_message($res["body"]);
        throw new MxliDeslindesError(
            "parse_error",
            $msg !== "" ? "El portal respondió: " . $msg : "No se encontró la tabla de resultados en la respuesta del portal.",
            mxli_deslindes_save_debug("consulta", $res)
        );
    }

    $result = [
        "tramite" => $tramite,
        "rows" => $grid["rows"],
        "total" => count($grid["rows"]),
        "headers" => $grid["headers"],
        "mensaje" => $grid["rows"] === []
            ? ($grid["message"] !== "" ? $grid["message"] : (mxli_deslindes_portal_message($res["body"]) ?: "Sin trámites para ese número."))
            : "",
        "consultado_en" => gmdate("c"),
        "fuente" => "portal_mexicali_deslindes",
        "cache" => false,
    ];
    if ($grid["rows"] !== []) {
        @file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    return $result;
}

/**
 * Concede map.deslindes a los roles de perito deslindador existentes en BD.
 * (admin lo recibe siempre vía mxli_permissions_for_role).
 */
function mxli_deslindes_grant_roles(?PDO $pdo = null): int
{
    $pdo = $pdo ?? mxli_db();
    if (!mxli_roles_table_exists()) {
        return 0;
    }
    $rows = $pdo->query("SELECT slug, name, permissions FROM app_roles")->fetchAll();
    $upd = $pdo->prepare(
        "UPDATE app_roles SET permissions = CAST(:p AS jsonb), updated_at = NOW() WHERE slug = :slug"
    );
    $changed = 0;
    foreach ($rows as $row) {
        $slug = (string)$row["slug"];
        $name = mb_strtolower((string)($row["name"] ?? ""), "UTF-8");
        if (!($slug === "perito_deslindador" || str_contains($slug, "deslind") || str_contains($name, "deslind"))) {
            continue;
        }
        $perms = mxli_decode_json_list($row["permissions"] ?? []);
        if (in_array("map.deslindes", $perms, true)) {
            continue;
        }
        $perms[] = "map.deslindes";
        $upd->execute(["p" => json_encode(array_values($perms), JSON_UNESCAPED_UNICODE), "slug" => $slug]);
        mxli_clear_role_cache($slug);
        $changed++;
    }
    return $changed;
}
