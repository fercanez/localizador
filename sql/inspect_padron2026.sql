-- Pegar en el servidor (como postgres o un superusuario):
--   sudo -u postgres psql -d geonode_data -f /tmp/inspect_padron2026.sql
-- o:
--   sudo -u postgres psql -d geonode_data

\echo '=== tablas padron* ==='
SELECT schemaname, tablename
FROM pg_tables
WHERE tablename ILIKE '%padron%'
ORDER BY 1, 2;

\echo '=== columnas public.padron2026 ==='
SELECT ordinal_position, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public' AND table_name = 'padron2026'
ORDER BY ordinal_position;

\echo '=== indices ==='
SELECT indexname, indexdef
FROM pg_indexes
WHERE schemaname = 'public' AND tablename = 'padron2026';

\echo '=== conteo ==='
SELECT count(*) AS filas FROM public.padron2026;

\echo '=== muestra (sin adeudos) ==='
SELECT * FROM public.padron2026 LIMIT 3;

\echo '=== permiso del usuario de la app (canez) ==='
SELECT has_table_privilege('canez', 'public.padron2026', 'SELECT') AS canez_puede_leer;
