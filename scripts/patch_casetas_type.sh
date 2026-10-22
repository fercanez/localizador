#!/usr/bin/env bash
# Agrega casetas_de_fraccs a PHP de producción (mapa de tipos / checkboxes).
# Uso en el servidor:
#   bash /tmp/patch_casetas_type.sh
# Opcional: MXLI_ROOT=/otra/ruta bash /tmp/patch_casetas_type.sh

set -euo pipefail

ROOT="${MXLI_ROOT:-/var/www/html/localizacion}"
STAMP="$(date +%Y%m%d%H%M%S)"
KEY="casetas_de_fraccs"
LABEL="Casetas de Fraccs."

echo "==> Patch ${KEY} en ${ROOT}"

patch_one() {
  local f="$1"
  if [[ ! -f "$f" ]]; then
    echo "  (no existe) $f"
    return 0
  fi
  if grep -q "$KEY" "$f"; then
    echo "  (ya presente) $f"
    return 0
  fi

  cp -a "$f" "${f}.bak.${STAMP}"
  echo "  backup ${f}.bak.${STAMP}"

  python3 - "$f" "$KEY" "$LABEL" <<'PY'
import re, sys
path, key, label = sys.argv[1], sys.argv[2], sys.argv[3]
text = open(path, "r", encoding="utf-8", errors="replace").read()
orig = text

# 1) PHP assoc array: insert before 'otro' => 'Otro' (single or double quotes)
def repl_assoc(m):
    q1, q2 = m.group(1), m.group(2)
    return f"{q1}{key}{q1} => {q2}{label}{q2},\n            {m.group(0)}"

text2, n = re.subn(
    r"(['\"])otro\1\s*=>\s*(['\"])Otro\2",
    repl_assoc,
    text,
    count=1,
)
if n:
    text = text2

# 2) HTML checkbox: insert a line before value="otro"
if f'value="{key}"' not in text and 'value="otro"' in text:
    lines = text.splitlines(keepends=True)
    out = []
    inserted = False
    for line in lines:
        if not inserted and 'value="otro"' in line:
            indent = re.match(r"^(\s*)", line).group(1)
            # Try to mirror a simple checkbox/label pattern
            if "<label" in line.lower() or "<input" in line.lower():
                out.append(
                    f'{indent}<label><input type="checkbox" name="inspection_types" value="{key}"> {label}</label>\n'
                )
            else:
                out.append(f'{indent}// {key} => {label}\n')
            inserted = True
        out.append(line)
    text = "".join(out)

# 3) Fallback: allowed-types list containing 'otro' or lic_construccion
if key not in text:
    text2, n = re.subn(
        r"(['\"])lic_construccion\1",
        lambda m: f"{m.group(0)}, {m.group(1)}{key}{m.group(1)}",
        text,
        count=1,
    )
    if n and key in text2:
        text = text2

if text == orig:
    print(f"  WARN: sin cambios automáticos en {path}", file=sys.stderr)
    sys.exit(2)

open(path, "w", encoding="utf-8").write(text)
print(f"  OK {path}")
PY
}

FILES=(
  "${ROOT}/includes/inspections.php"
  "${ROOT}/admin/inspecciones.php"
)

shopt -s nullglob
for f in "${ROOT}/admin/inspecciones-"*.php; do
  FILES+=("$f")
done
shopt -u nullglob

status=0
for f in "${FILES[@]}"; do
  if ! patch_one "$f"; then
    status=1
  fi
done

echo "==> Verificar:"
grep -n "$KEY" "${ROOT}/includes/inspections.php" "${ROOT}/admin/inspecciones.php" \
  "${ROOT}/admin"/inspecciones-*.php 2>/dev/null || true

exit "$status"
