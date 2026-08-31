<?php

namespace App\Jobs;

use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\RitActualizacionAutomaticaService;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evalúa UN RIT contra UN documento legal nuevo (Plan B de actualización
 * automática del RIT).
 *
 * Antes esto corría en línea dentro de DocumentoLegalObserver::updated(), es
 * decir dentro del ciclo de vida de ProcesarBibliotecaLegal (timeout 600s,
 * tries 1). Con pocas empresas cabía; medido con 9 RITs activos, 8 pasaban el
 * filtro de tema (89%) y cada uno implica una llamada grande a Gemini. A
 * escala de ~100 empresas serían ~89 llamadas encadenadas dentro de un mismo
 * job: timeout garantizado, sin reintento, y un fallo a mitad de camino dejaba
 * sin notificar a todas las empresas restantes.
 *
 * Un job por RIT hace el trabajo reanudable y aislado: si uno falla o agota
 * cuota, los demás siguen. La cola 'gemini' es la misma que ya usan los otros
 * trabajos que consumen la API compartida.
 */
class EvaluarActualizacionRitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /**
     * 1 intento: la evaluación llama a IA generativa y crea una sugerencia.
     * Reintentar tras un fallo parcial arriesga duplicar sugerencias sobre el
     * mismo bloque, y el respaldo (notificación genérica) ya cubre el caso de
     * que el motor no produzca nada.
     */
    public int $tries = 1;

    public function __construct(
        public ReglamentoInterno $rit,
        public DocumentoLegal $documento,
        /** @var array<int, string> nombres de los temas en común, para el mensaje genérico */
        public array $temasComunes,
    ) {
        $this->onQueue('gemini');
    }

    public function handle(
        RitActualizacionAutomaticaService $actualizacionRit,
        NotificacionService $notificacionService,
    ): void {
        $sugerencia = null;

        try {
            $cambio = $actualizacionRit->evaluarCambio($this->rit, $this->documento);
            if ($cambio !== null) {
                $sugerencia = $actualizacionRit->crearSugerencia($this->rit, $this->documento, $cambio);
            }
        } catch (\Throwable $e) {
            // No relanzar: el respaldo es la notificación genérica de abajo. Si
            // se propagara, el cliente se quedaría sin ningún aviso.
            Log::warning('EvaluarActualizacionRitJob: falló el motor de decisión, se mantiene la notificación genérica', [
                'rit_id'       => $this->rit->id,
                'documento_id' => $this->documento->id,
                'error'        => $e->getMessage(),
            ]);
        }

        if ($sugerencia) {
            $notificacionService->notificarSugerenciaActualizacionRit($sugerencia);

            return;
        }

        $this->notificarGenerico();
    }

    /**
     * Respaldo: no se encontró un cambio puntual, pero el documento sí toca
     * temas del RIT - el cliente debe enterarse igual.
     */
    private function notificarGenerico(): void
    {
        $usuarios = User::where('empresa_id', $this->rit->empresa_id)
            ->where('active', true)
            ->where('role', 'cliente')
            ->get();

        foreach ($usuarios as $user) {
            FilamentNotification::make()
                ->title('Nueva normativa disponible: ' . $this->documento->titulo)
                ->body('Aplica a su RIT por: ' . implode(', ', $this->temasComunes) . '. Recomendamos auditar su RIT para verificar el cumplimiento.')
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
