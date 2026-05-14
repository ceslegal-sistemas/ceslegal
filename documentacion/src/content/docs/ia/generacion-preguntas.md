---
title: Generacion de Preguntas
description: Como CES Legal utiliza inteligencia artificial para generar preguntas en las diligencias de descargos laborales
---

## Descripcion General

El servicio `IADescargoService` es el encargado de generar preguntas para las diligencias de descargos laborales utilizando la API de Google Gemini. El sistema genera dos tipos de preguntas:

1. **Preguntas iniciales**: Se generan al crear la diligencia de descargos, basadas en los hechos del proceso, la informacion del trabajador, la empresa y las sanciones laborales presuntamente incumplidas.
2. **Preguntas dinamicas**: Se generan en tiempo real durante la diligencia, basadas en las respuestas que el trabajador va proporcionando.

El limite maximo de preguntas por diligencia es de **30 preguntas** (combinando iniciales, generadas por IA y de cierre).

---

## Estructura de preguntas

Cada diligencia de descargos sigue una estructura de tres bloques:

### 1. Preguntas estandar iniciales (10 preguntas)

Son preguntas fijas que se aplican en toda diligencia de descargos:

```php
const PREGUNTAS_INICIALES = [
    '¿Va a asistir acompañado(a) por alguien?',
    '¿Que relacion tiene esa persona con usted?',
    '¿Para que empresa trabaja usted?',
    '¿Cual es su cargo en la empresa?',
    '¿Que tareas realiza en ese cargo?',
    '¿Conoce el reglamento interno de la empresa?',
    '¿Quien es su jefe directo?',
    '¿Usted cumple con las funciones de su cargo?',
    '¿Sigue las instrucciones que le da su jefe?',
    '¿Sabe por que fue citado(a) a estos descargos?',
];
```

### 2. Preguntas generadas por IA (dinamicas)

Preguntas especificas generadas por Gemini basadas en:

- Los hechos del proceso disciplinario.
- La informacion del trabajador (nombre, cargo).
- Las sanciones laborales del reglamento presuntamente incumplidas.
- Las respuestas previas del trabajador (para preguntas de seguimiento).

### 3. Preguntas estandar de cierre (3 preguntas)

```php
const PREGUNTAS_CIERRE = [
    '¿Le aviso esta situacion a su jefe directo?',
    '¿Ha estado antes en descargos?',
    '¿Sabe que no cumplir con sus obligaciones de trabajo puede traerle sanciones?',
];
```

---

## Como funciona

### Flujo de generacion inicial

```
1. Cliente crea la diligencia de descargos en Filament
2. Se invoca IADescargoService::generarPreguntasCompletas()
3. Se crean las 10 preguntas estandar iniciales
4. Se construye el prompt con el contexto del proceso
5. Se envia el prompt a Google Gemini
6. Se parsea la respuesta y se crean las preguntas IA
7. Se crean las 3 preguntas estandar de cierre
8. Se registra la trazabilidad de la llamada IA
9. Total estimado: 10 iniciales + N de IA + 3 cierre (maximo 30)
```

### Flujo de preguntas dinamicas (seguimiento)

```
1. El trabajador responde una pregunta en el formulario publico
2. Se invoca IADescargoService::generarPreguntasDinamicas()
3. Se verifica que no se haya excedido el limite de 30 preguntas
4. Se construye el contexto: hechos + todas las preguntas/respuestas previas
5. Se envia el prompt a Gemini con la ultima respuesta del trabajador
6. Si la IA detecta incongruencias o evasivas, genera hasta 1 pregunta nueva
7. Si no se requieren mas preguntas, la IA responde "NO_REQUIERE"
8. Se registra la trazabilidad
```

---

## Contexto enviado a la IA

El servicio construye un contexto completo del proceso para que Gemini pueda generar preguntas relevantes:

```php
$contexto = [
    'hechos' => $proceso->hechos,
    'sanciones_laborales' => $sancionesLaborales,     // Titulos y Descripciones
    'preguntas_respuestas' => $preguntasYRespuestas, // Historial completo
    'trabajador' => $proceso->trabajador->nombre_completo,
    'cargo' => $proceso->trabajador->cargo,
];
```

