<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\DocumentoLegal;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Observers\DocumentoLegalObserver;
use App\Observers\ProcesoDisciplinarioObserver;
use App\Observers\ReglamentoInternoObserver;
use App\Observers\SolicitudContratoObserver;
use App\Observers\UserObserver;

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
        ReglamentoInterno::observe(ReglamentoInternoObserver::class);
        User::observe(UserObserver::class);

        // Quita el botón "Crear y crear otro" de TODOS los formularios de
        // creación del panel (pedido del usuario 2026-08-13). $canCreateAnother
        // es una propiedad static declarada una sola vez en la clase base
        // Filament\Resources\Pages\CreateRecord - como ninguna página
        // CreateXxx propia la redeclara, esta única llamada la desactiva en
        // todas a la vez (misma storage por herencia de static properties).
        \Filament\Resources\Pages\CreateRecord::disableCreateAnother();

        // El disco 'local' de este proyecto es privado (storage/app/private,
        // ver gotcha-storage-disco-local-private) - Laravel no soporta
        // temporaryUrl() nativamente en el driver local, así que cualquier
        // FileUpload de Filament con ->disk('local')->visibility('private')
        // no mostraba enlace de abrir/descargar. Este callback lo habilita
        // de forma genérica para TODO el panel (no solo un campo puntual):
        // Filament pide una URL temporal, y esta la resuelve con una ruta
        // firmada que sirve el archivo real.
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTimeInterface $expiration, array $options) => URL::temporarySignedRoute(
                'storage.local.temporary',
                $expiration,
                array_merge($options, ['path' => $path]),
            )
        );

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

                        // Servicio/Job que originó la llamada (para etiquetar el paso del flujo).
                        $fuente = null;
                        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
                            $cls = $frame['class'] ?? '';
                            if (str_starts_with($cls, 'App\\Services\\') || str_starts_with($cls, 'App\\Jobs\\')) {
                                $fuente = class_basename($cls);
                                break;
                            }
                        }

                        $linea  = json_encode([
                            'ts'     => now()->toIso8601String(),
                            'modelo' => $m[1] ?? 'desconocido',
                            'metodo' => $m[2] ?? '',
                            'prompt' => $prompt,
                            'salida' => $salida,
                            'total'  => (int) ($usage['totalTokenCount'] ?? ($prompt + $salida)),
                            'fuente' => $fuente,
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
