<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualizar automáticamente estados de procesos con descargos completados
Schedule::command('procesos:actualizar-estados-descargos')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Procesar documentos pendientes de la Biblioteca Legal
Schedule::command('biblioteca:procesar --todos')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sincronizar nuevas sentencias desde fuentes oficiales (semanal)
Schedule::command('biblioteca:sincronizar --limite=15')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();
