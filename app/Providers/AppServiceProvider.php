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
    }
}
