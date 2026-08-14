<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoLaboral extends Model
{
    protected $table = 'contratos_laborales';

    /** Tipos de contrato laboral reconocidos (CST Art. 45-46). */
    public const TIPOS = [
        'fijo'       => 'Término Fijo',
        'indefinido' => 'Término Indefinido',
        'obra_labor' => 'Por Obra o Labor',
        'ocasional'  => 'Ocasional / Accidental',
    ];

    protected $fillable = [
        'trabajador_id',
        'empresa_id',
        'tipo',
        'salario',
        'periodicidad_pago',
        'jornada',
        'funciones_cargo',
        'fecha_inicio',
        'fecha_fin',
        'descripcion_obra',
        'eps',
        'arl',
        'fondo_pension',
        'caja_compensacion',
        'clausulas_generadas',
        'articulos_cst_citados',
        'estado',
        'documento_path',
    ];

    protected $casts = [
        'salario'               => 'decimal:2',
        'fecha_inicio'          => 'date',
        'fecha_fin'             => 'date',
        'articulos_cst_citados' => 'array',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
