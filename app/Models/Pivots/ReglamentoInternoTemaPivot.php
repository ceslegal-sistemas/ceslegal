<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot custom para reglamento_interno_tema - necesario SOLO para que
 * 'opciones' (columna JSON de las preguntas de selección múltiple, ver
 * migración 2026_10_03_140000) se decodifique a array automáticamente.
 * BelongsToMany no tiene un withPivotCasts() en esta versión de Laravel
 * (withCasts() en la relación aplica al modelo relacionado, NO al pivot) -
 * la única forma real de castear columnas del pivot es con un Pivot propio.
 */
class ReglamentoInternoTemaPivot extends Pivot
{
    protected $casts = [
        'respuesta_correcta' => 'boolean',
        'opciones' => 'array',
    ];
}
