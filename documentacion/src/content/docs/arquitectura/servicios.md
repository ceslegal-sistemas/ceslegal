---
title: Servicios
description: Servicios de negocio que encapsulan la lógica principal de CES Legal
---

## Visión General

Los servicios encapsulan la lógica de negocio compleja, manteniéndola separada de los controladores y modelos. CES Legal cuenta con **9 servicios** que totalizan aproximadamente **5,600 líneas de código**.

```
app/Services/
├── DocumentGeneratorService.php    # 1,141 líneas
├── ActaDescargosService.php        # 705 líneas
├── IADescargoService.php           # 645 líneas
├── NotificacionService.php         # 393 líneas
├── DocumentoService.php            # 344 líneas
├── IAAnalisisSancionService.php    # 333 líneas
├── EstadoProcesoService.php        # 243 líneas
├── TimelineService.php             # 238 líneas
├── TerminoLegalService.php         # 223 líneas
└── DisponibilidadHelper.php        # 154 líneas
```

## DocumentGeneratorService

**Responsabilidad:** Generación de documentos legales en PDF y Word.

### Métodos Principales

```php
class DocumentGeneratorService
{
    /**
     * Genera la citación a diligencia de descargos
     */
    public function generarCitacion(
        ProcesoDisciplinario $proceso,
        string $formato = 'pdf'
    ): string;

    /**
     * Genera el documento de sanción
     */
    public function generarSancion(
        ProcesoDisciplinario $proceso,
        Sancion $sancion,
        string $formato = 'pdf'
    ): string;

    /**
     * Interpola variables en el documento
     */
    protected function interpolateVariables(
        string $template,
        array $data
    ): string;
}
```

### Variables Disponibles

| Variable | Descripción |
|----------|-------------|
| `{NOMBRE_TRABAJADOR}` | Nombre completo del trabajador |
| `{CEDULA_TRABAJADOR}` | Número de cédula |
| `{CARGO_TRABAJADOR}` | Cargo actual |
| `{NOMBRE_EMPRESA}` | Nombre de la empresa |
| `{FECHA_DILIGENCIA}` | Fecha de la diligencia |
| `{HORA_DILIGENCIA}` | Hora de la diligencia |
| `{LUGAR_DILIGENCIA}` | Lugar o enlace virtual |
| `{HECHOS}` | Descripción de los hechos |
| `{ARTICULOS}` | Artículos presuntamente incumplidos |

### Ejemplo de Uso

```php
$documentService = app(DocumentGeneratorService::class);

// Generar citación en PDF
$pdfPath = $documentService->generarCitacion($proceso, 'pdf');

// Generar citación en Word
$wordPath = $documentService->generarCitacion($proceso, 'word');
```

---

## ActaDescargosService

**Responsabilidad:** Generación de actas de la diligencia de descargos.

### Métodos Principales

```php
class ActaDescargosService
{
    /**
     * Genera el acta de descargos con todas las preguntas y respuestas
     */
    public function generarActa(DiligenciaDescargo $diligencia): string;

    /**
     * Formatea las preguntas y respuestas para el acta
     */
    protected function formatearPreguntasRespuestas(
        Collection $preguntas
    ): array;

    /**
     * Calcula el tiempo total de la diligencia
     */
    protected function calcularDuracion(DiligenciaDescargo $diligencia): string;
}
```

### Estructura del Acta

1. **Encabezado**: Datos de la empresa y el proceso
2. **Comparecientes**: Trabajador, abogado, testigos
3. **Preguntas y Respuestas**: Numeradas con marcación de IA
4. **Observaciones**: Notas adicionales
5. **Firmas**: Espacios para firmas de los participantes

---

## IADescargoService

**Responsabilidad:** Integración con Google Gemini para generación de preguntas.

### Métodos Principales

