<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro append-only de quién autorizó una versión del RIT (equivalente
 * funcional de firma) - ver migración de creación para el contexto completo.
 */
class AutorizacionReglamentoInterno extends Model
{
    protected $table = 'autorizaciones_reglamento_interno';

    protected $fillable = [
        'reglamento_interno_id',
        'empresa_id',
        'user_id',
        'autorizador_nombre',
        'autorizador_cargo',
        'foto_autorizador_path',
        'foto_autorizador_en',
        'disclaimer_datos_autorizador_en',
        'disclaimer_datos_autorizador_ip',
        'texto_rit_hash',
        'texto_rit_snapshot',
    ];

    protected $casts = [
        'foto_autorizador_en' => 'datetime',
        'disclaimer_datos_autorizador_en' => 'datetime',
    ];

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
