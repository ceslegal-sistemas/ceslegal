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

        $analisisFinal      = $this->analisisSancion;
        $analisisOriginal   = null;
        $motivoCorreccion   = null;
        $puntosClave        = [];

        if (!$todosFallaron) {
            $filas = $servicio->evaluarMotoresV6($resultados);
            $hallazgosGraves = array_filter($filas, fn($f) => $f['estado'] === 'riesgo');

            if (!empty($hallazgosGraves)) {
                try {
                    $corregido = $servicio->corregirRecomendacionConHallazgosV6(
                        $this->proceso,
                        $this->analisisSancion,
                        $hallazgosGraves
                    );

                    if (!empty($corregido) && !empty($corregido['resumen_correccion'])) {
                        $analisisOriginal = $this->analisisSancion;
                        $motivoCorreccion = $corregido['resumen_correccion'];
                        $analisisFinal    = $corregido;

                        // Re-evaluar los 6 motores sobre la versión YA CORREGIDA para que
                        // el checklist que ve Recursos Humanos refleje la recomendación
                        // final, no la original. Deliberadamente NO se vuelve a llamar a
                        // corregirRecomendacionConHallazgosV6() aquí (tope de 1 corrección
                        // por ciclo, sin importar qué diga esta segunda pasada).
                        $resultados = $servicio->ejecutarValidacionesV6($this->proceso, $analisisFinal);
                        $filas      = $servicio->evaluarMotoresV6($resultados);

                        Log::info('EjecutarValidacionesV6Job: recomendación corregida automáticamente', [
                            'proceso_id' => $this->proceso->id,
                            'motores_graves' => array_keys($hallazgosGraves),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('EjecutarValidacionesV6Job: falló la corrección automática, se conserva la recomendación original', [
                        'proceso_id' => $this->proceso->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // Los 6 motores auditan el mismo caso desde ángulos distintos, así que
            // suelen repetir el mismo hecho de fondo con palabras distintas. Se
            // consolida en una sola lista de puntos únicos (ver
            // consolidarHallazgosV6) para que el modal no muestre lo mismo 4 o 5
            // veces. Si falla, el detalle por motor sigue disponible como respaldo.
            try {
                $puntosClave = $servicio->consolidarHallazgosV6($filas);
            } catch (\Throwable $e) {
                Log::warning('EjecutarValidacionesV6Job: falló la consolidación de hallazgos', [
                    'proceso_id' => $this->proceso->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->proceso->update([
            'validaciones_v6_estado'           => $todosFallaron ? 'error' : 'completado',
            'validaciones_v6'                  => $resultados,
            'validaciones_v6_en'               => now(),
            'analisis_recomendacion'           => $analisisFinal,
            'analisis_recomendacion_original'  => $analisisOriginal,
            'correccion_v6_motivo'             => $motivoCorreccion,
            'validaciones_v6_puntos_clave'     => $puntosClave,
        ]);

        Log::info('EjecutarValidacionesV6Job: completado', [
            'proceso_id' => $this->proceso->id,
            'estado'     => $todosFallaron ? 'error' : 'completado',
            'corregido'  => $motivoCorreccion !== null,
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
