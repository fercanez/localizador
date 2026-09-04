-- Ejecutar como postgres si el visor (usuario canez) no puede leer el padrón:
--   sudo -u postgres psql -d geonode_data -f /var/www/html/localizacion/sql/grant_padron2026.sql

GRANT CONNECT ON DATABASE geonode_data TO canez;
GRANT USAGE ON SCHEMA public TO canez;
GRANT SELECT ON TABLE public.padron2026 TO canez;
