<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
