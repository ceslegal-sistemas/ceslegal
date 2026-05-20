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
    }
}
