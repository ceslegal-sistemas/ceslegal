<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
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
        ProcesoDisciplinario::observe(ProcesoDisciplinarioObserver::class);
        SolicitudContrato::observe(SolicitudContratoObserver::class);

        // Aumentar timeout de MySQL por sesión para evitar "MySQL server has gone away"
        // durante operaciones largas (generación de documentos con IA)
        try {
            \Illuminate\Support\Facades\DB::statement("SET SESSION wait_timeout = 300");
        } catch (\Exception $e) {
            // Ignorar si no se puede establecer
        }
    }
}
