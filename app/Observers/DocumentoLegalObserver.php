<?php

namespace App\Observers;

use App\Jobs\EvaluarActualizacionRitJob;
use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Services\TemaClasificadorService;

class DocumentoLegalObserver
{
    public function __construct(
        protected TemaClasificadorService $clasificador,
    ) {
    }

    /**
     * Cuando un documento legal pasa a estado 'procesado', clasifica sus
     * temas y despacha un job por cada RIT activo que comparta al menos un
     * tema con el documento - reemplaza la notificación genérica anterior
     * ("vaya audite su RIT" a TODOS los clientes con RIT activo, sin ningún
     * filtro de relevancia real).
     *
     * Este método debe mantenerse barato: corre dentro del ciclo de vida de
     * ProcesarBibliotecaLegal. Todo lo que llame a IA generativa va en el job.
     */
    public function updated(DocumentoLegal $documento): void
    {
        if (!$documento->wasChanged('estado')
            || $documento->estado !== 'procesado'
            || !$documento->activo) {
            return;
        }

        // Idempotencia: ignorar si ya estaba procesado antes
        if ($documento->getOriginal('estado') === 'procesado') {
            return;
        }

        $this->clasificador->clasificarDocumento($documento);
        $documento->refresh();

        $temaIds = $documento->temasNormativos()->pluck('temas_normativos.id');
        if ($temaIds->isEmpty()) {
            return;
        }

        $ritsAfectados = ReglamentoInterno::where('activo', true)->get();

        // Defensivo: un RIT activo que nunca haya sido clasificado (ej.
        // creado antes de este despliegue, backfill pendiente) se
        // clasifica aquí mismo antes de comparar.
        foreach ($ritsAfectados as $rit) {
            if (empty($rit->temas_texto_hash)) {
                $this->clasificador->asegurarTemas($rit);
                $rit->refresh();
            }
        }

        foreach ($ritsAfectados as $rit) {
            $temasComunes = $rit->temasNormativos()
                ->whereIn('temas_normativos.id', $temaIds)
                ->pluck('nombre');

            if ($temasComunes->isEmpty()) {
                continue;
            }

            // Ya hay una propuesta sin resolver para este RIT y este documento:
            // no volver a evaluar. Reprocesar un documento desde el panel lo
            // pasa a 'pendiente' y luego a 'procesado', esquivando la guarda de
            // idempotencia de arriba - sin esto, cada reproceso repetía la
            // notificación y la sugerencia al mismo cliente.
            if (SugerenciaActualizacionRit::yaPropuestaPendiente($rit->id, $documento->id)) {
                continue;
            }

            // Plan B: la evaluación con IA (y su notificación, específica o
            // genérica) se delega a un job POR RIT. Antes corría aquí en
            // línea, dentro del ciclo de vida de ProcesarBibliotecaLegal
            // (timeout 600s, tries 1): medido con 9 RITs activos, 8 pasaban
            // este filtro y cada uno implica una llamada grande a Gemini, así
            // que a escala de ~100 empresas serían ~89 llamadas encadenadas en
            // un mismo job - timeout garantizado y sin reintento, dejando sin
            // notificar a todas las empresas que quedaran detrás del fallo.
            EvaluarActualizacionRitJob::dispatch($rit, $documento, $temasComunes->all());
        }
    }
}
