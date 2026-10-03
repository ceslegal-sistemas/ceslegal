<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia de cada vez que un trabajador declaró (manual o por fallos
 * repetidos del quiz) que no entendió el RIT - ver
 * SocializacionRit::marcarNoComprendio(). A partir de la 2da vez, dispara
 * el bloqueo + alerta a RRHH.
 */
class RechazoComprensionRit extends Model
{
    protected $table = 'rechazos_comprension_rit';

    protected $fillable = [
        'trabajador_id',
        'reglamento_interno_id',
        'texto_rit_hash',
        'origen',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }
}
