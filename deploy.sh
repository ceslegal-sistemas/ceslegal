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

echo "==> 1/8  git pull"
git pull

echo "==> 2/8  composer install (--no-dev)"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader

echo "==> 3/8  build de assets (Vite/Tailwind)"
# Sin esto, public/build/manifest.json no existe - cualquier vista con
# @vite(...) revienta con 500 "Vite manifest not found" (bug real
# 2026-09-28: las paginas publicas de RIT/descargos empezaron a usar
# @vite() para dejar de depender del CDN fragil de Tailwind).
#
# Este host de Hostinger NO tiene npm/node en el PATH (confirmado
# 2026-09-28: "npm: command not found") - por eso public/build/ dejo de
# estar en .gitignore: los assets se compilan LOCALMENTE y se comprometen
# al repo, y `git pull` ya los trae listos. Si algun dia este host (u
# otro) SI tiene npm, este paso recompila con lo ultimo; si no, usa el
# build ya versionado - nunca debe tumbar el resto del deploy.
if command -v npm >/dev/null 2>&1; then
    npm ci
    npm run build
else
    echo "    (npm no disponible en este host - se usan los assets ya compilados y versionados en git)"
fi

echo "==> 4/8  migraciones"
php artisan migrate --force

echo "==> 5/8  limpiar y recachear"
# Salvaguarda: algunos hosts no ejecutan el hook de composer que crea esta carpeta
# (el paquete filament-notification-sound la necesita o falla el view:cache).
mkdir -p vendor/moataz-01/filament-notification-sound/resources/views
php artisan optimize:clear
php artisan optimize

echo "==> 6/8  resetear OPcache del SAPI web"
echo '<?php opcache_reset(); echo "OPCACHE_RESET_OK ".PHP_VERSION;' > public/_oc.php
curl -s "${APP_URL}/_oc.php" || echo "(no se pudo hacer curl; visita ${APP_URL}/_oc.php en el navegador)"
echo
rm -f public/_oc.php

echo "==> 7/8  commit desplegado:"
git log --oneline -1

echo "==> 8/8  smoke test: confirma que las paginas publicas respondan bien"
# Pedido explicito del usuario (2026-09-28, "necesito que no suceda nunca"):
# ningun paso anterior avisaba si el deploy dejaba algo roto de verdad para
# un visitante real - el bug del manifest de Vite faltante se descubrio por
# una captura de pantalla de un trabajador, no por el propio deploy. Esta
# verificacion visita una pagina PUBLICA real (sin necesitar datos validos -
# VerificacionDocumentoController siempre renderiza esta vista, con o sin
# token real) y avisa FUERTE en esta misma terminal si el servidor responde
# con un error, en vez de quedar en silencio hasta que alguien mas lo note.
SMOKE_URL="${APP_URL}/verificar/deploy-smoke-check"
SMOKE_STATUS="$(curl -s -o /tmp/lupe_deploy_smoke.html -w "%{http_code}" "$SMOKE_URL" || echo "000")"
if [ "$SMOKE_STATUS" = "200" ]; then
    echo "    OK - $SMOKE_URL respondio 200"
else
    echo ""
    echo "    #############################################################"
    echo "    #  ALERTA: $SMOKE_URL respondio HTTP $SMOKE_STATUS (se esperaba 200)"
    echo "    #  El deploy probablemente dejo algo roto para un visitante real."
    echo "    #  Revisa /tmp/lupe_deploy_smoke.html y los logs de Laravel."
    echo "    #############################################################"
    echo ""
fi
rm -f /tmp/lupe_deploy_smoke.html

echo "==> LISTO. Reabre 'Emitir Sanción' (la cache de analisis se regenera sola)."
