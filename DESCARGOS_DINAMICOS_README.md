# Sistema de Descargos Dinámicos con IA

Este sistema permite que los trabajadores presenten sus descargos disciplinarios a través de un formulario inteligente que genera preguntas adaptativas usando IA generativa (OpenAI o Anthropic Claude).

## Características Principales

- **Preguntas dinámicas**: La IA genera preguntas de seguimiento basadas en las respuestas del trabajador
- **Acceso temporal controlado**: Los trabajadores solo pueden acceder el día programado
- **Trazabilidad completa**: Todas las interacciones con la IA quedan registradas
- **Formulario reactivo**: Las nuevas preguntas aparecen automáticamente sin recargar la página
- **Verificación de identidad**: Sistema de tokens seguros y verificación de fecha

## Instalación y Configuración

### 1. Configurar API de IA

Edita el archivo `.env` y configura tu proveedor de IA preferido:

#### Opción A: OpenAI
```env
IA_PROVIDER=openai
OPENAI_API_KEY=tu-api-key-aqui
OPENAI_MODEL=gpt-4
OPENAI_MAX_TOKENS=1000
```

#### Opción B: Anthropic (Claude)
```env
IA_PROVIDER=anthropic
ANTHROPIC_API_KEY=tu-api-key-aqui
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
ANTHROPIC_MAX_TOKENS=1024
```

### 2. Migraciones

Las migraciones ya fueron ejecutadas automáticamente. Las tablas creadas son:

- `preguntas_descargos`: Almacena todas las preguntas (iniciales y generadas por IA)
- `respuestas_descargos`: Almacena las respuestas del trabajador
- `trazabilidad_ia_descargos`: Registra todas las llamadas a la IA
- Campos nuevos en `diligencias_descargos`: token_acceso, fecha_acceso_permitida, etc.

## Flujo de Uso

### Para el Abogado/Administrador

#### 1. Crear o editar una Diligencia de Descargo

Cuando crees una diligencia de descargo, el sistema automáticamente:
- Generará un token de acceso único
- Configurará los campos de acceso temporal

#### 2. Generar Preguntas Iniciales con IA

Usa el servicio `IADescargoService` para generar preguntas iniciales:

```php
use App\Services\IADescargoService;
use App\Models\DiligenciaDescargo;

$diligencia = DiligenciaDescargo::find($id);
$iaService = new IADescargoService();

// Generar 5 preguntas iniciales basadas en los hechos del proceso
$preguntas = $iaService->generarPreguntasIniciales($diligencia, 5);
```

#### 3. Configurar Acceso Temporal

```php
$diligencia = DiligenciaDescargo::find($id);

// Generar token de acceso (válido por 7 días)
$token = $diligencia->generarTokenAcceso();

// Configurar fecha de acceso permitida (ej: fecha de la audiencia)
$diligencia->fecha_acceso_permitida = Carbon::parse('2025-01-15');
$diligencia->acceso_habilitado = true;
$diligencia->save();

// Generar URL de acceso
$url = route('descargos.acceso', ['token' => $token]);
```

#### 4. Enviar Link al Trabajador

Envía el link por email al trabajador:

```php
use Illuminate\Support\Facades\Mail;

$url = route('descargos.acceso', ['token' => $diligencia->token_acceso]);

Mail::to($trabajador->email)->send(new CitacionDescargos($diligencia, $url));
```

### Para el Trabajador

1. **Recibe el link por email** con instrucciones
2. **Accede el día programado** (el sistema valida la fecha automáticamente)
3. **Responde las preguntas** una por una
4. **Nuevas preguntas se generan automáticamente** basadas en sus respuestas
5. **Finaliza el proceso** cuando todas las preguntas estén respondidas

## Arquitectura del Sistema

### Modelos

**PreguntaDescargo**
- `diligencia_descargo_id`: FK a la diligencia
- `pregunta`: Texto de la pregunta
- `orden`: Orden de presentación
- `es_generada_por_ia`: Boolean
- `pregunta_padre_id`: FK opcional a la pregunta que la generó
- `estado`: 'activa' o 'respondida'

