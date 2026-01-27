---
title: Rutas Publicas
description: Endpoints publicos del sistema CES Legal que no requieren autenticacion
---

## Descripcion General

CES Legal expone un conjunto reducido de rutas publicas que no requieren autenticacion. Estas rutas permiten a los trabajadores interactuar con el proceso disciplinario sin necesidad de tener una cuenta en el sistema, y proporcionan funcionalidades de monitoreo.

Todas las rutas publicas se definen en `routes/web.php`.

## Endpoints Publicos

### 1. Formulario de Descargos del Trabajador

```
GET /descargos/{token}
```

| Atributo | Valor |
|----------|-------|
| Metodo | `GET` |
| Controlador | `DescargoPublicoController@mostrarAcceso` |
| Nombre de ruta | `descargos.acceso` |
| Autenticacion | No requerida |
| Parametro | `{token}` - Token de acceso unico de 64 caracteres hexadecimales |

**Descripcion:**
Esta es la ruta principal publica del sistema. Permite al trabajador acceder al formulario de descargos mediante un enlace unico generado por el sistema. El trabajador no necesita cuenta ni contrasena; solo el enlace con el token valido.

**Flujo de validacion:**

```
Trabajador accede con token
        |
        v
  Token existe en BD? ---- No ----> Vista acceso-invalido
        |
        Si
        v
  Token habilitado y         No ----> Vista acceso-invalido
  no expirado?            (expirado o deshabilitado)
        |
        Si
        v
  Fecha de hoy =             No ----> Vista acceso-denegado
  fecha_acceso_permitida?    (muestra fecha permitida)
        |
        Si
        v
  Registrar acceso (IP, timestamp)
        |
        v
  Mostrar formulario de descargos
  (Livewire con timer de 45 minutos)
```

**Validacion del token:**

1. **Existencia**: Se busca en `diligencias_descargos.token_acceso`
2. **Expiracion**: El token tiene una validez de **6 dias** desde su creacion (`token_expira_en`)
3. **Habilitacion**: El campo `acceso_habilitado` debe ser `true`
4. **Fecha permitida**: El trabajador solo puede acceder el dia exacto configurado en `fecha_acceso_permitida`

**Generacion del token:**

```php
// DiligenciaDescargo::generarTokenAcceso()
$this->token_acceso = bin2hex(random_bytes(32)); // 64 caracteres hex
$this->token_expira_en = now()->addDays(6);
```

**Registro de acceso:**
Cuando el trabajador accede por primera vez, se registra:
- `trabajador_accedio_en`: Fecha y hora del acceso
- `ip_acceso`: Direccion IP del trabajador

**Respuestas posibles:**

| Codigo | Vista | Condicion |
|--------|-------|-----------|
| 200 | `descargos.formulario` | Token valido, fecha correcta |
| 200 | `descargos.acceso-invalido` | Token no existe o expirado |
| 200 | `descargos.acceso-denegado` | Fecha de acceso no es hoy |

:::tip[Nota sobre el Timer]
Una vez el trabajador accede al formulario, se inicia un timer de **45 minutos** controlado por Livewire. Al expirar, el formulario se deshabilita automaticamente y las respuestas se guardan.
:::

:::caution[Seguridad del Token]
- Los tokens son de un solo uso logico (solo una diligencia)
- Se pueden regenerar desde el panel de administracion
- Al regenerar un token, el anterior deja de funcionar inmediatamente
- El token se genera con `random_bytes(32)` para maxima entropia
:::

---

### 2. Pixel de Seguimiento de Email

```
GET /email/track/{token}.gif
```

| Atributo | Valor |
|----------|-------|
| Metodo | `GET` |
| Controlador | `EmailTrackingController@pixel` |
| Nombre de ruta | `email.tracking.pixel` |
| Autenticacion | No requerida |
| Parametro | `{token}` - Token unico de tracking de 64 caracteres |

**Descripcion:**
Endpoint que devuelve una imagen GIF transparente de 1x1 pixel. Se incrusta en los correos electronicos enviados al trabajador (citaciones y sanciones) para rastrear si el correo fue abierto.

**Funcionamiento:**

```
Correo contiene <img src="/email/track/{token}.gif">
        |
        v
  Trabajador abre correo
        |
        v
  Cliente de correo carga la imagen
        |
        v
  Servidor registra la apertura:
    - Incrementa veces_abierto
    - Guarda IP de apertura
    - Guarda User-Agent
    - Registra fecha/hora primera apertura
        |
        v
  Devuelve GIF 1x1 transparente
```

**Logica de conteo de aperturas:**

