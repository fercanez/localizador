-- Inspecciones fotográficas en campo (rol inspector)
CREATE TABLE IF NOT EXISTS app_inspection_photos (
    id              SERIAL PRIMARY KEY,
    clavecatas      VARCHAR(32)  NOT NULL,
    photo_path      VARCHAR(500) NOT NULL,
    notes           TEXT         NOT NULL DEFAULT '',
    lat             DOUBLE PRECISION,
    lng             DOUBLE PRECISION,
    captured_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    created_by      INTEGER      REFERENCES app_users(id) ON DELETE SET NULL,
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_inspection_photos_clave ON app_inspection_photos (clavecatas);
CREATE INDEX IF NOT EXISTS idx_inspection_photos_user ON app_inspection_photos (created_by);
CREATE INDEX IF NOT EXISTS idx_inspection_photos_date ON app_inspection_photos (captured_at DESC);

COMMENT ON TABLE app_inspection_photos IS 'Registro fotográfico de predios en campo';
