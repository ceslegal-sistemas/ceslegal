<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudContrato extends Model
{
    use SoftDeletes, ScopedToBufeteOrEmpresa;

    protected $table = 'solicitudes_contrato';

    /**
     * Propiedades PHP explícitas (NO columnas) usadas por
     * SolicitudContratoObserver para pasar datos entre updating()/updated()
     * sin que Eloquent las trate como atributos a persistir. Sin esta
     * declaración, $solicitud->_cambioEstado = [...] cae en el __set()
     * mágico de Eloquent (setAttribute()), que las incluye en el UPDATE SQL
     * y revienta con "Unknown column '_cambioEstado'" - pasaba con
     * CUALQUIER cambio de estado, no solo desde código nuevo.
     */
    public $_cambioEstado = null;
    public $_abogadoAsignado = null;

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
        'jornada',
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
