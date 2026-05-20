<?php

namespace App\Jobs;

use App\Models\AuditoriaRIT;
use App\Models\GapReporte;
use App\Services\GAPReporteService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarGAPReporteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** DomPDF puro, sin HTTP — 60 s es más que suficiente */
    public int $timeout = 60;

    /** Reintentar una vez en caso de fallo transitorio (ej: disco lleno momentáneo) */
    public int $tries = 2;

    public function __construct(
        public readonly AuditoriaRIT $auditoria,
        public readonly int          $userId,
    ) {
        // Cola default: no requiere rate limiter de Gemini
        $this->onQueue('default');
    }

    public function handle(GAPReporteService $service): void
    {
        $auditoria = $this->auditoria->fresh();

        if (!$auditoria || $auditoria->estado !== 'completado') {
            Log::warning('GenerarGAPReporteJob: auditoría no completada, ignorando', [
                'auditoria_id' => $this->auditoria->id,
            ]);
            return;
        }

        $reporte = $service->generarAmbosReportes($auditoria);

        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Reporte GAP generado')
                ->body('Los reportes ejecutivo y técnico de cumplimiento normativo están listos para descarga.')
                ->success()
                ->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerarGAPReporteJob: falló', [
            'auditoria_id' => $this->auditoria->id,
            'error'        => $e->getMessage(),
        ]);

        // Actualizar estado del reporte a 'error'
        GapReporte::where('auditoria_rit_id', $this->auditoria->id)
            ->update([
                'estado'        => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

        $user = \App\Models\User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Error al generar Reporte GAP')
                ->body('No se pudieron generar los reportes de cumplimiento. Intente nuevamente.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
