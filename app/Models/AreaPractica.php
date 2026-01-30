<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaPractica extends Model
{
    protected $table = 'areas_practica';

    protected $fillable = [
        'nombre',
        'color',
        'active',
        'orden',
    ];

    protected $casts = [
        'active' => 'boolean',
        'orden' => 'integer',
    ];

    public function informesJuridicos(): HasMany
    {
        return $this->hasMany(InformeJuridico::class, 'area_practica_id');
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
