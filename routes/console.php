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
