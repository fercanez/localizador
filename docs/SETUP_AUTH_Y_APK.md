# Puesta en marcha: login + usuarios + APK

## 1) PostgreSQL (en el servidor Linux)

```bash
# Crear tabla (instalación nueva)
psql -U canez -d NOMBRE_BD -f /var/www/html/localizacion/sql/schema_app_users.sql

# Si la tabla ya existía con roles admin/user, migre a admin/consulta/campo:
psql -U canez -d NOMBRE_BD -f /var/www/html/localizacion/sql/migrate_roles_v2.sql

# Alternativa más simple (misma migración vía PHP):
cd /var/www/html/localizacion
php scripts/migrate_roles.php

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

## Roles y permisos

Los roles viven en la tabla `app_roles` (permisos de herramientas + capas visibles).
Roles base sembrados: **admin**, **consulta**, **campo**.

```bash
cd /var/www/html/localizacion
php scripts/migrate_custom_roles.php
```

También puede aplicar el SQL directo:

```bash
psql -U canez -d NOMBRE_BD -f /var/www/html/localizacion/sql/migrate_custom_roles.sql
```

| Rol (por defecto) | Mapa / búsqueda / info | Imprimir | GPS / medir | Subir SHP/KML | Usuarios | Capas |
|---|---|---|---|---|---|---|
| **admin** | Sí | Sí | Sí | Sí | Sí | Todas |
| **consulta** | Sí | Sí | Sí | No | No | Todas |
| **campo** | Sí | Sí | Sí | Sí | No | Todas |

Desde **Usuarios → Roles y permisos** puede crear roles nuevos (p. ej. “Urbanismo”) eligiendo herramientas y un subconjunto de las 8 capas WMS.

Los usuarios antiguos con rol `user` se convierten a `consulta` en la migración de roles v2.

## Fecha de vencimiento

Cada usuario puede tener una fecha `expires_at` (último día de acceso). Vacío = sin límite.

```bash
cd /var/www/html/localizacion
php scripts/migrate_user_expiry.php
```

Si un usuario vencido intenta entrar, verá: “Su acceso venció…”.

## 2) Probar en web

1. Abra `https://localizacion.geomexicali.info/login.php`
2. Entre con admin
3. Vaya a **Usuarios** y cree cuentas con roles distintos
4. Cierre sesión y pruebe cada rol

## 3) APK

Vea `mobile/COMO_GENERAR_APK.md`.
