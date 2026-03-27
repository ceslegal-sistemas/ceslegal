<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticuloLegal extends Model
{
    protected $table = 'articulos_legales';

    protected $fillable = [
        'codigo',
        'titulo',
        'descripcion',
        'texto_completo',
        'categoria',
        'fuente',
        'activo',
        'orden',
        'embedding',
    ];

    protected $casts = [
        'activo'    => 'boolean',
        'orden'     => 'integer',
        'embedding' => 'array',
    ];

    /**
     * Scope para obtener solo artículos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para ordenar por orden personalizado
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('codigo');
    }

    /**
     * Etiqueta corta para mostrar en selectores / dropdowns
     */
    public function getLabelAttribute(): string
    {
        return "{$this->codigo} - {$this->titulo}";
    }
}
