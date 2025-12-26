<?php

use App\Http\Controllers\DescargoPublicoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/descargos/{token}', [DescargoPublicoController::class, 'mostrarAcceso'])
    ->name('descargos.acceso');
