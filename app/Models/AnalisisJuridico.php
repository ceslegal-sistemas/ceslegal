<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisJuridico extends Model
{
    protected $fillable = [
        'proceso_id',
        'abogado_id',
        'fecha_analisis',
        'analisis_hechos',
        'analisis_pruebas',
        'analisis_normativo',
        'conclusion',
        'recomendacion',
        'tipo_sancion_recomendada',
        'fundamento_legal',
        'observaciones',
    ];

    protected $casts = [
        'fecha_analisis' => 'datetime',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }
}
