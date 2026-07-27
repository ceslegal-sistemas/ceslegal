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

    /** 8 motores secuenciales, cada uno con su propio cascade de modelos. */
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

        $resultados = $servicio->ejecutarValidacionesV6($this->proceso, $this->analisisSancion);

        // Si TODOS los motores fallaron, el estado queda en error para diferenciarlo
        // de un resultado completado (aunque sea con hallazgos parciales).
        $todosFallaron = collect($resultados)->every(fn($r) => isset($r['error']));

        $this->proceso->update([
            'validaciones_v6_estado' => $todosFallaron ? 'error' : 'completado',
            'validaciones_v6'        => $resultados,
            'validaciones_v6_en'     => now(),
        ]);

        Log::info('EjecutarValidacionesV6Job: completado', [
            'proceso_id' => $this->proceso->id,
            'estado'     => $todosFallaron ? 'error' : 'completado',
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
