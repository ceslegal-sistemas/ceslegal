<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Las rutas protegidas fuera de los paneles de Filament (ej. las de
        // descarga en routes/web.php, con middleware 'auth' a secas) no
        // tienen ningún login "genérico" al cual caer - Filament define su
        // propio login POR PANEL (filament.admin.auth.login), no una ruta
        // llamada 'login'. Sin esto, cualquier visitante sin sesión que
        // caiga en uno de esos enlaces protegidos veía un error 500
        // (RouteNotFoundException: Route [login] not defined) en vez de que
        // lo mandara a iniciar sesión.
        $middleware->redirectGuestsTo(
            fn () => \Filament\Facades\Filament::getPanel('admin')->getLoginUrl()
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Actualizar términos legales diariamente a las 8:00 AM
        $schedule->command('terminos:actualizar')->dailyAt('08:00');
        // Alertar contratos a término fijo por vencer y aplicar renovación
        // automática del Art. 46 CST cuando aplique.
        $schedule->command('contratos:verificar-vencimientos')->dailyAt('08:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
