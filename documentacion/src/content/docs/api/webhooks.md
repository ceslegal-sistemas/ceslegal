---
title: Webhooks
description: Endpoints de webhooks y callbacks del sistema CES Legal
---

## Descripcion General

Actualmente, CES Legal tiene una implementacion minima de webhooks. El sistema se basa principalmente en un flujo interno de tracking por pixel para el seguimiento de correos electronicos. Esta seccion documenta los mecanismos existentes y las integraciones futuras planificadas.

## Mecanismos Actuales

### Tracking de Email (Pseudo-Webhook)

El sistema utiliza un enfoque de **tracking por pixel** en lugar de webhooks tradicionales para monitorear la entrega y lectura de correos electronicos.

**Flujo actual:**

```
1. Sistema envia correo (citacion/sancion)
   con imagen de tracking incrustada
          |
          v
2. Se crea registro en EmailTracking
   con token unico y estado "pendiente"
          |
          v
3. Servidor de correo del destinatario
   precarga la imagen (veces_abierto = 1)
          |
          v
4. Trabajador abre el correo
   y carga la imagen (veces_abierto = 2+)
          |
          v
5. El panel Filament consulta el estado
   via /api/email-tracking/{procesoId}
```

**Modelo de datos:**

```php
// EmailTracking
[
    'token'               => 'string(64)',  // Token unico
    'tipo_correo'         => 'string',      // 'citacion' | 'sancion'
    'proceso_id'          => 'integer',     // FK al proceso
    'trabajador_id'       => 'integer',     // FK al trabajador
    'email_destinatario'  => 'string',      // Email del trabajador
    'enviado_en'          => 'datetime',    // Fecha de envio
    'abierto_en'          => 'datetime',    // Primera apertura
    'veces_abierto'       => 'integer',     // Contador de aperturas
    'ip_apertura'         => 'string',      // IP de ultima apertura
    'user_agent'          => 'string',      // User-Agent del navegador
]
```

**Estados de lectura:**

| Aperturas | Estado | Color Badge | Interpretacion |
|-----------|--------|-------------|----------------|
| 0 | Pendiente | Gris | Correo no procesado aun |
| 1 | Entregado | Amarillo | Precarga del servidor de correo |
| 2+ | Leido | Verde | Trabajador abrio el correo |

---

## Consideraciones de Seguridad

### Para Webhooks Entrantes (Futuro)

Cuando se implementen webhooks de servicios externos, se deben considerar las siguientes medidas de seguridad:

#### 1. Verificacion de Firma

```php
// Ejemplo de verificacion de firma HMAC
public function handleWebhook(Request $request)
{
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->getContent();
    $secret = config('services.webhook.secret');

    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($expectedSignature, $signature)) {
        abort(403, 'Firma de webhook invalida');
    }

    // Procesar el webhook...
}
```

#### 2. Validacion de IP de Origen

```php
// Middleware para validar IP del webhook
$allowedIps = config('services.webhook.allowed_ips');

if (!in_array($request->ip(), $allowedIps)) {
    abort(403, 'IP no autorizada');
}
```

#### 3. Idempotencia

```php
// Evitar procesamiento duplicado
$webhookId = $request->header('X-Webhook-ID');

if (WebhookLog::where('webhook_id', $webhookId)->exists()) {
    return response()->json(['status' => 'already_processed'], 200);
}
```

#### 4. Procesamiento Asincrono

```php
// Encolar el procesamiento para responder rapido
WebhookReceived::dispatch($payload);

return response()->json(['status' => 'received'], 200);
```

---

## Integraciones Futuras Planificadas

### 1. Webhooks de Proveedor de Email

**Proposito:** Recibir notificaciones en tiempo real sobre el estado de entrega de correos electronicos (delivered, bounced, complained, etc.).

```
POST /api/webhooks/email-delivery
```

**Payload esperado:**

```json
{
    "event": "delivered",
    "message_id": "abc123",
    "recipient": "trabajador@empresa.com",
    "timestamp": "2026-01-20T10:30:00Z",
    "details": {
        "smtp_response": "250 OK"
    }
}
```

**Eventos a manejar:**

| Evento | Accion en CES Legal |
|--------|---------------------|
| `delivered` | Actualizar EmailTracking como entregado |
| `bounced` | Marcar como no entregado, notificar al abogado |
| `complained` | Registrar queja, revision manual |
| `opened` | Complementar tracking por pixel |

---

### 2. Webhooks de Pasarela de Pagos

**Proposito:** Recibir confirmaciones de pago para el modulo de contratacion de servicios legales.

```
POST /api/webhooks/pagos
```

**Payload esperado:**

```json
{
    "event": "payment.confirmed",
    "reference": "CES-2026-001",
    "amount": 150000,
    "currency": "COP",
    "status": "approved",
    "timestamp": "2026-01-20T15:00:00Z"
}
```

---

### 3. Webhooks de Integracion con Sistemas de RRHH

**Proposito:** Sincronizar datos de trabajadores y empresas con sistemas de recursos humanos externos.

```
POST /api/webhooks/rrhh-sync
```

**Eventos potenciales:**

| Evento | Accion |
|--------|--------|
| `employee.created` | Crear trabajador en CES Legal |
| `employee.updated` | Actualizar datos del trabajador |
| `employee.terminated` | Desactivar trabajador |
| `company.updated` | Actualizar datos de empresa |

---

## Registro y Auditoria

### Logs Actuales

El sistema registra todas las interacciones de tracking en los logs de Laravel:

```php
Log::info('Email tracking registrado', [
    'token' => substr($token, 0, 10) . '...',
    'tipo_correo' => $tracking->tipo_correo,
    'proceso_id' => $tracking->proceso_id,
    'trabajador_email' => $tracking->email_destinatario,
    'veces_abierto' => $tracking->veces_abierto,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

### Tabla de Auditoria Futura

Para webhooks entrantes, se recomienda crear una tabla de logs:

```
webhook_logs
  id                  - bigint
  source              - string (email, pagos, rrhh)
  event               - string (tipo de evento)
  webhook_id          - string (ID unico del webhook)
  payload             - json (payload completo)
  status              - string (received, processed, failed)
  response_code       - integer
  processed_at        - datetime
  error_message       - text (nullable)
  created_at          - datetime
```

---

## Resumen de Endpoints

### Actuales

| Endpoint | Metodo | Auth | Descripcion |
|----------|--------|------|-------------|
| `/email/track/{token}.gif` | GET | No | Pixel de tracking |
| `/api/email-tracking/{procesoId}` | GET | Si | Estado de tracking |

### Futuros (Planificados)

| Endpoint | Metodo | Auth | Descripcion |
|----------|--------|------|-------------|
| `/api/webhooks/email-delivery` | POST | Firma HMAC | Eventos de entrega de email |
| `/api/webhooks/pagos` | POST | Firma HMAC | Confirmaciones de pago |
| `/api/webhooks/rrhh-sync` | POST | API Key | Sincronizacion de RRHH |

---

## Archivos Relacionados

```
app/Http/Controllers/EmailTrackingController.php  # Tracking actual
app/Models/EmailTracking.php                       # Modelo de tracking
routes/web.php                                     # Rutas actuales
```

## Proximos Pasos

- [Rutas Publicas](/api/rutas-publicas/) - Endpoints sin autenticacion
- [Rutas Protegidas](/api/rutas-protegidas/) - Panel Filament
- [Notificaciones](/modulos/notificaciones/) - Sistema de notificaciones por email
