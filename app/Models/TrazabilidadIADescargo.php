<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrazabilidadIADescargo extends Model
{
    protected $table = 'trazabilidad_ia_descargos';

    protected $fillable = [
        'diligencia_descargo_id',
        'prompt_enviado',
        'respuesta_recibida',
        'tipo',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function diligenciaDescargo(): BelongsTo
    {
        return $this->belongsTo(DiligenciaDescargo::class, 'diligencia_descargo_id');
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
