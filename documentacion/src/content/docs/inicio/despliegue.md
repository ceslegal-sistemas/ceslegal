---
title: Despliegue
description: Guía para desplegar CES Legal en un servidor de producción
---

## Requisitos del Servidor

### Hardware Mínimo

| Recurso | Mínimo | Recomendado |
|---------|--------|-------------|
| CPU | 1 core | 2+ cores |
| RAM | 2 GB | 4+ GB |
| Disco | 20 GB SSD | 50+ GB SSD |

### Software del Servidor

- Ubuntu 22.04 LTS o similar
- PHP 8.2+ con FPM
- Nginx o Apache
- MySQL 8.0+
- Composer 2.0+
- Node.js 18+

## Preparación del Servidor

### 1. Actualizar el Sistema

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Instalar PHP y Extensiones

```bash
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-bcmath php8.2-curl php8.2-gd php8.2-zip php8.2-intl -y
```

### 3. Instalar MySQL

```bash
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

### 4. Instalar Apache

```bash
sudo apt install apache2 
```
:::note
Si desea instalar con Nginx, usa:
```bash
sudo apt install nginx -y
```
:::

### 5. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 6. Instalar Node.js

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs -y
```

## Configuración de la Aplicación

### 1. Clonar el Repositorio

```bash
cd /var/www
sudo git clone https://github.com/juanparen15/ces-legal.git
cd ces-legal
```

### 2. Configurar Permisos

```bash
sudo chown -R www-data:www-data /var/www/ces-legal
sudo chmod -R 755 /var/www/ces-legal
sudo chmod -R 775 /var/www/ces-legal/storage
sudo chmod -R 775 /var/www/ces-legal/bootstrap/cache
```

### 3. Instalar Dependencias

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 4. Configurar Variables de Entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con configuración de producción:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
```

### 5. Crear Base de Datos

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE ces_legal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ces_user'@'localhost' IDENTIFIED BY 'contraseña_segura';
GRANT ALL PRIVILEGES ON ces_legal.* TO 'ces_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 6. Ejecutar Migraciones

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 7. Optimizar para Producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```
## Configuaración de Apache

Crea el archivo de configuración:

```bash
sudo nano /etc/apache2/sites-available/tu-dominio.com.conf
```

```
<VirtualHost *:80>
    ServerName tu-dominio.com
    ServerAlias www.tu-dominio.com
    DocumentRoot /var/www/ces-legal/public

    <Directory /var/www/ces-legal/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Headers de seguridad
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"

    # Charset
    AddDefaultCharset utf-8

    # Index
    DirectoryIndex index.php

    # Manejo de rutas tipo Laravel (try_files)
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [L]
    </IfModule>

    # PHP-FPM 8.2
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Errores
    ErrorLog ${APACHE_LOG_DIR}/ces-legal-error.log
    CustomLog ${APACHE_LOG_DIR}/ces-legal-access.log combined

    # Desactivar logs para favicon y robots
    <Files "favicon.ico">
        SetEnv no-log
    </Files>

    <Files "robots.txt">
        SetEnv no-log
    </Files>

    # Limitar tamaño de subida (50MB)
    LimitRequestBody 52428800
</VirtualHost>
```

### Habilitar módulos necesarios

Ejecuta esto **una sola vez**:
```bash
sudo a2enmod rewrite headers proxy proxy_fcgi setenvif
```

Activar el sitio:

```bash
sudo a2ensite tu-dominio.com
sudo systemctl reload apache2
```

## Configuración de Nginx

Crea el archivo de configuración:

```bash
sudo nano /etc/nginx/sites-available/ces-legal
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tu-dominio.com www.tu-dominio.com;
    root /var/www/ces-legal/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Limitar tamaño de archivos
    client_max_body_size 50M;
}
```

Activar el sitio:

```bash
sudo ln -s /etc/nginx/sites-available/ces-legal /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Configuración de SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d tu-dominio.com -d www.tu-dominio.com
```

## Configuración de Cron (Tareas Programadas)

```bash
sudo crontab -e -u www-data
```

Agregar:

```bash
* * * * * cd /var/www/ces-legal &&  php artisan procesos:actualizar-estados-descargos >> /dev/null 2>&1
```

## Script de Despliegue Automatizado

Crea un script `deploy.sh`:

```bash
#!/bin/bash

cd /var/www/ces-legal

# Modo mantenimiento
php artisan down

# Obtener últimos cambios
git pull origin main

# Instalar dependencias
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Migraciones
php artisan migrate --force

# Limpiar y cachear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar colas
sudo supervisorctl restart ces-legal-worker:*

# Salir de mantenimiento
php artisan up

echo "Despliegue completado!"
```

```bash
chmod +x deploy.sh
```

## Verificación Post-Despliegue

1. **Verificar la aplicación**: Accede a `https://tu-dominio.com/admin`
2. **Verificar logs**: `tail -f /var/www/ces-legal/storage/logs/laravel.log`
3. **Verificar SSL**: Usa [SSL Labs](https://www.ssllabs.com/ssltest/)

## Monitoreo

### Logs de Laravel

```bash
tail -f /var/www/ces-legal/storage/logs/laravel.log
```

### Logs de Apache

```bash
tail -f /var/log/apache2/error.log
```

### Logs de Nginx

```bash
tail -f /var/log/nginx/error.log
```

### Estado de Servicios

```bash
sudo systemctl status apache2
# sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo supervisorctl status
```

## Backup

### Backup de Base de Datos

```bash
mysqldump -u ces_user -p ces_legal > backup_$(date +%Y%m%d).sql
```

### Backup de Archivos

```bash
tar -czf storage_backup_$(date +%Y%m%d).tar.gz /var/www/ces-legal/storage/app
```

## Próximos Pasos

- [Troubleshooting](/referencia/troubleshooting/) - Solución de problemas
- [Variables de Entorno](/referencia/variables-entorno/) - Referencia completa
