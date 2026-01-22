#!/bin/bash

# ===========================================
# Script de Despliegue - CES Legal
# ===========================================
# Uso: bash deploy.sh
# ===========================================

echo "🚀 Iniciando despliegue de CES Legal..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar que estamos en la carpeta correcta
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: No se encontró el archivo artisan. Asegúrate de estar en la carpeta del proyecto Laravel.${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Instalando dependencias de Composer...${NC}"
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}🔑 Generando clave de aplicación (si no existe)...${NC}"
php artisan key:generate --force

echo -e "${YELLOW}🗄️ Ejecutando migraciones...${NC}"
php artisan migrate --force

echo -e "${YELLOW}🌱 Ejecutando seeders (si es necesario)...${NC}"
# php artisan db:seed --force

echo -e "${YELLOW}🧹 Limpiando cachés...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo -e "${YELLOW}⚡ Optimizando para producción...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

echo -e "${YELLOW}🔗 Creando enlace simbólico de storage...${NC}"
php artisan storage:link 2>/dev/null || echo "El enlace ya existe"

echo -e "${YELLOW}📁 Configurando permisos...${NC}"
chmod -R 775 storage bootstrap/cache
chmod 644 .env

echo -e "${GREEN}✅ Despliegue completado exitosamente!${NC}"
echo ""
echo -e "${YELLOW}📋 Recuerda verificar:${NC}"
echo "   1. APP_URL en .env apunta a tu dominio"
echo "   2. APP_ENV=production"
echo "   3. APP_DEBUG=false"
echo "   4. Configuración de base de datos correcta"
echo ""