**RespuestaDescargo**
- `pregunta_descargo_id`: FK a la pregunta
- `respuesta`: Texto de la respuesta
- `respondido_en`: Timestamp

**TrazabilidadIADescargo**
- `diligencia_descargo_id`: FK a la diligencia
- `prompt_enviado`: Prompt completo enviado a la IA
- `respuesta_recibida`: Respuesta completa de la IA
- `tipo`: 'generacion_preguntas' o 'analisis_respuestas'
- `metadata`: JSON con información adicional (modelo, provider, etc.)

### Servicio IADescargoService

**Métodos principales:**

`generarPreguntasIniciales(DiligenciaDescargo $diligencia, int $cantidad = 5)`
- Genera preguntas iniciales basadas en los hechos y artículos legales del proceso

`generarPreguntasDinamicas(PreguntaDescargo $pregunta, RespuestaDescargo $respuesta)`
- Genera hasta 2 nuevas preguntas basadas en la respuesta del trabajador
- Retorna array vacío si no se requieren más preguntas

### Componente Livewire: FormularioDescargos

**Propiedades públicas:**
- `diligencia`: Instancia de DiligenciaDescargo
- `respuestas`: Array de respuestas indexadas por pregunta_id
- `preguntasProcesadas`: Array de boolean para evitar reprocesamiento
- `longitudMinimaRespuesta`: Caracteres mínimos requeridos (default: 20)

**Métodos principales:**
- `guardarRespuesta(int $preguntaId)`: Guarda la respuesta y genera nuevas preguntas
- `refrescarPreguntas()`: Recarga las preguntas desde la BD
- `finalizarDescargos()`: Marca el proceso como completado

**Eventos Livewire:**
- `preguntasGeneradas`: Se dispara cuando la IA genera nuevas preguntas
- `respuestaGuardada`: Se dispara cuando se guarda una respuesta
- `descargosFinalizados`: Se dispara cuando el trabajador finaliza

## Seguridad

### Validaciones Implementadas

1. **Token único de 64 caracteres** (generado con `bin2hex(random_bytes(32))`)
2. **Expiración del token** (configurable, default: 7 días)
3. **Validación de fecha de acceso** (solo puede acceder el día programado)
4. **Estado del acceso** (debe estar habilitado explícitamente)
5. **Registro de IP y timestamp** del acceso del trabajador
6. **Validación de longitud mínima** de respuestas
7. **Prevención de reprocesamiento** de respuestas ya guardadas

### Control de Loops Infinitos

El sistema previene loops infinitos de preguntas mediante:

1. **Límite de 2 preguntas por iteración** en el prompt de la IA
2. **Marca de "procesada"** para evitar regenerar preguntas de la misma respuesta
3. **Respuesta "NO_REQUIERE"** de la IA cuando no hay más preguntas relevantes

## Personalización del Prompt de IA

El prompt base usado por el servicio está en:
`app/Services/IADescargoService.php:construirPromptGeneracionPreguntas()`

Puedes personalizarlo para:
- Ajustar el tono (más formal, más neutral, etc.)
- Cambiar el contexto legal (otras jurisdicciones)
- Modificar las instrucciones de generación de preguntas
- Ajustar el límite de preguntas por iteración

## Trazabilidad y Auditoría

Toda interacción con la IA queda registrada en `trazabilidad_ia_descargos`:

```php
// Ver trazabilidad de una diligencia
$trazabilidad = TrazabilidadIADescargo::where('diligencia_descargo_id', $diligenciaId)
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($trazabilidad as $registro) {
    echo "Prompt enviado:\n{$registro->prompt_enviado}\n\n";
    echo "Respuesta recibida:\n{$registro->respuesta_recibida}\n\n";
    echo "Modelo usado: {$registro->metadata['model']}\n";
}
```

## Monitoreo de Logs

El sistema registra eventos importantes:

```php
// Logs automáticos
Log::info('Trabajador accedió a descargos', [...]); // Cuando accede al formulario
Log::error('Error al generar preguntas dinámicas con IA', [...]); // Errores de IA
Log::error('Error al guardar respuesta', [...]); // Errores al guardar
```

## Casos de Uso Avanzados

### Generar Preguntas Manualmente (sin IA)