| `veces_abierto` | Estado | Significado |
|------------------|--------|-------------|
| 0 | Pendiente | El correo no ha sido procesado |
| 1 | Entregado | Precarga automatica del servidor de correo |
| 2+ | Leido | El trabajador abrio el correo (se resta 1 para el conteo real) |

**Cabeceras de respuesta:**
La imagen se devuelve con cabeceras anti-cache para garantizar que cada apertura genere una nueva solicitud:

```
Content-Type: image/gif
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: Thu, 01 Jan 1970 00:00:00 GMT
```

**Tipos de correo rastreados:**
- `citacion` - Citacion a audiencia de descargos
- `sancion` - Notificacion de sancion disciplinaria

:::note[Limitaciones del Tracking por Pixel]
- Algunos clientes de correo bloquean imagenes externas por defecto (Outlook, Gmail en algunos casos)
- Las lecturas en texto plano no cargan el pixel
- La precarga del servidor de correo puede generar un falso positivo (por eso se cuenta a partir de 2 aperturas)
:::

---

### 3. Rutas de Descarga de Documentos (Autenticadas)

Aunque requieren autenticacion, estas rutas son accesibles fuera del panel Filament:

```
GET /descargar/acta/{diligenciaId}
GET /descargar/citacion/{procesoId}
GET /descargar/sancion/{procesoId}
```

| Atributo | Valor |
|----------|-------|
| Middleware | `auth` |
| Nombre de ruta | `descargar.acta`, `descargar.citacion`, `descargar.sancion` |

**Descripcion:**
Rutas para descargar documentos generados por el sistema. Aunque requieren sesion activa, se documentan aqui porque no forman parte del panel Filament.

| Ruta | Documento | Formato |
|------|-----------|---------|
| `/descargar/acta/{diligenciaId}` | Acta de descargos | DOCX |
| `/descargar/citacion/{procesoId}` | Citacion a descargos | PDF o DOCX |
| `/descargar/sancion/{procesoId}` | Documento de sancion | PDF o HTML |

---

### 4. Estado de Tracking de Email (API Interna)

```
GET /api/email-tracking/{procesoId}
```

| Atributo | Valor |
|----------|-------|
| Metodo | `GET` |
| Controlador | `EmailTrackingController@estado` |
| Middleware | `auth` |
| Nombre de ruta | `email.tracking.estado` |
| Respuesta | JSON |

**Respuesta de ejemplo:**

```json
{
  "proceso_id": 15,
  "trackings": [
    {
      "tipo_correo": "Citacion a Descargos",
      "email": "trabajador@empresa.com",
      "enviado_en": "20/01/2026 10:30:00",
      "estado": "Leido (3)",
      "abierto_en": "20/01/2026 14:15:22",
      "veces_abierto": 4,
      "tiempo_hasta_apertura": "3 horas"
    }
  ]
}
```

---

### 5. Health Check

```
GET /up
```

| Atributo | Valor |
|----------|-------|
| Metodo | `GET` |
| Autenticacion | No requerida |
| Proposito | Verificar que la aplicacion esta en funcionamiento |

**Descripcion:**
Endpoint estandar de Laravel para health check. Devuelve un codigo HTTP 200 si la aplicacion esta funcionando correctamente. Util para balanceadores de carga y sistemas de monitoreo.

---

## Resumen de Rutas

| Ruta | Metodo | Auth | Descripcion |
|------|--------|------|-------------|
| `/descargos/{token}` | GET | No | Formulario publico de descargos |
| `/email/track/{token}.gif` | GET | No | Pixel de tracking de email |
| `/descargar/acta/{id}` | GET | Si | Descarga de acta DOCX |
| `/descargar/citacion/{id}` | GET | Si | Descarga de citacion |
| `/descargar/sancion/{id}` | GET | Si | Descarga de sancion |
| `/api/email-tracking/{id}` | GET | Si | Estado de tracking JSON |
| `/up` | GET | No | Health check |

## Archivos Relacionados

```
routes/web.php                                    # Definicion de rutas
app/Http/Controllers/DescargoPublicoController.php # Controlador de descargos
app/Http/Controllers/EmailTrackingController.php   # Controlador de tracking
app/Models/DiligenciaDescargo.php                  # Modelo con logica de token
app/Models/EmailTracking.php                       # Modelo de tracking
```

## Proximos Pasos

- [Rutas Protegidas](/api/rutas-protegidas/) - Rutas del panel Filament
- [Webhooks](/api/webhooks/) - Endpoints de integraciones externas
- [Diligencias de Descargos](/modulos/diligencias-descargos/) - Modulo completo
