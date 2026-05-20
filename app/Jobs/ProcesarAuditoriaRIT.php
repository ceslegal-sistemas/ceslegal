<?php

namespace App\Jobs;

use App\Jobs\GenerarRITMejoradoJob;
use App\Models\AuditoriaRIT;
use App\Services\AuditoriaRITService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;

class ProcesarAuditoriaRIT implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Segundos máximos por job.
     *  9 secciones × ~20s c/u con cascade rápido = ~180s real.
     *  280 deja margen bajo el límite CLI de 300s de shared hosting. */
    public int $timeout = 280;

    /** Sin reintentos automáticos: las secciones tienen su propio cascade de modelos */
    public int $tries = 1;

    public function __construct(
        public readonly AuditoriaRIT $auditoria,
        public readonly int $userId,
    ) {
        // Cola dedicada para IA — permite controlar concurrencia con --queue=gemini
        $this->onQueue('gemini');
    }

    /**
     * Throttle global: máximo 800 llamadas/min a Gemini entre todos los workers.
     * Si se supera, el job se libera de vuelta a la cola y se reintenta en 5 segundos.
     */
    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(AuditoriaRITService $service): void
    {
        $service->procesarAuditoria($this->auditoria);

        $auditoria = $this->auditoria->fresh();
        $score     = $auditoria?->score ?? 0;

        // Notificar al usuario cuando termina
        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Auditoría de RIT completada')
                ->body("La revisión finalizó con un score de {$score}/100. Revise los resultados.")
                ->success()
                ->sendToDatabase($user);
        }

        // Si hay hallazgos (score < 100), generar automáticamente el RIT mejorado
        if ($auditoria && $score < 100 && $auditoria->estado === 'completado') {
            $auditoria->update(['estado_mejora' => 'procesando']);
            GenerarRITMejoradoJob::dispatch($auditoria, $this->userId);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->auditoria->update([
            'estado'        => 'error',
            'mensaje_error' => $e->getMessage(),
        ]);

        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Error en auditoría de RIT')
                ->body('Ocurrió un error al procesar la auditoría. Intente nuevamente.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
