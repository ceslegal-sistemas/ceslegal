<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trabajador extends Model
{
    protected $table = 'trabajadores';

    protected $fillable = [
        'empresa_id',
        'tipo_documento',
        'numero_documento',
        'genero',
        'nombres',
        'apellidos',
        'departamento_nacimiento',
        'ciudad_nacimiento',
        'cargo',
        'area',
        'fecha_ingreso',
        'email',
        'telefono',
        'direccion',
        'active',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'active' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function procesosDisciplinarios(): HasMany
    {
        return $this->hasMany(ProcesoDisciplinario::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
