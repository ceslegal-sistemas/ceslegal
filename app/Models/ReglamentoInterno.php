<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglamentoInterno extends Model
{
    protected $table = 'reglamentos_internos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'texto_completo',
        'activo',
        'respuestas_cuestionario',
        'fuente',
    ];

    protected $casts = [
        'activo'                 => 'boolean',
        'respuestas_cuestionario' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
