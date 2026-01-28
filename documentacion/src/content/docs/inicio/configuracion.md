---
title: Configuración
description: Guía completa de configuración de variables de entorno y ajustes del sistema
---

## Variables de Entorno

El archivo `.env` contiene todas las configuraciones del sistema. A continuación se detallan las variables más importantes.

### Configuración de Aplicación

```ini
APP_NAME="CES LEGAL"
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=true
APP_TIMEZONE=America/Bogota
APP_URL=http://localhost:8000
APP_LOCALE=es
```

| Variable | Descripción | Valores |
|----------|-------------|---------|
| `APP_NAME` | Nombre de la aplicación | String |
| `APP_ENV` | Entorno de ejecución | `local`, `staging`, `production` |
| `APP_DEBUG` | Modo debug | `true` (desarrollo), `false` (producción) |
| `APP_TIMEZONE` | Zona horaria | `America/Bogota` para Colombia |
| `APP_LOCALE` | Idioma por defecto | `es` |

### Configuración de Base de Datos

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ces_legal
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

:::tip[Recomendación]
En producción, usa un usuario de base de datos con permisos limitados, no `root`.
:::

### Configuración de Correo (Gmail SMTP)

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_correo@gmail.com
MAIL_PASSWORD=tu_contraseña_de_aplicacion
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@ceslegal.com
MAIL_FROM_NAME="${APP_NAME}"
```

:::note[Contraseña de Aplicación de Gmail]
Para usar Gmail SMTP, necesitas crear una "Contraseña de aplicación":
1. Ve a tu cuenta de Google > Seguridad
2. Activa la verificación en 2 pasos
3. Crea una contraseña de aplicación para "Correo"
4. Usa esa contraseña en `MAIL_PASSWORD`
:::

### Configuración de Google Gemini (IA)

```ini
GEMINI_API_KEY=tu_api_key_de_gemini
GEMINI_MODEL=gemini-2.5-flash
```

Para obtener una API Key de Gemini:
1. Ve a [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Crea un nuevo proyecto o selecciona uno existente
3. Genera una API Key
4. Copia la clave en `GEMINI_API_KEY`

### Configuración de Sesión

```ini
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
```

| Driver | Uso Recomendado |
|--------|-----------------|
| `file` | Desarrollo local |
| `database` | Producción (requiere migración de sesiones) |
| `redis` | Alto rendimiento |

### Configuración de Caché

```ini
CACHE_STORE=database
CACHE_PREFIX=ces_legal
```

### Configuración de Colas (Queues)

```ini
QUEUE_CONNECTION=database
```

:::caution[Producción]
En producción, configura un worker de colas:
```bash
php artisan queue:work --daemon
```
O usa Supervisor para mantenerlo activo.
:::

### Configuración de Logs

```ini
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```

En producción, cambia `LOG_LEVEL` a `error` o `warning`.

### Configuración de Filament

```ini
FILAMENT_FILESYSTEM_DISK=public
```

## Configuración de Filament Shield (Permisos)

Los permisos se gestionan automáticamente. Para regenerar:

```bash
php artisan shield:generate --all
php artisan shield:super-admin --user=1
```

## Configuración de Caché en Producción

```bash
# Cachear configuración
php artisan config:cache

# Cachear rutas
php artisan route:cache

# Cachear vistas
php artisan view:cache

# Cachear eventos
php artisan event:cache
```

## Optimización de Composer

```bash
composer install --optimize-autoloader --no-dev
```

## Configuración de Términos Legales

Los términos legales se configuran en la base de datos a través del seeder o desde el panel de administración:

| Término | Descripción | Valor por Defecto |
|---------|-------------|-------------------|
| Plazo de citación | Días para citar al trabajador | 5 días hábiles |
| Plazo de descargos | Tiempo para realizar descargos | 5 días hábiles |
| Plazo de decisión | Tiempo para emitir sanción | 10 días hábiles |
| Token de acceso | Vigencia del token público | 6 días |

## Configuración de Días No Hábiles

Los días festivos de Colombia se cargan automáticamente con el seeder. Para actualizar:

```bash
php artisan db:seed --class=DiaNoHabilSeeder
```

## Archivo de Configuración Completo

```ini
# Aplicación
APP_NAME="CES LEGAL"
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_TIMEZONE=America/Bogota
APP_URL=https://tu-dominio.com
APP_LOCALE=es

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ces_legal
DB_USERNAME=ces_user
DB_PASSWORD=contraseña_segura

# Correo
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@ceslegal.com
MAIL_PASSWORD=contraseña_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@ceslegal.com
MAIL_FROM_NAME="${APP_NAME}"

# IA
GEMINI_API_KEY=tu_api_key
GEMINI_MODEL=gemini-2.5-flash

# Sesión y Caché
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# Logs
LOG_CHANNEL=stack
LOG_LEVEL=error

# Filament
FILAMENT_FILESYSTEM_DISK=public
```

## Próximos Pasos

- [Despliegue](/inicio/despliegue/) - Despliega en producción
- [Variables de Entorno](/referencia/variables-entorno/) - Referencia completa
