<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro append-only de un video didáctico REEMPLAZADO (ver migración de
 * creación para el contexto completo) - permite recuperarlo como evidencia
 * aunque ya no sea el video vigente del RIT.
 */
class HistoricoVideoDidacticoRit extends Model
{
    use ScopedToBufeteOrEmpresa;

    protected $table = 'historico_videos_didacticos_rit';

    protected $fillable = [
        'reglamento_interno_id',
        'empresa_id',
        'capitulos',
        'texto_rit_hash',
        'generado_en',
        'archivado_en',
    ];

    protected $casts = [
        'capitulos' => 'array',
        'generado_en' => 'datetime',
        'archivado_en' => 'datetime',
    ];

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
