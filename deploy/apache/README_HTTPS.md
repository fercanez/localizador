# Corrección HTTPS - localizacion.geomexicali.info

## Diagnóstico

| URL | Estado actual | Qué sirve |
|---|---|---|
| `http://localizacion.geomexicali.info` | 200 OK | App PHP en `/var/www/html/localizacion` |
| `https://localizacion.geomexicali.info` | 400 Bad Request | GeoNode/Django (no la app PHP) |

El error exacto en HTTPS es:

```text
DisallowedHost: Invalid HTTP_HOST header: 'localizacion.geomexicali.info'.
You may need to add 'localizacion.geomexicali.info' to ALLOWED_HOSTS.
```

`ALLOWED_HOSTS` actual en GeoNode:

```text
['localhost', '5.78.201.11', 'www.geomexicali.info']
```

**No agregues el subdominio a Django/GeoNode.** Eso haría que HTTPS muestre GeoNode, no el visor catastral.

La solución correcta es crear un **VirtualHost SSL de Apache** para `localizacion.geomexicali.info` apuntando a `/var/www/html/localizacion`.

## Archivos incluidos

- `localizacion.geomexicali.info.conf` - VirtualHost HTTP + HTTPS
- `aplicar_https_localizacion.sh` - script de instalación

## Instalación en el servidor (5.78.201.11)

1. Copia la carpeta `deploy/apache` al servidor.

2. Ejecuta:

```bash
cd /ruta/deploy/apache
sudo chmod +x aplicar_https_localizacion.sh
sudo ./aplicar_https_localizacion.sh
```

3. Verifica:

```bash
curl -I https://localizacion.geomexicali.info/
```

Debe devolver `HTTP/1.1 200 OK` y `Content-Type: text/html; charset=UTF-8`.

4. Cuando HTTPS funcione, activa redirección HTTP→HTTPS descomentando en `localizacion.geomexicali.info.conf`:

```apache
RewriteEngine On
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Luego:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Instalación manual (si prefieres no usar el script)

```bash
sudo cp localizacion.geomexicali.info.conf /etc/apache2/sites-available/
sudo a2enmod ssl rewrite
sudo a2ensite localizacion.geomexicali.info.conf

sudo certbot certonly --apache \
  --cert-name www.geomexicali.info \
  --expand \
  -d www.geomexicali.info \
  -d geomexicali.info \
  -d localizacion.geomexicali.info

sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Notas

- El certificado actual de `www.geomexicali.info` **no incluye** el subdominio; hay que ampliarlo con `certbot --expand`.
- HTTP del subdominio ya funciona; solo falta el bloque `:443`.
- La app PHP ya consume GeoServer por HTTPS, así que al corregir el subdominio se elimina el riesgo de mixed content.
