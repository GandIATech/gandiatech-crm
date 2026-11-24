#!/usr/bin/env bash
#
# Genera un paquete listo para publicar dentro de una subcarpeta de un hosting
# tradicional (Apache + PHP), por ejemplo https://midominio.com/crm/
#
#   ./empaquetar-subcarpeta.sh [/crm] [carpeta_salida]
#
# El primer argumento es la ruta de la subcarpeta (con barra inicial y sin
# barra final). El segundo es la carpeta de destino; por defecto, demo_online
# junto al repositorio.
#
# Diferencias con el paquete normal:
#   - Las URLs del frontend se reescriben con el prefijo de la subcarpeta.
#   - .htaccess usa RewriteBase y rutas relativas.
#   - El backend calcula el prefijo en tiempo de ejecución (crm_base_path()).
set -euo pipefail

BASE="${1:-/crm}"
OUT="${2:-$(cd "$(dirname "$0")/.." && pwd)/demo_online}"
ROOT="$(cd "$(dirname "$0")" && pwd)"
BASE="${BASE%/}"

if [ -z "$BASE" ] || [ "${BASE#/}" = "$BASE" ]; then
    echo "La subcarpeta debe empezar por '/': por ejemplo /crm o /apps/crm" >&2
    exit 1
fi
if ! printf '%s' "$BASE" | grep -qE '^(/[A-Za-z0-9._-]+)+$'; then
    echo "Ruta de subcarpeta no válida: $BASE" >&2
    exit 1
fi

echo "Subcarpeta: $BASE"
echo "Destino:    $OUT"

rm -rf "$OUT"
mkdir -p "$OUT"/{config,data/uploads,app,api,install,src,vendor,database}

cp -R "$ROOT/public/app/." "$OUT/app/"
cp -R "$ROOT/public/api/." "$OUT/api/"
cp -R "$ROOT/public/install/." "$OUT/install/"
cp -R "$ROOT/src/." "$OUT/src/"
cp -R "$ROOT/vendor/." "$OUT/vendor/"
cp -R "$ROOT/database/." "$OUT/database/"

cp "$ROOT/config/.htaccess" "$OUT/config/.htaccess"
cp "$ROOT/data/.htaccess" "$OUT/data/.htaccess"
if [ -f "$ROOT/config/config.example.php" ]; then
    cp "$ROOT/config/config.example.php" "$OUT/config/config.example.php"
fi

for doc in LICENSE README.md CHANGELOG.md SECURITY.md; do
    [ -f "$ROOT/$doc" ] && cp "$ROOT/$doc" "$OUT/$doc"
done
[ -f "$ROOT/LEEME-INSTALACION.md" ] && cp "$ROOT/LEEME-INSTALACION.md" "$OUT/LEEME.md"
[ -f "$ROOT/SEGURIDAD.md" ] && cp "$ROOT/SEGURIDAD.md" "$OUT/SEGURIDAD.md"

# Prefijo de las URLs del frontend (HTML/CSS/JS ya compilados).
BASE="$BASE" OUT="$OUT" python3 - <<'PY'
import os, re

base = os.environ['BASE']
out = os.environ['OUT']
exts = ('.html', '.htm', '.js', '.css', '.json', '.svg', '.webmanifest')
changed = 0
counts = {'app': 0, 'api': 0, 'install': 0}

for root, _dirs, files in os.walk(out):
    if os.sep + 'app' + os.sep not in root + os.sep:
        continue
    for name in files:
        if not name.lower().endswith(exts):
            continue
        path = os.path.join(root, name)
        with open(path, encoding='utf-8', errors='surrogateescape') as fh:
            src = fh.read()
        dst = src
        dst = dst.replace('/app/', base + '/app/')
        dst = dst.replace('/install/', base + '/install/')
        dst, n_api = re.subn(r'(["\'(]|`)/api(?=["\'`)`/$])', r'\1' + base + '/api', dst)
        if dst != src:
            with open(path, 'w', encoding='utf-8', errors='surrogateescape') as fh:
                fh.write(dst)
            changed += 1
            counts['app'] += src.count('/app/')
            counts['install'] += src.count('/install/')
            counts['api'] += n_api

print(f'Ficheros del frontend reescritos: {changed}')
print(f'Referencias prefijadas: /app/ x{counts["app"]}, /api x{counts["api"]}, /install/ x{counts["install"]}')

# Verificacion: no deben quedar rutas absolutas sin el prefijo.
leftovers = []
for root, _dirs, files in os.walk(out):
    if os.sep + 'app' + os.sep not in root + os.sep:
        continue
    for name in files:
        if not name.lower().endswith(exts):
            continue
        path = os.path.join(root, name)
        with open(path, encoding='utf-8', errors='surrogateescape') as fh:
            text = fh.read()
        for pattern in ('"/app/', "'/app/", '`/app/', ') "/api', '"/api"', "'/api'"):
            if pattern in text:
                leftovers.append((os.path.relpath(path, out), pattern))
