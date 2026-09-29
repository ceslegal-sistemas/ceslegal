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

echo "==> 1/7  git pull"
git pull

echo "==> 2/7  composer install (--no-dev)"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader

echo "==> 3/7  npm install + build de assets (Vite/Tailwind)"
# Sin este paso, public/build/manifest.json nunca existe en el servidor -
# cualquier vista con @vite(...) revienta con 500 "Vite manifest not found"
# (bug real 2026-09-28: las paginas publicas de RIT/descargos empezaron a
# usar @vite() para dejar de depender del CDN fragil de Tailwind, pero el
# deploy nunca habia compilado assets, asi que quedaron sin manifest).
npm ci
npm run build

echo "==> 4/7  migraciones"
php artisan migrate --force

echo "==> 5/7  limpiar y recachear"
# Salvaguarda: algunos hosts no ejecutan el hook de composer que crea esta carpeta
# (el paquete filament-notification-sound la necesita o falla el view:cache).
mkdir -p vendor/moataz-01/filament-notification-sound/resources/views
php artisan optimize:clear
php artisan optimize

echo "==> 6/7  resetear OPcache del SAPI web"
echo '<?php opcache_reset(); echo "OPCACHE_RESET_OK ".PHP_VERSION;' > public/_oc.php
curl -s "${APP_URL}/_oc.php" || echo "(no se pudo hacer curl; visita ${APP_URL}/_oc.php en el navegador)"
echo
rm -f public/_oc.php

echo "==> 7/7  commit desplegado:"
git log --oneline -1

echo "==> LISTO. Reabre 'Emitir Sanción' (la cache de analisis se regenera sola)."
