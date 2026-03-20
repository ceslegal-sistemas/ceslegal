---
title: Modulo de Feedback
description: Sistema de recoleccion y analisis de feedback de trabajadores y clientes en CES Legal
---

## Descripcion General

El modulo de Feedback recolecta opiniones de dos tipos de usuarios:

- **Trabajadores**: al finalizar su formulario de descargos (`/descargos/{token}`)
- **Clientes y Abogados**: automaticamente desde el panel de administracion, segun el momento de uso

Toda la informacion recolectada es visible en el recurso **Feedback** del panel (`/admin/feedbacks`).

---

## Tipos de Feedback

| Tipo | Valor | Quien responde | Cuando |
|------|-------|----------------|--------|
| Diligencia de descargos | `descargo_trabajador` | El trabajador | Al finalizar el formulario de descargos |
| Registro de proceso | `descargo_registro` | Cliente / Abogado | Modal automatico en el panel |
| Plataforma general | `plataforma_general` | Cliente / Abogado | Modal automatico en el panel |

---

## Flujo del Trabajador

El feedback del trabajador es **organico**: las preguntas de opinion aparecen una a una despues de responder todas las preguntas del formulario de descargos, con el mismo estilo y botones "Guardar y continuar". No es un modal separado.

### Preguntas (5 pasos — todas opcionales)

| Paso | Pregunta | Tipo de campo |
|------|----------|---------------|
| 1 | ¿Como fue su experiencia usando la aplicacion? | Radio: Muy buena / Buena / Mala / Muy mala |
| 2 | ¿Encontro algo confuso durante el proceso? | Radio: Si / No → si Si: textarea |
| 3 | ¿Que cambiaria o mejoraria? | Textarea libre |
| 4 | ¿Las preguntas del formulario fueron claras? | Radio: Si / No → si No: textarea |
| 5 | ¿Pudo completar el proceso sin ayuda? | Radio: Si / No → si No: textarea |

Despues del paso 5 aparece la pantalla de evidencias y el boton "Enviar Descargos".

### Comportamiento

- Las preguntas son **opcionales**: el trabajador puede avanzar sin responder las de texto libre (pasos 3 y condiciones de paso 2, 4, 5). Los radios de los pasos 1, 2, 4 y 5 si requieren seleccion para avanzar.
- La barra de progreso del header incluye las 5 preguntas de feedback en el total, para que el avance se vea continuo.
- El sistema registra en `preguntas_completadas_en` la primera vez que el trabajador llega a esta seccion, aunque no finalice.
- El feedback se guarda al presionar "Enviar Descargos" (no en un paso separado). Si el trabajador no responde ninguna pregunta de feedback, no se crea ningun registro.

```php
// app/Livewire/FormularioDescargos.php

// Propiedades de feedback
public int    $feedbackPaso      = 1;    // 1-5 = paso activo, 6 = completado
public string $fbExperiencia     = '';   // 'muy_buena'|'buena'|'mala'|'muy_mala'
public string $fbAlgoConfuso     = '';   // 'si'|'no'
public string $fbConfusoDetalle  = '';
public string $fbQueCambiaria    = '';
public string $fbPreguntasClaras = '';   // 'si'|'no'
public string $fbClarasDetalle   = '';
public string $fbSinAyuda        = '';   // 'si'|'no'
public string $fbSinAyudaDetalle = '';

// Avanza al siguiente paso y valida el campo requerido
public function avanzarFeedback(): void { ... }

// Guarda el feedback junto con finalizarDescargos() (no bloqueante)
private function guardarFeedbackOrganico(): void { ... }
```

El feedback se guarda en `Feedback` con:
- `calificacion`: mapeado de la opcion textual a numero (Muy buena=5, Buena=4, Mala=2, Muy mala=1)
- `sugerencia`: contenido de "¿Que cambiaria?"
- `respuestas_adicionales`: JSON con `algo_confuso`, `confuso_detalle`, `preguntas_claras`, `claras_detalle`, `sin_ayuda`, `sin_ayuda_detalle`
- `tipo`: `descargo_trabajador`

