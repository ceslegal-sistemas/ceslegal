<?php

namespace App\Jobs;

use App\Models\Impugnacion;
use App\Services\IAResolucionImpugnacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analiza con IA la impugnación apenas se presenta y guarda el resultado
 * (decisión sugerida, valoración de cada motivo, riesgos y fundamento). En cola,
 * no en la petición web: Gemini tarda 30-90 s. Mismo patrón y límites que
 * GenerarYEnviarSancionJob (cola 'gemini', timeout por debajo de los 280 s del worker).
 * La IA solo SUGIERE: la decisión final la toma el cliente en "Resolver impugnación".
 */
class AnalizarImpugnacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public readonly Impugnacion $impugnacion)
    {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(IAResolucionImpugnacionService $servicio): void
    {
        $impugnacion = $this->impugnacion->fresh();
        if (! $impugnacion || ! $impugnacion->proceso) {
            return;
        }

        $impugnacion->forceFill(['analisis_ia_estado' => 'procesando'])->saveQuietly();

        try {
            $resultado = $servicio->analizarImpugnacion($impugnacion->proceso);

            $impugnacion->forceFill([
                'analisis_ia' => $resultado['analisis'] ?? null,
                'analisis_ia_estado' => ($resultado['success'] ?? false) ? 'completado' : 'error',
                'analisis_ia_en' => now(),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('AnalizarImpugnacionJob: fallo el análisis', ['impugnacion_id' => $impugnacion->id, 'error' => $e->getMessage()]);
            $impugnacion->forceFill(['analisis_ia_estado' => 'error', 'analisis_ia_en' => now()])->saveQuietly();
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->impugnacion->forceFill(['analisis_ia_estado' => 'error'])->saveQuietly();
    }
}