if leftovers:
    print('AVISO: quedan rutas sin prefijar:')
    for rel, pattern in leftovers[:10]:
        print(f'  {rel}: {pattern}')
else:
    print('Sin rutas absolutas pendientes de prefijar.')
PY

# Reglas de Apache para la subcarpeta.
cat > "$OUT/.htaccess" <<HTACCESS
# GandiaTech CRM · publicacion en subcarpeta $BASE
Options -Indexes -MultiViews
DirectoryIndex index.html index.php
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase $BASE/

    # Carpetas privadas: nunca se sirven por HTTP.
    RewriteRule ^(?:src|vendor|config|data|database)(?:/|\$) - [F,L,NC]

    # Instalador (si ya hay configuracion, la propia pagina lo indica).
    RewriteRule ^install(?:/.*)?\$ install/index.php [L,QSA]

    # API: /api y /api/* -> api/index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^api(?:/.*)?\$ api/index.php [L,QSA]

    # La raiz la resuelve index.php. La comprobacion de si hay configuracion
    # la hace PHP: %{DOCUMENT_ROOT} no siempre coincide con la ruta real del
    # hosting (IONOS sirve desde una ruta distinta a la del cliente FTP).
    RewriteRule ^\$ index.php [L]
    RewriteRule ^app\$ app/ [R=302,L]

    # Archivos y directorios reales.
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^app/ app/404.html [L]
</IfModule>

ErrorDocument 404 $BASE/app/404.html

<FilesMatch "(^\.|^composer\.(json|lock|phar)\$|\.(md|sql|sqlite|sqlite3|log|ini|sh|bak|phar)\$)">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "same-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set X-Permitted-Cross-Domain-Policies "none"
    Header always set X-Robots-Tag "noindex, nofollow"
    Header always set Content-Security-Policy "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline'; connect-src 'self'"
    Header always set Strict-Transport-Security "max-age=31536000" env=HTTPS
</IfModule>
HTACCESS

# Punto de entrada de la raiz. Es el mismo front controller que usa el paquete
# de dominio: decide entre la app y el instalador con rutas reales de PHP, sin
# depender de %{DOCUMENT_ROOT} (que en algunos hostings no coincide con la ruta
# del cliente FTP y dejaba la app atrapada en /install/).
cp "$ROOT/deploy-index.php" "$OUT/index.php"

cat > "$OUT/LEEME-SUBCARPETA.md" <<LEEME
# GandiaTech CRM en una subcarpeta ($BASE)

Paquete preparado para publicarse en \`https://tu-dominio.com$BASE/\` en un
hosting tradicional con Apache + PHP 8.2 o superior (IONOS, Raiola, Cdmon…).

## Publicación

1. Sube **el contenido** de esta carpeta a la subcarpeta \`$BASE\` (no la carpeta
   en sí): \`.htaccess\`, \`index.php\`, \`app/\`, \`api/\`, \`install/\`, \`src/\`,
   \`vendor/\`, \`database/\`, \`config/\`, \`data/\` y los documentos.
2. Da permiso de escritura a \`config/\` y \`data/\` (por ejemplo \`755\` o \`775\`).
3. Selecciona PHP 8.2 o superior en el panel del hosting.
4. Abre \`https://tu-dominio.com$BASE/\`: te llevará al instalador. El primer
   paso comprueba el entorno (versión de PHP, extensiones, permisos de
   escritura y reglas de Apache) y explica cómo corregir lo que falte antes
   de tocar nada. Si la versión de PHP es antigua verás una página con la
   versión detectada, no un error 500.
5. El instalador crea \`config/config.php\` y la base de datos (SQLite por
   defecto) y se borra solo al terminar.
6. Entra en \`https://tu-dominio.com$BASE/app/\` con el usuario administrador.

## Si la subcarpeta tiene otro nombre

Las URLs del frontend llevan el prefijo \`$BASE\` escrito en el HTML y el JS.
Si publicas en otra ruta, vuelve a generar el paquete desde el repositorio:

\`\`\`bash
./empaquetar-subcarpeta.sh /otra-ruta /ruta/de/salida
\`\`\`

## Requisitos y límites

- Apache con \`mod_rewrite\` (IONOS lo tiene activo). En Nginx habría que
  traducir las reglas del \`.htaccess\`.
- HTTPS es muy recomendable: el CRM maneja credenciales y datos de clientes.
- SQLite necesita que \`data/\` sea escribible; si no, usa MySQL/MariaDB desde
  el instalador.
- El instalador y la API deben poder escribir en \`data/uploads\` para adjuntos.
LEEME

chmod -R u+rwX "$OUT/config" "$OUT/data"

printf '\nPaquete generado: %s\n' "$OUT"
printf 'URL de instalacion prevista: https://tu-dominio.com%s/install/\n' "$BASE"
