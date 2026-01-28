---
title: Variables de Entorno
description: Referencia completa de todas las variables de entorno del sistema CES Legal
---

El archivo `.env` en la raiz del proyecto controla toda la configuracion del sistema. A continuacion se documenta cada variable agrupada por categoria.

## Aplicacion

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `APP_NAME` | Nombre de la aplicacion mostrado en la interfaz y correos | Si | `Laravel` | `"CES LEGAL"` |
| `APP_ENV` | Entorno de ejecucion | Si | `local` | `production` |
| `APP_KEY` | Clave de cifrado de la aplicacion (generada con `php artisan key:generate`) | Si | — | `base64:abc123...` |
| `APP_DEBUG` | Activa el modo de depuracion con mensajes de error detallados | Si | `true` | `false` |
| `APP_URL` | URL base de la aplicacion, usada para generar enlaces en correos y documentos | Si | `http://localhost` | `https://app.ceslegal.com` |
| `APP_TIMEZONE` | Zona horaria del servidor | Si | `UTC` | `America/Bogota` |
| `APP_LOCALE` | Idioma por defecto de la aplicacion | Si | `en` | `es` |
| `APP_FALLBACK_LOCALE` | Idioma de respaldo si no se encuentra una traduccion | No | `en` | `es` |

```ini
APP_NAME="CES LEGAL"
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_URL=https://app.ceslegal.com
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
```

:::caution[Produccion]
**Nunca** dejes `APP_DEBUG=true` en produccion. Expone informacion sensible como rutas de archivos, variables de entorno y trazas de error al usuario final.
:::

---

## Base de Datos

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `DB_CONNECTION` | Driver de base de datos | Si | `sqlite` | `mysql` |
| `DB_HOST` | Direccion del servidor de base de datos | Si (MySQL) | `127.0.0.1` | `127.0.0.1` |
| `DB_PORT` | Puerto del servidor de base de datos | Si (MySQL) | `3306` | `3306` |
| `DB_DATABASE` | Nombre de la base de datos | Si | `laravel` | `ces_legal` |
| `DB_USERNAME` | Usuario de la base de datos | Si (MySQL) | `root` | `ces_user` |
| `DB_PASSWORD` | Contrasena del usuario de la base de datos | Si (MySQL) | — | `contraseña_segura` |
| `DB_CHARSET` | Conjunto de caracteres de la base de datos | No | `utf8mb4` | `utf8mb4` |
| `DB_COLLATION` | Regla de ordenamiento de la base de datos | No | `utf8mb4_unicode_ci` | `utf8mb4_unicode_ci` |

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ces_legal
DB_USERNAME=ces_user
DB_PASSWORD=contraseña_segura
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

:::tip[Recomendacion]
En produccion, crea un usuario de base de datos dedicado con permisos limitados en lugar de usar `root`. Asigna unicamente los privilegios `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER` y `DROP` sobre la base de datos del proyecto.
:::

---

