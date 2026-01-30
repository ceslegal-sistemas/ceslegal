<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubtipoGestion extends Model
{
    protected $table = 'subtipos_gestion';

    protected $fillable = [
        'tipo_gestion_id',
        'nombre',
        'active',
        'orden',
    ];

    public function tipoGestion(): BelongsTo
    {
        return $this->belongsTo(TipoGestion::class, 'tipo_gestion_id');
    }

    protected $casts = [
        'active' => 'boolean',
        'orden' => 'integer',
    ];

    public function informesJuridicos(): HasMany
    {
        return $this->hasMany(InformeJuridico::class, 'subtipo_id');
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
