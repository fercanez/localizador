<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";
require_once dirname(__DIR__, 2) . "/includes/layer-catalog.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$raw = file_get_contents("php://input") ?: "";
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

function mxli_ensure_roles_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_roles (
            slug            VARCHAR(80)  PRIMARY KEY,
            name            VARCHAR(120) NOT NULL,
            description     VARCHAR(255) NOT NULL DEFAULT '',
            is_system       BOOLEAN      NOT NULL DEFAULT FALSE,
            permissions     JSONB        NOT NULL DEFAULT '[]'::jsonb,
            allowed_layers  JSONB        NOT NULL DEFAULT '[\"*\"]'::jsonb,
            created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )"
    );
    $pdo->exec("ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check");
    $done = true;
}

function mxli_normalize_permissions_input(mixed $value): array
{
    $allowed = array_keys(mxli_tool_permission_defs());
    $list = [];
    if (is_array($value)) {
        foreach ($value as $item) {
            $perm = trim((string)$item);
            if (in_array($perm, $allowed, true)) {
                $list[] = $perm;
            }
        }
    }
    if (!in_array("map.view", $list, true)) {
        array_unshift($list, "map.view");
    }
    return array_values(array_unique($list));
}

function mxli_normalize_layers_input(mixed $value): array
{
    $valid = mxli_layer_keys();
    if (!is_array($value) || $value === []) {
        return ["*"];
    }
    $list = [];
    foreach ($value as $item) {
        $key = trim((string)$item);
        if ($key === "*") {
            return ["*"];
        }
        if (in_array($key, $valid, true)) {
            $list[] = $key;
        }
    }
    return $list !== [] ? array_values(array_unique($list)) : ["*"];
}

function mxli_public_role(array $role): array
{
    return [
        "slug" => (string)$role["slug"],
        "name" => (string)$role["name"],
        "description" => (string)($role["description"] ?? ""),
        "is_system" => (bool)($role["is_system"] ?? false),
        "permissions" => array_values($role["permissions"] ?? []),
        "allowed_layers" => array_values($role["allowed_layers"] ?? ["*"]),
    ];
}

