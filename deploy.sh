#!/usr/bin/env bash
#
# Despliegue de LUPE en producción (Hostinger).
# Uso:  bash deploy.sh
#
# Resuelve el problema recurrente del OPcache: tras `git pull`, el servidor web
# sigue ejecutando el PHP viejo en memoria hasta que se resetea el OPcache del
# SAPI web (php artisan NO lo resetea). Este script lo hace vía una petición HTTP.

set -e

# Lee APP_URL del .env para no atarlo a un dominio concreto (funciona al migrar de hosting).
APP_URL="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d "\"' ")"
APP_URL="${APP_URL:-http://localhost}"

echo "==> 1/6  git pull"
git pull

echo "==> 2/6  composer install (--no-dev)"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader

echo "==> 3/6  migraciones"
php artisan migrate --force

echo "==> 4/6  limpiar y recachear"
# Salvaguarda: algunos hosts no ejecutan el hook de composer que crea esta carpeta
# (el paquete filament-notification-sound la necesita o falla el view:cache).
mkdir -p vendor/moataz-01/filament-notification-sound/resources/views
php artisan optimize:clear
php artisan optimize

echo "==> 5/6  resetear OPcache del SAPI web"
echo '<?php opcache_reset(); echo "OPCACHE_RESET_OK ".PHP_VERSION;' > public/_oc.php
curl -s "${APP_URL}/_oc.php" || echo "(no se pudo hacer curl; visita ${APP_URL}/_oc.php en el navegador)"
echo
rm -f public/_oc.php

echo "==> 6/6  commit desplegado:"
git log --oneline -1

echo "==> LISTO. Reabre 'Emitir Sanción' (la cache de analisis se regenera sola)."
