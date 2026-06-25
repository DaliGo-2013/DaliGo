#!/usr/bin/env bash
#
# Despliegue de DaliGo en HostGator (hosting compartido, sin daemons).
# Uso: ejecutar desde la raiz del proyecto en el servidor (~/daligo):
#     bash deploy.sh
#
# NOTA: para el PRIMER despliegue primero hay que crear el archivo .env
# (con las credenciales de la BD) y ejecutar:  php artisan key:generate
# Este script asume que .env ya existe y tiene APP_KEY definido.

set -euo pipefail
cd "$(dirname "$0")"

# PHP 8.3 explicito: misma version que el entorno local y el web de produccion
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER_BIN=/opt/cpanel/composer/bin/composer

echo "==> Actualizando codigo (git fetch + reset --hard origin/main)"
# El servidor es un destino de despliegue: debe quedar identico a origin/main.
# Usamos reset --hard (no pull) para descartar cualquier cambio del arbol de
# trabajo que rompa el merge: el bloque de handler PHP que cPanel reintroduce en
# public/.htaccess, binarios que el hosting altera, etc. Los archivos NO rastreados
# (.env, vendor/, public/storage) no se tocan.
git fetch origin main
git reset --hard origin/main

echo "==> Instalando dependencias de produccion (PHP 8.3)"
export COMPOSER_MEMORY_LIMIT=-1
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Ejecutando migraciones"
"$PHP_BIN" artisan migrate --force

echo "==> Seed base (idempotente): roles/permisos + sucursales"
"$PHP_BIN" artisan db:seed --force

echo "==> Enlazando storage publico"
"$PHP_BIN" artisan storage:link --force >/dev/null 2>&1 || true

echo "==> Reconstruyendo caches (config/rutas/vistas)"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "==> Reset de cache de permisos (spatie)"
"$PHP_BIN" artisan permission:cache-reset

echo "==> Despliegue completado"
