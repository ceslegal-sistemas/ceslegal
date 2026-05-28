<?php

namespace App\Jobs;

use App\Models\AuditoriaRIT;
use App\Services\RITMejoradoService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class GenerarRITMejoradoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Mejora capítulo por capítulo puede tardar hasta 480s. */
    public int $timeout = 600;

    /** Sin reintentos automáticos: el cascade de modelos maneja la redundancia */
    public int $tries = 1;

    public function __construct(
        public readonly AuditoriaRIT $auditoria,
        public readonly int          $userId,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(RITMejoradoService $service): void
    {
        // Recargar auditoría para tener el estado más reciente
        $auditoria = $this->auditoria->fresh();

        if (!$auditoria || $auditoria->estado !== 'completado') {
            return;
        }

        $ritMejorado = $service->generar(
            $auditoria,
            function (int $cap, int $total, string $titulo) use ($auditoria): void {
                $auditoria->update(['progreso_mejora' => "Capítulo {$cap}/{$total}: {$titulo}"]);
            }
        );

        $auditoria->update(['progreso_mejora' => null]);

        // Notificar al usuario
        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('RIT Mejorado generado')
                ->body("Se generó la versión {$ritMejorado->version} del RIT con las correcciones de la auditoría. Puede descargarlo desde la página de auditoría.")
                ->success()
                ->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('GenerarRITMejoradoJob: falló', [
            'auditoria_id' => $this->auditoria->id,
            'error'        => $e->getMessage(),
        ]);

        $this->auditoria->update([
            'estado_mejora' => 'fallido',
            'mensaje_error' => $e->getMessage(),
        ]);

        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Error al generar RIT Mejorado')
                ->body('No se pudo generar la versión mejorada del RIT. Revise los logs o intente nuevamente.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
