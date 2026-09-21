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
        'aceptado_en',
        'ip_aceptacion',
        'user_agent',
    ];

    protected $casts = [
        'aceptado_en' => 'datetime',
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