```php
use App\Models\PreguntaDescargo;

$pregunta = PreguntaDescargo::create([
    'diligencia_descargo_id' => $diligencia->id,
    'pregunta' => '¿Puede explicar las circunstancias que rodearon el incidente?',
    'orden' => 1,
    'es_generada_por_ia' => false,
    'estado' => 'activa',
]);
```

### Consultar Preguntas Generadas por IA

```php
$preguntasIA = $diligencia->preguntas()
    ->where('es_generada_por_ia', true)
    ->with('preguntaPadre', 'respuesta')
    ->get();

foreach ($preguntasIA as $pregunta) {
    echo "Pregunta: {$pregunta->pregunta}\n";
    echo "Generada a partir de: {$pregunta->preguntaPadre->pregunta}\n";
    echo "Respuesta: {$pregunta->respuesta->respuesta}\n\n";
}
```

### Ver Árbol de Preguntas

```php
// Obtener pregunta inicial con todas sus "hijas" (generadas a partir de ella)
$preguntaInicial = PreguntaDescargo::with('preguntasHijas.respuesta')
    ->where('es_generada_por_ia', false)
    ->first();

foreach ($preguntaInicial->preguntasHijas as $hija) {
    echo "↳ {$hija->pregunta}\n";
    echo "  Respuesta: {$hija->respuesta?->respuesta}\n\n";
}
```

## Integración con Filament Admin

Para integrar el sistema con Filament, puedes crear un Resource:

```php
// app/Filament/Admin/Resources/DiligenciaDescargoResource.php

use App\Services\IADescargoService;
use Filament\Actions\Action;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('proceso.codigo'),
            TextColumn::make('fecha_diligencia'),
            TextColumn::make('trabajador_asistio')->boolean(),
        ])
        ->actions([
            Action::make('generar_preguntas')
                ->label('Generar Preguntas IA')
                ->icon('heroicon-o-sparkles')
                ->action(function (DiligenciaDescargo $record) {
                    $iaService = new IADescargoService();
                    $preguntas = $iaService->generarPreguntasIniciales($record, 5);

                    Notification::make()
                        ->success()
                        ->title('Preguntas generadas')
                        ->body(count($preguntas) . ' preguntas generadas con IA')
                        ->send();
                }),

            Action::make('ver_descargos')
                ->label('Ver Descargos')
                ->url(fn (DiligenciaDescargo $record) =>
                    route('descargos.acceso', ['token' => $record->token_acceso])
                )
                ->openUrlInNewTab(),
        ]);
}
```

## Troubleshooting

### Error: "No se pueden generar preguntas"

**Causa**: API key no configurada o inválida

**Solución**:
1. Verifica que `OPENAI_API_KEY` o `ANTHROPIC_API_KEY` esté configurada en `.env`
2. Verifica que la API key sea válida
3. Revisa los logs en `storage/logs/laravel.log`

### Error: "El enlace de acceso ha expirado"

**Causa**: El token ha expirado (más de 7 días desde su generación)

**Solución**:
```php
$diligencia->generarTokenAcceso(); // Regenera el token
```

### Las preguntas nuevas no aparecen automáticamente

**Causa**: Problema con Livewire reactivity

**Solución**:
- Verifica que `@livewireStyles` y `@livewireScripts` estén en el layout
- Limpia la caché: `php artisan view:clear`
- Verifica que el evento `preguntasGeneradas` se esté disparando

## Mejoras Futuras Sugeridas

1. **Análisis de respuestas**: Usar IA para analizar contradicciones o inconsistencias
2. **Generación automática de actas**: Generar el acta de descargos con IA
3. **Sugerencias de análisis jurídico**: IA sugiere conclusiones basadas en las respuestas
4. **Múltiples idiomas**: Soporte para trabajadores que hablen otros idiomas
5. **Firma electrónica**: Integración con firma electrónica para el acta final

## Soporte

Para preguntas o problemas:
- Revisa los logs en `storage/logs/laravel.log`
- Consulta la trazabilidad en la tabla `trazabilidad_ia_descargos`
- Verifica la configuración en `config/services.php`

## Licencia

Este módulo es parte del sistema CES LEGAL.
