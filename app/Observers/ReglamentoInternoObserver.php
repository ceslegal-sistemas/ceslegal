<?php

namespace App\Observers;

use App\Models\ReglamentoInterno;
use App\Services\TemaClasificadorService;
use Illuminate\Support\Facades\Storage;

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

        // Hueco real reportado por el usuario: sanciones_extraidas y
        // conductas_sancionables se calculan UNA vez y se guardan con
        // saveQuietly() (a propósito, para no generar un loop con este mismo
        // observer) - pero nada los invalidaba cuando el texto del RIT
        // cambiaba después (por una mejora automática aprobada, el wizard, o
        // un re-upload). El resultado: el sistema seguía usando conductas
        // calculadas sobre una versión VIEJA del RIT indefinidamente, sin
        // volver a extraer nunca por su cuenta. Al limpiarlos aquí, el
        // próximo consumidor (generación de contrato, Mi Reglamento Interno,
        // etc.) dispara la re-extracción real automáticamente - sin que
        // nadie tenga que acordarse de darle a "Re-extraer sanciones" a
        // mano. organigrama también se limpia por el mismo motivo.
        if (!empty($rit->sanciones_extraidas) || !empty($rit->conductas_sancionables) || !empty($rit->organigrama)) {
            $rit->sanciones_extraidas = null;
            $rit->conductas_sancionables = null;
            $rit->organigrama = null;
            $rit->saveQuietly();
        }

        // Segundo hueco real, mismo síntoma que el de arriba pero con el PDF
        // descargable: ruta_pdf se genera UNA sola vez, al crear el RIT
        // mejorado (RITMejoradoService::generar()) - ningún otro punto del
        // código lo vuelve a escribir. Cuando después se aprueba una
        // sugerencia quirúrgica (RitActualizacionAutomaticaService::
        // aplicarSugerencia()), esta SÍ actualiza texto_completo en el mismo
        // registro, pero el PDF cacheado en disco queda desactualizado para
        // siempre - RitDescarga::responder() SIEMPRE prefiere ese archivo
        // cacheado sobre generar uno nuevo, así que el cliente terminaba
        // descargando una versión más vieja que el propio texto_completo
        // que el sistema ya venía usando para todo lo demás (auditorías,
        // contratos). Al limpiarlo aquí, la próxima descarga cae al
        // fallback de RitDescarga (generar el PDF al vuelo desde el texto
        // actual) hasta que algo vuelva a generar un PDF permanente.
        if (!empty($rit->ruta_pdf)) {
            Storage::delete($rit->ruta_pdf);
            $rit->ruta_pdf = null;
            $rit->saveQuietly();
        }

        $this->clasificador->asegurarTemas($rit);
    }
}
