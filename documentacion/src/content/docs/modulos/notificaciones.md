---
title: Notificaciones
description: Sistema de notificaciones en tiempo real con tracking de correos electronicos en CES Legal
---

## Descripcion General

El modulo de **Notificaciones** combina dos sistemas complementarios:

1. **Notificaciones internas**: Sistema nativo de Laravel/Filament para alertas en tiempo real dentro del panel administrativo, con sonido, iconos por prioridad y polling cada 30 segundos.
2. **Tracking de correos**: Sistema de rastreo por pixel invisible que monitorea si los correos de citaciones y sanciones fueron entregados y leidos por el trabajador.

Ambos sistemas estan integrados con el flujo de procesos disciplinarios y se disparan automaticamente en los eventos clave del ciclo de vida del proceso.

## Caracteristicas Principales

### Notificaciones Internas (Laravel/Filament)

#### Notificacion Nativa de Laravel

Se utiliza la clase `ProcesoNotification` que implementa `Notification` de Laravel y se almacena en la tabla `notifications` (formato UUID):

```php
$user->notify(new ProcesoNotification(
    tipo: 'apertura',
    titulo: 'Nuevo Proceso Disciplinario Asignado',
    mensaje: 'Se te ha asignado el proceso PD-2026-0001...',
    prioridad: 'alta',
    relacionadoTipo: ProcesoDisciplinario::class,
    relacionadoId: $proceso->id,
    url: '/admin/procesos-disciplinarios/1',
));
```

#### Polling en Tiempo Real

Filament consulta las notificaciones no leidas cada **30 segundos** mediante polling. Las notificaciones nuevas se muestran automaticamente en el icono de campana del panel administrativo.

#### Sonido de Notificacion

Cuando llega una nueva notificacion, se reproduce un sonido de alerta para llamar la atencion del usuario, especialmente util para notificaciones urgentes.

#### Prioridad e Iconos

Las notificaciones tienen niveles de prioridad que determinan su apariencia visual:

| Prioridad | Color | Icono | Uso |
|-----------|-------|-------|-----|
| `urgente` | Rojo | Alerta | Terminos vencidos, impugnaciones |
| `alta` | Naranja | Advertencia | Procesos asignados, descargos proximos |
| `media` | Azul | Informacion | Procesos cerrados, contratos generados |
| `baja` | Gris | Info | Actualizaciones generales |

#### Eventos que Generan Notificaciones

| Evento | Destinatarios | Prioridad | Tipo |
|--------|--------------|-----------|------|
| Proceso aperturado | Abogado asignado | Alta | `apertura` |
| Descargos proximos | Abogado asignado | Alta/Urgente | `descargos_pendientes` |
| Descargos completados | Abogado + cliente | Alta | `descargos_realizados` |
| Sancion aplicada | Abogado + RRHH (clientes) | Alta/Urgente | `sancion_emitida` |
| Impugnacion recibida | Abogado asignado | Urgente | `impugnacion_realizada` |
| Proceso cerrado | Abogado + RRHH | Media/Baja | `cerrado` |
| Termino vencido | Usuario responsable | Urgente | `termino_vencido` |
| Contrato generado | Abogado + RRHH | Media/Alta | `contrato_generado` |

#### Gestion de Notificaciones

El servicio `NotificacionService` proporciona metodos para:

```php
// Marcar como leida
$service->marcarComoLeida($notificationId);

// Marcar todas como leidas
$service->marcarTodasComoLeidas($userId);

// Obtener no leidas
$service->obtenerNoLeidas($userId);

// Contar no leidas
$service->contarNoLeidas($userId);

// Estadisticas
$service->obtenerEstadisticas($userId);
// Retorna: total, no_leidas, urgentes, porcentaje_leidas

// Limpiar antiguas (90 dias, solo leidas)
$service->limpiarNotificacionesAntiguas(90);
```

### Tracking de Correos (Email Tracking)

#### Pixel de Tracking

Cada correo enviado (citacion o sancion) incluye un pixel invisible (imagen 1x1) que permite rastrear si el correo fue abierto:

```html
<img src="https://cesapp.com/track/{token}" width="1" height="1" />
```

Cuando el cliente de correo carga esta imagen, el sistema registra la apertura en la base de datos.

#### Estados del Correo

El tracking define cuatro estados basados en el numero de aperturas:

| Aperturas | Estado | Color | Descripcion |
|-----------|--------|-------|-------------|
| 0 | `Pendiente` | Gris | No ha llegado al destinatario |
| 1 | `Correo Entregado` | Amarillo | Precarga del servidor de correo |
| 2+ | `Leido (N)` | Verde | El trabajador abrio el correo |

:::note[Logica de Aperturas]
La primera apertura (aperturas = 1) corresponde tipicamente a la precarga automatica del servidor de correo. Por eso se considera "entregado" pero no "leido". Solo cuando se registran 2 o mas aperturas se considera que el trabajador realmente abrio el correo.
:::

#### Tipos de Correo Rastreados

| Tipo | Codigo | Descripcion |
|------|--------|-------------|
| Citacion a descargos | `citacion` | Correo con la citacion adjunta |
| Notificacion de sancion | `sancion` | Correo con el documento de sancion |

#### Informacion Registrada

Cada registro de tracking almacena:

