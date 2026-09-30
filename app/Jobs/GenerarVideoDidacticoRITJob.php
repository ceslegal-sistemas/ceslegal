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
     * El video completo encadena hasta 4 llamadas a Gemini (1 clip inicial +
     * hasta 2 "extend" de contenido + 1 despedida final - bajado de 8+1 a
     * 3+1 segmentos el 2026-09-30 tras un error real de producción: Gemini
     * tiene un límite DURO de 30s totales por video extendido, y con 8+1
     * llamadas se sobrepasaba ese límite siempre, ver
     * RitVideoDidacticoService::MAX_SEGMENTOS) - cada llamada puede tardar
     * hasta 300s (ver RitVideoDidacticoService::llamarGemini() - 180s no
     * alcanzó en producción, confirmado 2026-09-29 con cURL error 28), así
     * que el timeout debe cubrir el peor caso de las 4 en serie con margen.
     * IMPORTANTE: el wrapper `--timeout` del `queue:work` en producción
     * (Hostinger) debe cubrir este mismo valor con margen también - un
     * timeout externo más corto mata el proceso antes de que Laravel llegue
     * a marcarlo como fallido. También DB_QUEUE_RETRY_AFTER (.env) debe ser
     * mayor a este valor - si no, la cola puede re-tomar el mismo job dos
     * veces mientras el primero sigue corriendo (mismo bug ya resuelto una
     * vez para la Auditoría de RIT, ver auditoria-rit-cola-retry-after).
     */
    public int $timeout = 1500;

    /** Sin reintentos automáticos: cada intento fallido consume tokens de video igual que uno exitoso. */
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
