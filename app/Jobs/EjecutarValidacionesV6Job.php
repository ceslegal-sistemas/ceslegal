<?php

namespace App\Jobs;

use App\Models\ProcesoDisciplinario;
use App\Services\IAAnalisisSancionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EjecutarValidacionesV6Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Los 8 motores corren en paralelo (~1 min típico, ver
     * IAAnalisisSancionService::llamarGeminiEnParalelo()), pero el timeout
     * generoso cubre el peor caso: corrección automática + re-validación +
     * consolidación, más el cascade de modelos en serie si algún motor
     * falla en el intento paralelo.
     */
    public int $timeout = 900;

    /** Cada motor ya maneja sus propios fallos internamente (ver ejecutarValidacionesV6). */
    public int $tries = 1;

    public function __construct(
        public readonly ProcesoDisciplinario $proceso,
        public readonly array $analisisSancion,
    ) {
        // Misma cola que el resto de llamadas a Gemini - comparte el mismo throttle.
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(IAAnalisisSancionService $servicio): void
    {
        $this->proceso->update(['validaciones_v6_estado' => 'procesando']);

        $r = $servicio->ejecutarRevisionCompletaV6($this->proceso, $this->analisisSancion);

        $this->proceso->update([
            'validaciones_v6_estado'           => $r['estado'],
            'validaciones_v6'                  => $r['resultados'],
            'validaciones_v6_en'               => now(),
            'analisis_recomendacion'           => $r['analisisFinal'],
            'analisis_recomendacion_original'  => $r['analisisOriginal'],
            'correccion_v6_motivo'             => $r['motivoCorreccion'],
            'validaciones_v6_puntos_clave'     => $r['puntosClave'],
        ]);

        Log::info('EjecutarValidacionesV6Job: completado', [
            'proceso_id' => $this->proceso->id,
            'estado'     => $r['estado'],
            'corregido'  => $r['motivoCorreccion'] !== null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->proceso->update(['validaciones_v6_estado' => 'error']);

        Log::error('EjecutarValidacionesV6Job: fallo total', [
            'proceso_id' => $this->proceso->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
