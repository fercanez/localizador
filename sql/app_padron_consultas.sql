-- Bitácora de consultas al padrón (BD de la app, no geonode_data).
-- Se crea automáticamente desde PHP; este script es opcional / documentación.
--
--   sudo -u postgres psql -d localizacion_app -v ON_ERROR_STOP=1 -f sql/app_padron_consultas.sql

CREATE TABLE IF NOT EXISTS app_padron_consultas (
  id              SERIAL PRIMARY KEY,
  user_id         INTEGER REFERENCES app_users(id) ON DELETE SET NULL,
  username        VARCHAR(80)  NOT NULL,
  full_name       VARCHAR(200) NOT NULL DEFAULT '',
  clave           VARCHAR(32)  NOT NULL DEFAULT '',
  query_text      VARCHAR(200) NOT NULL DEFAULT '',
  tipo            VARCHAR(20)  NOT NULL DEFAULT 'clave',
  found           BOOLEAN,
  result_count    INTEGER,
  ip              VARCHAR(64)  NOT NULL DEFAULT '',
  consulted_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_padron_consultas_at ON app_padron_consultas (consulted_at DESC);
CREATE INDEX IF NOT EXISTS idx_padron_consultas_clave ON app_padron_consultas (clave);
CREATE INDEX IF NOT EXISTS idx_padron_consultas_user ON app_padron_consultas (username);
CREATE INDEX IF NOT EXISTS idx_padron_consultas_tipo ON app_padron_consultas (tipo);
