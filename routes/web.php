<?php

use App\Http\Controllers\DescargoPublicoController;
use App\Http\Controllers\EmailTrackingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/descargos/{token}', [DescargoPublicoController::class, 'mostrarAcceso'])
    ->name('descargos.acceso');

// Ruta para descargar archivos de manera segura (requiere autenticación)
Route::get('/descargar/acta/{diligenciaId}', function ($diligenciaId) {
    $diligencia = \App\Models\DiligenciaDescargo::findOrFail($diligenciaId);

    if (!$diligencia->ruta_acta || !file_exists($diligencia->ruta_acta)) {
        abort(404, 'Archivo no encontrado');
    }

    $filename = 'Acta_Descargos_' . $diligencia->proceso->codigo . '.docx';

    return Response::download($diligencia->ruta_acta, $filename, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
})->middleware(['auth'])->name('descargar.acta');

Route::get('/descargar/citacion/{procesoId}', function ($procesoId) {
    $proceso = \App\Models\ProcesoDisciplinario::findOrFail($procesoId);

    // Buscar el documento de citación más reciente
    $documento = $proceso->documentos()
        ->where('tipo_documento', 'citacion_descargos')
        ->latest()
        ->first();

    if (!$documento || !file_exists($documento->ruta_archivo)) {
        abort(404, 'Archivo no encontrado');
    }

    $extension = pathinfo($documento->ruta_archivo, PATHINFO_EXTENSION);
    $filename = 'Citacion_Descargos_' . $proceso->codigo . '.' . $extension;

    $mimeType = $extension === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    return Response::download($documento->ruta_archivo, $filename, [
        'Content-Type' => $mimeType,
    ]);
})->middleware(['auth'])->name('descargar.citacion');

Route::get('/descargar/sancion/{procesoId}', function ($procesoId) {
    $proceso = \App\Models\ProcesoDisciplinario::findOrFail($procesoId);

    // Buscar el documento de sanción más reciente
    $documento = $proceso->documentos()
        ->where('tipo_documento', 'sancion')
        ->latest()
        ->first();

    if (!$documento || !file_exists($documento->ruta_archivo)) {
        abort(404, 'Archivo no encontrado');
    }

    $extension = pathinfo($documento->ruta_archivo, PATHINFO_EXTENSION);
    $filename = 'Sancion_' . $proceso->codigo . '.' . $extension;

    $mimeType = $extension === 'pdf' ? 'application/pdf' : 'text/html';

    return Response::download($documento->ruta_archivo, $filename, [
        'Content-Type' => $mimeType,
    ]);
})->middleware(['auth'])->name('descargar.sancion');

// Rutas de Email Tracking
Route::get('/email/track/{token}.gif', [EmailTrackingController::class, 'pixel'])
    ->name('email.tracking.pixel');

Route::get('/api/email-tracking/{procesoId}', [EmailTrackingController::class, 'estado'])
    ->middleware(['auth'])
    ->name('email.tracking.estado');


// Servir documentación estática de Starlight
Route::get('/docs/{path?}', function ($path = '') {
    $basePath = public_path('docs');

    // Si es la raíz o termina en /, buscar index.html
    if (empty($path) || str_ends_with($path, '/')) {
        $filePath = $basePath . '/' . $path . 'index.html';
    } else {
        // Intentar como directorio con index.html
        $dirPath = $basePath . '/' . $path . '/index.html';
        if (File::exists($dirPath)) {
            $filePath = $dirPath;
        } else {
            // Intentar como archivo directo
            $filePath = $basePath . '/' . $path;
        }
    }

    if (File::exists($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(File::get($filePath), 200)
            ->header('Content-Type', $mimeType);
    }

    abort(404);
})->where('path', '.*');
