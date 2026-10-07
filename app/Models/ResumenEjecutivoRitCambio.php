<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache del "Resumen ejecutivo" de cambios del RIT (pedido de Andrés
 * Sarmiento, reunión 2026-10-05) - se cachea por hash del diff exacto, no
 * por RIT, porque dos trabajadores en la misma actualización pueden tener
 * un "antes" distinto (ver RitResumenEjecutivoService).
 */
class ResumenEjecutivoRitCambio extends Model
{
    protected $table = 'resumenes_ejecutivos_rit_cambios';

    protected $fillable = [
        'reglamento_interno_id',
        'hash_comparacion',
        'resumen',
    ];
}
