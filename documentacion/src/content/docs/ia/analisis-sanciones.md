---
title: Analisis de Sanciones
description: Como CES Legal utiliza inteligencia artificial para analizar procesos disciplinarios y sugerir sanciones apropiadas segun la legislacion laboral colombiana
---

## Descripcion General

El servicio `IAAnalisisSancionService` analiza el proceso disciplinario completo y sugiere sanciones apropiadas basandose en:

- Los **hechos** del caso.
- Las **sanciones laborales** del reglamento interno presuntamente incumplidas.
- Los **descargos** del trabajador (preguntas y respuestas).
- El **historial** de procesos disciplinarios previos del trabajador.
- La **legislacion laboral colombiana** (Codigo Sustantivo del Trabajo).

El analisis se presenta al abogado dentro del modal de **emitir sancion** en la interfaz de Filament, donde puede revisar la sugerencia de la IA y tomar la decision final.

---

## Como funciona

### Flujo completo

```
1. El abogado hace clic en "Emitir Sancion" en el proceso disciplinario
2. Se invoca IAAnalisisSancionService::analizarYSugerirSanciones($proceso)
3. El servicio recopila:
   a. Informacion del trabajador y empresa
   b. Historial de procesos disciplinarios previos
   c. Preguntas y respuestas de los descargos (si existen)
4. Se construye el prompt con todo el contexto
5. Se envia a Google Gemini con temperature=0.3 (respuestas consistentes)
6. Se parsea la respuesta JSON de la IA
7. Se retorna el analisis al formulario modal de Filament
8. El abogado revisa la sugerencia y toma la decision
```

### Recopilacion de datos

El servicio recopila automaticamente toda la informacion relevante:

**Historial del trabajador:**

```php
$procesos = ProcesoDisciplinario::where('trabajador_id', $trabajador->id)
    ->where('id', '!=', $procesoActualId)
    ->where('estado', '!=', 'archivado')
    ->orderBy('created_at', 'desc')
    ->get();
```

Cada proceso previo incluye: fecha, hechos, sanciones del reglamento incumplidas, sancion aplicada y estado.

**Descargos del trabajador:**

```php
$preguntas = $diligencia->preguntas()
    ->with('respuesta')
    ->ordenadas()
    ->get();
```

Se formatean como texto numerado con preguntas y respuestas.

---

## Estructura del prompt

El prompt esta disenado para que Gemini actue como un experto en derecho laboral colombiano:

```text
Eres un experto en derecho laboral colombiano. Analiza el siguiente proceso
disciplinario y determina que tipos de sanciones son APROPIADAS segun la
gravedad de la falta, el Codigo Sustantivo del Trabajo, el reglamento
interno de trabajo de la empresa y el historial del trabajador.

INFORMACION DEL PROCESO:
- Empresa: {razon_social}
- Trabajador: {nombre_completo}
- Cargo: {cargo}

HECHOS DEL CASO ACTUAL:
{hechos}

SANCIONES LABORALES DEL REGLAMENTO INCUMPLIDAS:
{sanciones_laborales}

DESCARGOS DEL TRABAJADOR:
{preguntas_y_respuestas}

HISTORIAL DEL TRABAJADOR:
{historial_procesos_previos}
```

### Criterios de gravedad

El prompt define criterios claros para clasificar las faltas:

**Faltas leves:**
- Llegadas tarde ocasionales (primera o segunda vez).
- Incumplimientos menores sin impacto grave.
- Primera vez cometiendo una falta (sin antecedentes).
- Descuidos leves que no causan dano significativo.
- Sancion: Solo llamado de atencion.

**Faltas graves - Nivel bajo (1-8 dias de suspension):**
- Reincidencia en faltas leves (2 o mas procesos previos).
- Insubordinacion leve o falta de respeto.
- Incumplimiento de normas de seguridad sin consecuencias graves.
- Negligencia que cause dano leve o moderado.
- Ausencias injustificadas (pocas).

