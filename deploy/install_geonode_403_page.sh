#!/usr/bin/env bash
set -euo pipefail

ROOT_ETC="/etc/geonode"
ROOT_OPT="/opt/geonode/geonode"
STATIC_IMG="/var/www/geonode/static/img"
LOGO_SRC="/var/www/html/localizacion/escudo-mexicali.png"
LOGO_ALT="/var/www/geonode/uploaded/img/2025/10/Logo_Gobierno_de_Mexicali.png"

mkdir -p "${ROOT_ETC}/templates" "${ROOT_ETC}/middleware" "${STATIC_IMG}"
mkdir -p "${ROOT_OPT}/templates"

# Logo estatico
if [[ -f "${LOGO_SRC}" ]]; then
  cp -f "${LOGO_SRC}" "${STATIC_IMG}/escudo-mexicali.png"
elif [[ -f "${LOGO_ALT}" ]]; then
  cp -f "${LOGO_ALT}" "${STATIC_IMG}/escudo-mexicali.png"
fi
chown www-data:www-data "${STATIC_IMG}/escudo-mexicali.png" || true

# Paquete middleware minimo
touch "${ROOT_ETC}/middleware/__init__.py"
cat > "${ROOT_ETC}/middleware/forbidden_page.py" <<'PY'
"""Reemplaza respuestas 403 planas ('Not allowed') por pagina institucional."""

from django.shortcuts import render


class MexicaliForbiddenPageMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)
        if response.status_code != 403:
            return response

        content_type = (response.get("Content-Type") or "").lower()
        if "text/html" in content_type and b"<html" in (response.content or b"").lower():
            return response

        try:
            text = (response.content or b"").decode("utf-8", errors="ignore").strip()
        except Exception:
            text = ""

        # Solo sustituir respuestas cortas / "Not allowed" de GeoNode
        lowered = text.lower()
        if text and "not allowed" not in lowered and "not permitted" not in lowered and "no permitido" not in lowered:
            if len(text) > 120:
                return response

        return render(request, "403_unauthorized.html", status=403)
PY

cat > "${ROOT_ETC}/templates/403_unauthorized.html" <<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso no autorizado | GeoMexicali</title>
  <style>
    :root {
      --mx-ink: #1f2a30;
      --mx-muted: #4a5a63;
      --mx-line: #d7dde1;
      --mx-bg: #f4f6f7;
      --mx-card: #ffffff;
      --mx-accent: #8b1e2d;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.25rem;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      color: var(--mx-ink);
      background:
        radial-gradient(circle at top left, rgba(139, 30, 45, 0.08), transparent 40%),
        linear-gradient(180deg, #fafbfc 0%, var(--mx-bg) 100%);
    }
    .card {
      width: min(560px, 100%);
      background: var(--mx-card);
      border: 1px solid var(--mx-line);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      text-align: center;
      box-shadow: 0 12px 40px rgba(31, 42, 48, 0.08);
    }
    .logo {
      width: min(220px, 70vw);
      height: auto;
      margin: 0 auto 1.5rem;
      display: block;
    }
    h1 {
      margin: 0 0 0.85rem;
      font-size: 1.35rem;
      line-height: 1.35;
      font-weight: 700;
      color: var(--mx-accent);
    }
    p {
      margin: 0;
      font-size: 1.05rem;
      line-height: 1.55;
      color: var(--mx-muted);
    }
    .actions {
      margin-top: 1.75rem;
      display: flex;
      gap: 0.75rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    a {
      display: inline-block;
      text-decoration: none;
      border-radius: 8px;
      padding: 0.7rem 1.1rem;
      font-weight: 600;
      font-size: 0.95rem;
    }
    a.primary {
      background: var(--mx-accent);
      color: #fff;
    }
    a.secondary {
      background: #eef2f4;
      color: var(--mx-ink);
    }
  </style>
</head>
<body>
  <main class="card" role="main">
    <img class="logo" src="/static/img/escudo-mexicali.png" alt="Escudo del Municipio de Mexicali">
    <h1>Acceso no autorizado</h1>
    <p>
      Usted no está autorizado para entrar a estas páginas.
      Favor de solicitar acceso al Área de Catastro en Mexicali.
    </p>
    <div class="actions">
      <a class="primary" href="/account/login/?next={{ request.get_full_path|urlencode }}">Ingresar</a>
      <a class="secondary" href="/">Ir al inicio</a>
    </div>
  </main>
</body>
</html>
HTML

# Copiar template al path de templates de GeoNode
cp -f "${ROOT_ETC}/templates/403_unauthorized.html" "${ROOT_OPT}/templates/403_unauthorized.html"

# Asegurar que /etc/geonode este en PYTHONPATH via symlink en site package path del proyecto
# Middleware se importa como geonode.middleware... creando symlink dentro del paquete geonode
mkdir -p "${ROOT_OPT}/middleware_mexicali"
cp -f "${ROOT_ETC}/middleware/__init__.py" "${ROOT_OPT}/middleware_mexicali/__init__.py"
cp -f "${ROOT_ETC}/middleware/forbidden_page.py" "${ROOT_OPT}/middleware_mexicali/forbidden_page.py"

# Patch local_settings.py
LOCAL="/etc/geonode/local_settings.py"
if ! grep -q 'MexicaliForbiddenPageMiddleware' "${LOCAL}"; then
  cat >> "${LOCAL}" <<'EOF'

# Pagina institucional en lugar de "Not allowed" (403)
TEMPLATES[0]["DIRS"] = list(TEMPLATES[0].get("DIRS") or []) + ["/etc/geonode/templates"]
MIDDLEWARE = tuple(MIDDLEWARE) + ("geonode.middleware_mexicali.forbidden_page.MexicaliForbiddenPageMiddleware",)
EOF
fi

# Limpiar cache y reiniciar Apache/WSGI
rm -f "${ROOT_OPT}/__pycache__/local_settings"*.pyc
rm -rf "${ROOT_OPT}/middleware_mexicali/__pycache__"
touch /var/www/geonode/wsgi/geonode.wsgi
apache2ctl graceful
sleep 2
systemctl is-active apache2

echo "OK: pagina 403 institucional instalada"
