<?php

namespace App\Observers;

use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\RitActualizacionAutomaticaService;
use App\Services\TemaClasificadorService;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;

class DocumentoLegalObserver
{
    public function __construct(
        protected TemaClasificadorService $clasificador,
        protected RitActualizacionAutomaticaService $actualizacionRit,
        protected NotificacionService $notificacionService,
    ) {
    }

    /**
     * Cuando un documento legal pasa a estado 'procesado', clasifica sus
     * temas y notifica solo a los clientes cuyo RIT activo comparte al
     * menos un tema con el documento - reemplaza la notificación genérica
     * anterior ("vaya audite su RIT" a TODOS los clientes con RIT activo,
     * sin ningún filtro de relevancia real).
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

            // Plan B: antes de la notificación genérica, intentar el motor
            // de decisión quirúrgico - si encuentra un cambio real, se
            // propone y notifica específicamente en vez de solo "vaya
            // audite". Si falla (ej. cuota de IA agotada) o no encuentra
            // cambio, se mantiene la notificación genérica de siempre - no
            // debe quedar SIN notificación en ningún caso.
            $sugerenciaEspecifica = null;
            try {
                $cambio = $this->actualizacionRit->evaluarCambio($rit, $documento);
                if ($cambio !== null) {
                    $sugerenciaEspecifica = $this->actualizacionRit->crearSugerencia($rit, $documento, $cambio);
                }
            } catch (\Throwable $e) {
                Log::warning('DocumentoLegalObserver: falló el motor de decisión de actualización del RIT, se mantiene la notificación genérica', [
                    'rit_id'        => $rit->id,
                    'documento_id'  => $documento->id,
                    'error'         => $e->getMessage(),
                ]);
            }

            if ($sugerenciaEspecifica) {
                $this->notificacionService->notificarSugerenciaActualizacionRit($sugerenciaEspecifica);
                continue;
            }

            $usuarios = User::where('empresa_id', $rit->empresa_id)
                ->where('active', true)
                ->where('role', 'cliente')
                ->get();

            foreach ($usuarios as $user) {
                FilamentNotification::make()
                    ->title('Nueva normativa disponible: ' . $documento->titulo)
                    ->body('Aplica a su RIT por: ' . $temasComunes->implode(', ') . '. Recomendamos auditar su RIT para verificar el cumplimiento.')
                    ->icon('heroicon-o-scale')
                    ->iconColor('warning')
                    ->actions([
                        FilamentAction::make('auditar')
                            ->label('Auditar RIT')
                            ->url(url('/empresa/mi-reglamento-interno') . '?resaltar=auditar')
                            ->button(),
                    ])
                    ->sendToDatabase($user);
            }
        }
    }
}
