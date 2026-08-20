<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión"], 401);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$raw = file_get_contents("php://input") ?: "";
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

try {
    $pdo = mxli_db();
    mxli_ensure_inspections_table($pdo);
    $user = mxli_current_user();
    if ($user === null) {
        mxli_json_response(["ok" => false, "error" => "Sesión inválida"], 401);
    }

    if ($method === "GET") {
        if (!mxli_can("map.inspect") && !mxli_can_inspection_manage()) {
            mxli_json_response(["ok" => false, "error" => "Sin permiso"], 403);
        }

        $clave = mxli_normalize_inspection_clave($_GET["clave"] ?? "");
        $limit = min(200, max(1, (int)($_GET["limit"] ?? 50)));
        $params = [];
        $where = "1=1";

        if (!mxli_can_inspection_manage()) {
            $where .= " AND p.created_by = :uid";
            $params["uid"] = (int)$user["id"];
        }
        if ($clave !== "") {
            $where .= " AND p.clavecatas ILIKE :clave";
            $params["clave"] = $clave . "%";
        }

        $sql =
            "SELECT p.*, u.username AS created_by_name
             FROM app_inspection_photos p
             LEFT JOIN app_users u ON u.id = p.created_by
             WHERE {$where}
             ORDER BY p.captured_at DESC
             LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        mxli_json_response([
            "ok" => true,
            "items" => array_map(
                static fn ($row) => mxli_public_inspection_row($row, $user),
                $rows
            ),
        ]);
    }

    if ($method === "POST") {
        if (!mxli_can("map.inspect")) {
            mxli_json_response(["ok" => false, "error" => "Sin permiso para capturar"], 403);
        }

        $clave = mxli_normalize_inspection_clave($_POST["clavecatas"] ?? "");
        $notes = trim((string)($_POST["notes"] ?? ""));
        $lat = $_POST["lat"] ?? null;
        $lng = $_POST["lng"] ?? null;

        if ($clave === "" || !preg_match('/^[A-Z0-9]{4,32}$/', $clave)) {
            mxli_json_response(["ok" => false, "error" => "Clave catastral inválida"], 400);
        }

        if (empty($_FILES["photo"]) || !is_array($_FILES["photo"])) {
            mxli_json_response(["ok" => false, "error" => "Falta la fotografía"], 400);
        }

        $file = $_FILES["photo"];
        if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            mxli_json_response(["ok" => false, "error" => "Error al subir la foto"], 400);
        }

        $maxBytes = 8 * 1024 * 1024;
        if ((int)($file["size"] ?? 0) > $maxBytes) {
            mxli_json_response(["ok" => false, "error" => "La foto supera 8 MB"], 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file["tmp_name"]) ?: "";
        $allowed = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp",
        ];
        if (!isset($allowed[$mime])) {
            mxli_json_response(["ok" => false, "error" => "Formato no permitido (use JPG, PNG o WebP)"], 400);
        }

        $subdir = date("Y/m");
        $dir = mxli_inspections_upload_root() . "/" . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            mxli_json_response(["ok" => false, "error" => "No se pudo guardar la foto"], 500);
        }

        $filename = bin2hex(random_bytes(12)) . "." . $allowed[$mime];
        $relative = $subdir . "/" . $filename;
        $dest = mxli_inspections_upload_root() . "/" . $relative;

        if (!move_uploaded_file($file["tmp_name"], $dest)) {
            mxli_json_response(["ok" => false, "error" => "No se pudo almacenar la foto"], 500);
        }

        $latVal = is_numeric($lat) ? (float)$lat : null;
        $lngVal = is_numeric($lng) ? (float)$lng : null;

        $stmt = $pdo->prepare(
            "INSERT INTO app_inspection_photos
                (clavecatas, photo_path, notes, lat, lng, created_by, captured_at, updated_at)
             VALUES
                (:clave, :path, :notes, :lat, :lng, :uid, NOW(), NOW())
             RETURNING *"
        );
        $stmt->execute([
            "clave" => $clave,
            "path" => $relative,
            "notes" => $notes,
            "lat" => $latVal,
            "lng" => $lngVal,
            "uid" => (int)$user["id"],
        ]);
        $row = $stmt->fetch();
        $row["created_by_name"] = (string)($user["username"] ?? "");

        mxli_json_response([
            "ok" => true,
            "item" => mxli_public_inspection_row($row, $user),
        ], 201);
    }

    if ($method === "PUT" || $method === "PATCH") {
        $id = (int)($body["id"] ?? 0);
        if ($id <= 0) {
            mxli_json_response(["ok" => false, "error" => "ID inválido"], 400);
        }

        $stmt = $pdo->prepare(
            "SELECT p.*, u.username AS created_by_name
             FROM app_inspection_photos p
             LEFT JOIN app_users u ON u.id = p.created_by
             WHERE p.id = :id"
        );
        $stmt->execute(["id" => $id]);
        $current = $stmt->fetch();
        if (!$current) {
            mxli_json_response(["ok" => false, "error" => "Registro no encontrado"], 404);
        }
        if (!mxli_can_access_inspection_row($current, $user)) {
            mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
        }

        $clave = array_key_exists("clavecatas", $body)
            ? mxli_normalize_inspection_clave($body["clavecatas"])
            : (string)$current["clavecatas"];
        $notes = array_key_exists("notes", $body)
            ? trim((string)$body["notes"])
            : (string)$current["notes"];

        if ($clave === "" || !preg_match('/^[A-Z0-9]{4,32}$/', $clave)) {
            mxli_json_response(["ok" => false, "error" => "Clave catastral inválida"], 400);
        }

        $upd = $pdo->prepare(
            "UPDATE app_inspection_photos
             SET clavecatas = :clave,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id
             RETURNING *"
        );
        $upd->execute([
            "clave" => $clave,
            "notes" => $notes,
            "id" => $id,
        ]);
        $row = $upd->fetch();
        $row["created_by_name"] = (string)($current["created_by_name"] ?? "");

        mxli_json_response([
            "ok" => true,
            "item" => mxli_public_inspection_row($row, $user),
        ]);
    }

    if ($method === "DELETE") {
        $id = (int)($body["id"] ?? $_GET["id"] ?? 0);
        if ($id <= 0) {
            mxli_json_response(["ok" => false, "error" => "ID inválido"], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM app_inspection_photos WHERE id = :id");
        $stmt->execute(["id" => $id]);
        $current = $stmt->fetch();
        if (!$current) {
            mxli_json_response(["ok" => false, "error" => "Registro no encontrado"], 404);
        }
        if (!mxli_can_access_inspection_row($current, $user)) {
            mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
        }

        mxli_delete_inspection_file((string)$current["photo_path"]);
        $pdo->prepare("DELETE FROM app_inspection_photos WHERE id = :id")->execute(["id" => $id]);

        mxli_json_response(["ok" => true, "deleted" => $id]);
    }

    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
} catch (Throwable $e) {
    mxli_json_response(["ok" => false, "error" => "Error de servidor", "detail" => $e->getMessage()], 500);
}
