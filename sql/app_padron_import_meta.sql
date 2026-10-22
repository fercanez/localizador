-- Meta de importación del padrón (BD de la app, no geonode_data).
-- Se crea automáticamente desde PHP; este script es opcional / documentación.
--
--   sudo -u postgres psql -d localizacion_app -v ON_ERROR_STOP=1 -f sql/app_padron_import_meta.sql
-- (Ajuste el nombre de la BD de la app si difiere.)

CREATE TABLE IF NOT EXISTS app_padron_import_meta (
  id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  imported_at TIMESTAMPTZ NULL,
  imported_by VARCHAR(80) NULL,
  filename TEXT NULL,
  rows_before BIGINT NULL,
  rows_after BIGINT NULL,
  claves_nuevas BIGINT NULL,
  claves_cambiadas BIGINT NULL,
  claves_eliminadas BIGINT NULL,
  claves_iguales BIGINT NULL,
  backup_table TEXT NULL,
  note TEXT NULL
);

INSERT INTO app_padron_import_meta (id) VALUES (1)
ON CONFLICT (id) DO NOTHING;
