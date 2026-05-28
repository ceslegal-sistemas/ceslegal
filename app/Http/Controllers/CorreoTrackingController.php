<?php

namespace App\Http\Controllers;

use App\Models\CorreoEnviado;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CorreoTrackingController extends Controller
{
    /**
     * Pixel de tracking 1x1 GIF transparente.
     * Se llama cada vez que el cliente de correo carga las imágenes del mensaje.
     *
     * Primera carga: precarga del servidor de correo → estado 'entregado'
     * Segunda carga+: apertura real del usuario → estado 'leido'
     */
    public function pixel(Request $request, string $token): Response
    {
        try {
            $correo = CorreoEnviado::where('token', $token)->first();

            if ($correo) {
                $correo->registrarApertura(
                    $request->ip(),
                    $request->userAgent(),
                );

                Log::info('Correo tracking registrado', [
                    'correo_id'     => $correo->id,
                    'veces_abierto' => $correo->veces_abierto,
                    'estado'        => $correo->estado,
                    'ip'            => $request->ip(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en correo tracking', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        // GIF 1x1 transparente, sin caché para forzar carga en cada apertura
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Content-Length', strlen($pixel))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }
}
