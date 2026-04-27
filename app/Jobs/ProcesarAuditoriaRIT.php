<?php

namespace App\Jobs;

use App\Models\AuditoriaRIT;
use App\Services\AuditoriaRITService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;

class ProcesarAuditoriaRIT implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 360;
    public int $tries   = 1;

    public function __construct(
        public readonly AuditoriaRIT $auditoria,
        public readonly int $userId,
    ) {}

    public function handle(AuditoriaRITService $service): void
    {
        $service->procesarAuditoria($this->auditoria);

        // Notificar al usuario cuando termina
        $user = \App\Models\User::find($this->userId);
        if ($user) {
            $score = $this->auditoria->fresh()->score;
            Notification::make()
                ->title('Auditoría de RIT completada')
                ->body("La revisión finalizó con un score de {$score}/100. Revise los resultados.")
                ->success()
                ->sendToDatabase($user);
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
