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
        'categoria',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
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
     * Obtener el texto completo del artículo para mostrar en el selector
     */
    public function getTextoCompletoAttribute(): string
    {
        return "{$this->codigo} - {$this->titulo}";
    }
}
