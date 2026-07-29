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

/**
 * Genera la recomendación de sanción Y corre la revisión V6 completa (motores +
 * corrección si hace falta) en un solo job en cola. Reemplaza el intento anterior
 * de hacer todo esto SÍNCRONO dentro de la misma petición que abre el modal
 * "Emitir Sanción": en producción (Hostinger) eso choca con el timeout del
 * proxy/PHP-FPM del servidor (~2 min de espera real, el servidor corta antes) -
 * un error real reportado por el usuario ("ventana negra"). El servidor no
 * permite ejecuciones tan largas en una sola petición HTTP, así que esto debe
 * seguir corriendo en cola, PERO el modal ya no se abre hasta que termine (ver
 * ProcesoDisciplinarioResource::emitir_sancion) - un único wire:poll ligero
 * antes de mostrar el formulario real, en vez de abrir con contenido parcial.
 */
class GenerarRecomendacionYRevisarV6Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Análisis inicial + 8 motores en paralelo + posible corrección y re-validación. */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly ProcesoDisciplinario $proceso,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(IAAnalisisSancionService $servicio): void
    {
        $resultado = $servicio->analizarYSugerirSanciones($this->proceso);
        $esFallback = !($resultado['success'] ?? false)
            || str_contains($resultado['analisis']['justificacion'] ?? '', 'Análisis manual requerido');

        if ($esFallback) {
            // La IA no estuvo disponible en absoluto - no tiene sentido correr la
            // revisión V6 sobre un análisis de respaldo. analisis_recomendacion
            // queda null: es la señal que usa el Resource para mostrar la pantalla
            // de "IA no disponible" en vez del formulario normal.
            $this->proceso->update([
                'validaciones_v6_estado'  => 'error',
                'analisis_recomendacion'  => null,
            ]);

            Log::warning('GenerarRecomendacionYRevisarV6Job: análisis inicial falló (fallback)', [
                'proceso_id' => $this->proceso->id,
                'error'      => $resultado['error'] ?? 'desconocido',
            ]);

            return;
        }

        $r = $servicio->ejecutarRevisionCompletaV6($this->proceso, $resultado['analisis']);

        $this->proceso->update([
            'validaciones_v6_estado'           => $r['estado'],
            'validaciones_v6'                  => $r['resultados'],
            'validaciones_v6_en'               => now(),
            'analisis_recomendacion'           => $r['analisisFinal'],
            'analisis_recomendacion_original'  => $r['analisisOriginal'],
            'correccion_v6_motivo'             => $r['motivoCorreccion'],
            'validaciones_v6_puntos_clave'     => $r['puntosClave'],
        ]);

        Log::info('GenerarRecomendacionYRevisarV6Job: completado', [
            'proceso_id' => $this->proceso->id,
            'estado'     => $r['estado'],
            'corregido'  => $r['motivoCorreccion'] !== null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->proceso->update([
            'validaciones_v6_estado' => 'error',
            'analisis_recomendacion' => null,
        ]);

        Log::error('GenerarRecomendacionYRevisarV6Job: fallo total', [
            'proceso_id' => $this->proceso->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