```php
class IADescargoService
{
    /**
     * Genera las preguntas iniciales basadas en los hechos
     */
    public function generarPreguntasIniciales(
        DiligenciaDescargo $diligencia,
        int $cantidad = 10
    ): Collection;

    /**
     * Genera preguntas dinámicas basadas en respuestas
     */
    public function generarPreguntaDinamica(
        DiligenciaDescargo $diligencia,
        RespuestaDescargo $respuesta
    ): ?PreguntaDescargo;

    /**
     * Construye el prompt para Gemini
     */
    protected function buildPrompt(
        ProcesoDisciplinario $proceso,
        array $context = []
    ): string;

    /**
     * Llama a la API de Gemini
     */
    protected function callGeminiAPI(string $prompt): array;
}
```

### Configuración

```php
// config/services.php
'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'max_tokens' => 2048,
    'temperature' => 0.7,
],
```

### Prompt de Ejemplo

```text
Eres un abogado laboralista colombiano experto en procesos disciplinarios.

CONTEXTO DEL CASO:
- Empresa: {NOMBRE_EMPRESA}
- Trabajador: {NOMBRE_TRABAJADOR}
- Cargo: {CARGO}
- Hechos: {HECHOS}
- Artículos presuntamente incumplidos: {ARTICULOS}

INSTRUCCIONES:
Genera exactamente 10 preguntas para la diligencia de descargos que:
1. Sean claras y directas
2. Permitan al trabajador explicar su versión
3. Cubran todos los aspectos de los hechos
4. Sean imparciales y respetuosas

Responde SOLO con un JSON array de preguntas.
```

### Trazabilidad

Cada llamada a la IA queda registrada:

```php
TrazabilidadIADescargo::create([
    'diligencia_descargo_id' => $diligencia->id,
    'tipo_operacion' => 'generacion_preguntas',
    'prompt_enviado' => $prompt,
    'respuesta_ia' => $response,
    'modelo_utilizado' => config('services.gemini.model'),
    'tokens_utilizados' => $tokenCount,
    'tiempo_respuesta_ms' => $responseTime,
    'exitoso' => true,
]);
```

---

## IAAnalisisSancionService

**Responsabilidad:** Análisis de respuestas y recomendación de sanciones.

### Métodos Principales

```php
class IAAnalisisSancionService
{
    /**
     * Analiza las respuestas y recomienda una sanción
     */
    public function analizarYRecomendar(
        DiligenciaDescargo $diligencia
    ): array;

    /**
     * Evalúa la gravedad de la falta
     */
    protected function evaluarGravedad(array $respuestas): string;

    /**
     * Sugiere sanciones proporcionales
     */
    protected function sugerirSanciones(
        string $gravedad,
        array $antecedentes
    ): Collection;
}
```

### Respuesta del Análisis

```php
[
    'gravedad' => 'grave',
    'fundamento' => 'Explicación del análisis...',
    'sancion_recomendada' => [
        'tipo' => 'suspension',
        'dias' => 3,
        'sancion_laboral_id' => 15,
    ],
    'factores_atenuantes' => ['Sin antecedentes', 'Colaboración'],
    'factores_agravantes' => ['Reincidencia', 'Daño material'],
    'confianza' => 0.85,
]
```

---

## NotificacionService

**Responsabilidad:** Sistema de notificaciones nativo de Laravel/Filament.

### Métodos Principales

```php
class NotificacionService
{
    /**
     * Envía notificación de proceso aperturado
     */
    public function notificarProcesoAperturado(
        ProcesoDisciplinario $proceso
    ): void;

    /**
     * Envía notificación de descargos próximos
     */
    public function notificarDescargosProximos(
        DiligenciaDescargo $diligencia
    ): void;

    /**
     * Envía notificación de sanción emitida
     */
    public function notificarSancionEmitida(
        ProcesoDisciplinario $proceso,
        Sancion $sancion
    ): void;
}
```

### Tipos de Notificaciones

