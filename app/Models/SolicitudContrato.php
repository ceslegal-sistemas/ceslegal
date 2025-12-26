<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudContrato extends Model
{
    use SoftDeletes;

    protected $table = 'solicitudes_contrato';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'abogado_id',
        'estado',
        'tipo_contrato',
        'fecha_solicitud',
        'trabajador_id',
        'trabajador_nombres',
        'trabajador_apellidos',
        'trabajador_documento_tipo',
        'trabajador_documento_numero',
        'trabajador_email',
        'trabajador_telefono',
        'trabajador_direccion',
        'cargo_contrato',
        'responsabilidades',
        'objeto_comercial',
        'manual_funciones',
        'ruta_orden_compra',
        'ruta_manual_funciones',
        'fecha_inicio_propuesta',
        'salario_propuesto',
        'fecha_analisis',
        'objeto_juridico_redactado',
        'observaciones_juridicas',
        'fecha_generacion_contrato',
        'ruta_contrato',
        'fecha_envio_rrhh',
        'fecha_cierre',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_inicio_propuesta' => 'date',
        'salario_propuesto' => 'decimal:2',
        'fecha_analisis' => 'datetime',
        'fecha_generacion_contrato' => 'datetime',
        'fecha_envio_rrhh' => 'datetime',
        'fecha_cierre' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class, 'proceso_id')
            ->where('proceso_tipo', 'contrato')
            ->orderBy('created_at', 'desc');
    }

    public function terminosLegales(): HasMany
    {
        return $this->hasMany(TerminoLegal::class, 'proceso_id')
            ->where('proceso_tipo', 'contrato');
    }
}
