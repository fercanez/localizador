<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function mxli_inspections_upload_root(): string
{
    return dirname(__DIR__) . "/uploads/inspections";
}

function mxli_inspection_area_labels(): array
{
    return [
        "urbano" => "Control Urbano",
        "catastro" => "Catastro",
    ];
}

function mxli_normalize_inspection_area(mixed $value): string
{
    $area = strtolower(trim((string)$value));
    if ($area === "catastro") {
        return "catastro";
    }
    return "urbano";
}

function mxli_inspection_type_labels(): array
{
    return [
        "lic_construccion" => "Lic. Construcción",
        "imagen_urbana" => "Imagen Urbana",
        "uso_suelo" => "Uso de Suelo",
        "casetas_de_fraccs" => "Casetas de Fraccs.",
        "otro" => "Otro",
    ];
}

function mxli_normalize_inspection_types(mixed $value): string
{
    $allowed = array_keys(mxli_inspection_type_labels());
    $parts = [];
    if (is_array($value)) {
        foreach ($value as $v) {
            $parts[] = (string)$v;
        }
    } else {
        $raw = trim((string)$value);
        if ($raw !== "") {
            $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        }
    }
    $out = [];
    foreach ($parts as $p) {
        $key = strtolower(trim((string)$p));
        $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? "";
        if ($key !== "" && in_array($key, $allowed, true) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return implode(",", $out);
}

function mxli_inspection_types_list(string $csv): array
{
    $n = mxli_normalize_inspection_types($csv);
    if ($n === "") {
        return [];
    }
    return explode(",", $n);
}

function mxli_inspection_types_display(string $csv): string
{
    $labels = mxli_inspection_type_labels();
    $parts = [];
    foreach (mxli_inspection_types_list($csv) as $key) {
        $parts[] = $labels[$key] ?? $key;
    }
    return implode(" · ", $parts);
}

function mxli_ensure_inspections_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_inspection_photos (
            id              SERIAL PRIMARY KEY,
            clavecatas      VARCHAR(32)  NOT NULL,
            photo_path      VARCHAR(500) NOT NULL,
            notes           TEXT         NOT NULL DEFAULT '',
            lat             DOUBLE PRECISION,
            lng             DOUBLE PRECISION,
            captured_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            created_by      INTEGER      REFERENCES app_users(id) ON DELETE SET NULL,
            updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inspection_photos_clave ON app_inspection_photos (clavecatas)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inspection_photos_user ON app_inspection_photos (created_by)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inspection_photos_date ON app_inspection_photos (captured_at DESC)");
    $pdo->exec("ALTER TABLE app_inspection_photos ADD COLUMN IF NOT EXISTS inspection_types VARCHAR(120) NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE app_inspection_photos ADD COLUMN IF NOT EXISTS inspection_area VARCHAR(20) NOT NULL DEFAULT 'urbano'");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inspection_photos_area ON app_inspection_photos (inspection_area)");

    $root = mxli_inspections_upload_root();
    if (!is_dir($root)) {
        mkdir($root, 0755, true);
    }

    $done = true;
}

function mxli_normalize_inspection_clave(mixed $value): string
{
    return strtoupper(preg_replace('/\s+/', '', (string)$value));
}

function mxli_normalize_inspection_notes(mixed $value): string
{
    $text = trim((string)$value);
    if ($text === "") {
        return "";
    }
    return function_exists("mb_strtoupper") ? mb_strtoupper($text, "UTF-8") : strtoupper($text);
}

function mxli_can_inspection_manage_urbano(?array $user = null): bool
{
    return mxli_can("inspections.manage", $user) || mxli_can("users.manage", $user);
}

function mxli_can_inspection_manage_catastro(?array $user = null): bool
{
    return mxli_can("inspections.catastro.manage", $user) || mxli_can("users.manage", $user);
}

/** Compat: manage Control Urbano (histórico). */
function mxli_can_inspection_manage(?array $user = null): bool
{
    return mxli_can_inspection_manage_urbano($user);
}

function mxli_can_capture_inspection_area(string $area, ?array $user = null): bool
{
    $area = mxli_normalize_inspection_area($area);
    if ($area === "catastro") {
        return mxli_can("map.inspect.catastro", $user) || mxli_can_inspection_manage_catastro($user);
    }
    return mxli_can("map.inspect", $user) || mxli_can_inspection_manage_urbano($user);
}

function mxli_can_manage_inspection_area(string $area, ?array $user = null): bool
{
    $area = mxli_normalize_inspection_area($area);
    return $area === "catastro"
        ? mxli_can_inspection_manage_catastro($user)
        : mxli_can_inspection_manage_urbano($user);
}

/** Áreas que el usuario puede ver/capturar. */
function mxli_inspection_areas_for_user(?array $user = null): array
{
    $areas = [];
    if (mxli_can("map.inspect", $user) || mxli_can_inspection_manage_urbano($user)) {
        $areas[] = "urbano";
    }
    if (mxli_can("map.inspect.catastro", $user) || mxli_can_inspection_manage_catastro($user)) {
        $areas[] = "catastro";
    }
    return $areas;
}

function mxli_can_view_inspections_map_layer(?array $user = null): bool
{
    return mxli_inspection_areas_for_user($user) !== [];
}

function mxli_can_access_inspection_row(array $row, ?array $user = null): bool
{
    $user = $user ?? mxli_current_user();
    if ($user === null) {
        return false;
    }
    $area = mxli_normalize_inspection_area($row["inspection_area"] ?? "urbano");
    if (!in_array($area, mxli_inspection_areas_for_user($user), true)) {
        return false;
    }
    if (mxli_can_manage_inspection_area($area, $user)) {
        return true;
    }
    if (!mxli_can_capture_inspection_area($area, $user)) {
        return false;
    }
    $ownerId = (int)($row["created_by"] ?? 0);
    return $ownerId > 0 && $ownerId === (int)($user["id"] ?? 0);
}

function mxli_public_inspection_row(array $row, ?array $user = null): array
{
    $id = (int)$row["id"];
    $latRaw = $row["lat"] ?? null;
    $lngRaw = $row["lng"] ?? null;
    $lat = is_numeric($latRaw) ? (float)$latRaw : null;
    $lng = is_numeric($lngRaw) ? (float)$lngRaw : null;
    if ($lat !== null && ($lat < -90 || $lat > 90)) {
        $lat = null;
    }
    if ($lng !== null && ($lng < -180 || $lng > 180)) {
        $lng = null;
    }

    $typesCsv = mxli_normalize_inspection_types($row["inspection_types"] ?? "");
    $area = mxli_normalize_inspection_area($row["inspection_area"] ?? "urbano");
    $areaLabels = mxli_inspection_area_labels();

    return [
        "id" => $id,
        "clavecatas" => (string)$row["clavecatas"],
        "notes" => (string)($row["notes"] ?? ""),
        "inspection_area" => $area,
        "area_label" => $areaLabels[$area] ?? $area,
        "inspection_types" => $typesCsv,
        "types" => mxli_inspection_types_list($typesCsv),
        "types_label" => mxli_inspection_types_display($typesCsv),
        "lat" => $lat,
        "lng" => $lng,
        "captured_at" => $row["captured_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
        "created_by" => isset($row["created_by"]) ? (int)$row["created_by"] : null,
        "created_by_name" => (string)($row["created_by_name"] ?? $row["username"] ?? ""),
        "photo_url" => "api/inspection-photo.php?id=" . $id,
    ];
}

function mxli_inspection_photo_abs_path(string $relativePath): string
{
    $relativePath = str_replace("\\", "/", trim($relativePath));
    if ($relativePath === "" || str_contains($relativePath, "..")) {
        throw new InvalidArgumentException("Ruta de foto inválida");
    }
    return mxli_inspections_upload_root() . "/" . ltrim($relativePath, "/");
}

function mxli_delete_inspection_file(string $relativePath): void
{
    try {
        $abs = mxli_inspection_photo_abs_path($relativePath);
    } catch (Throwable $e) {
        return;
    }
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/** Paleta de colores por inspector (misma que js/inspeccionesCapa.js). */
function mxli_inspection_user_colors(): array
{
    return [
        "#9b2f55",
        "#1a5fd0",
        "#2f7d4b",
        "#e67e22",
        "#8e44ad",
        "#16a085",
        "#c0392b",
        "#2980b9",
        "#d35400",
        "#27ae60",
        "#6c3483",
        "#117a65",
        "#b7950b",
        "#5d6d7e",
        "#922b21",
    ];
}

/** Color estable por usuario (misma lógica que el mapa). */
function mxli_inspection_color_for_user(?int $userId, string $userName = ""): string
{
    $key = $userId !== null && $userId > 0 ? (string)$userId : trim($userName);
    if ($key === "") {
        $key = "0";
    }
    $hash = 0;
    $len = strlen($key);
    for ($i = 0; $i < $len; $i++) {
        $hash = ($hash * 31 + ord($key[$i])) & 0xffffffff;
    }
    $colors = mxli_inspection_user_colors();
    return $colors[$hash % count($colors)];
}

/** Hex #RRGGBB → color KML AABBGGRR. */
function mxli_kml_color_from_hex(string $hex, string $alpha = "ff"): string
{
    $hex = ltrim(trim($hex), "#");
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = "9b2f55";
    }
    $rr = strtolower(substr($hex, 0, 2));
    $gg = strtolower(substr($hex, 2, 2));
    $bb = strtolower(substr($hex, 4, 2));
    $aa = strtolower($alpha);
    return $aa . $bb . $gg . $rr;
}

/**
 * PNG 32×32: círculo (urbano) o triángulo (catastro), con borde blanco.
 * Requiere extensión GD.
 */
function mxli_inspection_marker_png(string $hex, string $shape = "circle"): string
{
    if (!function_exists("imagecreatetruecolor") || !function_exists("imagepng")) {
        throw new RuntimeException("GD no disponible para generar iconos");
    }

    $hex = ltrim(trim($hex), "#");
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = "9b2f55";
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $size = 32;
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $size, $size, $transparent);
    imagealphablending($im, true);

    $fill = imagecolorallocate($im, $r, $g, $b);
    $stroke = imagecolorallocate($im, 255, 255, 255);

    if ($shape === "triangle") {
        // Triángulo punta arriba (como en el mapa)
        $points = [
            (int)($size / 2), 3,
            $size - 3, $size - 3,
            3, $size - 3,
        ];
        imagefilledpolygon($im, $points, $fill);
        imagesetthickness($im, 2);
        imagepolygon($im, $points, $stroke);
        imagesetthickness($im, 1);
    } else {
        $cx = (int)($size / 2);
        $cy = (int)($size / 2);
        $radius = 12;
        imagefilledellipse($im, $cx, $cy, $radius * 2, $radius * 2, $fill);
        imagesetthickness($im, 2);
        imageellipse($im, $cx, $cy, $radius * 2, $radius * 2, $stroke);
        imagesetthickness($im, 1);
    }

    ob_start();
    imagepng($im);
    $png = (string)ob_get_clean();
    imagedestroy($im);
    return $png;
}
