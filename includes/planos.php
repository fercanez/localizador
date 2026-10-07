<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function mxli_planos_normalize_homoclave(string $raw): string
{
    return strtoupper(preg_replace("/\s+/", "", $raw) ?? "");
}

function mxli_planos_valid_homoclave(string $clave): bool
{
    return (bool)preg_match('/^[A-Z][A-Z0-9]{1,3}$/', $clave);
}

function mxli_planos_doc_url(): string
{
    $url = trim(mxli_config(
        "planos_doc_url",
        "https://www.mexicali.gob.mx/webpub/consultacatastro/documentacion.aspx?"
    ));
    if ($url === "") {
        $url = "https://www.mexicali.gob.mx/webpub/consultacatastro/documentacion.aspx?";
    }
    return $url;
}

/**
 * Carpeta GROMA (consulta de planos). El Ayuntamiento lista
 * \\SCATASTRO\GROMA\{homoclave}\*.pdf|dwg
 */
function mxli_planos_dir(): string
{
    $configured = trim(mxli_config("planos_dir", ""));
    $candidates = [];
    if ($configured !== "") {
        $candidates[] = $configured;
    }
    $candidates[] = "\\\\SCATASTRO\\GROMA";
    $candidates[] = "/mnt/groma";
    $candidates[] = "/mnt/scatastro/GROMA";
    $candidates[] = "/var/www/catastro/planos";

    foreach ($candidates as $dir) {
        if ($dir !== "" && is_dir($dir) && is_readable($dir)) {
            return $dir;
        }
    }
    return $configured;
}

function mxli_planos_index_dir(): string
{
    return dirname(__DIR__) . "/data/planos";
}

function mxli_planos_ext(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ["pdf", "dwg", "dxf", "jpg", "jpeg"], true) ? $ext : "";
}

/**
 * @return list<array{name:string,rel:string,ext:string,size:int,mtime:int}>
 */
function mxli_planos_scan_dir(string $dir, string $prefix, int $max = 2500): array
{
    $out = [];
    if ($dir === "" || !is_dir($dir)) {
        return $out;
    }

    $root = rtrim($dir, "/\\");
    $targets = [];
    $direct = $root . DIRECTORY_SEPARATOR . $prefix;
    if (is_dir($direct)) {
        $targets[] = $direct;
    }
    foreach (glob($root . DIRECTORY_SEPARATOR . $prefix . " *", GLOB_ONLYDIR | GLOB_NOSORT) ?: [] as $extra) {
        $targets[] = $extra;
    }

    $seen = [];
    foreach ($targets as $target) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
            );
        } catch (Throwable $e) {
            continue;
        }
        foreach ($iterator as $file) {
            if (count($out) >= $max) {
                break 2;
            }
            if (!$file->isFile()) {
                continue;
            }
            $ext = mxli_planos_ext($file->getFilename());
            if ($ext === "") {
                continue;
            }
            $real = $file->getPathname();
            $key = strtolower(str_replace("\\", "/", $real));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $fromRoot = ltrim(str_replace("\\", "/", substr($real, strlen($root))), "/");
            if ($fromRoot === "" || $fromRoot === str_replace("\\", "/", $real)) {
                $fromRoot = $prefix . "/" . $file->getFilename();
            }
            $out[] = [
                "name" => $file->getFilename(),
                "rel" => $fromRoot,
                "ext" => $ext,
                "size" => (int)$file->getSize(),
                "mtime" => (int)$file->getMTime(),
            ];
        }
    }

    usort($out, static function ($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    });
    return $out;
}

/**
 * Índice generado desde \\SCATASTRO\GROMA (para el servidor Linux).
 *
 * @return list<array{name:string,rel:string,ext:string,size:int,mtime:int}>
 */
function mxli_planos_scan_index(string $prefix): array
{
    $path = mxli_planos_index_dir() . "/" . $prefix . ".json";
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === "") {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    if (isset($data["name"]) || isset($data["rel"])) {
        $data = [$data];
    }
    $out = [];
    $seen = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = (string)($row["name"] ?? "");
        $rel = mxli_planos_safe_rel((string)($row["rel"] ?? ($prefix . "/" . $name)));
        $ext = mxli_planos_ext($name !== "" ? $name : $rel);
        if ($rel === "" || $ext === "") {
            continue;
        }
        $key = strtolower($rel);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = [
            "name" => $name !== "" ? $name : basename($rel),
            "rel" => $rel,
            "ext" => $ext,
            "size" => (int)($row["size"] ?? 0),
            "mtime" => (int)($row["mtime"] ?? 0),
        ];
    }
    usort($out, static function ($a, $b) {
        return strcasecmp($a["name"], $b["name"]);
    });
    return $out;
}

/**
 * @return array{0: list<array{name:string,rel:string,ext:string,size:int,mtime:int}>, 1: string}
 */
function mxli_planos_search(string $prefix): array
{
    $dir = mxli_planos_dir();
    $files = mxli_planos_scan_dir($dir, $prefix);
    if ($files) {
        return [$files, "dir"];
    }
    $indexed = mxli_planos_scan_index($prefix);
    if ($indexed) {
        return [$indexed, "index"];
    }
    return [[], ($dir !== "" && is_dir($dir)) ? "dir" : "none"];
}

