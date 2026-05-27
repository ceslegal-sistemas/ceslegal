<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuditoriaRIT extends Model
{
    protected $table = 'auditorias_rit';

    protected $fillable = [
        'empresa_id',
        'reglamento_interno_id',
        'estado',
        'secciones',
        'resumen_general',
        'score',
        'mensaje_error',
        'fuente',
        'estado_mejora',
        'reglamento_mejorado_id',
        'texto_auditado',
        'progreso_mejora',
    ];

    protected $casts = [
        'secciones' => 'array',
        'score'     => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class, 'reglamento_interno_id');
    }

    public function reglamentoMejorado(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class, 'reglamento_mejorado_id');
    }

    public function gapReporte(): HasOne
    {
        return $this->hasOne(GapReporte::class, 'auditoria_rit_id');
    }

    public function getSeccionesCompletadasAttribute(): int
    {
        return collect($this->secciones ?? [])->count();
    }

    public function getColorScoreAttribute(): string
    {
        return match(true) {
            $this->score >= 80 => 'success',
            $this->score >= 50 => 'warning',
            default            => 'danger',
        };
    }

    public function estaEnProceso(): bool
    {
        return in_array($this->estado, ['pendiente', 'procesando']);
    }

    public function mejorando(): bool
    {
        return $this->estado_mejora === 'procesando';
    }

    public function mejoraCompletada(): bool
    {
        return $this->estado_mejora === 'completado';
    }

    public function mejoraFallo(): bool
    {
        return $this->estado_mejora === 'fallido';
    }
}
