<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadEconomica extends Model
{
    protected $table = 'actividades_economicas';

    protected $fillable = [
        'codigo',
        'seccion',
        'nombre_seccion',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function getCodigoNombreAttribute(): string
    {
        return "{$this->codigo} - {$this->nombre}";
    }

    public function empresasPrincipales(): HasMany
    {
        return $this->hasMany(Empresa::class, 'actividad_economica_id');
    }

    public function empresasSecundarias(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_actividades_secundarias');
    }
}
