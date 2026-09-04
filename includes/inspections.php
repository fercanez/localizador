<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function mxli_inspections_upload_root(): string
{
    return dirname(__DIR__) . "/uploads/inspections";
}

function mxli_inspection_type_labels(): array
{
    return [
        "lic_construccion" => "Lic. Construcción",
        "imagen_urbana" => "Imagen Urbana",
        "uso_suelo" => "Uso de Suelo",
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

function mxli_can_inspection_manage(?array $user = null): bool
{
    return mxli_can("inspections.manage", $user) || mxli_can("users.manage", $user);
}

function mxli_can_view_inspections_map_layer(?array $user = null): bool
{
    return mxli_can("map.inspect", $user)
        || mxli_can_inspection_manage($user);
}

function mxli_can_access_inspection_row(array $row, ?array $user = null): bool
{
    $user = $user ?? mxli_current_user();
    if ($user === null) {
        return false;
    }
    if (mxli_can_inspection_manage($user)) {
        return true;
    }
    if (!mxli_can("map.inspect", $user)) {
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

    return [
        "id" => $id,
        "clavecatas" => (string)$row["clavecatas"],
        "notes" => (string)($row["notes"] ?? ""),
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