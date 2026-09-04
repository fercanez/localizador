<?php
declare(strict_types=1);

return [
    "google_maps_key" => "",
    "bing_maps_key" => "",
    "geoserver_url" => "https://www.geomexicali.info/geoserver/",
    "gs_ows_url" => "https://www.geomexicali.info/gs/ows",

    // PostgreSQL (PDO) — usuarios / inspecciones de la app
    "db_dsn" => "pgsql:host=127.0.0.1;port=5432;dbname=localizacion_app",
    "db_user" => "canez",
    "db_pass" => "CAMBIAR_PASSWORD",

    // Padrón de propietarios (misma instancia Postgres, otra base)
    // Si se omite, se reutilizan host/usuario/clave y dbname=geonode_data
    "geonode_data_dsn" => "pgsql:host=127.0.0.1;port=5432;dbname=geonode_data",
    "padron_table" => "public.padron2026",
];
