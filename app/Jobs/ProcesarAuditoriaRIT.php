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
     *  8 secciones × ~15s (flash normal) + segunda pasada de reintentos ~60s = ~180s nominal.
     *  600 permite cascade completo hasta gemini-2.5-pro + retry de secciones fallidas. */
    public int $timeout = 600;

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

        // Solo para RITs externos (subidos manualmente): generar versión mejorada automáticamente.
        // Los RITs generados por el sistema ya cumplen los estándares jurídicos del CST
        // y no requieren una versión mejorada (v+1) adicional.
        if ($auditoria && $score < 100 && $auditoria->estado === 'completado' && $auditoria->fuente === 'externo') {
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
