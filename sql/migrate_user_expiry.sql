-- Fecha de vencimiento de acceso (NULL = sin vencimiento)
-- Último día válido inclusive. Después de esa fecha no puede iniciar sesión.
--   psql -U canez -d NOMBRE_BD -f sql/migrate_user_expiry.sql
--   php scripts/migrate_user_expiry.php

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS expires_at DATE NULL;

COMMENT ON COLUMN app_users.expires_at IS
  'Último día de acceso (inclusive). NULL = sin vencimiento.';

CREATE INDEX IF NOT EXISTS idx_app_users_expires_at ON app_users (expires_at);
