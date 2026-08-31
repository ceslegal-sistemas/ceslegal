<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una sugerencia de cambio quirúrgico a un bloque del RIT, propuesta por
 * IA a partir de un DocumentoLegal nuevo/modificado de Biblioteca Legal
 * que comparte tema con el RIT afectado (ver TemaClasificadorService).
 * Un registro nuevo por sugerencia - nunca se sobrescribe, mismo criterio
 * ya aplicado en ModificacionContractual. Nada se aplica al RIT real
 * hasta que bufete/cliente la aprueba explícitamente.
 */
class SugerenciaActualizacionRit extends Model
{
    use ScopedToBufeteOrEmpresa;

    protected $table = 'sugerencias_actualizacion_rit';

    public const TIPOS_CAMBIO = [
        'modificar' => 'Modificar bloque existente',
        'agregar'   => 'Agregar bloque nuevo',
        'eliminar'  => 'Eliminar bloque existente',
    ];

    protected $fillable = [
        'empresa_id',
        'reglamento_interno_id',
        'documento_legal_id',
        'bloque_indice',
        'tipo_cambio',
        'texto_anterior',
        'texto_propuesto',
        'justificacion_ia',
        'estado',
        'resuelto_por',
        'resuelto_en',
    ];

    protected $casts = [
        'bloque_indice' => 'integer',
        'resuelto_en'   => 'datetime',
    ];

    /**
     * ¿Este RIT ya tiene una propuesta SIN RESOLVER para este documento?
     *
     * Reprocesar un documento desde el panel ("Encolar") lo pasa a 'pendiente'
     * y luego a 'procesado', lo que esquiva la guarda de idempotencia del
     * observer (que solo mira si YA estaba procesado). Sin esta verificación,
     * cada reproceso volvía a evaluar todos los RITs y el cliente recibía
     * notificaciones y sugerencias repetidas del mismo documento.
     *
     * Solo bloquea si está 'pendiente': si el cliente ya la aprobó o rechazó,
     * un reproceso posterior sí puede volver a evaluar, porque el documento
     * pudo haberse re-subido con contenido distinto.
     */
    public static function yaPropuestaPendiente(int $reglamentoInternoId, int $documentoLegalId): bool
    {
        return static::withoutGlobalScopes()
            ->where('reglamento_interno_id', $reglamentoInternoId)
            ->where('documento_legal_id', $documentoLegalId)
            ->where('estado', 'pendiente')
            ->exists();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }

    public function documentoLegal(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class);
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }
}