function mxli_planos_colonia_hint(string $prefix): string
{
    try {
        require_once __DIR__ . "/padron.php";
        $pdo = mxli_geonode_db();
        $columns = mxli_padron_column_names($pdo);
        $claveCol = mxli_padron_pick_column($columns, mxli_padron_field_aliases()["clave"]);
        $colCol = mxli_padron_pick_column($columns, mxli_padron_field_aliases()["colonia"]);
        if ($claveCol === null || $colCol === null) {
            return "";
        }
        $table = mxli_padron_qualified();
        $sql = "SELECT TRIM(BOTH FROM {$colCol}::text) AS colonia
                FROM {$table}
                WHERE replace(upper(trim(COALESCE({$claveCol}::text, ''))), ' ', '') LIKE :p
                  AND TRIM(BOTH FROM COALESCE({$colCol}::text, '')) <> ''
                GROUP BY 1
                ORDER BY COUNT(*) DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["p" => $prefix . "%"]);
        $row = $stmt->fetch();
        return is_array($row) ? trim((string)($row["colonia"] ?? "")) : "";
    } catch (Throwable $e) {
        return "";
    }
}

function mxli_planos_external_url(string $rel): string
{
    $rel = str_replace("\\", "/", $rel);
    $rel = ltrim($rel, "/");
    $parts = explode("/", $rel);
    $encoded = array_map("rawurlencode", $parts);
    return mxli_planos_doc_url() . implode("/", $encoded);
}

function mxli_planos_safe_rel(string $rel): string
{
    $rel = str_replace("\\", "/", $rel);
    $rel = str_replace("\0", "", $rel);
    $rel = ltrim($rel, "/");
    if ($rel === "") {
        return "";
    }
    $parts = explode("/", $rel);
    if (count($parts) < 2 || count($parts) > 8) {
        return "";
    }
    foreach ($parts as $part) {
        if ($part === "" || $part === "." || $part === "..") {
            return "";
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.,;+&\'°#()áéíóúüÁÉÍÓÚÜñÑ.\-]*$/u', $part)) {
            return "";
        }
    }
    if (mxli_planos_ext($parts[count($parts) - 1]) === "") {
        return "";
    }
    return implode("/", $parts);
}

function mxli_planos_mime(string $ext): string
{
    $map = [
        "pdf" => "application/pdf",
        "dwg" => "application/acad",
        "dxf" => "image/vnd.dxf",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
    ];
    return $map[$ext] ?? "application/octet-stream";
}

/**
 * Flags de marca de agua / descarga / impresión (igual que expediente).
 *
 * @return array{
 *   puedeVer:bool,modo:int,activa:bool,puedeQuitar:bool,forzada:bool,
 *   puedeDescargar:bool,puedeImprimir:bool
 * }
 */
