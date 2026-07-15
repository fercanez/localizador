-- Migración: roles admin / consulta / campo
-- Ejecutar:
--   psql -U canez -d NOMBRE_BD -f sql/migrate_roles_v2.sql

ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check;

UPDATE app_users
SET role = 'consulta'
WHERE role = 'user' OR role IS NULL OR role = '';

ALTER TABLE app_users
  ALTER COLUMN role SET DEFAULT 'consulta';

ALTER TABLE app_users
  ADD CONSTRAINT app_users_role_check
  CHECK (role IN ('admin', 'consulta', 'campo'));

COMMENT ON COLUMN app_users.role IS
  'admin=todo; consulta=mapa/buscar/imprimir; campo=consulta+subir archivos';
