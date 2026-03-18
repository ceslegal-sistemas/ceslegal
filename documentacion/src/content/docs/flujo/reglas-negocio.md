---
title: Reglas de Negocio
description: Validaciones y reglas de negocio del proceso disciplinario
---

## Reglas Generales

### 1. Códigos de Proceso

| Regla          | Descripción                             |
| -------------- | --------------------------------------- |
| **Formato**    | `PD-YYYY-XXXX` (ej: PD-2026-0001)       |
| **Unicidad**   | Único por empresa                       |
| **Generación** | Automática al crear el proceso          |
| **Inmutable**  | No se puede modificar después de creado |

```php
// Generación automática de código
$ultimoCodigo = ProcesoDisciplinario::withTrashed()
    ->where('empresa_id', $empresa->id)
    ->whereYear('created_at', now()->year)
    ->max('codigo');

$siguiente = $ultimoCodigo ? intval(substr($ultimoCodigo, -4)) + 1 : 1;
$codigo = 'PD-' . now()->year . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
```

### 2. Plazos Legales

| Plazo                   | Días Hábiles   | Base Legal         |
| ----------------------- | -------------- | ------------------ |
| Citación a descargos    | Mínimo 5 días  | Art. 115 CST       |
| Vigencia del token      | 6 días         | Política interna   |
| Decisión post-descargos | Máximo 10 días | Reglamento interno |
| Notificación de sanción | 3 días         | Reglamento interno |

:::note[Días Hábiles]
El sistema calcula plazos excluyendo:

- Sábados y domingos (Si la empresa labora los Sábados contará como día hábil)
- Festivos de Colombia (cargados en `dias_no_habiles`)
  :::

## Reglas por Estado

### Estado: Apertura

**Validaciones de entrada:**

```php
// Al crear proceso
$rules = [
    'empresa_id' => 'required|exists:empresas,id',
    'trabajador_id' => 'required|exists:trabajadores,id',
    'hechos' => 'required|string|min:20',
    'articulos_incumplidos' => 'required|array|min:1',
    'fecha_apertura' => 'required|date|before_or_equal:today',
];

```

**Validaciones de salida (para avanzar):**

- Debe tener al menos una diligencia programada
- La fecha de descargos debe ser al menos 5 días hábiles después

### Estado: Descargos Pendientes

**Validaciones de entrada:**

```php
// Al programar descargos
$rules = [
    'fecha' => 'required|date|after:today',
    'hora' => 'required|date_format:H:i',
    'modalidad' => 'required|in:virtual',
    'enlace_virtual' => 'required',
];
```

**Reglas adicionales:**

- La citación debe ser enviada al menos 5 días hábiles antes
- El token de acceso expira 6 días después de generado
- Solo se puede tener una diligencia activa por proceso

### Estado: Descargos Realizados/No Realizados

**Transición automática:**

```php
// Cuando el trabajador completa el formulario
if ($diligencia->preguntas()->whereHas('respuesta')->count() > 0) {
    $proceso->estado = 'descargos_realizados';
} else {
    $proceso->estado = 'descargos_no_realizados';
}
```

**Reglas:**

- Se genera acta automáticamente al completar
- Máximo 30 preguntas por diligencia
- Timer de 45 minutos para completar

### Estado: Sanción Emitida

**Validaciones:**

```php
$rules = [
    'sancion_laboral_id' => 'required|exists:sanciones_laborales,id',
    'descripcion' => 'required|string|min:100',
    'fundamento_legal' => 'required|string',
    'fecha_notificacion' => 'required|date',
    'dias_suspension' => 'required_if:tipo,suspension|integer|min:1|max:8',
];
```

**Reglas:**

- La suspensión no puede exceder 8 día por primera vez (Art. 112 CST)
- Si hay reincidencia no puede exceder 2 meses (Art. 112 CST)
- Debe incluir fundamento legal
- Debe notificarse por escrito al trabajador

### Estado: Impugnación

**Validaciones:**

```php
$rules = [
    'tipo_recurso' => 'required|in:reposicion,apelacion',
    'fecha_presentacion' => 'required|date',
    'argumentos' => 'required|string|min:100',
];
```

**Reglas:**

- El recurso debe presentarse dentro del plazo establecido
- Debe resolverse en los términos del reglamento interno

## Reglas de la Diligencia de Descargos

### Generación de Preguntas

| Regla               | Valor                         |
| ------------------- | ----------------------------- |
| Preguntas iniciales | 13                            |
| Máximo de preguntas | 30                            |
| Preguntas dinámicas | Basadas en respuestas         |
| Marcación IA        | Obligatoria para trazabilidad |