Para las preguntas dinamicas, tambien se incluye:

- Todas las preguntas anteriores con sus respuestas.
- Indicacion de si cada pregunta fue generada por IA o es estandar.
- La ultima pregunta respondida y la respuesta del trabajador.

---

## Ingenieria de prompts

### Prompt para preguntas iniciales

El prompt asigna un rol especifico a la IA y proporciona instrucciones detalladas:

```text
Eres un abogado laboral experto en procesos disciplinarios en Colombia.

CONTEXTO DEL PROCESO:

Trabajador: {nombre_completo}
Cargo: {cargo}

Hechos del proceso:
{hechos}

Articulos legales presuntamente incumplidos:
- {articulos}

INSTRUCCIONES:
Genera {N} preguntas iniciales para que el trabajador presente sus descargos.

Las preguntas deben:
- MUY IMPORTANTE: Usa lenguaje SENCILLO y CLARO que cualquier trabajador
  pueda entender facilmente. Evita terminos juridicos complejos.
- Ser breves y directas
- Ser especificas y neutrales
- Permitir al trabajador explicar su version de los hechos
- Indagar sobre circunstancias, motivaciones y contexto
- Dirigidas a esclarecer el hecho que dio origen a la diligencia de descargos
  con una tendencia de juzgamiento a favor del empleador y en contra del trabajador.
```

### Prompt para preguntas dinamicas

El prompt de seguimiento incluye todo el historial de la diligencia:

```text
Eres un abogado especialista en derecho laboral con enfasis y experiencia
en procesos disciplinarios y descargos en Colombia.

CONTEXTO DEL PROCESO:
Trabajador: {nombre}
Cargo: {cargo}
Hechos del proceso: {hechos}
Articulos legales presuntamente incumplidos: {articulos}

Preguntas realizadas y respuestas del trabajador:
[Inicial] P: ¿Conoce el reglamento interno de la empresa?
   R: Si lo conozco.
[IA] P: ¿Puede explicar por que no siguio el procedimiento?
   R: No sabia que existia ese procedimiento.

ULTIMA PREGUNTA RESPONDIDA:
{pregunta}

RESPUESTA DEL TRABAJADOR:
{respuesta}

INSTRUCCIONES:
Analiza las respuestas del trabajador teniendo en cuenta los hechos
en contraste con la conducta realizada que trasgrede las normas internas.
- Genera nuevas preguntas si y solo si existen inexactitudes,
  incongruencias, evasivas y/o contradicciones.
- Maximo 1 pregunta.
- Si no se requieren mas preguntas, responde: NO_REQUIERE
```

### Principios de lenguaje claro

Los prompts incluyen ejemplos explicitos de lenguaje claro vs lenguaje juridico:

| Incorrecto                                                 | Correcto                             |
| ---------------------------------------------------------- | ------------------------------------ |
| "¿Tuvo conocimiento de las directrices impartidas?"        | "¿Sabia que debia hacer?"            |
| "¿Ejercio sus funciones cabalmente?"                       | "¿Hizo bien su trabajo?"             |
| "¿Informo a su superior jerarquico?"                       | "¿Le conto a su jefe?"               |
| "¿Tenia conocimiento de las disposiciones del reglamento?" | "¿Conocia las reglas de la empresa?" |
| "¿Cual fue el movil de su actuacion?"                      | "¿Por que hizo eso?"                 |
| "¿Informo oportunamente a su superior jerarquico?"         | "¿Le aviso a tiempo a su jefe?"      |
| "¿Efectuo debidamente sus labores?"                        | "¿Hizo bien su trabajo?"             |

---

## Formato de respuesta de la IA

### Cuando hay preguntas

La IA responde en un formato estructurado que el sistema puede parsear:

```text
PREGUNTA_1: ¿Sabia usted que no podia hacer eso?
PREGUNTA_2: ¿Le aviso a su jefe antes de hacerlo?
```

### Cuando no se requieren preguntas

```text
NO_REQUIERE
```

### Parseo de la respuesta

