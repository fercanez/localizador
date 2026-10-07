<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function mxli_padron_log_ensure(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = $pdo ?? mxli_db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_padron_consultas (
            id              SERIAL PRIMARY KEY,
            user_id         INTEGER REFERENCES app_users(id) ON DELETE SET NULL,
            username        VARCHAR(80)  NOT NULL,
            full_name       VARCHAR(200) NOT NULL DEFAULT '',
            clave           VARCHAR(32)  NOT NULL DEFAULT '',
            query_text      VARCHAR(200) NOT NULL DEFAULT '',
            tipo            VARCHAR(20)  NOT NULL DEFAULT 'clave',
            found           BOOLEAN,
            result_count    INTEGER,
            ip              VARCHAR(64)  NOT NULL DEFAULT '',
            consulted_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_padron_consultas_at ON app_padron_consultas (consulted_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_padron_consultas_clave ON app_padron_consultas (clave)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_padron_consultas_user ON app_padron_consultas (username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_padron_consultas_tipo ON app_padron_consultas (tipo)");

    $done = true;
}

/**
 * Bitácora de consulta al padrón. Nunca interrumpe la búsqueda.
 *
 * @param array{
 *   tipo?:string,
 *   clave?:string,
 *   query_text?:string,
 *   found?:?bool,
 *   result_count?:?int
 * } $opts
 */
function mxli_padron_log_consulta(array $opts): void
{
    try {
        $pdo = mxli_db();
        mxli_padron_log_ensure($pdo);

        $user = function_exists("mxli_current_user") ? mxli_current_user() : null;
        $username = trim((string)($user["username"] ?? ""));
        if ($username === "") {
            return;
        }

        $tipo = strtolower(trim((string)($opts["tipo"] ?? "clave")));
        if ($tipo !== "nombre") {
            $tipo = "clave";
        }

        $clave = strtoupper(preg_replace("/\s+/", "", (string)($opts["clave"] ?? "")) ?? "");
        $clave = substr($clave, 0, 32);

        $queryText = trim((string)($opts["query_text"] ?? ""));
        if (function_exists("mb_substr")) {
            $queryText = mb_substr($queryText, 0, 200, "UTF-8");
        } else {
            $queryText = substr($queryText, 0, 200);
        }

        $found = array_key_exists("found", $opts) ? $opts["found"] : null;
        $foundSql = null;
        if ($found === true) {
            $foundSql = true;
        } elseif ($found === false) {
            $foundSql = false;
        }

        $count = array_key_exists("result_count", $opts) ? $opts["result_count"] : null;
        $countSql = is_int($count) || is_float($count) || (is_string($count) && is_numeric($count))
            ? (int)$count
            : null;

        $ip = function_exists("mxli_client_ip") ? mxli_client_ip() : substr(trim((string)($_SERVER["REMOTE_ADDR"] ?? "")), 0, 45);

        $stmt = $pdo->prepare(
            "INSERT INTO app_padron_consultas
                (user_id, username, full_name, clave, query_text, tipo, found, result_count, ip, consulted_at)
             VALUES
                (:user_id, :username, :full_name, :clave, :query_text, :tipo, :found, :result_count, :ip, NOW())"
        );
        $userId = (int)($user["id"] ?? 0);
        $stmt->bindValue(":user_id", $userId > 0 ? $userId : null, $userId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(":username", substr($username, 0, 80));
        $stmt->bindValue(":full_name", substr(trim((string)($user["full_name"] ?? "")), 0, 200));
        $stmt->bindValue(":clave", $clave);
        $stmt->bindValue(":query_text", $queryText);
        $stmt->bindValue(":tipo", $tipo);
        if ($foundSql === null) {
            $stmt->bindValue(":found", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":found", $foundSql, PDO::PARAM_BOOL);
        }
        if ($countSql === null) {
            $stmt->bindValue(":result_count", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":result_count", $countSql, PDO::PARAM_INT);
        }
        $stmt->bindValue(":ip", substr($ip, 0, 64));
        $stmt->execute();
    } catch (Throwable $e) {
        error_log("mxli_padron_log_consulta: " . $e->getMessage());
    }
}

function mxli_padron_log_valid_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === "" || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat("Y-m-d", $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if ($dt === false || ($errors && (($errors["warning_count"] ?? 0) > 0 || ($errors["error_count"] ?? 0) > 0))) {
        return null;
    }
    return $dt->format("Y-m-d");
}

/**
 * @return array{rows:list<array<string,mixed>>,total:int,page:int,limit:int,pages:int}
 */
function mxli_padron_log_list(array $filters): array
{
    $pdo = mxli_db();
    mxli_padron_log_ensure($pdo);

    $where = ["1=1"];
    $params = [];

    $usuario = trim((string)($filters["usuario"] ?? ""));
    if ($usuario !== "") {
        $where[] = "(username ILIKE :usuario OR COALESCE(full_name, '') ILIKE :usuario)";
        $params["usuario"] = "%" . $usuario . "%";
    }

    $clave = strtoupper(preg_replace("/\s+/", "", (string)($filters["clave"] ?? "")) ?? "");
    if ($clave !== "") {
        $where[] = "clave ILIKE :clave";
        $params["clave"] = "%" . $clave . "%";
    }

    $tipo = strtolower(trim((string)($filters["tipo"] ?? "")));
    if ($tipo === "clave" || $tipo === "nombre") {
        $where[] = "tipo = :tipo";
        $params["tipo"] = $tipo;
    }

    $desde = mxli_padron_log_valid_date((string)($filters["desde"] ?? ""));
    if ($desde !== null) {
        $where[] = "consulted_at >= CAST(:desde AS date)";
        $params["desde"] = $desde;
    }

    $hasta = mxli_padron_log_valid_date((string)($filters["hasta"] ?? ""));
    if ($hasta !== null) {
        $where[] = "consulted_at < (CAST(:hasta AS date) + INTERVAL '1 day')";
        $params["hasta"] = $hasta;
    }

    $whereSql = implode(" AND ", $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM app_padron_consultas WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $limit = (int)($filters["limit"] ?? 50);
    if ($limit < 1) {
        $limit = 50;
    }
    if ($limit > 200) {
        $limit = 200;
    }
    $page = (int)($filters["page"] ?? 1);
    if ($page < 1) {
        $page = 1;
    }
    $pages = $total > 0 ? (int)ceil($total / $limit) : 1;
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $limit;

    $sql = "SELECT id, user_id, username, full_name, clave, query_text, tipo, found, result_count, ip,
                   to_char(consulted_at AT TIME ZONE 'America/Tijuana', 'YYYY-MM-DD HH24:MI:SS') AS consulted_at_local,
                   consulted_at
            FROM app_padron_consultas
            WHERE {$whereSql}
            ORDER BY consulted_at DESC, id DESC
            LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $foundRaw = $row["found"] ?? null;
        $found = null;
        if ($foundRaw === true || $foundRaw === "t" || $foundRaw === "1" || $foundRaw === 1) {
            $found = true;
        } elseif ($foundRaw === false || $foundRaw === "f" || $foundRaw === "0" || $foundRaw === 0) {
            $found = false;
        }
        $rows[] = [
            "id" => (int)($row["id"] ?? 0),
            "user_id" => isset($row["user_id"]) && $row["user_id"] !== null ? (int)$row["user_id"] : null,
            "username" => (string)($row["username"] ?? ""),
            "full_name" => (string)($row["full_name"] ?? ""),
            "clave" => (string)($row["clave"] ?? ""),
            "query_text" => (string)($row["query_text"] ?? ""),
            "tipo" => (string)($row["tipo"] ?? "clave"),
            "found" => $found,
            "result_count" => isset($row["result_count"]) && $row["result_count"] !== null ? (int)$row["result_count"] : null,
            "ip" => (string)($row["ip"] ?? ""),
            "consulted_at" => (string)($row["consulted_at_local"] ?? $row["consulted_at"] ?? ""),
        ];
    }

    return [
        "rows" => $rows,
        "total" => $total,
        "page" => $page,
        "limit" => $limit,
        "pages" => $pages,
    ];
}
