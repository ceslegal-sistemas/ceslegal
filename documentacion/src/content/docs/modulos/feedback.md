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

El feedback del trabajador se solicita automaticamente al completar todas las preguntas del formulario de descargos virtual.

**Campos del formulario (todos obligatorios):**
1. **Calificacion** — 1 a 5 estrellas (interactivo con hover)
2. **Comentario o sugerencia** — campo de texto libre obligatorio

El boton de envio permanece deshabilitado hasta que el trabajador seleccione una calificacion **y** escriba un comentario. El modal no tiene opcion de omitir.

```php
// app/Livewire/FormularioDescargos.php
public function enviarFeedback(): void
{
    if ($this->feedbackCalificacion < 1 || $this->feedbackCalificacion > 5) return;
    if (empty(trim($this->feedbackSugerencia))) return;

    Feedback::create([
        'calificacion'             => $this->feedbackCalificacion,
        'sugerencia'               => trim($this->feedbackSugerencia),
        'tipo'                     => 'descargo_trabajador',
        'proceso_disciplinario_id' => $this->diligencia->proceso_disciplinario_id,
        'diligencia_descargo_id'   => $this->diligencia->id,
        'ip_address'               => request()->ip(),
        'user_agent'               => request()->userAgent(),
    ]);
}
```

---

## Flujo del Cliente / Admin (Triggers)

El sistema detecta automaticamente el momento ideal para solicitar feedback al cliente o abogado. Se muestran como modales no descartables (sin boton de cerrar) en el listado de procesos.

### Trigger 1: Primer proceso (`primer_proceso`)

**Cuando:** La primera vez que el usuario tiene al menos un proceso en estado avanzado (no `apertura` ni `archivado`).
**Cooldown:** Una sola vez en la vida del usuario.
**Campos obligatorios:**
- Calificacion de la primera experiencia (1-5 estrellas)
- ¿El proceso inicial fue claro?
- ¿La plataforma cumplio las expectativas?
- ¿Que fue lo mas confuso?

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
resources/views/livewire/formulario-descargos.blade.php  (modal feedback trabajador)
database/migrations/2026_02_17_151614_create_feedback_table.php
database/migrations/2026_03_09_154610_add_trigger_fields_to_feedbacks_table.php
```
