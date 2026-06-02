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

COMPOSER_BIN="$(command -v composer || echo /opt/cpanel/composer/bin/composer)"

echo "==> Actualizando codigo (git pull)"
git pull --ff-only origin main

echo "==> Instalando dependencias de produccion"
export COMPOSER_MEMORY_LIMIT=-1
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Ejecutando migraciones"
php artisan migrate --force

echo "==> Enlazando storage publico"
php artisan storage:link || true

echo "==> Reconstruyendo caches (config/rutas/vistas)"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Despliegue completado"
