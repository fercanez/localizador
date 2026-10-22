-- Sesión única por usuario: token en BD vs PHP $_SESSION
-- Política default "lock": rechaza 2.º login si hay token (admin siempre entra).
-- Idle: session_idle_seconds (default 300). Columnas last_activity + ip.
--   psql -U canez -d NOMBRE_BD -f sql/migrate_session_token.sql
--   php scripts/migrate_session_token.php

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL;

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_last_activity TIMESTAMPTZ NULL;

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_ip VARCHAR(45) NULL;

COMMENT ON COLUMN app_users.session_token IS
  'Token de sesión activa (política lock). NULL = libre. Admin puede limpiarlo desde Usuarios.';

COMMENT ON COLUMN app_users.session_last_activity IS
  'Última presencia (heartbeat). Libera sesión única tras idle/desconexión.';

COMMENT ON COLUMN app_users.session_ip IS
  'IP al login; si cambia se invalida la sesión.';
