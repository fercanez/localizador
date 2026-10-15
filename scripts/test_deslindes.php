<?php
declare(strict_types=1);

/**
 * Prueba del proxy de deslindes.
 *
 *   php scripts/test_deslindes.php --selftest       Pruebas sin red con scripts/fixtures/*.html
 *   php scripts/test_deslindes.php archivo.html     Parsea un HTML guardado de Deslindes.aspx
 *   php scripts/test_deslindes.php --live 575617    Login + consulta real con config.local.php
 */

if (PHP_SAPI !== "cli") {
    exit(1);
}

$_SERVER["REQUEST_URI"] = $_SERVER["REQUEST_URI"] ?? "/scripts/test_deslindes.php";
require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/deslindes.php";

$arg = $argv[1] ?? "";
$out = static fn($v) => print(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

if ($arg === "--live") {
    if (!mxli_deslindes_configured()) {
        fwrite(STDERR, "Faltan deslindes_portal_user / deslindes_portal_pass en includes/config.local.php\n");
        exit(2);
    }
    try {
        $out(mxli_deslindes_consultar((string)($argv[2] ?? ""), false));
    } catch (MxliDeslindesError $e) {
        fwrite(STDERR, "[{$e->kind}] " . $e->getMessage() . "\n");
        if ($e->debugPath !== null) {
            fwrite(STDERR, "HTML devuelto por el portal (sin contraseñas): {$e->debugPath}\n");
        }
        if ($e->kind === "form_unknown") {
            $jar = mxli_deslindes_jar_load();
            $out(mxli_deslindes_describe_form(mxli_deslindes_open_page($jar)["body"]));
        }
        exit(1);
    }
    exit(0);
}

if ($arg === "--selftest") {
    $fx = __DIR__ . "/fixtures/";
    $base = "https://www.mexicali.gob.mx/webpub/consultacatastro/";
    $read = static fn(string $f) => mxli_deslindes_to_utf8((string)file_get_contents($fx . $f));
    $rejected = $read("login-rechazado.html");
    $loginSinMensaje = preg_replace('~(<span[^>]*id="Mensaje"[^>]*>).*?(</span>)~is', '$1$2', $rejected);
    $cambio = $read("login-cambio-contrasena.html");
    $mant = $read("portal-mantenimiento.html");
    $ok = $read("deslindes-muestra.html");
    $res = static fn(int $s, string $b, string $u) => ["status" => $s, "body" => $b, "url" => $base . $u];

    $checks = [
        "rechazo explícito → rejected" =>
            [mxli_deslindes_classify_login_response($res(200, $rejected, "Login.aspx")), "rejected"],
        "regresa a Login.aspx sin mensaje → login_page (sin bloqueo)" =>
            [mxli_deslindes_classify_login_response($res(200, $loginSinMensaje, "Login.aspx")), "login_page"],
        "página intermedia CambioContrasena.aspx → password_change" =>
            [mxli_deslindes_classify_login_response($res(200, $cambio, "CambioContrasena.aspx")), "password_change"],
        "contraseña expirada en otra URL → password_change" =>
            [mxli_deslindes_classify_login_response($res(200, $cambio, "Consulta.aspx")), "password_change"],
        "login exitoso (menú con 'Cambio de contraseña', sin password) → ok" =>
            [mxli_deslindes_classify_login_response($res(200, $ok, "Consulta.aspx")), "ok"],
        "HTTP 503 → mantenimiento" =>
            [mxli_deslindes_is_maintenance($res(503, "Service Unavailable", "Login.aspx")), true],
        "página de mantenimiento 200 → mantenimiento" =>
            [mxli_deslindes_is_maintenance($res(200, $mant, "Login.aspx")), true],
        "Deslindes normal → no es mantenimiento" =>
            [mxli_deslindes_is_maintenance($res(200, $ok, "Deslindes.aspx")), false],
        "grid: 1 fila con clave LO006001" =>
            [(mxli_deslindes_parse_grid($ok)["rows"][0]["clave_catastral"] ?? ""), "LO006001"],
        "formulario: campo txtTramite / botón btnConsultar" =>
            [mxli_deslindes_find_tramite_field($ok) . "|" . mxli_deslindes_find_submit($ok)["target"], "txtTramite|btnConsultar"],
        "real GET: campo/botón y estado cliente del ASPxGridView" =>
            [(static function () use ($read): string {
                $h = $read("deslindes-real-get.html");
                return mxli_deslindes_find_tramite_field($h) . "|"
                    . implode(",", array_keys(mxli_deslindes_find_submit($h)["extra"])) . "|"
                    . (str_starts_with(mxli_deslindes_form_fields($h)['ctl00$ContentPlaceHolder1$ASPxGridView'] ?? "", '{"selection"') ? "estado" : "sin estado");
            })(), 'ctl00$ContentPlaceHolder1$ASPxTxtTramite|ctl00$ContentPlaceHolder1$btnConsultar|estado'],
        "real sin resultados: grid vacío con mensaje" =>
            [(static function () use ($read): string {
                $g = mxli_deslindes_parse_grid($read("deslindes-real-vacio.html"));
                return count($g["rows"]) . "|" . $g["message"];
            })(), "0|Sin datos para mostrar"],
        "real con resultado (575700): 1 fila, clave WY054052" =>
            [(static function () use ($read): string {
                $g = mxli_deslindes_parse_grid($read("deslindes-real-resultado.html"));
                return count($g["rows"]) . "|" . ($g["rows"][0]["folio"] ?? "") . "|" . ($g["rows"][0]["clave_catastral"] ?? "")
                    . "|" . ($g["rows"][0]["estatus"] ?? "") . "|" . ($g["rows"][0]["fecha_termino"] ?? "");
            })(), "1|575700|WY054052|ENTREGA DE TRAMITE|29/12/2017"],
        "sanitizado: sin valores de password/hidden" =>
            [str_contains(mxli_deslindes_sanitize_html('<input type="password" name="Contrasena" value="secreto">'
                . '<input type="hidden" name="__VIEWSTATE" value="abc">'), "secreto")
                || str_contains(mxli_deslindes_sanitize_html('<input type="hidden" name="__VIEWSTATE" value="abc">'), "abc"), false],
    ];

    $GLOBALS["MXLI_CONFIG"]["deslindes_portal_url"] = "https://127.0.0.1:9/";
    $t0 = microtime(true);
    try {
        $jar = [];
        mxli_deslindes_http(mxli_deslindes_base_url() . "Deslindes.aspx", $jar);
        $kind = "sin error";
    } catch (MxliDeslindesError $e) {
        $kind = $e->kind;
    }
    $checks["portal inalcanzable → portal_down (" . round(microtime(true) - $t0, 1) . " s)"] = [$kind, "portal_down"];

    $fails = 0;
    foreach ($checks as $name => [$got, $want]) {
        $pass = $got === $want;
        $fails += $pass ? 0 : 1;
        echo ($pass ? "OK   " : "FALLA"), " ", $name, $pass ? "" : " (obtuvo " . var_export($got, true) . ")", "\n";
    }
    echo $fails === 0 ? "Todas las pruebas pasaron.\n" : "{$fails} prueba(s) fallaron.\n";
    exit($fails === 0 ? 0 : 1);
}

if ($arg === "" || !is_file($arg)) {
    fwrite(STDERR, "Uso: php scripts/test_deslindes.php --selftest | <archivo.html> | --live <tramite>\n");
    exit(2);
}

$html = mxli_deslindes_to_utf8((string)file_get_contents($arg));
$out([
    "form" => mxli_deslindes_describe_form($html),
    "grid" => mxli_deslindes_parse_grid($html),
    "mensaje_portal" => mxli_deslindes_portal_message($html),
]);
