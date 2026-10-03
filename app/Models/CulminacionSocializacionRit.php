<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Declaración MANUAL del admin de que culminó la socialización del RIT con
 * todos sus trabajadores (pedido de Andrés Sarmiento, 2026-10-03) - evidencia
 * de la EMPRESA, distinta de PublicacionReglamentoInterno/
 * AceptacionReglamentoInterno (esas son evidencia de cada TRABAJADOR).
 */
class CulminacionSocializacionRit extends Model
{
    protected $table = 'culminaciones_socializacion_rit';

    protected $fillable = [
        'empresa_id',
        'reglamento_interno_id',
        'user_id',
        'texto_rit_hash',
        'foto_admin_path',
        'declarado_en',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'declarado_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
