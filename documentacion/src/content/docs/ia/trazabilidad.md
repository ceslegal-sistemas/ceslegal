---
title: Trazabilidad de IA
description: Sistema de registro y auditoria de todas las llamadas a inteligencia artificial realizadas por CES Legal
---

## Descripcion General

CES Legal implementa un sistema de **trazabilidad completa** para todas las interacciones con la inteligencia artificial. Cada llamada a la API de Google Gemini queda registrada en la base de datos, permitiendo:

- **Auditoria**: Revisar que prompts se enviaron y que respuestas se recibieron.
- **Depuracion**: Diagnosticar problemas cuando la IA genera respuestas inesperadas.
- **Monitoreo**: Detectar respuestas truncadas, errores y patrones de uso.
- **Trazabilidad legal**: Documentar que las preguntas generadas por IA fueron revisadas y aprobadas dentro del proceso disciplinario.

---

## Modelo TrazabilidadIADescargo

El modelo `TrazabilidadIADescargo` almacena cada interaccion con la IA:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrazabilidadIADescargo extends Model
{
    protected $table = 'trazabilidad_ia_descargos';

    protected $fillable = [
        'diligencia_descargo_id',
        'prompt_enviado',
        'respuesta_recibida',
        'tipo',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function diligenciaDescargo(): BelongsTo
    {
        return $this->belongsTo(DiligenciaDescargo::class, 'diligencia_descargo_id');
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
```

---

## Estructura de la tabla

La migracion crea la tabla `trazabilidad_ia_descargos`:

```php
Schema::create('trazabilidad_ia_descargos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('diligencia_descargo_id')
          ->constrained('diligencias_descargos')
          ->cascadeOnDelete();
    $table->longText('prompt_enviado');
    $table->longText('respuesta_recibida');
    $table->enum('tipo', ['generacion_preguntas', 'analisis_respuestas'])
          ->default('generacion_preguntas');
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index('diligencia_descargo_id');
    $table->index(['diligencia_descargo_id', 'tipo']);
});
```

### Descripcion de campos

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | `bigint` | Identificador unico del registro |
| `diligencia_descargo_id` | `foreignId` | Relacion con la diligencia de descargos |
| `prompt_enviado` | `longText` | Texto completo del prompt enviado a la IA |
| `respuesta_recibida` | `longText` | Texto completo de la respuesta de la IA |
| `tipo` | `enum` | Tipo de operacion: `generacion_preguntas` o `analisis_respuestas` |
| `metadata` | `json` | Datos adicionales: proveedor, modelo, timestamp |
| `created_at` | `timestamp` | Fecha y hora del registro |
| `updated_at` | `timestamp` | Fecha y hora de la ultima actualizacion |

### Indices

- `diligencia_descargo_id`: Busqueda rapida por diligencia.
- `(diligencia_descargo_id, tipo)`: Busqueda compuesta por diligencia y tipo de operacion.

---

## Como funciona el registro

### Registro en IADescargoService

Cada vez que `IADescargoService` realiza una llamada exitosa a la IA, registra la trazabilidad:

```php
protected function registrarTrazabilidad(
    int $diligenciaId,
    string $prompt,
    string $respuesta,
    string $tipo
): void {
    TrazabilidadIADescargo::create([
        'diligencia_descargo_id' => $diligenciaId,
        'prompt_enviado' => $prompt,
        'respuesta_recibida' => $respuesta,
        'tipo' => $tipo,
        'metadata' => [
            'provider' => $this->provider,
            'model' => $this->config['model'],
            'timestamp' => now()->toIso8601String(),
        ],
    ]);
}
```

### Datos almacenados en metadata

El campo `metadata` (JSON) contiene informacion adicional sobre la llamada:

```json
{
  "provider": "gemini",
  "model": "gemini-2.5-flash",
  "timestamp": "2026-01-27T10:30:00-05:00"
}
```

| Campo metadata | Descripcion |
|----------------|-------------|
| `provider` | Proveedor de IA utilizado (`gemini`, `openai`, `anthropic`) |
| `model` | Modelo especifico utilizado (e.g., `gemini-2.5-flash`) |
| `timestamp` | Fecha y hora exacta de la llamada en formato ISO 8601 |

---

## Tipos de operaciones registradas

### generacion_preguntas

Se registra cuando el servicio genera preguntas, ya sean iniciales o dinamicas:

```
Flujo:
1. Se construye el prompt con el contexto del proceso
2. Se envia a Gemini
3. Se recibe la respuesta
4. Se registra la trazabilidad con tipo='generacion_preguntas'
5. Se parsean las preguntas y se guardan en la BD
```

Este tipo se registra en dos escenarios:
- **Preguntas iniciales**: Al crear la diligencia de descargos (`generarPreguntasIA`).
- **Preguntas dinamicas**: Al responder una pregunta que genera seguimiento (`generarPreguntasDinamicas`).

### analisis_respuestas

Se registra cuando el servicio analiza las respuestas del trabajador para sugerir sanciones.

---

## Registro de errores

### Errores en la llamada a la IA

Cuando la llamada a Gemini falla, el error se registra en el **log de Laravel** (no en la tabla de trazabilidad, ya que no hay respuesta que almacenar):

```php
catch (\Exception $e) {
    Log::error('Error al generar preguntas dinamicas con IA', [
        'pregunta_id' => $preguntaRespondida->id,
        'error' => $e->getMessage(),
    ]);
    return [];
}
```

Para el servicio de analisis de sanciones, se incluye el stack trace completo:

```php
Log::error('Error al analizar proceso para sugerir sanciones', [
    'proceso_id' => $proceso->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Errores de parseo

Cuando la respuesta de la IA no tiene el formato esperado (e.g., JSON invalido en el analisis de sanciones), se registra un warning:

```php
Log::warning('Error al parsear analisis de IA, usando valores por defecto', [
    'error' => $e->getMessage(),
    'respuesta_ia' => $analisisTexto,
]);
```

---

## Monitoreo de respuestas truncadas

Cuando Google Gemini corta una respuesta por alcanzar el limite de tokens (`MAX_TOKENS`), el sistema lo detecta y registra:

```php
$finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';

if ($finishReason === 'MAX_TOKENS') {
    Log::warning('Respuesta de Gemini truncada por limite de tokens', [
        'finish_reason' => $finishReason,
        'max_tokens' => $this->config['max_tokens'],
        'respuesta_parcial' => substr(
            $responseData['candidates'][0]['content']['parts'][0]['text'],
            0,
            200
        ),
    ]);
}
```

### Valores de finishReason

| Valor | Significado | Accion del sistema |
|-------|-------------|-------------------|
| `STOP` | Respuesta completa | Procesamiento normal |
| `MAX_TOKENS` | Respuesta truncada por limite de tokens | Warning en log, se procesa la respuesta parcial |
| `SAFETY` | Bloqueada por filtros de seguridad | Excepcion por contenido invalido |
| `UNKNOWN` | Razon desconocida | Se procesa si hay contenido |

---

## Integracion con el Timeline

El sistema de timeline (`TimelineService`) complementa la trazabilidad de IA registrando los eventos de alto nivel del proceso:

```php
// Cuando se generan preguntas con IA
$timelineService->registrar(
    procesoTipo: 'ProcesoDisciplinario',
    procesoId: $proceso->id,
    accion: 'Documento generado',
    descripcion: 'Se generaron preguntas con IA para la diligencia de descargos',
    metadata: [
        'tipo_documento' => 'preguntas_ia',
        'diligencia_id' => $diligencia->id,
    ]
);
```

### Diferencia entre trazabilidad IA y timeline

| Aspecto | TrazabilidadIADescargo | Timeline |
|---------|----------------------|----------|
| **Proposito** | Registro tecnico detallado de llamadas a IA | Registro de eventos de negocio |
| **Contenido** | Prompts completos + respuestas completas | Descripciones de acciones de alto nivel |
| **Audiencia** | Desarrolladores, auditores tecnicos | Abogados, administradores, usuarios |
| **Granularidad** | Cada llamada individual a la API | Cada accion significativa del proceso |
| **Relacion** | Vinculada a `diligencia_descargo_id` | Vinculada a `proceso_id` |

---

## Consultas utiles

### Obtener trazabilidad de una diligencia

```php
$trazabilidad = TrazabilidadIADescargo::where('diligencia_descargo_id', $diligenciaId)
    ->orderBy('created_at', 'desc')
    ->get();
```

### Filtrar por tipo de operacion

```php
// Solo generacion de preguntas
$generaciones = TrazabilidadIADescargo::tipo('generacion_preguntas')
    ->where('diligencia_descargo_id', $diligenciaId)
    ->get();

// Solo analisis de respuestas
$analisis = TrazabilidadIADescargo::tipo('analisis_respuestas')
    ->where('diligencia_descargo_id', $diligenciaId)
    ->get();
```

### Contar llamadas totales a la IA

```php
$totalLlamadas = TrazabilidadIADescargo::count();
$llamadasHoy = TrazabilidadIADescargo::whereDate('created_at', today())->count();
```

### Buscar por proveedor en metadata

```php
$llamadasGemini = TrazabilidadIADescargo::whereJsonContains('metadata->provider', 'gemini')
    ->count();
```

---

## Ciclo de vida de un registro

```
1. IADescargoService recibe solicitud de generar preguntas
2. Construye el prompt con el contexto completo del proceso
3. Envia el prompt a Google Gemini
4. Recibe la respuesta de la IA
5. REGISTRA en trazabilidad_ia_descargos:
   - prompt_enviado: El prompt completo enviado
   - respuesta_recibida: La respuesta completa de Gemini
   - tipo: 'generacion_preguntas'
   - metadata: {provider, model, timestamp}
6. Parsea las preguntas de la respuesta
7. Guarda las preguntas en preguntas_descargos
8. Si finishReason == MAX_TOKENS: Log::warning
9. Si hay error: Log::error (no se crea registro de trazabilidad)
```

---

## Retencion de datos

Los registros de trazabilidad se mantienen indefinidamente, vinculados a la diligencia de descargos. Si se elimina una diligencia, los registros de trazabilidad se eliminan en cascada (`cascadeOnDelete` en la migracion).

---

## Archivos relacionados

| Archivo | Descripcion |
|---------|-------------|
| `app/Models/TrazabilidadIADescargo.php` | Modelo Eloquent de trazabilidad |
| `database/migrations/2025_12_23_145008_create_trazabilidad_ia_descargos_table.php` | Migracion de la tabla |
| `app/Services/IADescargoService.php` | Servicio que registra la trazabilidad de preguntas |
| `app/Services/IAAnalisisSancionService.php` | Servicio de analisis de sanciones |
| `app/Services/TimelineService.php` | Servicio de timeline complementario |

## Proximos pasos

- [Google Gemini](/ia/google-gemini/) - Configuracion del proveedor de IA
- [Generacion de Preguntas](/ia/generacion-preguntas/) - Como se generan las preguntas con IA
- [Analisis de Sanciones](/ia/analisis-sanciones/) - Como la IA sugiere sanciones
