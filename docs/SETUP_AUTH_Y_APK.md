# Puesta en marcha: login + usuarios + APK

## 1) PostgreSQL (en el servidor Linux)

```bash
# Crear tabla
psql -U canez -d NOMBRE_BD -f /var/www/html/localizacion/sql/schema_app_users.sql

# Configurar BD en includes/config.local.php
# db_dsn, db_user, db_pass

# Crear usuario admin
cd /var/www/html/localizacion
php scripts/seed_admin.php admin 'AdminMxli2026!'
```

Usuario inicial por defecto:

- usuario: `admin`
- contraseña: `AdminMxli2026!` (cámbiela)

Requiere extensión PHP `pdo_pgsql`.

## 2) Probar en web

1. Abra `https://localizacion.geomexicali.info/login.php`
2. Entre con admin
3. Vaya a **Usuarios** y cree cuentas
4. Cierre sesión y pruebe con un usuario normal

## 3) APK

Vea `mobile/COMO_GENERAR_APK.md`.
