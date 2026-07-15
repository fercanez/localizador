-- Usuarios de la app localizacion (PostgreSQL)
-- Ejecutar como usuario con permisos de creación de tablas, p.ej.:
--   psql -U canez -d NOMBRE_BD -f sql/schema_app_users.sql

CREATE TABLE IF NOT EXISTS app_users (
    id              SERIAL PRIMARY KEY,
    username        VARCHAR(80)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(160) NOT NULL DEFAULT '',
    email           VARCHAR(160) NOT NULL DEFAULT '',
    role            VARCHAR(20)  NOT NULL DEFAULT 'consulta'
                    CHECK (role IN ('admin', 'consulta', 'campo')),
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    expires_at      DATE         NULL,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    last_login_at   TIMESTAMPTZ  NULL
);

CREATE INDEX IF NOT EXISTS idx_app_users_role ON app_users (role);
CREATE INDEX IF NOT EXISTS idx_app_users_active ON app_users (is_active);
CREATE INDEX IF NOT EXISTS idx_app_users_expires_at ON app_users (expires_at);

COMMENT ON TABLE app_users IS 'Usuarios del visor localizacion (web/APK)';
COMMENT ON COLUMN app_users.role IS
  'admin=todo; consulta=mapa/buscar/imprimir; campo=consulta+subir archivos';
COMMENT ON COLUMN app_users.expires_at IS
  'Último día de acceso (inclusive). NULL = sin vencimiento.';
