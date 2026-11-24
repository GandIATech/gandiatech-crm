#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-1.0.0}"
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="$ROOT/dist"
NAME="gandiatech-crm-basico-$VERSION"
STAGE="$OUT/$NAME"

rm -rf "$STAGE"
mkdir -p "$STAGE/config" "$STAGE/data/uploads" "$STAGE/app" "$STAGE/api" "$STAGE/install" "$STAGE/src" "$STAGE/vendor" "$STAGE/database"

# Frontend, API e instalador.
cp -R "$ROOT/public/app/." "$STAGE/app/"
cp -R "$ROOT/public/api/." "$STAGE/api/"
cp -R "$ROOT/public/install/." "$STAGE/install/"

# Backend y dependencias.
cp -R "$ROOT/src/." "$STAGE/src/"
cp -R "$ROOT/vendor/." "$STAGE/vendor/"
cp -R "$ROOT/database/." "$STAGE/database/"

# Configuración y datos vacíos, protegidos por .htaccess.
cp "$ROOT/config/.htaccess" "$STAGE/config/.htaccess"
cp "$ROOT/data/.htaccess" "$STAGE/data/.htaccess"

# Reglas de Apache y documentación de la versión distribuible.
cp "$ROOT/deploy-root.htaccess" "$STAGE/.htaccess"
cp "$ROOT/deploy-router.php" "$STAGE/router.php"
if [ -f "$ROOT/LEEME-INSTALACION.md" ]; then
    cp "$ROOT/LEEME-INSTALACION.md" "$STAGE/LEEME.md"
elif [ -f "$ROOT/../LEEME-INSTALACION.md" ]; then
    cp "$ROOT/../LEEME-INSTALACION.md" "$STAGE/LEEME.md"
fi

if [ -f "$ROOT/SEGURIDAD.md" ]; then
    cp "$ROOT/SEGURIDAD.md" "$STAGE/SEGURIDAD.md"
elif [ -f "$ROOT/../SEGURIDAD.md" ]; then
    cp "$ROOT/../SEGURIDAD.md" "$STAGE/SEGURIDAD.md"
fi

rm -f "$OUT/$NAME.zip"
(cd "$OUT" && zip -qr "$NAME.zip" "$NAME")

echo "Paquete generado: $OUT/$NAME.zip"
