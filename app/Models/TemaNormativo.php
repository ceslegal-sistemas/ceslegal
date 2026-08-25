<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TemaNormativo extends Model
{
    protected $table = 'temas_normativos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function reglamentosInternos(): BelongsToMany
    {
        return $this->belongsToMany(ReglamentoInterno::class, 'reglamento_interno_tema');
    }
}
