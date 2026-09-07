-- Roles personalizados con permisos y capas
--   psql -U canez -d NOMBRE_BD -f sql/migrate_custom_roles.sql
--   php scripts/migrate_custom_roles.php

CREATE TABLE IF NOT EXISTS app_roles (
    slug            VARCHAR(80)  PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    is_system       BOOLEAN      NOT NULL DEFAULT FALSE,
    permissions     JSONB        NOT NULL DEFAULT '[]'::jsonb,
    allowed_layers  JSONB        NOT NULL DEFAULT '["*"]'::jsonb,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_app_roles_system ON app_roles (is_system);

-- Quitar CHECK rígido de roles fijos en app_users (cualquier nombre)
ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check;
DO $$
DECLARE r record;
BEGIN
  FOR r IN
    SELECT c.conname
    FROM pg_constraint c
    JOIN pg_attribute a
      ON a.attrelid = c.conrelid
     AND a.attnum = ANY (c.conkey)
    WHERE c.conrelid = 'app_users'::regclass
      AND c.contype = 'c'
      AND a.attname = 'role'
  LOOP
    EXECUTE format('ALTER TABLE app_users DROP CONSTRAINT IF EXISTS %I', r.conname);
  END LOOP;
END $$;
ALTER TABLE app_users
  ALTER COLUMN role TYPE VARCHAR(80)
  USING trim(both FROM role::text);
ALTER TABLE app_users ALTER COLUMN role SET DEFAULT 'consulta';

COMMENT ON TABLE app_roles IS 'Roles configurables (permisos + capas visibles)';
COMMENT ON COLUMN app_roles.allowed_layers IS 'Keys de capa o ["*"] = todas';
COMMENT ON COLUMN app_users.role IS 'Slug de app_roles (no el nombre visible)';
