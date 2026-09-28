<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TerminacionContrato extends Model
{
    use SoftDeletes, ScopedToBufeteOrEmpresa;

    protected $table = 'terminaciones_contrato';

    public const TIPOS = [
        'con_justa_causa' => 'Con Justa Causa',
        'sin_justa_causa' => 'Sin Justa Causa',
    ];

    protected $fillable = [
        'solicitud_contrato_id',
        'empresa_id',
        'abogado_id',
        'proceso_disciplinario_id',
        'tipo',
        'motivo',
        'fecha_terminacion',
        'salario_base_calculo',
        'dias_indemnizacion',
        'monto_indemnizacion',
        'detalle_calculo',
        'texto_documento_redactado',
        'ruta_documento',
        'fecha_generacion_documento',
    ];

    protected $casts = [
        'fecha_terminacion' => 'date',
        'salario_base_calculo' => 'decimal:2',
        'dias_indemnizacion' => 'integer',
        'monto_indemnizacion' => 'decimal:2',
        'fecha_generacion_documento' => 'datetime',
    ];

    public function solicitudContrato(): BelongsTo
    {
        return $this->belongsTo(SolicitudContrato::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    public function procesoDisciplinario(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class);
    }

    // Mismo patrón de ModificacionContractual: empresa_id SIEMPRE se deriva
    // del contrato real, nunca del valor asignado en masa - evita que
    // ScopedToBufeteOrEmpresa filtre mal si algún día se desincroniza.
    protected static function booted(): void
    {
        static::creating(function (self $terminacion) {
            if ($terminacion->solicitud_contrato_id) {
                $terminacion->empresa_id = SolicitudContrato::withoutGlobalScopes()
                    ->find($terminacion->solicitud_contrato_id)
                    ?->empresa_id ?? $terminacion->empresa_id;
            }
        });
    }
}
