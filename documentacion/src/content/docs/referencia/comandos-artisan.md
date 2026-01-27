---
title: Comandos Artisan
description: Referencia de comandos Artisan personalizados y estandar utilizados en CES Legal
---

CES Legal incluye comandos Artisan personalizados para la automatizacion de procesos disciplinarios, ademas de los comandos estandar de Laravel. A continuacion se documentan todos los comandos relevantes.

## Comandos Personalizados

### `procesos:actualizar-estados-descargos`

Detecta y actualiza automaticamente los estados de procesos disciplinarios segun el resultado de las diligencias de descargos.

```bash
php artisan procesos:actualizar-estados-descargos
```

**Que hace este comando:**

1. **Caso 1 - Descargos realizados:** Busca procesos en estado `descargos_pendientes` donde el trabajador haya respondido al menos una pregunta. Los marca como `descargos_realizados` y registra que el trabajador asistio.

2. **Caso 2 - Descargos no realizados:** Busca procesos en estado `descargos_pendientes` cuya fecha programada ya paso y donde el trabajador no asistio ni respondio preguntas. Los marca como `descargos_no_realizados`.

**Ejemplo de salida:**

```
✓ PD-2026-001 → descargos_realizados (5 preguntas respondidas)
✓ PD-2026-002 → descargos_realizados (3 preguntas respondidas)
⚠ PD-2026-003 → descargos_no_realizados (no asistio, fecha: 2026-01-20)

Resumen:
  - Descargos realizados: 2
  - Descargos no realizados: 1
```

**Programacion:** Se ejecuta automaticamente **cada 5 minutos** mediante el scheduler de Laravel. Configurado en `routes/console.php`:

```php
Schedule::command('procesos:actualizar-estados-descargos')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

:::note[Sin superposicion]
La opcion `withoutOverlapping()` garantiza que no se ejecuten dos instancias del comando simultaneamente, evitando condiciones de carrera al actualizar estados.
:::

---

### `terminos:actualizar`

Actualiza el estado de todos los terminos legales activos y envia notificaciones para los que estan proximos a vencer o ya vencidos.

```bash
php artisan terminos:actualizar
```

**Que hace este comando:**

1. Actualiza todos los terminos legales activos en la base de datos.
2. Identifica terminos **proximos a vencer** (2 dias habiles o menos) y notifica al abogado asignado.
3. Identifica terminos **ya vencidos** y envia notificacion de alerta al abogado responsable.

**Ejemplo de salida:**

```
Iniciando actualizacion de terminos legales...
Se encontraron 3 terminos proximos a vencer
  → Notificacion enviada para termino #45 (2 dias restantes)
  → Notificacion enviada para termino #46 (1 dias restantes)
  → Notificacion enviada para termino #47 (0 dias restantes)
Se encontraron 1 terminos vencidos
  → Notificacion de vencimiento enviada para termino #38
Actualizacion completada exitosamente
```

**Programacion:** Se ejecuta automaticamente **todos los dias a las 8:00 AM** (hora de Colombia). Configurado en `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('terminos:actualizar')->dailyAt('08:00');
})
```

---

## Configuracion del Scheduler

Los comandos programados se registran en dos ubicaciones:

### `routes/console.php`

Archivo principal para definir comandos programados con la fachada `Schedule`:

```php
use Illuminate\Support\Facades\Schedule;

// Ejecutar cada 5 minutos
Schedule::command('procesos:actualizar-estados-descargos')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

### `bootstrap/app.php`

Configuracion alternativa usando el metodo `withSchedule` del bootstrapper de Laravel 12:

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('terminos:actualizar')->dailyAt('08:00');
})
```

### Activar el Scheduler en el Servidor

Para que los comandos programados se ejecuten, es necesario configurar un cron job en el servidor:

```bash
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

En **Windows** con Laragon, el scheduler se puede activar desde la configuracion de Laragon o mediante el Programador de Tareas de Windows:

```powershell
# Ejecutar manualmente el scheduler (util para pruebas)
php artisan schedule:run

# Ver lista de tareas programadas
php artisan schedule:list

# Ejecutar el scheduler en modo trabajo continuo (desarrollo)
php artisan schedule:work
```

:::caution[Produccion]
Sin el cron job configurado, **ninguno de los comandos automaticos se ejecutara**. Verifica que el scheduler este activo ejecutando `php artisan schedule:list` y confirmando que aparecen los comandos.
:::

---

## Comandos Estandar de Laravel

### Migraciones y Base de Datos

```bash
# Ejecutar todas las migraciones pendientes
php artisan migrate

# Revertir la ultima migracion
php artisan migrate:rollback

# Recrear toda la base de datos (DESTRUCTIVO - solo desarrollo)
php artisan migrate:fresh

# Ejecutar migraciones y seeders
php artisan migrate --seed

# Ejecutar seeders especificos
php artisan db:seed
php artisan db:seed --class=DiaNoHabilSeeder
php artisan db:seed --class=TipoSancionSeeder
php artisan db:seed --class=RoleSeeder
```

### Filament Shield (Permisos y Roles)

```bash
# Generar todos los permisos para los recursos de Filament
php artisan shield:generate --all

# Asignar rol de super administrador a un usuario
php artisan shield:super-admin --user=1

# Instalar Shield por primera vez
php artisan shield:install
```

:::tip[Despues de crear un nuevo recurso]
Cada vez que se agrega un nuevo recurso de Filament, es necesario regenerar los permisos con `shield:generate --all` para que el nuevo recurso aparezca en la configuracion de roles.
:::

### Optimizacion y Cache

```bash
# Optimizar la aplicacion para produccion (cachea config, rutas, vistas)
php artisan optimize

# Limpiar toda la cache de optimizacion
php artisan optimize:clear

# Cachear configuracion
php artisan config:cache
php artisan config:clear

# Cachear rutas
php artisan route:cache
php artisan route:clear

# Cachear vistas Blade
php artisan view:cache
php artisan view:clear

# Cachear eventos
php artisan event:cache
php artisan event:clear

# Limpiar cache de la aplicacion
php artisan cache:clear
```

### Almacenamiento

```bash
# Crear enlace simbolico de storage a public
php artisan storage:link
```

### Colas de Trabajo

```bash
# Procesar trabajos en cola (produccion con Supervisor)
php artisan queue:work --daemon

# Procesar un solo trabajo
php artisan queue:work --once

# Reintentar trabajos fallidos
php artisan queue:retry all

# Ver trabajos fallidos
php artisan queue:failed

# Limpiar trabajos fallidos
php artisan queue:flush
```

### Clave de Aplicacion

```bash
# Generar clave de cifrado
php artisan key:generate
```

### Mantenimiento

```bash
# Activar modo mantenimiento
php artisan down

# Activar modo mantenimiento con pagina personalizada
php artisan down --secret="ceslegal2026"

# Desactivar modo mantenimiento
php artisan up
```

---

## Resumen de Comandos Programados

| Comando | Frecuencia | Ubicacion | Proposito |
|---------|------------|-----------|-----------|
| `procesos:actualizar-estados-descargos` | Cada 5 minutos | `routes/console.php` | Detectar descargos realizados/no realizados |
| `terminos:actualizar` | Diario a las 8:00 AM | `bootstrap/app.php` | Actualizar terminos legales y notificar |

---

## Proximos Pasos

- [Variables de Entorno](/referencia/variables-entorno/) - Configuracion del sistema
- [Troubleshooting](/referencia/troubleshooting/) - Solucion de problemas comunes
- [Despliegue](/inicio/despliegue/) - Guia de despliegue en produccion
