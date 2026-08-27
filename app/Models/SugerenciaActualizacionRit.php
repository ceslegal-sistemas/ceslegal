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
