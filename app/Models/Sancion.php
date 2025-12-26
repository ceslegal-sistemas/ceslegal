<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sancion extends Model
{
    protected $table = 'sanciones';

    protected $fillable = [
        'proceso_id',
        'tipo_sancion',
        'dias_suspension',
        'fecha_inicio_suspension',
        'fecha_fin_suspension',
        'motivo_sancion',
        'fundamento_legal',
        'observaciones',
        'documento_generado',
        'ruta_documento',
        'fecha_notificacion_rrhh',
        'fecha_notificacion_trabajador',
        'notificado_por',
    ];

    protected $casts = [
        'dias_suspension' => 'integer',
        'fecha_inicio_suspension' => 'date',
        'fecha_fin_suspension' => 'date',
        'documento_generado' => 'boolean',
        'fecha_notificacion_rrhh' => 'datetime',
        'fecha_notificacion_trabajador' => 'datetime',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function impugnacion(): HasOne
    {
        return $this->hasOne(Impugnacion::class);
    }
}