El servicio utiliza una expresion regular para extraer las preguntas:

```php
preg_match_all('/PREGUNTA_\d+:\s*(.+?)(?=PREGUNTA_\d+:|$)/s', $respuestaIA, $matches);
```

Ademas aplica las siguientes validaciones:

- Las preguntas vacias se descartan.
- Las preguntas con menos de 20 caracteres se descartan (con log de warning).
- Se limita la cantidad de preguntas segun el espacio disponible hasta el maximo de 30.

---

## Almacenamiento de preguntas

Cada pregunta generada se almacena en la tabla `preguntas_descargos`:

```php
PreguntaDescargo::create([
    'diligencia_descargo_id' => $diligencia->id,
    'pregunta' => $preguntaTexto,
    'orden' => $ordenInicial + $index,
    'es_generada_por_ia' => true,          // Marca la pregunta como generada por IA
    'pregunta_padre_id' => $preguntaPadreId, // Referencia a la pregunta que origino esta
    'estado' => 'activa',
]);
```

| Campo                | Descripcion                                                    |
| -------------------- | -------------------------------------------------------------- |
| `es_generada_por_ia` | `true` para preguntas de IA, `false` para estandar             |
| `pregunta_padre_id`  | ID de la pregunta cuya respuesta genero esta pregunta dinamica |
| `orden`              | Posicion secuencial dentro de la diligencia                    |
| `estado`             | Estado de la pregunta (`activa`, etc.)                         |

---

## Limites y restricciones

| Restriccion                           | Valor         | Descripcion                       |
| ------------------------------------- | ------------- | --------------------------------- |
| **Maximo total de preguntas**         | 30            | Incluye iniciales, IA y cierre    |
| **Preguntas dinamicas por respuesta** | 1-2           | Segun espacio disponible          |
| **Longitud minima de pregunta**       | 20 caracteres | Preguntas mas cortas se descartan |
| **Timeout HTTP**                      | 30 segundos   | Tiempo maximo de espera a Gemini  |

Cuando se alcanza el limite de 30 preguntas, el sistema registra un warning y no genera mas:

```php
if ($totalPreguntasActuales >= self::LIMITE_MAXIMO_PREGUNTAS) {
    Log::warning('No se pueden generar mas preguntas dinamicas - limite alcanzado', [
        'diligencia_id' => $diligencia->id,
        'total_preguntas' => $totalPreguntasActuales,
        'limite_maximo' => self::LIMITE_MAXIMO_PREGUNTAS,
    ]);
    return [];
}
```

---

## Manejo de errores

Los errores en la generacion de preguntas **nunca interrumpen el flujo del proceso**. Si Gemini falla:

1. Se registra el error en `Log::error` con el ID de la pregunta y el mensaje de error.
2. Se retorna un array vacio (sin preguntas nuevas).
3. La diligencia continua normalmente con las preguntas estandar.
4. La trazabilidad queda registrada para auditoria.

```php
catch (\Exception $e) {
    Log::error('Error al generar preguntas dinamicas con IA', [
        'pregunta_id' => $preguntaRespondida->id,
        'error' => $e->getMessage(),
    ]);
    return [];
}
```

---

## Archivos relacionados

| Archivo                                 | Descripcion                                   |
| --------------------------------------- | --------------------------------------------- |
| `app/Services/IADescargoService.php`    | Servicio principal de generacion de preguntas |
| `app/Models/PreguntaDescargo.php`       | Modelo de preguntas de descargos              |
| `app/Models/RespuestaDescargo.php`      | Modelo de respuestas de descargos             |
| `app/Models/DiligenciaDescargo.php`     | Modelo de la diligencia de descargos          |
| `app/Models/TrazabilidadIADescargo.php` | Modelo de trazabilidad IA                     |

## Proximos pasos

- [Google Gemini](/ia/google-gemini/) - Configuracion del proveedor de IA
- [Analisis de Sanciones](/ia/analisis-sanciones/) - Como la IA sugiere sanciones
- [Trazabilidad](/ia/trazabilidad/) - Registro de auditoria de llamadas a IA
