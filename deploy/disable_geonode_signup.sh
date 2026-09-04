#!/usr/bin/env bash
set -euo pipefail

TS="$(date +%Y%m%d_%H%M%S)"
ENV_FILE="/opt/geonode/.env"
LOCAL_SETTINGS="/etc/geonode/local_settings.py"

echo "==> Respaldando configuracion ($TS)"
cp -a "$LOCAL_SETTINGS" "${LOCAL_SETTINGS}.bak_nosignup_${TS}"
cp -a "$ENV_FILE" "${ENV_FILE}.bak_nosignup_${TS}"

echo "==> Actualizando .env"
sed -i 's/^ACCOUNT_OPEN_SIGNUP=.*/ACCOUNT_OPEN_SIGNUP=False/' "$ENV_FILE"
sed -i 's/^ACCOUNT_APPROVAL_REQUIRED=.*/ACCOUNT_APPROVAL_REQUIRED=True/' "$ENV_FILE"
if grep -q '^SOCIALACCOUNT_AUTO_SIGNUP=' "$ENV_FILE"; then
  sed -i 's/^SOCIALACCOUNT_AUTO_SIGNUP=.*/SOCIALACCOUNT_AUTO_SIGNUP=False/' "$ENV_FILE"
else
  echo 'SOCIALACCOUNT_AUTO_SIGNUP=False' >> "$ENV_FILE"
fi

echo "==> Actualizando local_settings.py"
if grep -q 'ACCOUNT_OPEN_SIGNUP' "$LOCAL_SETTINGS"; then
  sed -i 's/^ACCOUNT_OPEN_SIGNUP.*/ACCOUNT_OPEN_SIGNUP = False/' "$LOCAL_SETTINGS"
  if grep -q 'ACCOUNT_APPROVAL_REQUIRED' "$LOCAL_SETTINGS"; then
    sed -i 's/^ACCOUNT_APPROVAL_REQUIRED.*/ACCOUNT_APPROVAL_REQUIRED = True/' "$LOCAL_SETTINGS"
  else
    printf '\nACCOUNT_APPROVAL_REQUIRED = True\n' >> "$LOCAL_SETTINGS"
  fi
  if grep -q 'SOCIALACCOUNT_AUTO_SIGNUP' "$LOCAL_SETTINGS"; then
    sed -i 's/^SOCIALACCOUNT_AUTO_SIGNUP.*/SOCIALACCOUNT_AUTO_SIGNUP = False/' "$LOCAL_SETTINGS"
  else
    printf 'SOCIALACCOUNT_AUTO_SIGNUP = False\n' >> "$LOCAL_SETTINGS"
  fi
else
  cat >> "$LOCAL_SETTINGS" <<'EOF'

# Deshabilitar auto-registro publico (solo alta por administradores)
ACCOUNT_OPEN_SIGNUP = False
ACCOUNT_APPROVAL_REQUIRED = True
SOCIALACCOUNT_AUTO_SIGNUP = False
EOF
fi

echo "==> Limpiando pycache y recargando Apache"
rm -f /opt/geonode/geonode/__pycache__/local_settings*.pyc
rm -f /etc/geonode/__pycache__/local_settings*.pyc
systemctl reload apache2
sleep 2
systemctl is-active apache2

echo "==> Valores aplicados"
grep -E 'ACCOUNT_OPEN_SIGNUP|ACCOUNT_APPROVAL_REQUIRED|SOCIALACCOUNT_AUTO_SIGNUP' "$ENV_FILE"
echo '---'
tail -20 "$LOCAL_SETTINGS"

echo "==> Listo: registro publico deshabilitado"
