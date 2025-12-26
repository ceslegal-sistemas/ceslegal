<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Documento extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo',
        'formato',
        'generado_por',
        'version',
        'plantilla_usada',
        'variables_usadas',
        'fecha_generacion',
    ];

    protected $casts = [
        'version' => 'integer',
        'variables_usadas' => 'array',
        'fecha_generacion' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