## Correo Electronico

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `MAIL_MAILER` | Driver de envio de correo | Si | `log` | `smtp` |
| `MAIL_HOST` | Servidor SMTP | Si (SMTP) | `127.0.0.1` | `smtp.gmail.com` |
| `MAIL_PORT` | Puerto del servidor SMTP | Si (SMTP) | `2525` | `587` |
| `MAIL_USERNAME` | Usuario de autenticacion SMTP | Si (SMTP) | `null` | `notificaciones@ceslegal.com` |
| `MAIL_PASSWORD` | Contrasena o contrasena de aplicacion SMTP | Si (SMTP) | `null` | `abcd efgh ijkl mnop` |
| `MAIL_ENCRYPTION` | Protocolo de cifrado del correo | No | `null` | `tls` |
| `MAIL_FROM_ADDRESS` | Direccion de remitente por defecto | Si | `hello@example.com` | `notificaciones@ceslegal.com` |
| `MAIL_FROM_NAME` | Nombre de remitente por defecto | Si | `${APP_NAME}` | `"CES LEGAL"` |

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@ceslegal.com
MAIL_PASSWORD=abcd_efgh_ijkl_mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@ceslegal.com
MAIL_FROM_NAME="${APP_NAME}"
```

:::note[Contrasena de Aplicacion de Gmail]
Si usas Gmail como servidor SMTP, debes generar una **contrasena de aplicacion**:
1. Accede a tu cuenta de Google > Seguridad.
2. Activa la verificacion en 2 pasos.
3. Busca "Contrasenas de aplicaciones" y genera una para "Correo".
4. Usa esa contrasena de 16 caracteres en `MAIL_PASSWORD`.
:::

---

## Inteligencia Artificial (Gemini)

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `IA_PROVIDER` | Proveedor de IA a utilizar | No | `gemini` | `openai` / `gemini` |
| `GEMINI_API_KEY` | Clave de API de Google Gemini | Si (si usa Gemini) | — | `AIzaSyD...` |
| `GEMINI_MODEL` | Modelo de Gemini a utilizar | No | `gemini-2.5-flash` | `gemini-2.5-flash` |
| `GEMINI_MAX_TOKENS` | Limite maximo de tokens por solicitud | No | `8192` | `4096` |

```ini
IA_PROVIDER=gemini
GEMINI_API_KEY=AIzaSyD_tu_clave_aqui
GEMINI_MODEL=gemini-2.5-flash
GEMINI_MAX_TOKENS=8192
```

Para obtener una API Key de Gemini:
1. Accede a [Google AI Studio](https://makersuite.google.com/app/apikey).
2. Crea un nuevo proyecto o selecciona uno existente.
3. Genera una API Key.
4. Copia la clave en `GEMINI_API_KEY`.

:::caution[Costos de API]
Gemini tiene un nivel gratuito con limites de tasa. Para produccion con alto volumen, revisa los [precios de Google AI](https://ai.google.dev/pricing) y configura alertas de facturacion.
:::

---

## Sesion, Cache y Colas

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `SESSION_DRIVER` | Driver de almacenamiento de sesiones | Si | `database` | `database` / `file` / `redis` |
| `SESSION_LIFETIME` | Duracion de la sesion en minutos | No | `120` | `120` |
| `SESSION_ENCRYPT` | Cifrar datos de sesion | No | `false` | `true` |
| `CACHE_STORE` | Driver de almacenamiento de cache | Si | `database` | `database` / `file` / `redis` |
| `CACHE_PREFIX` | Prefijo para claves de cache | No | — | `ces_legal` |
| `QUEUE_CONNECTION` | Driver de colas para trabajos en segundo plano | Si | `database` | `database` / `redis` / `sync` |

```ini
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
CACHE_STORE=database
CACHE_PREFIX=ces_legal
QUEUE_CONNECTION=database
```

| Driver | Uso Recomendado |
|--------|-----------------|
| `file` | Desarrollo local unicamente |
| `database` | Produccion estandar |
| `redis` | Alto rendimiento y produccion con alta concurrencia |

---

## Sistema de Archivos

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `FILESYSTEM_DISK` | Disco de almacenamiento por defecto | No | `local` | `public` |

```ini
FILESYSTEM_DISK=public
```

El disco `public` almacena archivos accesibles desde la web (documentos generados, evidencias). Asegurate de ejecutar `php artisan storage:link` para crear el enlace simbolico.

---

## Filament

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `FILAMENT_PATH` | Ruta base del panel de administracion | No | `admin` | `admin` |
| `FILAMENT_FILESYSTEM_DISK` | Disco de almacenamiento para archivos subidos en Filament | No | `public` | `public` |

```ini
FILAMENT_PATH=admin
FILAMENT_FILESYSTEM_DISK=public
```

---

## Logs

| Variable | Descripcion | Requerida | Valor por defecto | Ejemplo |
|----------|-------------|-----------|-------------------|---------|
| `LOG_CHANNEL` | Canal de log principal | No | `stack` | `stack` |
| `LOG_STACK` | Canales apilados | No | `single` | `single` |
| `LOG_LEVEL` | Nivel minimo de log | No | `debug` | `error` |
| `LOG_DEPRECATIONS_CHANNEL` | Canal para deprecaciones de PHP | No | `null` | `null` |

```ini
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null
```

:::tip[Produccion]
En produccion usa `LOG_LEVEL=error` o `LOG_LEVEL=warning` para evitar llenar los archivos de log con mensajes de depuracion.
:::

---

## Archivo .env Completo de Ejemplo (Produccion)

```ini
# ── Aplicacion ──────────────────────────────────────────
APP_NAME="CES LEGAL"
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=false
APP_URL=https://app.ceslegal.com
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

# ── Base de Datos ───────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ces_legal
DB_USERNAME=ces_user
DB_PASSWORD=contraseña_segura
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# ── Correo (Gmail SMTP) ────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@ceslegal.com
MAIL_PASSWORD=abcd_efgh_ijkl_mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@ceslegal.com
MAIL_FROM_NAME="${APP_NAME}"

# ── Inteligencia Artificial ─────────────────────────────
IA_PROVIDER=gemini
GEMINI_API_KEY=tu_api_key
GEMINI_MODEL=gemini-2.5-flash
GEMINI_MAX_TOKENS=8192

# ── Sesion y Cache ──────────────────────────────────────
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
CACHE_PREFIX=ces_legal
QUEUE_CONNECTION=database

# ── Sistema de Archivos ────────────────────────────────
FILESYSTEM_DISK=public
FILAMENT_FILESYSTEM_DISK=public

# ── Logs ────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_LEVEL=error
```

---

## Proximos Pasos

- [Configuracion](/inicio/configuracion/) - Guia detallada de configuracion inicial
- [Comandos Artisan](/referencia/comandos-artisan/) - Comandos personalizados del sistema
- [Troubleshooting](/referencia/troubleshooting/) - Solucion de problemas comunes
