<?php

namespace App\Observers;

use App\Models\ReglamentoInterno;
use App\Services\TemaClasificadorService;

class ReglamentoInternoObserver
{
    public function __construct(
        protected TemaClasificadorService $clasificador
    ) {
    }

    /**
     * Primer Observer sobre ReglamentoInterno en este proyecto (confirmado
     * sin colisión con ninguno existente). Se usa `saved` (cubre alta y
     * edición con un solo hook) - NO `saving`: en `saving`, para un RIT
     * NUEVO, `$rit->id` todavía no existe (el INSERT no ha corrido), y
     * `asegurarTemas()` necesita un id real para `sync()` en la tabla
     * pivote (`reglamento_interno_id` es NOT NULL con FK) - guardar ahí
     * habría reventado con un error de FK en CADA creación de RIT con
     * texto (bug real encontrado en la revisión de este plan). En
     * `saved`, el registro ya existe con id real (tanto en alta como en
     * edición), y `isDirty()`/`wasChanged()` todavía reflejan el cambio
     * porque `syncOriginal()` corre después de disparar este evento.
     */
    public function saved(ReglamentoInterno $rit): void
    {
        if (!$rit->isDirty('texto_completo') || empty($rit->texto_completo)) {
            return;
        }

        $this->clasificador->asegurarTemas($rit);
    }
}
