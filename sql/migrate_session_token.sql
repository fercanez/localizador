-- Sesión única por usuario: token en BD vs PHP $_SESSION
-- Nuevo login (política invalidate) reemplaza el token; la sesión anterior queda inválida.
--   psql -U canez -d NOMBRE_BD -f sql/migrate_session_token.sql
--   php scripts/migrate_session_token.php

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL;

COMMENT ON COLUMN app_users.session_token IS
  'Token de sesión activa. Un solo valor vigente por usuario; NULL = sin sesión.';