---

## Flujo del Cliente / Admin (Triggers)

El sistema detecta automaticamente el momento ideal para solicitar feedback al cliente o abogado. Se muestran como modales no descartables (sin boton de cerrar) en el listado de procesos.

### Trigger 1: Primer proceso (`primer_proceso`)

**Cuando:** La primera vez que el usuario tiene al menos un proceso en estado avanzado (no `apertura` ni `archivado`).
**Cooldown:** Una sola vez en la vida del usuario.
**Campos:**

| # | Pregunta | Tipo |
|---|----------|------|
| 1 | ¿Como calificaria su experiencia general? | Radio: Muy buena / Buena / Mala / Muy mala |
| 2 | ¿En que parte tuvo mas dificultad? | Radio: Registro de trabajadores / Creacion de la citacion / Ninguna / Todas |
| 3 | ¿Le resulto facil crear la citacion? | Radio: Si / No |
| 4 | ¿Por que no le resulto facil? | Textarea (condicional — aparece si respuesta es No) |
| 5 | ¿Que mejoraria de la plataforma? | Textarea obligatorio |
| 6 | ¿Pudo completar el proceso sin ayuda? | Radio: Si / No |
| 7 | ¿En que necesito ayuda? | Textarea (condicional — aparece si respuesta es No) |

Campos guardados en `respuestas_adicionales`: `calificacion_experiencia`, `dificultad_proceso`, `facilidad_citacion`, `facilidad_citacion_porque`, `mejora_sugerida`, `completo_sin_ayuda`, `completo_sin_ayuda_porque`.

### Trigger 2: Post diligencia (`post_diligencia`)

**Cuando:** Hay un proceso que llego a `descargos_realizados` despues del ultimo feedback del usuario.
**Cooldown:** 14 dias desde el ultimo feedback.
**Campos obligatorios:**
- Calificacion del proceso (1-5 estrellas)
- ¿La plataforma brindo seguridad juridica?
- ¿Cuanto tiempo aproximado ahorro la herramienta?
- ¿El acta de descargos fue de calidad?
- Comentario adicional

### Trigger 3: Periodico (`periodico`)

**Cuando:** Han pasado 14 dias desde el ultimo feedback y el usuario tiene procesos avanzados.
**Cooldown:** 14 dias.
**Campos obligatorios:**
- Calificacion general de la plataforma (1-5 estrellas)
- NPS (0–10): probabilidad de recomendar la plataforma
- Si pudieras cambiar una sola cosa, ¿que seria?
- ¿Hay alguna funcionalidad que faltaba?

### Trigger 4: Hito (`hito`)

**Cuando:** El usuario alcanza un multiplo de 5 procesos completados (5, 10, 15...).
**Cooldown:** Sin cooldown (se muestra una vez por cada hito alcanzado).
**Campos obligatorios:**
- NPS (0–10)
- Aspectos mas valorados (seleccion multiple)
- ¿Recomendaria CES Legal?
- Comentario libre

---

## NPS (Net Promoter Score)

El NPS mide la probabilidad de que un usuario recomiende la plataforma (escala 0-10).

**Categorias:**

| Categoria | Puntuacion | Color |
|-----------|------------|-------|
| Promotor | 9 – 10 | Verde |
| Neutro | 7 – 8 | Amarillo |
| Detractor | 0 – 6 | Rojo |

**Formula de calculo:**

```
NPS = ((Promotores - Detractores) / Total_con_NPS) × 100
```

El widget de estadisticas muestra el NPS en tiempo real junto con el conteo de promotores y detractores.

---

## Panel de Administracion (`/admin/feedbacks`)

### Tabs de Listado

| Tab | Filtro |
|-----|--------|
| Todos | Sin filtro |
| Trabajadores | `tipo = descargo_trabajador` |
| Clientes | `tipo = descargo_registro` |
| Con comentario | `sugerencia IS NOT NULL` |
| Con NPS | `nps_score IS NOT NULL` |
| Negativos | `calificacion <= 2` |

