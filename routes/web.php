<?php

use App\Http\Controllers\DescargoPublicoController;
use App\Http\Controllers\VerificacionDocumentoController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\PayUConfirmationController;
use App\Http\Controllers\SuscripcionController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('landing');
});

// ── Ruta de PRUEBA DE CARGA (oculta) ─────────────────────────────────────────
// Simula la ocupación de proceso de un envío de descargos (usleep ~3.2s) SIN
// llamar a Gemini, para medir el tope real de peticiones pesadas concurrentes.
// Apagada por defecto: requiere CAP_TEST_ENABLED=true y el token correcto.
Route::get('/__cap-test', function (\Illuminate\Http\Request $request) {
    abort_unless((bool) config('services.cap_test.enabled'), 404);
    abort_unless(
        hash_equals((string) config('services.cap_test.token'), (string) $request->query('token')),
        403
    );
    $ms = min(10000, max(0, (int) $request->query('ms', 3200)));
    usleep($ms * 1000);
    return response()->json(['ok' => true, 'slept_ms' => $ms, 'pid' => getmypid()]);
});

// PayU - URL de confirmación server-to-server (sin CSRF, sin auth)
Route::post('/payu/confirmacion', [PayUConfirmationController::class, 'handle'])
    ->name('payu.confirmacion')
    ->withoutMiddleware([VerifyCsrfToken::class]);

// Retorno del cliente después del checkout PayU
Route::get('/suscripcion/retorno', [SuscripcionController::class, 'retorno'])
    ->name('suscripcion.retorno')
    ->middleware('auth');

Route::post('/tts', \App\Http\Controllers\TtsController::class)
    ->middleware('auth')
    ->name('tts');

Route::post('/transcribir', \App\Http\Controllers\TranscribeController::class)
    ->middleware('auth')
    ->name('transcribir');

Route::get('/descargos/{token}', [DescargoPublicoController::class, 'mostrarAcceso'])
    ->name('descargos.acceso');

// Aceptación de invitación de un bufete a una empresa (enlace por token)
Route::get('/bufete/invitacion/{token}', [\App\Http\Controllers\BufeteInvitacionController::class, 'aceptar'])
    ->name('bufete.invitacion.aceptar')
    ->where('token', '[a-zA-Z0-9]+');

// Verificación pública de documentos generados por CES Legal
Route::get('/verificar/{token}', [VerificacionDocumentoController::class, 'verificar'])
    ->name('verificacion.documento')
    ->where('token', '[a-zA-Z0-9\-_]+');

// Fotos de verificación accesibles por token (sin auth, solo si el token es válido)
Route::get('/verificar/{token}/foto/{tipo}', [VerificacionDocumentoController::class, 'foto'])
    ->name('verificacion.foto')
    ->where(['token' => '[a-zA-Z0-9\-_]+', 'tipo' => 'inicio|fin']);

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

// Fotos privadas de descargos (inicio/fin) - solo usuarios autenticados
Route::get('/admin/fotos-descargos/{diligencia}/{tipo}', function ($diligenciaId, $tipo) {
    abort_unless(in_array($tipo, ['inicio', 'fin']), 404);
    $diligencia = \App\Models\DiligenciaDescargo::findOrFail($diligenciaId);
    $campo = "foto_{$tipo}_path";
    $ruta  = $diligencia->$campo;
    if (!$ruta || !\Illuminate\Support\Facades\Storage::exists($ruta)) {
        abort(404);
    }
    return response(\Illuminate\Support\Facades\Storage::get($ruta), 200)
        ->header('Content-Type', 'image/jpeg')
        ->header('Cache-Control', 'private, max-age=3600');
})->middleware(['auth'])->name('admin.fotos-descargos');

// Descarga del RIT del cliente autenticado
Route::get('/descargar/rit', function () {
    $user    = auth()->user();
    $empresa = $user?->empresa;

    if (!$empresa) {
        abort(403, 'No autorizado');
    }

    return \App\Support\RitDescarga::responder($empresa);
})->middleware(['auth'])->name('rit.descargar');

// Descarga del RIT para super admin (por empresa_id)
Route::get('/descargar/rit/admin/{empresa}', function (\App\Models\Empresa $empresa) {
    $user = auth()->user();
    if (!$user || (!$user->hasRole('super_admin') && !$user->hasRole('abogado'))) {
        abort(403, 'No autorizado');
    }

    return \App\Support\RitDescarga::responder($empresa);
})->middleware(['auth'])->name('rit.descargar.admin');

// Descarga de documentos de la Biblioteca Legal
Route::get('/biblioteca-legal/{documento}/descargar', function (\App\Models\DocumentoLegal $documento) {
    abort_if(!auth()->user()?->hasRole('super_admin'), 403);
    $path = \Illuminate\Support\Facades\Storage::disk('public')->path($documento->archivo_path);
    abort_if(!file_exists($path), 404, 'Archivo no encontrado.');
    return response()->download($path, $documento->archivo_nombre_original ?? basename($documento->archivo_path));
})->middleware(['auth'])->name('biblioteca.descargar');

// Rutas de Email Tracking
Route::get('/email/track/{token}.gif', [EmailTrackingController::class, 'pixel'])
    ->name('email.tracking.pixel');

Route::get('/api/email-tracking/{procesoId}', [EmailTrackingController::class, 'estado'])
    ->middleware(['auth'])
    ->name('email.tracking.estado');

// Tracking de correos oficiales (CorreoEnviado)
Route::get('/correo/track/{token}.gif', [\App\Http\Controllers\CorreoTrackingController::class, 'pixel'])
    ->name('correo.tracking.pixel');


// Google OAuth para Gmail por empresa
Route::middleware('auth')->group(function () {
    Route::get('/google/oauth/iniciar/{empresaId}', [\App\Http\Controllers\GoogleOAuthController::class, 'iniciar'])
        ->name('google.oauth.iniciar');
    Route::get('/google/oauth/callback', [\App\Http\Controllers\GoogleOAuthController::class, 'callback'])
        ->name('google.oauth.callback');
});

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