```php
// Validar límite de preguntas
if ($diligencia->preguntas()->count() >= 30) {
    throw new Exception('Se alcanzó el límite máximo de 30 preguntas');
}
```

### Formulario Público

| Regla               | Valor                         |
| ------------------- | ----------------------------- |
| Tiempo máximo       | 45 minutos                    |
| Vigencia del token  | 6 días                        |
| Intentos permitidos | 1 (no se puede reiniciar)     |
| Archivos adjuntos   | Máximo 5, hasta 10MB cada uno |

```php
// Validar token
public function validarToken(string $token): ?DiligenciaDescargo
{
    $diligencia = DiligenciaDescargo::where('token_acceso', $token)
        ->where('token_expira_at', '>', now())
        ->where('estado', '!=', 'completada')
        ->first();

    if (!$diligencia) {
        throw new TokenExpiredException('El enlace ha expirado o ya fue utilizado');
    }

    return $diligencia;
}
```

## Reglas de Documentos

### Citación

| Regla                | Descripción                                 |
| -------------------- | ------------------------------------------- |
| Formatos             | PDF y Word                                  |
| Almacenamiento       | `storage/app/public/documentos/citaciones/` |

### Acta de Descargos

| Regla      | Descripción                           |
| ---------- | ------------------------------------- |
| Generación | Automática al completar descargos     |
| Contenido  | Todas las preguntas con respuestas    |
| Marcación  | Indica cuáles fueron generadas por IA |

### Documento de Sanción

| Regla        | Descripción                             |
| ------------ | --------------------------------------- |
| Requisito    | Solo en estado `sancion_emitida`        |
| Contenido    | Sanción, fundamento, fechas de vigencia |
| Notificación | Debe incluir vías de impugnación        |

## Reglas de Seguridad

### Multi-tenancy

```php
// Filtro automático por empresa
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (auth()->user()->hasRole('cliente')) {
        return $query->where('empresa_id', auth()->user()->empresa_id);
    }

    if (auth()->user()->hasRole('abogado')) {
        return $query->where('abogado_id', auth()->user()->id);
    }

    return $query;
}
```

### Permisos por Rol

| Acción                 | Super Admin | Abogado | Cliente |
| ---------------------- | ----------- | ------- | ------- |
| Crear proceso          | ✅          | ✅      | ✅      |
| Ver todos los procesos | ✅          | ❌      | ❌      |
| Ver procesos propios   | ✅          | ✅      | ✅      |
| Editar proceso         | ✅          | ✅\*    | ❌      |
| Eliminar proceso       | ✅          | ❌      | ❌      |
| Generar documentos     | ✅          | ✅      | ✅      |
| Emitir sanción         | ✅          | ✅      | ✅      |

\*Solo procesos asignados

## Reglas de Auditoría

### Timeline Obligatorio

Eventos que deben registrarse:

- Creación del proceso
- Cambios de estado
- Asignación/reasignación de abogado
- Programación de descargos
- Generación de preguntas IA (manual o automático)
- Completación de descargos
- Emisión de sanción
- Impugnaciones
- Cierre del proceso

### Trazabilidad de IA

Cada llamada a la IA debe registrar:

```php
TrazabilidadIADescargo::create([
    'diligencia_descargo_id' => $diligencia->id,
    'tipo_operacion' => 'generacion_preguntas', // o 'analisis_sancion'
    'prompt_enviado' => $prompt,
    'respuesta_ia' => $response,
    'modelo_utilizado' => 'gemini-2.5-flash',
    'tokens_utilizados' => $tokenCount,
    'tiempo_respuesta_ms' => $responseTime,
    'exitoso' => true,
]);
```

## Excepciones y Casos Especiales

### Trabajador Eliminado

Si un trabajador es eliminado (soft delete), sus procesos:

- Permanecen visibles
- No pueden crear nuevos procesos para ese trabajador
- El proceso en curso puede continuar

### Proceso Archivado

Un proceso puede archivarse si:

- No hay mérito para sanción
- El trabajador renunció durante el proceso
- Prescribió el término para sancionar
- Otros motivos justificados (debe documentarse)

### Reprogramación de Descargos

Condiciones:

- Solo en estado `descargos_pendientes`
- Debe notificarse nuevamente al trabajador
- Se genera nuevo token de acceso
- El token anterior queda invalidado

## Próximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Módulo principal
- [Diligencias de Descargos](/modulos/diligencias-descargos/) - Gestión de descargos
