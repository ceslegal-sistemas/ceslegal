<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia LIGERA de la Fase 1 (Publicación) - a diferencia de
 * AceptacionReglamentoInterno (Fase 2, evidencia jurídica fuerte con
 * quiz/foto/PDF), esta tabla solo registra que un trabajador confirmó
 * haber sido informado de la publicación del RIT. Ver
 * docs/superpowers/specs/2026-09-30-socializacion-rit-dos-fases-design.md.
 */
class PublicacionReglamentoInterno extends Model
{
    protected $table = 'publicaciones_reglamento_interno';

    protected $fillable = [
        'trabajador_id',
        'reglamento_interno_id',
        'texto_rit_hash',
        'confirmado_en',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'confirmado_en' => 'datetime',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }
}
