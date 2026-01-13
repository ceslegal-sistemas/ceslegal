<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SancionLaboral extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_falta',
        'descripcion',
        'nombre_claro',
        'tipo_sancion',
        'dias_suspension_min',
        'dias_suspension_max',
        'activa',
        'orden',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'dias_suspension_min' => 'integer',
        'dias_suspension_max' => 'integer',
        'orden' => 'integer',
    ];

    /**
     * Scope para obtener solo sanciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Scope para ordenar por campo orden
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('id');
    }

    /**
     * Scope para filtrar por tipo de falta
     */
    public function scopeTipoFalta($query, $tipo)
    {
        return $query->where('tipo_falta', $tipo);
    }

    /**
     * Obtener el nombre con descripción para el selector
     */
    public function getNombreConDescripcionAttribute(): string
    {
        $tipo = $this->tipo_falta === 'leve' ? '🟢' : '🔴';
        return "{$tipo} {$this->nombre_claro}";
    }

    /**
     * Obtener el texto completo formateado
     */
    public function getTextoCompletoAttribute(): string
    {
        return "{$this->nombre_claro}: {$this->descripcion}";
    }

    /**
     * Obtener el rango de días de suspensión formateado
     */
    public function getDiasSuspensionTextoAttribute(): ?string
    {
        if ($this->tipo_sancion !== 'suspension') {
            return null;
        }

        if ($this->dias_suspension_min && $this->dias_suspension_max) {
            if ($this->dias_suspension_min === $this->dias_suspension_max) {
                return "{$this->dias_suspension_min} día" . ($this->dias_suspension_min > 1 ? 's' : '');
            }
            return "{$this->dias_suspension_min} a {$this->dias_suspension_max} días";
        }

        if ($this->dias_suspension_max) {
            return "hasta {$this->dias_suspension_max} día" . ($this->dias_suspension_max > 1 ? 's' : '');
        }

        return null;
    }

    /**
     * Obtener el tipo de sanción formateado
     */
    public function getTipoSancionTextoAttribute(): string
    {
        $texto = match ($this->tipo_sancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión',
            'terminacion' => 'Terminación del Contrato',
            default => ucfirst($this->tipo_sancion),
        };

        if ($this->tipo_sancion === 'suspension' && $this->dias_suspension_texto) {
            $texto .= " ({$this->dias_suspension_texto})";
        }

        return $texto;
    }
}
