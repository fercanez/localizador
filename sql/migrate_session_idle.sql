-- Idle 5 min + IP de sesión
--   psql -U canez -d NOMBRE_BD -f sql/migrate_session_idle.sql
-- También se auto-aplican vía mxli_ensure_session_token_column() en auth.php

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_last_activity TIMESTAMPTZ NULL;

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS session_ip VARCHAR(45) NULL;

COMMENT ON COLUMN app_users.session_last_activity IS
  'Última vez que el cliente reportó presencia (heartbeat). Usado para liberar sesión única tras idle/desconexión.';

COMMENT ON COLUMN app_users.session_ip IS
  'IP capturada al iniciar sesión. Si cambia, se invalida la sesión (session_bind_ip).';