**Faltas graves - Nivel alto (8-60 dias de suspension o terminacion):**
- Hurto o fraude.
- Agresion fisica.
- Acoso laboral o sexual.
- Violacion grave de seguridad que ponga en riesgo vidas.
- Reincidencia multiple (3 o mas procesos previos).
- Falsificacion de documentos.
- Cualquier conducta que constituya justa causa de terminacion segun Art. 62 CST.

### Escala de dias de suspension

| Rango | Aplicacion |
|-------|------------|
| 1-3 dias | Faltas graves nivel bajo, sin reincidencia reciente |
| 3-8 dias | Faltas graves nivel bajo con reincidencia o impacto moderado |
| 8-15 dias | Faltas graves nivel alto, conductas serias |
| 15-30 dias | Faltas graves nivel alto, conductas muy serias |
| 30-60 dias | Faltas graves nivel alto, maxima gravedad (alternativa a terminacion) |

---

## Estructura de la respuesta

La IA retorna un JSON con la siguiente estructura:

```json
{
  "gravedad": "leve|grave",
  "nivel_gravedad": "ninguno|bajo|alto",
  "es_reincidencia": true,
  "justificacion": "Explicacion clara de por que se clasifica asi y en que nivel",
  "sanciones_disponibles": ["llamado_atencion", "suspension", "terminacion"],
  "sancion_recomendada": "suspension",
  "dias_suspension_sugeridos": [1, 2, 3, 5, 8],
  "razonamiento_legal": "Explicacion basada en el CST y las sanciones del reglamento",
  "consideraciones_especiales": "Informacion adicional: historial, descargos, atenuantes, agravantes"
}
```

### Descripcion de cada campo

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `gravedad` | `string` | Clasificacion de la falta: `leve` o `grave` |
| `nivel_gravedad` | `string` | Nivel dentro de la gravedad: `ninguno`, `bajo` o `alto` |
| `es_reincidencia` | `boolean` | Si el trabajador tiene procesos disciplinarios previos |
| `justificacion` | `string` | Explicacion de la clasificacion |
| `sanciones_disponibles` | `array` | Tipos de sancion aplicables al caso |
| `sancion_recomendada` | `string` | Sancion sugerida por la IA |
| `dias_suspension_sugeridos` | `array` | Opciones de dias de suspension |
| `razonamiento_legal` | `string` | Fundamentacion en el CST y reglamento interno |
| `consideraciones_especiales` | `string` | Atenuantes, agravantes y observaciones |

### Reglas de sanciones segun gravedad

| Gravedad | Nivel | Sanciones disponibles | Dias sugeridos |
|----------|-------|-----------------------|----------------|
| Leve | Ninguno | `["llamado_atencion"]` | No aplica |
| Grave | Bajo | `["llamado_atencion", "suspension"]` | `[1, 2, 3, 5, 8]` |
| Grave | Alto | `["suspension", "terminacion"]` | `[8, 15, 30, 60]` |

---

## Uso en la interfaz de Filament

El analisis de la IA se presenta dentro del modal de la accion **emitir_sancion** en `ProcesoDisciplinarioResource`. El abogado puede:

1. Ver la sugerencia de gravedad y sancion recomendada.
2. Revisar la justificacion y el razonamiento legal.
3. Consultar los dias de suspension sugeridos.
4. Aceptar la sugerencia o elegir una sancion diferente.
5. Confirmar y emitir la sancion.

---

## Principios de lenguaje claro para documentos de sancion

Los documentos de sancion generados por el sistema siguen principios de **lenguaje claro** para asegurar que el trabajador comprenda el contenido:

| Principio | Descripcion | Ejemplo |
|-----------|-------------|---------|
| **Oraciones cortas** | Maximo 25 palabras por oracion | "Usted llego tarde el dia 15 de enero." en lugar de "Se ha evidenciado que usted, en su calidad de trabajador, no cumplio con el horario establecido." |
| **Voz activa** | Sujeto + verbo + complemento | "La empresa le impone una suspension" en lugar de "Una suspension ha sido impuesta" |
| **Palabras sencillas** | Evitar jerga juridica innecesaria | "reglas" en lugar de "disposiciones normativas" |
| **Dirigido al trabajador** | Usar "usted" directamente | "Usted no cumplio con..." en lugar de "El trabajador no cumplio con..." |