try {
    $pdo = mxli_db();
    mxli_ensure_roles_table($pdo);

    if ($method === "GET") {
        // Sembrar roles base si la tabla está vacía
        $count = (int)$pdo->query("SELECT COUNT(*) FROM app_roles")->fetchColumn();
        if ($count === 0) {
            $ins = $pdo->prepare(
                "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
                 VALUES (:slug, :name, :description, :is_system, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
                 ON CONFLICT (slug) DO NOTHING"
            );
            foreach (mxli_default_role_seeds() as $seed) {
                $ins->execute([
                    "slug" => $seed["slug"],
                    "name" => $seed["name"],
                    "description" => $seed["description"],
                    "is_system" => $seed["is_system"] ? "true" : "false",
                    "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
                    "layers" => json_encode(array_values($seed["allowed_layers"]), JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        mxli_json_response([
            "ok" => true,
            "roles" => array_map("mxli_public_role", mxli_list_roles()),
            "tools" => mxli_tool_permission_defs(),
            "layers" => mxli_layer_catalog(),
        ]);
    }

    if ($method === "POST") {
        $name = trim((string)($body["name"] ?? ""));
        $description = trim((string)($body["description"] ?? ""));
        $slug = trim((string)($body["slug"] ?? ""));
        if ($slug === "") {
            $slug = mxli_slugify_role_name($name);
        } else {
            $slug = mxli_normalize_role_slug($slug);
        }

        if ($name === "" || strlen($name) < 2) {
            mxli_json_response(["ok" => false, "error" => "El nombre del rol es obligatorio"], 400);
        }
        if ($slug === "" || in_array($slug, ["admin"], true)) {
            mxli_json_response(["ok" => false, "error" => "Slug de rol inválido"], 400);
        }

        $permissions = mxli_normalize_permissions_input($body["permissions"] ?? []);
        $layers = mxli_normalize_layers_input($body["allowed_layers"] ?? ["*"]);
        // Solo admin puede tener users.manage
        $permissions = array_values(array_filter($permissions, fn ($p) => $p !== "users.manage"));

        $stmt = $pdo->prepare(
            "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
             VALUES (:slug, :name, :description, FALSE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
             RETURNING slug, name, description, is_system, permissions, allowed_layers"
        );

        try {
            $stmt->execute([
                "slug" => $slug,
                "name" => $name,
                "description" => $description,
                "permissions" => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                "layers" => json_encode($layers, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), "unique") || str_contains($e->getMessage(), "duplicate")) {
                mxli_json_response(["ok" => false, "error" => "Ya existe un rol con ese identificador"], 409);
            }
            throw $e;
        }

        $row = $stmt->fetch();
        mxli_json_response([
            "ok" => true,
            "role" => mxli_public_role([
                "slug" => $row["slug"],
                "name" => $row["name"],
                "description" => $row["description"],
                "is_system" => mxli_to_bool($row["is_system"]),
                "permissions" => mxli_decode_json_list($row["permissions"]),
                "allowed_layers" => mxli_decode_json_list($row["allowed_layers"]),
            ]),
        ], 201);
    }

    if ($method === "PUT" || $method === "PATCH") {
        $slug = mxli_normalize_role_slug((string)($body["slug"] ?? ""));
        if ($slug === "") {
            mxli_json_response(["ok" => false, "error" => "Slug inválido"], 400);
        }

        $current = mxli_fetch_role($slug);
        if (!$current) {
            mxli_json_response(["ok" => false, "error" => "Rol no encontrado"], 404);
        }

        $name = array_key_exists("name", $body)
            ? trim((string)$body["name"])
            : $current["name"];
        $description = array_key_exists("description", $body)
            ? trim((string)$body["description"])
            : $current["description"];
        $permissions = array_key_exists("permissions", $body)
            ? mxli_normalize_permissions_input($body["permissions"])
            : $current["permissions"];
        $layers = array_key_exists("allowed_layers", $body)
            ? mxli_normalize_layers_input($body["allowed_layers"])
            : $current["allowed_layers"];

        if ($name === "") {
            mxli_json_response(["ok" => false, "error" => "El nombre del rol es obligatorio"], 400);
        }

        if ($slug === "admin") {
            $permissions = array_keys(mxli_tool_permission_defs());
            $layers = ["*"];
        } else {
            $permissions = array_values(array_filter($permissions, fn ($p) => $p !== "users.manage"));
        }

        $stmt = $pdo->prepare(
            "UPDATE app_roles
             SET name = :name,
                 description = :description,
                 permissions = CAST(:permissions AS jsonb),
                 allowed_layers = CAST(:layers AS jsonb),
                 updated_at = NOW()
             WHERE slug = :slug
             RETURNING slug, name, description, is_system, permissions, allowed_layers"
        );
        $stmt->execute([
            "slug" => $slug,
            "name" => $name,
            "description" => $description,
            "permissions" => json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(array_values($layers), JSON_UNESCAPED_UNICODE),
        ]);
        $row = $stmt->fetch();

        mxli_json_response([
            "ok" => true,
            "role" => mxli_public_role([
                "slug" => $row["slug"],
                "name" => $row["name"],
                "description" => $row["description"],
                "is_system" => mxli_to_bool($row["is_system"]),
                "permissions" => mxli_decode_json_list($row["permissions"]),
                "allowed_layers" => mxli_decode_json_list($row["allowed_layers"]),
            ]),
        ]);
    }

    if ($method === "DELETE") {
        $slug = mxli_normalize_role_slug((string)($_GET["slug"] ?? $body["slug"] ?? ""));
        if ($slug === "") {
            mxli_json_response(["ok" => false, "error" => "Slug inválido"], 400);
        }

        $current = mxli_fetch_role($slug);
        if (!$current) {
            mxli_json_response(["ok" => false, "error" => "Rol no encontrado"], 404);
        }
        if (!empty($current["is_system"])) {
            mxli_json_response(["ok" => false, "error" => "No se puede eliminar un rol del sistema"], 400);
        }

        $used = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE role = :slug");
        $used->execute(["slug" => $slug]);
        if ((int)$used->fetchColumn() > 0) {
            mxli_json_response([
                "ok" => false,
                "error" => "Hay usuarios con este rol. Reasígnelos antes de eliminarlo.",
            ], 400);
        }

        $del = $pdo->prepare("DELETE FROM app_roles WHERE slug = :slug AND is_system = FALSE");
        $del->execute(["slug" => $slug]);
        mxli_json_response(["ok" => true, "deleted" => $slug]);
    }

    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
} catch (Throwable $e) {
    mxli_json_response([
        "ok" => false,
        "error" => "Error al administrar roles",
        "detail" => $e->getMessage(),
    ], 500);
}
