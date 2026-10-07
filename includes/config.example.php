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

    // Sesión única por usuario:
    //   "lock" (default) = rechaza segundo login si ya hay session_token activo
    //                      (admin siempre puede entrar; idle auto-expira)
    //   "invalidate" = nuevo login genera token y cierra la sesión anterior
    "session_policy" => "lock",
    // Segundos sin actividad de usuario tras los cuales la sesión caduca
    // (cliente + servidor). También libera el bloqueo de sesión única.
    // Legacy: session_idle_hours se convierte a segundos si no hay seconds.
    "session_idle_seconds" => 300,
    // Si true, un cambio de IP invalida la sesión (heartbeat / auth).
    "session_bind_ip" => true,

    // Planos de colonia (GROMA). Si el servidor no ve el share,
    // se usa data/planos/{HOMO}.json y Documentacion.aspx.
    "planos_dir" => "\\\\SCATASTRO\\GROMA",
    "planos_doc_url" => "https://www.mexicali.gob.mx/webpub/consultacatastro/documentacion.aspx?",
];
