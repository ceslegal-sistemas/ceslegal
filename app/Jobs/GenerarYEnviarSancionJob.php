<?php

namespace App\Jobs;

use App\Models\ProcesoDisciplinario;
use App\Services\DocumentGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera el documento de sanción (o la constancia de no sanción) con IA, crea
 * el PDF y lo envía por correo - en cola, no dentro de la petición web. Antes
 * esto corría síncrono dentro del cierre de 4 Table Actions distintas en
 * ProcesoDisciplinarioResource, bloqueando un proceso PHP-FPM durante todo el
 * tiempo que tardara Gemini + generación de PDF + envío de correo - causa
 * raíz real de la lentitud reportada por el usuario el día del demo
 * (2026-09-16, ver memoria descargos-confeti-cortado-redirect-inmediato.md).
 * Mismo patrón que GenerarRecomendacionYRevisarV6Job (primer paso del mismo
 * flujo, ya resuelto).
 */
class GenerarYEnviarSancionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Por debajo del límite de 280s del worker en producción
     * (queue:work --timeout=280, cron cada minuto en Hostinger) - si el job
     * se pasara de ahí, el propio worker lo mataría antes de que este timeout
     * interno pudiera manejarlo con orden.
     */
    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(
        public readonly ProcesoDisciplinario $proceso,
        /** null = constancia de no sanción (sin tipo de sanción). */
        public readonly ?string $tipoSancion,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(DocumentGeneratorService $servicio): void
    {
        try {
            if ($this->tipoSancion === null) {
                $servicio->generarYEnviarConstanciaNoSancion($this->proceso);
            } else {
                $servicio->generarYEnviarSancion($this->proceso, $this->tipoSancion);
            }

            $this->proceso->update(['emision_sancion_estado' => 'completado']);
        } catch (\Throwable $e) {
            $this->proceso->update([
                'emision_sancion_estado' => 'error',
                'emision_sancion_error' => $e->getMessage(),
            ]);

            Log::error('GenerarYEnviarSancionJob: fallo al generar/enviar', [
                'proceso_id' => $this->proceso->id,
                'tipo_sancion' => $this->tipoSancion ?? 'no_sancion',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->proceso->update([
            'emision_sancion_estado' => 'error',
            'emision_sancion_error' => $e->getMessage(),
        ]);
    }
}
