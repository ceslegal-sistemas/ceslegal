<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\DocumentoLegal;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Observers\DocumentoLegalObserver;
use App\Observers\ProcesoDisciplinarioObserver;
use App\Observers\SolicitudContratoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar Observers
        DocumentoLegal::observe(DocumentoLegalObserver::class);
        ProcesoDisciplinario::observe(ProcesoDisciplinarioObserver::class);
        SolicitudContrato::observe(SolicitudContratoObserver::class);

        // Rate limiter para llamadas a Gemini API desde la cola
        // 800/min deja margen sobre el límite de 1,000 RPM con billing habilitado
        RateLimiter::for('gemini-api', function () {
            return Limit::perMinute(800);
        });

        // Aumentar timeout de MySQL por sesión para evitar "MySQL server has gone away"
        // durante operaciones largas (generación de documentos con IA)
        try {
            \Illuminate\Support\Facades\DB::statement("SET SESSION wait_timeout = 300");
        } catch (\Exception $e) {
            // Ignorar si no se puede establecer
        }

        // Registro opt-in de tokens de Gemini: captura el usageMetadata de TODA
        // llamada (generateContent/embedContent) sin tocar cada servicio. Se activa
        // con IA_TOKEN_LOG=true. Lo lee `php artisan ia:reporte-tokens`.
        if (config('services.ia.token_log')) {
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Http\Client\Events\ResponseReceived::class,
                function ($event) {
                    $url = (string) $event->request->url();
                    if (! str_contains($url, 'generativelanguage.googleapis.com')) {
                        return;
                    }
                    try {
                        $usage = $event->response->json('usageMetadata') ?? [];
                        preg_match('#/models/([^:/]+):(\w+)#', $url, $m);
                        $prompt = (int) ($usage['promptTokenCount'] ?? 0);
                        $salida = (int) ($usage['candidatesTokenCount'] ?? 0);
                        $linea  = json_encode([
                            'ts'     => now()->toIso8601String(),
                            'modelo' => $m[1] ?? 'desconocido',
                            'metodo' => $m[2] ?? '',
                            'prompt' => $prompt,
                            'salida' => $salida,
                            'total'  => (int) ($usage['totalTokenCount'] ?? ($prompt + $salida)),
                            'ruta'   => request() ? request()->path() : 'cli',
                        ], JSON_UNESCAPED_UNICODE);
                        @file_put_contents(storage_path('logs/ia-tokens.jsonl'), $linea . PHP_EOL, FILE_APPEND);
                    } catch (\Throwable $e) {
                        // El log de tokens nunca debe romper la app.
                    }
                }
            );
        }
    }
}
