<?php

namespace App\Jobs;

use App\Console\Commands\ScrapearArticulosCst;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Ejecuta en cola el scraping de artículos del CST (misma lógica de
 * `php artisan cst:scraper`, vía ScrapearArticulosCst::ejecutar()), reportando
 * progreso en caché ('cst_scraper_progreso') para que el panel admin lo
 * muestre en vivo sin bloquear el navegador durante los varios minutos que tarda.
 */
class ActualizarArticulosCstJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'cst_scraper_progreso';

    /** ~490 artículos × hasta ~1s (HTTP + embedding) puede acercarse a 10 min */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(ScrapearArticulosCst $comando): void
    {
        $total = $comando->totalArticulos();

        Cache::put(self::CACHE_KEY, [
            'estado'  => 'procesando',
            'actual'  => 0,
            'total'   => $total,
            'mensaje' => 'Iniciando...',
        ], now()->addHours(3));

        try {
            $resultado = $comando->ejecutar(force: false, soloNum: null, onProgreso: function (string $nivel, string $mensaje, int $indice, int $total) {
                Cache::put(self::CACHE_KEY, [
                    'estado'  => 'procesando',
                    'actual'  => $indice,
                    'total'   => $total,
                    'mensaje' => trim($mensaje),
                ], now()->addHours(3));
            });
        } catch (\Throwable $e) {
            Cache::put(self::CACHE_KEY, [
                'estado'  => 'error',
                'mensaje' => $e->getMessage(),
            ], now()->addMinutes(15));

            $this->notificar(danger: true, titulo: 'Error al actualizar los artículos legales', cuerpo: $e->getMessage());
            throw $e;
        }

        Cache::put(self::CACHE_KEY, [
            'estado'   => 'completado',
            'actual'   => $resultado['total'],
            'total'    => $resultado['total'],
            'ok'       => $resultado['ok'],
            'skip'     => $resultado['skip'],
            'errores'  => $resultado['errores'],
        ], now()->addMinutes(15));

        $this->notificar(
            danger: false,
            titulo: 'Artículos legales actualizados',
            cuerpo: "{$resultado['ok']} importados, {$resultado['skip']} omitidos, {$resultado['errores']} errores."
        );
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::CACHE_KEY, [
            'estado'  => 'error',
            'mensaje' => $e->getMessage(),
        ], now()->addMinutes(15));
    }

    private function notificar(bool $danger, string $titulo, string $cuerpo): void
    {
        $user = \App\Models\User::find($this->userId);
        if (! $user) {
            return;
        }

        $notificacion = Notification::make()->title($titulo)->body($cuerpo);
        $danger ? $notificacion->danger() : $notificacion->success();
        $notificacion->sendToDatabase($user);
    }
}
