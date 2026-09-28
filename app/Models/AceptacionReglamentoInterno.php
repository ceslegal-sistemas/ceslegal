<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AceptacionReglamentoInterno extends Model
{
    protected $table = 'aceptaciones_reglamento_interno';

    protected $fillable = [
        'trabajador_id',
        'reglamento_interno_id',
        'texto_rit_snapshot',
        'texto_rit_hash',
        'aceptado_en',
        'ip_aceptacion',
        'user_agent',
        'foto_aceptacion_path',
        'quiz_resultado',
        'ruta_acta',
        'fecha_generacion_acta',
    ];

    protected $casts = [
        'aceptado_en' => 'datetime',
        'quiz_resultado' => 'array',
        'fecha_generacion_acta' => 'datetime',
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
