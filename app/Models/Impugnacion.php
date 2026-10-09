<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Impugnacion extends Model
{
    protected $table = 'impugnaciones';

    protected $fillable = [
        'proceso_id',
        'sancion_id',
        'fecha_impugnacion',
        'medio_recepcion',
        'registrada_por',
        'ip_registro',
        'user_agent',
        'otp_verificado_en',
        'motivos_impugnacion',
        'pruebas_adicionales',
        'fecha_analisis_impugnacion',
        'abogado_analisis_id',
        'analisis_impugnacion',
        'decision_final',
        'nueva_sancion_tipo',
        'fundamento_decision',
        'fecha_decision',
        'documento_generado',
        'ruta_documento',
    ];

    protected $casts = [
        'fecha_impugnacion' => 'datetime',
        'fecha_analisis_impugnacion' => 'datetime',
        'fecha_decision' => 'datetime',
        'otp_verificado_en' => 'datetime',
        'documento_generado' => 'boolean',
        'pruebas_adicionales' => 'array',
    ];

    /** Número de radicado que ve el trabajador en su constancia (IMP-00012). */
    public function radicado(): string
    {
        return 'IMP-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function presentadaPorElTrabajador(): bool
    {
        return $this->registrada_por === 'trabajador';
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function sancion(): BelongsTo
    {
        return $this->belongsTo(Sancion::class);
    }

    public function abogadoAnalisis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_analisis_id');
    }
}