---

## Parametros de generacion

Para el analisis de sanciones se utilizan parametros mas conservadores que para la generacion de preguntas:

| Parametro | Valor | Razon |
|-----------|-------|-------|
| `temperature` | `0.3` | Respuestas mas consistentes y predecibles para decisiones legales |
| `maxOutputTokens` | `2048` | Suficiente para el JSON de analisis |
| `topP` | `0.95` | Sampling amplio pero controlado |
| `topK` | `40` | Limita la diversidad de tokens |
| `timeout` | `60 segundos` | Mayor tiempo por la complejidad del analisis |

---

## Manejo de errores

### Parseo de JSON

La respuesta de la IA puede venir con formato markdown (bloques de codigo). El servicio limpia la respuesta antes de parsear:

```php
$analisisTexto = trim($analisisTexto);
$analisisTexto = preg_replace('/```json\s*/', '', $analisisTexto);
$analisisTexto = preg_replace('/```\s*$/', '', $analisisTexto);
$analisisTexto = preg_replace('/```/', '', $analisisTexto);

$analisis = json_decode($analisisTexto, true);
```

### Validacion de estructura

Se valida que la respuesta contenga al menos los campos `gravedad` y `sanciones_disponibles`:

```php
if (!isset($analisis['gravedad']) || !isset($analisis['sanciones_disponibles'])) {
    throw new \Exception('Respuesta de IA con estructura invalida');
}
```

### Valores por defecto (fallback)

Si la IA falla o la respuesta no es valida, el sistema retorna opciones por defecto que permiten al abogado tomar la decision manualmente:

```php
private function obtenerOpcionesPorDefecto(): array
{
    return [
        'gravedad' => 'grave',
        'nivel_gravedad' => 'bajo',
        'es_reincidencia' => false,
        'justificacion' => 'Analisis manual requerido - el sistema no pudo determinar
                            automaticamente la gravedad.',
        'sanciones_disponibles' => ['llamado_atencion', 'suspension', 'terminacion'],
        'sancion_recomendada' => 'llamado_atencion',
        'dias_suspension_sugeridos' => [1, 2, 3, 5, 8],
        'razonamiento_legal' => 'Se requiere revision manual del caso.',
        'consideraciones_especiales' => 'El analisis automatico no estuvo disponible.',
    ];
}
```

La respuesta del servicio siempre incluye un indicador de exito:

```php
// Caso exitoso
return [
    'success' => true,
    'analisis' => $analisis,
];

// Caso de error
return [
    'success' => false,
    'error' => $e->getMessage(),
    'analisis' => $this->obtenerOpcionesPorDefecto(),
];
```

### Logging

Se registran logs informativos y de error en cada etapa:

```php
// Inicio del analisis
Log::info('Analizando proceso disciplinario para sugerir sanciones', [
    'proceso_id' => $proceso->id,
    'trabajador_id' => $trabajador->id,
    'cantidad_procesos_previos' => count($historialProcesos),
]);

// Analisis completado
Log::info('Analisis de sanciones completado', [
    'proceso_id' => $proceso->id,
    'sanciones_sugeridas' => $analisis['sanciones_disponibles'] ?? [],
    'gravedad' => $analisis['gravedad'] ?? 'desconocida',
]);

// Error
Log::error('Error al analizar proceso para sugerir sanciones', [
    'proceso_id' => $proceso->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

---

## Archivos relacionados

| Archivo | Descripcion |
|---------|-------------|
| `app/Services/IAAnalisisSancionService.php` | Servicio principal de analisis de sanciones |
| `app/Models/ProcesoDisciplinario.php` | Modelo del proceso disciplinario |
| `app/Models/Trabajador.php` | Modelo del trabajador |
| `app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php` | Resource de Filament con la accion `emitir_sancion` |

## Proximos pasos

- [Google Gemini](/ia/google-gemini/) - Configuracion del proveedor de IA
- [Generacion de Preguntas](/ia/generacion-preguntas/) - Como se generan las preguntas con IA
- [Trazabilidad](/ia/trazabilidad/) - Registro de auditoria de llamadas a IA
