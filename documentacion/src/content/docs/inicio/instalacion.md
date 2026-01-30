---
title: Instalación
description: Guía paso a paso para instalar CES Legal en tu entorno local
---

## Requisitos Previos

Antes de instalar el aplicativo web de CES Legal, asegúrate de tener los siguientes requisitos:

### Software Requerido

| Software | Versión Mínima | Verificar |
|----------|----------------|-----------|
| PHP | 8.2+ | `php -v` |
| Composer | 2.0+ | `composer -V` |
| Node.js | 18+ | `node -v` |
| npm | 9+ | `npm -v` |
| MySQL | 8.0+ | `mysql --version` |
| Git | 2.0+ | `git --version` |

### Extensiones PHP Requeridas

```bash
# Verifica las extensiones instaladas
php -m
```

Extensiones necesarias:
- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`
- `gd` o `imagick`
- `zip`

## Instalación Paso a Paso

### 1. Clonar el Repositorio

```bash
git clone https://github.com/juanparen15/ces-legal.git
cd ces-legal
```

### 2. Instalar Dependencias de PHP

```bash
composer install
```

:::note
Si tienes problemas de memoria, usa:
```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```
:::

### 3. Instalar Dependencias de Node.js

```bash
npm install
```

### 4. Configurar Variables de Entorno

```bash
# Copiar archivo de ejemplo
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### 5. Configurar Base de Datos

Edita el archivo `.env` con tus credenciales:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ces_legal
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

Crea la base de datos:

```bash
mysql -u root -p -e "CREATE DATABASE ces_legal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 6. Ejecutar Migraciones

```bash
php artisan migrate
```

### 7. Ejecutar Seeders (Datos Iniciales)

```bash
php artisan db:seed
```

Esto creará:
- Usuario administrador por defecto
- Catálogo de sanciones laborales (64 tipos)
- Días no hábiles de Colombia
- Roles y permisos
- Departamentos y Municipios (Datos desde la API oficial del DANE)

### 8. Compilar Assets

```bash
# Desarrollo
npm run dev

# Producción
npm run build
```

### 9. Crear Enlaces Simbólicos

```bash
php artisan storage:link
```

### 10. Iniciar el Servidor

```bash
php artisan serve
```

El sistema estará disponible en: `http://127.0.0.1:8000`

## Credenciales por Defecto

:::caution[Importante]
Cambia estas credenciales inmediatamente en producción.
:::

| Campo | Valor |
|-------|-------|
| URL | `http://127.0.0.1:8000/admin` |
| Email | `admin@ceslegal.co` |
| Contraseña | `admin12345` |

## Verificación de Instalación

Ejecuta el siguiente comando para verificar que todo esté correctamente configurado:

```bash
php artisan about
```

Deberías ver información sobre:
- Versión de Laravel
- Versión de PHP
- Configuración de caché
- Configuración de base de datos

## Solución de Problemas Comunes

### Error: "SQLSTATE[HY000] [1045] Access denied"

```bash
# Verifica las credenciales en .env
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### Error: "Class not found"

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Error: "The Mix manifest does not exist"

```bash
npm run build
```

### Error: "Permission denied" en storage/

```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Windows (ejecutar como administrador)
icacls storage /grant Everyone:F /T
```

## Próximos Pasos

- [Configuración](/inicio/configuracion/) - Configura las variables de entorno
- [Despliegue](/inicio/despliegue/) - Despliega en producción
