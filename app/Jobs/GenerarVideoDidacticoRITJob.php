<?php

namespace App\Jobs;

use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Services\RitVideoDidacticoService;
use Filament\Notifications\Actions\Action as NotifAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarVideoDidacticoRITJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * REDISEÑO 2026-09-30: el video ya no encadena "extend" (Gemini tiene un
     * límite duro de ~30-40s totales por video extendido, error real de
     * producción) - genera UN CAPÍTULO INDEPENDIENTE por llamada. Eso
     * significa que el número de llamadas a Gemini no es fijo.
     *
     * REDISEÑO 2026-10-14 (guion previo + profundidad, RitVideoDidacticoService):
     * ya no es 1 capítulo por tema (tope implícito de 27, la taxonomía
     * completa) - ahora un tema de importancia alta puede recibir varios
     * clips, con un tope explícito de hasta
     * config('services.ia.gemini.video_max_clips') (60 por defecto) para TODO
     * el RIT. Peor caso teórico: 1 llamada de guion (hasta 2 intentos × 240s)
     * + 60 clips de video × 300s = 480 + 18000 = 18480s. Timeout
     * dimensionado para ese peor caso con margen.
     * IMPORTANTE: el wrapper `--timeout` del `queue:work` en producción
     * (Hostinger) debe cubrir este mismo valor con margen también - un
     * timeout externo más corto mata el proceso antes de que Laravel llegue
     * a marcarlo como fallido. También DB_QUEUE_RETRY_AFTER (.env) debe ser
     * mayor a este valor - si no, la cola puede re-tomar el mismo job dos
     * veces mientras el primero sigue corriendo (mismo bug ya resuelto una
     * vez para la Auditoría de RIT, ver auditoria-rit-cola-retry-after).
     * Si baja services.ia.gemini.video_max_clips en .env, este timeout puede
     * bajarse proporcionalmente.
     */
    public int $timeout = 19000;

    /**
     * Sin reintentos AUTOMÁTICOS de Laravel (cada intento fallido consumía
     * tokens de video igual que uno exitoso con el diseño anterior). Desde
     * el rediseño 2026-10-14, RitVideoDidacticoService::generar() es
     * REANUDABLE (cada clip ya generado queda guardado en disco y no se
     * vuelve a pagar) - un reintento MANUAL del usuario desde "Mi Reglamento
     * Interno" (que despacha un job nuevo) retoma donde quedó, no desde
     * cero. tries sigue en 1 para no reintentar automáticamente sin que el
     * usuario lo sepa.
     */
    public int $tries = 1;

    /** @param array $cambios Diff de RitDiffService (opcional) - ver RitVideoDidacticoService::generar(). */
    public function __construct(
        public readonly ReglamentoInterno $rit,
        public readonly int               $userId,
        public readonly array             $cambios = [],
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(RitVideoDidacticoService $service): void
    {
        $rit = $this->rit->fresh();
        if (!$rit) return;

        $service->generar($rit, $this->cambios);

        Log::info('GenerarVideoDidacticoRITJob: completado', [
            'rit_id' => $rit->id,
            'empresa_id' => $rit->empresa_id,
        ]);

        $user = User::find($this->userId);
        if (!$user) return;

        Notification::make()
            ->title('¡Video didáctico del Reglamento listo!')
            ->body('Ya puede verlo y descargarlo desde "Mi Reglamento Interno".')
            ->success()
            ->actions([
                NotifAction::make('ver')
                    ->label('Ver Reglamento')
                    ->url(\App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(
                        panel: $user->role === 'cliente' ? 'empresa' : 'admin',
                    ))
                    ->button(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerarVideoDidacticoRITJob: falló', [
            'rit_id' => $this->rit->id,
            'error'  => $e->getMessage(),
        ]);

        $rit = $this->rit->fresh();
        if ($rit) {
            $rit->update([
                'video_didactico_estado' => 'error',
                'video_didactico_error'  => $e->getMessage(),
            ]);
        }

        $user = User::find($this->userId);
        if (!$user) return;

        Notification::make()
            ->title('Error al generar el video didáctico')
            ->body('No se pudo generar el video del Reglamento. Puede reintentarlo desde "Mi Reglamento Interno".')
            ->danger()
            ->actions([
                NotifAction::make('reintentar')
                    ->label('Ir a reintentar')
                    ->url(\App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(
                        panel: $user->role === 'cliente' ? 'empresa' : 'admin',
                    ))
                    ->button(),
            ])
            ->sendToDatabase($user);
    }
}
