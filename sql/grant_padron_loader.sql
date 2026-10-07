-- Permisos para que el usuario de la app (canez) pueda actualizar public.padron2026
-- desde la pestaña Admin → Actualizar padrón (api/admin/padron-import.php).
--
-- Ejecutar como postgres:
--   sudo -u postgres psql -d geonode_data -v ON_ERROR_STOP=1 -f /var/www/html/localizacion/sql/grant_padron_loader.sql
--
-- Staging en PHP usa CREATE TEMP TABLE (no requiere CREATE ON SCHEMA).
-- El respaldo opcional CREATE TABLE padron2026_backup_YYYYMMDD SÍ necesita CREATE.

GRANT CONNECT ON DATABASE geonode_data TO canez;
GRANT USAGE ON SCHEMA public TO canez;

-- Lectura + carga (TRUNCATE + INSERT / COPY)
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLE public.padron2026 TO canez;

-- Respaldo opcional y tablas permanentes padron_load_* (si se usan)
GRANT CREATE ON SCHEMA public TO canez;

-- Si ya existen backups / staging previos:
-- GRANT ALL ON TABLE public.padron2026_backup_202609 TO canez;

-- Verificación:
-- SELECT has_table_privilege('canez', 'public.padron2026', 'SELECT')  AS can_select;
-- SELECT has_table_privilege('canez', 'public.padron2026', 'INSERT')  AS can_insert;
-- SELECT has_table_privilege('canez', 'public.padron2026', 'TRUNCATE') AS can_truncate;
-- SELECT has_schema_privilege('canez', 'public', 'CREATE') AS can_create;
