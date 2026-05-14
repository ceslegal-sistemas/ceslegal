<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticuloLegal extends Model
{
    protected $table = 'articulos_legales';

    protected $fillable = [
        'empresa_id',
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

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Scope para artículos disponibles para una empresa:
     * universales (empresa_id null) + específicos de esa empresa.
     */
    public function scopeParaEmpresa($query, ?int $empresaId)
    {
        return $query->where(function ($q) use ($empresaId) {
            $q->whereNull('empresa_id');
            if ($empresaId) {
                $q->orWhere('empresa_id', $empresaId);
            }
        });
    }

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
