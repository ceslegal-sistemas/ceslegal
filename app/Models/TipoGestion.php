<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoGestion extends Model
{
    protected $table = 'tipos_gestion';

    protected $fillable = [
        'nombre',
        'active',
        'orden',
    ];

    protected $casts = [
        'active' => 'boolean',
        'orden' => 'integer',
    ];

    public function informesJuridicos(): HasMany
    {
        return $this->hasMany(InformeJuridico::class, 'tipo_gestion_id');
    }

    public function subtipos(): HasMany
    {
        return $this->hasMany(SubtipoGestion::class, 'tipo_gestion_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
