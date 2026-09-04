-- Migración opcional (también cubierta por scripts/migrate_padron_permission.php)
-- Base: localizacion_app
--   sudo -u postgres psql -d localizacion_app -f /var/www/html/localizacion/sql/migrate_padron_permission.sql

ALTER TABLE app_users
  ADD COLUMN IF NOT EXISTS can_view_padron BOOLEAN NOT NULL DEFAULT FALSE;

-- Otorgar a un usuario concreto (ejemplo):
-- UPDATE app_users SET can_view_padron = TRUE WHERE username = 'usuario';

-- El permiso por rol (map.padron) se gestiona en Admin → Roles y permisos,
-- o con: php scripts/migrate_padron_permission.php