Cada tab muestra un badge con el conteo de registros.

### Columnas de la Tabla

| Columna | Descripcion |
|---------|-------------|
| Fecha | Fecha y hora del feedback |
| Tipo | Badge: Trabajador / Cliente / Plataforma |
| Respondio | Nombre del trabajador (via proceso) o del usuario admin |
| Proceso | Codigo del proceso disciplinario asociado |
| Calificacion | Estrellas visuales con codigo de color |
| NPS | Badge Promotor / Neutro / Detractor (togglable) |
| Sugerencia / Comentario | Preview de 60 caracteres con tooltip completo |
| Resp. adicionales | Icono check si tiene respuestas adicionales (togglable) |
| Contexto | Trigger que disparo el feedback (togglable) |

### Widget de Estadisticas

Visible en la parte superior del listado:

| Stat | Descripcion |
|------|-------------|
| Calificacion promedio | Media de todas las calificaciones con grafico de barras |
| Trabajadores | Total de feedbacks tipo `descargo_trabajador` |
| Clientes | Total de feedbacks tipo `descargo_registro` |
| NPS | Puntuacion calculada con conteo de promotores/detractores |
| Con comentario | Cantidad y porcentaje con sugerencia |

### Vista de Detalle

Cada feedback tiene una vista de detalle organizada en secciones:

1. **¿Quien respondio?** — Nombre, tipo de respondente, contexto (trigger), proceso y fecha
2. **Calificacion** — Estrellas (1-5) con NPS y categoria lado a lado
3. **Comentario o sugerencia** — Texto completo con soporte Markdown
4. **Respuestas adicionales** — Lista de preguntas/respuestas del trigger especifico (oculta si vacia)
5. **Metadatos tecnicos** — Usuario autenticado, IP, ID de diligencia (colapsada por defecto)

---

## Modelo Eloquent

```php
// app/Models/Feedback.php

// Tipos de feedback
const TIPO_DESCARGO_TRABAJADOR = 'descargo_trabajador';
const TIPO_DESCARGO_REGISTRO   = 'descargo_registro';
const TIPO_PLATAFORMA_GENERAL  = 'plataforma_general';

// Triggers
const TRIGGER_PRIMER_PROCESO  = 'primer_proceso';
const TRIGGER_POST_DILIGENCIA = 'post_diligencia';
const TRIGGER_PERIODICO       = 'periodico';
const TRIGGER_HITO            = 'hito';

// Relaciones
public function procesoDisciplinario(): BelongsTo  // proceso asociado
public function diligenciaDescargo(): BelongsTo    // diligencia del trabajador
public function user(): BelongsTo                  // usuario admin (null para trabajadores)

// Accessors
$feedback->calificacion_text  // "Muy malo" / "Malo" / "Regular" / "Bueno" / "Excelente"
$feedback->tipo_text          // Nombre legible del tipo
$feedback->trigger_text       // Nombre legible del trigger
$feedback->getNpsCategoria()  // "Promotor" / "Neutro" / "Detractor" / null
```

---

## Archivos Relacionados

```
app/Models/Feedback.php
app/Livewire/FormularioDescargos.php                     (feedback del trabajador)
app/Filament/Admin/Resources/FeedbackResource.php
app/Filament/Admin/Resources/FeedbackResource/
  Pages/ListFeedback.php
  Pages/ViewFeedback.php
  Widgets/FeedbackStatsWidget.php
app/Filament/Admin/Resources/ProcesoDisciplinarioResource/
  Pages/ListProcesoDisciplinarios.php                    (triggers automaticos)
resources/views/livewire/formulario-descargos.blade.php  (preguntas organicas de feedback del trabajador)
database/migrations/2026_02_17_151614_create_feedback_table.php
database/migrations/2026_03_09_154610_add_trigger_fields_to_feedbacks_table.php
```
