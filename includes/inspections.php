<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function mxli_inspections_upload_root(): string
{
    return dirname(__DIR__) . "/uploads/inspections";
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
    return [
        "id" => $id,
        "clavecatas" => (string)$row["clavecatas"],
        "notes" => (string)($row["notes"] ?? ""),
        "lat" => isset($row["lat"]) ? (float)$row["lat"] : null,
        "lng" => isset($row["lng"]) ? (float)$row["lng"] : null,
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
