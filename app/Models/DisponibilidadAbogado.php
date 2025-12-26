<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisponibilidadAbogado extends Model
{
    protected $table = 'disponibilidad_abogados';

    protected $fillable = [
        'abogado_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'tipo',
        'disponible',
        'proceso_id',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'disponible' => 'boolean',
    ];

    // Relaciones
    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    // Scopes
    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true)->whereNull('proceso_id');
    }

    public function scopeFuturas($query)
    {
        return $query->where('fecha', '>=', now()->toDateString());
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where(function ($q) use ($tipo) {
            $q->where('tipo', $tipo)->orWhere('tipo', 'ambos');
        });
    }

    // Métodos
    public function marcarOcupado($procesoId)
    {
        $this->update(['disponible' => false, 'proceso_id' => $procesoId]);
    }

    public function liberar()
    {
        $this->update(['disponible' => true, 'proceso_id' => null]);
    }
}
