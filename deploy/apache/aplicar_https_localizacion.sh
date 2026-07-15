#!/usr/bin/env bash
set -euo pipefail

# Corrige HTTPS de localizacion.geomexicali.info
# Causa actual: el puerto 443 cae en GeoNode/Django (ALLOWED_HOSTS sin el subdominio)
# Solución: VirtualHost SSL dedicado para /var/www/html/localizacion

DOMAIN="localizacion.geomexicali.info"
DOCROOT="/var/www/html/localizacion"
CONF_NAME="localizacion.geomexicali.info.conf"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_CONF="/etc/apache2/sites-available/${CONF_NAME}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Ejecuta este script con sudo."
  exit 1
fi

if [[ ! -d "${DOCROOT}" ]]; then
  echo "No existe ${DOCROOT}. Ajusta DOCROOT antes de continuar."
  exit 1
fi

echo "==> 1/6 Respaldando configuración actual"
mkdir -p /root/backup-apache-localizacion-$(date +%F_%H%M%S)
cp -a /etc/apache2/sites-available /root/backup-apache-localizacion-$(date +%F_%H%M%S)/

echo "==> 2/6 Instalando VirtualHost de ${DOMAIN}"
cp "${SCRIPT_DIR}/${CONF_NAME}" "${TARGET_CONF}"

echo "==> 3/6 Habilitando módulos Apache necesarios"
a2enmod ssl rewrite headers >/dev/null
a2ensite "${CONF_NAME}" >/dev/null

echo "==> 4/6 Ampliando certificado Let's Encrypt para incluir ${DOMAIN}"
if command -v certbot >/dev/null 2>&1; then
  certbot certonly \
    --apache \
    --cert-name www.geomexicali.info \
    --expand \
    -d www.geomexicali.info \
    -d geomexicali.info \
    -d "${DOMAIN}" \
    --non-interactive \
    --agree-tos \
    --keep-until-expiring || {
      echo "Certbot --expand falló. Intentando certificado independiente..."
      certbot certonly \
        --apache \
        -d "${DOMAIN}" \
        --non-interactive \
        --agree-tos \
        --redirect
    }
else
  echo "ADVERTENCIA: certbot no está instalado. Debes ampliar el certificado manualmente."
fi

echo "==> 5/6 Validando sintaxis Apache"
apache2ctl configtest

echo "==> 6/6 Reiniciando Apache"
systemctl reload apache2

echo
echo "Validación esperada:"
echo "  curl -I https://${DOMAIN}/"
echo "Debe responder 200 OK con Content-Type text/html; charset=UTF-8"
echo
echo "Si HTTPS ya responde 200, activa redirección HTTP->HTTPS en:"
echo "  ${TARGET_CONF}"