function mxli_planos_flags(?array $user = null): array
{
    $user = $user ?? mxli_current_user();
    $canView = mxli_can("map.planos", $user);
    $canClean = mxli_can("map.planos.nowatermark", $user);
    $canDownload = mxli_can("map.planos.download", $user);
    $canPrint = mxli_can("map.planos.print", $user);

    $forzada = $canView && !$canClean;
    $off = !empty($_SESSION["mxli_planos_wm_off"]);
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

function mxli_planos_files_dir(): string
{
    return mxli_planos_index_dir() . "/files";
}

function mxli_planos_rel_key(string $rel): string
{
    $rel = str_replace("\\", "/", $rel);
    $rel = ltrim($rel, "/");
    return sha1(mb_strtolower($rel, "UTF-8"));
}

function mxli_planos_fold_name(string $s): string
{
    $s = mb_strtolower($s, "UTF-8");
    return strtr($s, [
        "á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u",
        "Á" => "a", "É" => "e", "Í" => "i", "Ó" => "o", "Ú" => "u", "Ü" => "u",
        "ñ" => "n", "Ñ" => "n",
    ]);
}

/**
 * @return array{body:string,name:string,ext:string,mime:string}|null
 */
function mxli_planos_read_path(string $path, string $name, string $ext): ?array
{
    if ($path === "" || !is_file($path) || !is_readable($path)) {
        return null;
    }
    $body = @file_get_contents($path);
    if ($body === false || $body === "") {
        return null;
    }
    return [
        "body" => $body,
        "name" => $name,
        "ext" => $ext,
        "mime" => mxli_planos_mime($ext),
    ];
}

/**
 * Lee un plano desde una carpeta local (caché o share GROMA).
 *
 * @return array{body:string,name:string,ext:string,mime:string}|null
 */
function mxli_planos_read_local_file(string $root, string $rel): ?array
{
    if ($root === "" || !is_dir($root)) {
        return null;
    }
    $name = basename($rel);
    $ext = mxli_planos_ext($name);
    if ($ext === "") {
        return null;
    }
    $root = rtrim(str_replace("\\", "/", $root), "/");
    $rel = ltrim(str_replace("\\", "/", $rel), "/");
    $homo = explode("/", $rel)[0];
    $key = mxli_planos_rel_key($rel);

    $candidates = [
        $root . "/" . $rel,
        $root . "/" . $homo . "/" . $key . "." . $ext,
        $root . "/" . $key . "." . $ext,
    ];
    foreach ($candidates as $path) {
        $got = mxli_planos_read_path($path, $name, $ext);
        if ($got !== null) {
            return $got;
        }
    }

    $dir = $root . "/" . $homo;
    if (is_dir($dir)) {
        $want = mxli_planos_fold_name($name);
        $list = @scandir($dir);
        if (is_array($list)) {
            foreach ($list as $fn) {
                if ($fn === "." || $fn === "..") {
                    continue;
                }
                if (mxli_planos_fold_name($fn) === $want) {
                    $got = mxli_planos_read_path($dir . "/" . $fn, $name, $ext);
                    if ($got !== null) {
                        return $got;
                    }
                }
            }
        }
    }
    return null;
}

/**
 * @return array{body:string,name:string,ext:string,mime:string}|null
 */
function mxli_planos_fetch_bytes(string $rel): ?array
{
    $rel = mxli_planos_safe_rel($rel);
    if ($rel === "") {
        return null;
    }
    $name = basename($rel);
    $ext = mxli_planos_ext($name);
    if ($ext === "") {
        return null;
    }

    $cached = mxli_planos_read_local_file(mxli_planos_files_dir(), $rel);
    if ($cached !== null) {
        return $cached;
    }

    $fromShare = mxli_planos_read_local_file(mxli_planos_dir(), $rel);
    if ($fromShare !== null) {
        return $fromShare;
    }

    // Documentacion.aspx solo busca en CATASTRO\CATASTRO y agrega .pdf:
    // no puede entregar DWG/DXF/JPG de GROMA. Los PDF de GROMA tampoco
    // están ahí; se sirven desde data/planos/files.
    if ($ext !== "pdf") {
        return null;
    }

    $url = mxli_planos_external_url($rel);
    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => 60,
            "ignore_errors" => true,
            "header" => "Accept: */*\r\nUser-Agent: Geomexicali-Localizacion/1.0\r\n",
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
    $trim = ltrim($body);
    if (stripos($trim, "<html") !== false || stripos($trim, "<!DOCTYPE") !== false) {
        return null;
    }
    if (strncmp($trim, "%PDF", 4) !== 0) {
        return null;
    }
    return [
        "body" => $body,
        "name" => $name,
        "ext" => $ext,
        "mime" => mxli_planos_mime($ext),
    ];
}

/**
 * Concede map.planos a admin y a roles de perito/deslindador existentes.
 */
function mxli_planos_grant_roles(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = $pdo ?? mxli_db();
        if (!mxli_roles_table_exists()) {
            return;
        }
        $rows = $pdo->query("SELECT slug, name, permissions FROM app_roles")->fetchAll();
        $upd = $pdo->prepare(
            "UPDATE app_roles SET permissions = CAST(:p AS jsonb), updated_at = NOW() WHERE slug = :slug"
        );
        foreach ($rows as $row) {
            $slug = (string)$row["slug"];
            $name = mb_strtolower((string)($row["name"] ?? ""), "UTF-8");
            $perms = mxli_decode_json_list($row["permissions"] ?? []);
            $before = $perms;
            $should = $slug === "admin"
                || $slug === "perito_deslindador"
                || str_contains($slug, "deslind")
                || str_contains($name, "deslind")
                || (str_contains($name, "perito") && str_contains($name, "deslind"));
            if (!$should) {
                continue;
            }
            if (!in_array("map.planos", $perms, true)) {
                $perms[] = "map.planos";
            }
            if ($slug === "admin") {
                foreach (array_keys(mxli_tool_permission_defs()) as $p) {
                    if (!in_array($p, $perms, true)) {
                        $perms[] = $p;
                    }
                }
            }
            $perms = array_values(array_unique($perms));
            if ($perms === $before) {
                continue;
            }
            $upd->execute([
                "p" => json_encode($perms, JSON_UNESCAPED_UNICODE),
                "slug" => $slug,
            ]);
            if (function_exists("mxli_clear_role_cache")) {
                mxli_clear_role_cache($slug);
            }
        }

        $seeds = mxli_default_role_seeds();
        foreach ($seeds as $seed) {
            if ($seed["slug"] !== "perito_deslindador") {
                continue;
            }
            $ins = $pdo->prepare(
                "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
                 VALUES (:slug, :name, :description, TRUE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
                 ON CONFLICT (slug) DO UPDATE SET
                   name = EXCLUDED.name,
                   description = EXCLUDED.description,
                   updated_at = NOW()"
            );
            $ins->execute([
                "slug" => $seed["slug"],
                "name" => $seed["name"],
                "description" => $seed["description"],
                "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
                "layers" => json_encode(array_values($seed["allowed_layers"]), JSON_UNESCAPED_UNICODE),
            ]);
        }
    } catch (Throwable $e) {
        // no bloquear la consulta si el alta de roles falla
    }
}
