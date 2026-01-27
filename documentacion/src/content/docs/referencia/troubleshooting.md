---
title: Troubleshooting
description: Guia de solucion de problemas frecuentes en CES Legal
---

Esta guia recopila los problemas mas comunes que pueden presentarse al instalar, configurar o usar CES Legal, junto con sus soluciones.

## LibreOffice no encontrado para conversion a PDF

**Sintoma:** Al generar documentos PDF aparece un error indicando que LibreOffice no fue encontrado, o el PDF generado tiene formato incorrecto.

**Causa:** CES Legal utiliza LibreOffice en modo headless para convertir documentos Word (.docx) a PDF con alta fidelidad. Si LibreOffice no esta instalado, el sistema usa **dompdf** como alternativa, que tiene limitaciones de formato.

**Solucion:**

```bash
# Linux (Ubuntu/Debian)
sudo apt-get install libreoffice

# Verificar la instalacion
libreoffice --version

# Verificar la ruta del ejecutable
which libreoffice
```

En **Windows** con Laragon:
1. Descarga e instala [LibreOffice](https://www.libreoffice.org/download/).
2. Asegurate de que `soffice.exe` este en el PATH del sistema.
3. Reinicia Laragon y el servidor web.

:::note[Fallback a dompdf]
Si LibreOffice no esta disponible, el sistema automaticamente utiliza dompdf como alternativa. Los documentos generados con dompdf pueden tener diferencias menores de formato respecto a los generados con LibreOffice (fuentes, margenes, tablas complejas).
:::

---

## Errores de la API de Gemini

### Error: Rate limit exceeded (429)

**Sintoma:** La generacion de preguntas con IA falla con el mensaje "Rate limit exceeded" o codigo HTTP 429.

**Causa:** Se excedio el limite de solicitudes por minuto de la API de Gemini.

**Solucion:**

1. Espera unos minutos antes de reintentar.
2. Verifica tu cuota actual en [Google AI Studio](https://makersuite.google.com/).
3. Si es persistente, considera actualizar al plan de pago.

```bash
# Verificar que la clave esta configurada
php artisan tinker
>>> config('services.gemini.api_key')
```

### Error: Invalid API Key (401)

**Sintoma:** Las solicitudes a Gemini fallan con "Invalid API Key" o "Unauthorized".

**Causa:** La clave de API es incorrecta, ha expirado o fue revocada.

**Solucion:**

1. Verifica la variable `GEMINI_API_KEY` en el archivo `.env`.
2. Genera una nueva clave en [Google AI Studio](https://makersuite.google.com/app/apikey).
3. Limpia la cache de configuracion:

```bash
php artisan config:clear
php artisan cache:clear
```

### Error: Request timeout

**Sintoma:** La generacion de preguntas se queda cargando indefinidamente y eventualmente muestra un error de timeout.

**Causa:** El modelo de Gemini tarda demasiado en responder, posiblemente por un prompt muy extenso o alta carga del servicio.

**Solucion:**

1. Reduce el valor de `GEMINI_MAX_TOKENS` en `.env`.
2. Verifica la conexion a internet del servidor.
3. Revisa los logs para mas detalles:

```bash
# Revisar los ultimos errores en el log
tail -50 storage/logs/laravel.log
```

---

## Fallos en el envio de correo electronico

**Sintoma:** Las notificaciones por correo no llegan al destinatario. El proceso queda en espera de confirmacion de envio.

**Diagnostico:**

```bash
# Verificar la configuracion de correo
php artisan tinker
>>> config('mail.mailers.smtp')

# Probar envio de correo
php artisan tinker
>>> Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

**Causas y soluciones comunes:**

| Causa | Solucion |
|-------|----------|
| Credenciales SMTP incorrectas | Verifica `MAIL_USERNAME` y `MAIL_PASSWORD` en `.env` |
| Puerto bloqueado por firewall | Prueba con los puertos 587 (TLS) o 465 (SSL) |
| Gmail bloquea la conexion | Usa una [contrasena de aplicacion](https://myaccount.google.com/apppasswords) |
| DNS no resuelve el host SMTP | Verifica `MAIL_HOST` y la conectividad del servidor |
| Cola de correo no procesada | Ejecuta `php artisan queue:work` si usas colas |

```bash
# Si usas colas, asegurate de que el worker este activo
php artisan queue:work --queue=default

# Ver trabajos fallidos en la cola
php artisan queue:failed
```

---

## Token expirado en formulario de descargos

**Sintoma:** El trabajador accede al enlace del formulario de descargos y ve un mensaje de "Token expirado" o "Enlace no valido".

**Causa:** El token de acceso publico tiene una vigencia de **6 dias**. Si el trabajador accede despues de ese periodo, el token ya no es valido.

**Solucion:**

1. Desde el panel de administracion, accede al proceso disciplinario correspondiente.
2. Reprograma la diligencia de descargos para generar un nuevo token.
3. Reenvie la notificacion al trabajador con el nuevo enlace.

:::tip[Prevencion]
Verifique que la fecha de descargos programada permita al trabajador acceder al formulario dentro del plazo de vigencia del token. El sistema muestra un temporizador de 45 minutos una vez que el trabajador inicia el formulario.
:::

---

## Errores de permisos (Shield no sincronizado)

**Sintoma:** Un usuario no puede acceder a un recurso que deberia tener disponible, o aparece el mensaje "No tiene permisos para acceder a esta pagina".

**Causa:** Los permisos de Filament Shield no estan sincronizados. Esto ocurre tipicamente despues de crear un nuevo recurso o actualizar el codigo.

**Solucion:**

```bash
# Regenerar todos los permisos
php artisan shield:generate --all

# Verificar permisos del usuario
php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $user->getAllPermissions()->pluck('name');

# Si es necesario, reasignar el rol de super admin
php artisan shield:super-admin --user=1
```

Despues de regenerar los permisos, accede al panel de administracion y verifica la asignacion de permisos en **Roles > [nombre del rol] > Permisos**.

---

## Proceso atascado en un estado

**Sintoma:** Un proceso disciplinario permanece en un estado (por ejemplo, `descargos_pendientes`) a pesar de que la condicion para avanzar ya se cumplio.

**Causa:** El comando automatico `procesos:actualizar-estados-descargos` no se ha ejecutado o el scheduler no esta activo.

**Solucion:**

```bash
# Ejecutar manualmente el comando de actualizacion
php artisan procesos:actualizar-estados-descargos

# Verificar que el scheduler esta activo
php artisan schedule:list

# Verificar que el cron job existe (Linux)
crontab -l

# Verificar el estado del proceso especifico
php artisan tinker
>>> \App\Models\ProcesoDisciplinario::where('codigo', 'PD-2026-001')->first()->estado;
```

Si el proceso sigue sin avanzar despues de ejecutar el comando, revisa los logs:

```bash
tail -100 storage/logs/laravel.log | grep "Error al actualizar"
```

---

## Errores de memoria al generar PDF

**Sintoma:** Al generar un documento PDF, especialmente para procesos con muchas evidencias o preguntas de descargos, aparece el error "Allowed memory size exhausted".

**Causa:** El proceso de generacion de PDF consume mas memoria de la permitida por la configuracion de PHP.

**Solucion:**

1. Aumenta el limite de memoria en `php.ini`:

```ini
; Aumentar a 512MB (valor recomendado para CES Legal)
memory_limit = 512M

; Para documentos muy grandes, hasta 1GB
memory_limit = 1G
```

2. En Laragon, edita la configuracion de PHP desde el menu:
   - **Laragon > PHP > php.ini** y busca `memory_limit`.

3. Reinicia el servidor web despues de modificar `php.ini`.

4. Si el problema persiste con documentos especificos, considera:
   - Reducir el tamano de las imagenes de evidencia antes de subirlas.
   - Generar el documento en formato Word (.docx) en lugar de PDF.
   - Aumentar el `max_execution_time` en `php.ini`.

---

## Scheduler no se esta ejecutando

**Sintoma:** Los comandos programados (actualizacion de estados, terminos legales) no se ejecutan automaticamente.

**Causa:** El cron job para el scheduler de Laravel no esta configurado en el servidor.

**Diagnostico:**

```bash
# Ver tareas programadas registradas
php artisan schedule:list

# Ejecutar el scheduler manualmente para probar
php artisan schedule:run
```

**Solucion para Linux (produccion):**

```bash
# Editar crontab
crontab -e

# Agregar esta linea
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

**Solucion para Windows (desarrollo con Laragon):**

1. Abre el **Programador de Tareas de Windows** (`taskschd.msc`).
2. Crea una tarea basica:
   - **Trigger:** Repetir cada 1 minuto.
   - **Accion:** Ejecutar `php artisan schedule:run` en el directorio del proyecto.
3. Alternativamente, usa `php artisan schedule:work` en una terminal abierta durante el desarrollo.

---

## Filtracion de datos entre empresas (multi-tenant)

**Sintoma:** Un usuario puede ver procesos o trabajadores de una empresa diferente a la suya.

**Causa:** Las politicas de aislamiento por `empresa_id` no se estan aplicando correctamente, o se creo un query sin el scope global.

**Diagnostico:**

```bash
php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $user->empresa_id;

# Verificar que los procesos respeten el filtro de empresa
>>> \App\Models\ProcesoDisciplinario::where('empresa_id', $user->empresa_id)->count();
>>> \App\Models\ProcesoDisciplinario::count(); // Si es diferente, hay filtracion
```

**Solucion:**

1. Verifica que el modelo `ProcesoDisciplinario` tenga el scope global de empresa.
2. Revisa que los recursos de Filament apliquen el filtro `empresa_id` en el metodo `getEloquentQuery()`.
3. Confirma que las politicas (Policies) validen `empresa_id` en los metodos `view`, `update` y `delete`.
4. El usuario administrador (super_admin) puede ver todos los datos; esto es el comportamiento esperado.

:::caution[Seguridad]
Cualquier filtracion de datos entre empresas es un problema critico de seguridad. Si detectas este comportamiento, revisa inmediatamente los Global Scopes y las Policies del modelo afectado.
:::

---

## CSS/JS no se cargan correctamente

**Sintoma:** La interfaz se muestra sin estilos, los botones no funcionan, o aparecen errores 404 para archivos `.css` y `.js` en la consola del navegador.

**Causa:** Los assets de Vite no se han compilado o la cache del navegador tiene una version antigua.

**Solucion:**

```bash
# Compilar assets para desarrollo
npm install
npm run dev

# Compilar assets para produccion
npm run build

# Limpiar cache de vistas y configuracion
php artisan optimize:clear

# Regenerar el enlace simbolico de storage
php artisan storage:link
```

Si el problema persiste en produccion:

1. Verifica que la carpeta `public/build/` existe y contiene los archivos compilados.
2. Confirma que `APP_URL` en `.env` coincide con la URL real del servidor.
3. Limpia la cache del navegador o prueba en una ventana de incognito.
4. Revisa los permisos de la carpeta `public/`:

```bash
# Linux
chmod -R 755 public/
chown -R www-data:www-data public/
```

---

## Resumen rapido de comandos de diagnostico

```bash
# Ver estado general del sistema
php artisan about

# Ver tareas programadas
php artisan schedule:list

# Ver rutas registradas
php artisan route:list

# Ver la configuracion actual (sin cache)
php artisan config:show mail
php artisan config:show database

# Limpiar toda la cache
php artisan optimize:clear

# Revisar los logs mas recientes
tail -100 storage/logs/laravel.log

# Verificar conexion a base de datos
php artisan tinker
>>> DB::connection()->getPdo();

# Verificar permisos de un usuario
php artisan tinker
>>> \App\Models\User::find(1)->getAllPermissions()->pluck('name');
```

---

## Proximos Pasos

- [Variables de Entorno](/referencia/variables-entorno/) - Configuracion del sistema
- [Comandos Artisan](/referencia/comandos-artisan/) - Comandos disponibles
- [Changelog](/referencia/changelog/) - Historial de cambios del proyecto