| Tipo | Icono | Color | Destinatario |
|------|-------|-------|--------------|
| Proceso aperturado | `heroicon-o-document-plus` | Azul | Abogado |
| Descargos próximos | `heroicon-o-clock` | Naranja | Abogado, Cliente |
| Descargos completados | `heroicon-o-check-circle` | Verde | Abogado |
| Sanción emitida | `heroicon-o-exclamation-triangle` | Rojo | Cliente |
| Impugnación recibida | `heroicon-o-arrow-uturn-left` | Morado | Abogado |

---

## EstadoProcesoService

**Responsabilidad:** Máquina de estados del proceso disciplinario.

### Transiciones Válidas

```php
class EstadoProcesoService
{
    protected array $transicionesValidas = [
        'apertura' => ['descargos_pendientes'],
        'descargos_pendientes' => ['descargos_realizados', 'descargos_no_realizados'],
        'descargos_realizados' => ['sancion_emitida', 'archivado'],
        'descargos_no_realizados' => ['sancion_emitida', 'archivado'],
        'sancion_emitida' => ['impugnacion_realizada', 'cerrado'],
        'impugnacion_realizada' => ['cerrado'],
        'archivado' => [],
        'cerrado' => [],
    ];

    public function cambiarEstado(
        ProcesoDisciplinario $proceso,
        string $nuevoEstado
    ): bool;

    public function puedeTransicionar(
        string $estadoActual,
        string $nuevoEstado
    ): bool;

    public function getEstadosSiguientes(string $estadoActual): array;
}
```

---

## TimelineService

**Responsabilidad:** Registro de auditoría de cambios.

### Métodos Principales

```php
class TimelineService
{
    public function registrarEvento(
        ProcesoDisciplinario $proceso,
        string $evento,
        string $descripcion,
        array $datosAnteriores = null,
        array $datosNuevos = null
    ): Timeline;

    public function getTimelineProceso(
        ProcesoDisciplinario $proceso
    ): Collection;
}
```

### Eventos Registrados

- Creación del proceso
- Cambios de estado
- Asignación de abogado
- Programación de descargos
- Generación de preguntas
- Completación de descargos
- Emisión de sanción
- Cierre del proceso

---

## TerminoLegalService

**Responsabilidad:** Cálculo de plazos legales considerando días hábiles.

### Métodos Principales

```php
class TerminoLegalService
{
    /**
     * Calcula la fecha límite considerando días hábiles
     */
    public function calcularFechaLimite(
        Carbon $fechaInicio,
        int $diasHabiles
    ): Carbon;

    /**
     * Verifica si una fecha es día hábil
     */
    public function esDiaHabil(Carbon $fecha): bool;

    /**
     * Obtiene los días no hábiles de un período
     */
    public function getDiasNoHabiles(
        Carbon $inicio,
        Carbon $fin
    ): Collection;
}
```

---

## DisponibilidadHelper

**Responsabilidad:** Gestión de disponibilidad de abogados.

### Métodos Principales

```php
class DisponibilidadHelper
{
    /**
     * Obtiene abogados disponibles para una fecha
     */
    public function getAbogadosDisponibles(Carbon $fecha): Collection;

    /**
     * Verifica si un abogado está disponible
     */
    public function estaDisponible(User $abogado, Carbon $fecha): bool;

    /**
     * Asigna automáticamente el abogado con menos carga
     */
    public function asignarAbogadoAutomatico(): ?User;
}
```

---

## Inyección de Dependencias

Los servicios se registran automáticamente en el contenedor de Laravel:

```php
// Uso en controladores o acciones de Filament
public function __construct(
    protected IADescargoService $iaService,
    protected DocumentGeneratorService $documentService
) {}

// O usando el helper app()
$resultado = app(IADescargoService::class)->generarPreguntasIniciales($diligencia);
```

## Próximos Pasos

- [Estados del Proceso](/flujo/estados-proceso/) - Flujo de estados
- [Google Gemini](/ia/google-gemini/) - Integración con IA
