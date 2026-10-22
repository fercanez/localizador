-- En el servidor:
--   sudo -u postgres psql -d geonode_data
-- Luego pegar por bloques.

\echo '=== 1) estructura ==='
\d public.padron2026

\echo '=== 2) columnas ==='
SELECT ordinal_position, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public' AND lower(table_name) = 'padron2026'
ORDER BY ordinal_position;

\echo '=== 3) conteo ==='
SELECT count(*) AS filas FROM public.padron2026;

\echo '=== 4) muestra ==='
SELECT * FROM public.padron2026 LIMIT 5;

\echo '=== 5a) SP001001 — columnas en minúsculas (típico) ==='
SELECT clavecatastral, nombredespliegue, colonia, calle, numerooficial
FROM public.padron2026
WHERE replace(upper(trim(clavecatastral::text)), ' ', '') = 'SP001001'
LIMIT 3;

\echo '=== 5b) SP001001 — columnas MAYÚSCULAS (si 5a falló) ==='
SELECT "CLAVECATASTRAL", "NOMBREDESPLIEGUE", "COLONIA", "CALLE", "NUMEROOFICIAL"
FROM public.padron2026
WHERE replace(upper(trim("CLAVECATASTRAL"::text)), ' ', '') = 'SP001001'
LIMIT 3;

\echo '=== 6) permiso usuario app canez ==='
SELECT
  has_database_privilege('canez', 'geonode_data', 'CONNECT') AS can_connect,
  has_schema_privilege('canez', 'public', 'USAGE') AS can_usage,
  has_table_privilege('canez', 'public.padron2026', 'SELECT') AS can_select;