```php
EmailTracking::create([
    'token' => EmailTracking::generarToken(),  // 64 caracteres aleatorios
    'tipo_correo' => 'citacion',
    'proceso_id' => $proceso->id,
    'trabajador_id' => $trabajador->id,
    'email_destinatario' => $trabajador->email,
    'enviado_en' => Carbon::now('America/Bogota'),
]);
```

Al detectar apertura:
- `abierto_en`: Fecha/hora de la primera apertura real.
- `veces_abierto`: Contador de aperturas totales.
- `ip_apertura`: IP desde donde se abrio.
- `user_agent`: Navegador/cliente de correo.

#### Metodos de Verificacion

El modelo `ProcesoDisciplinario` incluye metodos para verificar el estado de lectura:

```php
// Verificar si la citacion fue leida
$proceso->citacionFueLeida(); // true si veces_abierto >= 2

// Verificar si la sancion fue leida
$proceso->sancionFueLeida(); // true si veces_abierto >= 2

// Obtener ultimo tracking de citacion
$proceso->ultimoTrackingCitacion;

// Obtener ultimo tracking de sancion
$proceso->ultimoTrackingSancion;
```

## Modelo de Datos

### Tabla: `notifications` (Laravel nativa)

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | uuid | Identificador unico |
| `type` | string | Clase de la notificacion |
| `notifiable_type` | string | Tipo de modelo (User) |
| `notifiable_id` | bigint | ID del usuario |
| `data` | json | Contenido (tipo, titulo, mensaje, prioridad, url) |
| `read_at` | timestamp | Fecha de lectura (null si no leida) |
| `created_at` | timestamp | Fecha de creacion |
| `updated_at` | timestamp | Fecha de actualizacion |

### Tabla: `notificaciones` (Custom, legacy)

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `user_id` | foreignId | Usuario destinatario |
| `tipo` | string | Tipo de notificacion |
| `titulo` | string | Titulo de la notificacion |
| `mensaje` | text | Mensaje completo |
| `relacionado_tipo` | string | Modelo relacionado |
| `relacionado_id` | bigint | ID del modelo relacionado |
| `leida` | boolean | Si fue leida |
| `fecha_lectura` | datetime | Fecha/hora de lectura |
| `prioridad` | string | urgente, alta, media, baja |

### Tabla: `email_trackings`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `token` | string(64) | Token unico de tracking |
| `tipo_correo` | string | citacion, sancion |
| `proceso_id` | foreignId | Proceso disciplinario |
| `trabajador_id` | foreignId | Trabajador destinatario |
| `email_destinatario` | string | Email al que se envio |
| `enviado_en` | datetime | Fecha/hora de envio |
| `abierto_en` | datetime | Primera apertura real |
| `veces_abierto` | integer | Total de aperturas |
| `ip_apertura` | string | IP de la apertura |
| `user_agent` | string | Navegador/cliente de correo |

## Relaciones con Otros Modulos

### Notificaciones Internas

| Relacion | Tipo | Modelo | Descripcion |
|----------|------|--------|-------------|
| `user` | BelongsTo | User | Usuario destinatario |

El modelo `User` tiene la relacion inversa:

```php
public function notificaciones()
{
    return $this->hasMany(Notificacion::class);
}
```

### Email Tracking

| Relacion | Tipo | Modelo | Descripcion |
|----------|------|--------|-------------|
| `proceso` | BelongsTo | ProcesoDisciplinario | Proceso asociado |
| `trabajador` | BelongsTo | Trabajador | Trabajador destinatario |

El modelo `ProcesoDisciplinario` tiene la relacion inversa:

```php
public function emailTrackings(): HasMany
{
    return $this->hasMany(EmailTracking::class, 'proceso_id');
}
```

## Notas de Uso

### Configuracion de Correo

El sistema utiliza Gmail SMTP para el envio de correos. La configuracion esta en `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

### Plantillas de Correo

Los correos utilizan vistas Blade ubicadas en:

```
resources/views/emails/
  |-- citacion-descargos.blade.php
  |-- sancion-notificacion.blade.php
```

Cada plantilla incluye el pixel de tracking al final del cuerpo del correo.

### Limitaciones del Tracking

- Algunos clientes de correo bloquean imagenes externas por defecto (especialmente Outlook corporativo).
- Las aperturas en vista previa del correo pueden contar como aperturas.
- El tracking no funciona si el correo se lee en modo texto plano.
- La IP registrada puede ser la del proxy del servidor de correo, no la del usuario final.

### Limpieza de Notificaciones

El metodo `limpiarNotificacionesAntiguas()` elimina notificaciones que:

- Han sido leidas (`read_at` no es null).
- Tienen mas de 90 dias de antiguedad.

Se recomienda ejecutar este metodo periodicamente mediante un Job programado.

### Scopes Disponibles (Email Tracking)

```php
// Filtrar por tipo de correo
EmailTracking::tipoCorreo('citacion')->get();

// Solo correos abiertos
EmailTracking::abiertos()->get();

// Solo correos no abiertos
EmailTracking::noAbiertos()->get();
```

### Atributos Computados (Email Tracking)

- `tiempoHastaApertura`: Tiempo transcurrido entre envio y primera apertura.
- `tipoCorreoLegible`: Nombre legible del tipo ("Citacion a Descargos", "Notificacion de Sancion").

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Modulo principal
- [Documentos](/modulos/documentos/) - Generacion de documentos
- [Usuarios y Roles](/modulos/usuarios-roles/) - Gestion de acceso
