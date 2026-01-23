<?php

namespace App\Http\Controllers;

use App\Models\EmailTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailTrackingController extends Controller
{
    /**
     * Proxies de correo que precargan imágenes automáticamente
     * Estos NO son el trabajador abriendo el correo
     */
    private const EMAIL_PROXIES = [
        'GoogleImageProxy',
        'YahooMailProxy',
        'Outlook-iOS-Android',
        'Microsoft Office',
    ];

    /**
     * Verificar si es un proxy de correo (no el trabajador real)
     */
    private function esProxyDeCorreo(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        foreach (self::EMAIL_PROXIES as $proxy) {
            if (stripos($userAgent, $proxy) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pixel de tracking - Se llama cuando el correo es abierto
     * La imagen se carga automáticamente al abrir el correo
     */
    public function pixel(Request $request, string $token): Response
    {
        try {
            $tracking = EmailTracking::where('token', $token)->first();
            $userAgent = $request->userAgent();
            $ip = $request->ip();

            if ($tracking) {
                // Ignorar proxies de correo (Google, Yahoo, Outlook)
                if ($this->esProxyDeCorreo($userAgent)) {
                    Log::info('Email tracking ignorado (proxy de correo)', [
                        'token' => substr($token, 0, 10) . '...',
                        'user_agent' => $userAgent,
                        'ip' => $ip,
                    ]);
                } else {
                    // Es el trabajador abriendo el correo
                    $tracking->registrarApertura($ip, $userAgent);

                    Log::info('Email abierto por trabajador', [
                        'token' => substr($token, 0, 10) . '...',
                        'tipo_correo' => $tracking->tipo_correo,
                        'proceso_id' => $tracking->proceso_id,
                        'trabajador_email' => $tracking->email_destinatario,
                        'veces_abierto' => $tracking->veces_abierto,
                        'primera_apertura' => $tracking->veces_abierto === 1,
                        'ip' => $ip,
                        'user_agent' => $userAgent,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Silenciar errores para no afectar la experiencia del usuario
            Log::error('Error en email tracking', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        // Devolver imagen GIF transparente 1x1 pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Content-Length', strlen($pixel))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    /**
     * Ver estado de tracking de un proceso (para uso interno/admin)
     */
    public function estado(int $procesoId)
    {
        $trackings = EmailTracking::where('proceso_id', $procesoId)
            ->orderBy('enviado_en', 'desc')
            ->get();

        return response()->json([
            'proceso_id' => $procesoId,
            'trackings' => $trackings->map(function ($tracking) {
                return [
                    'tipo_correo' => $tracking->tipo_correo_legible,
                    'email' => $tracking->email_destinatario,
                    'enviado_en' => $tracking->enviado_en->format('d/m/Y H:i:s'),
                    'abierto' => $tracking->fueAbierto(),
                    'abierto_en' => $tracking->abierto_en?->format('d/m/Y H:i:s'),
                    'veces_abierto' => $tracking->veces_abierto,
                    'tiempo_hasta_apertura' => $tracking->tiempo_hasta_apertura,
                ];
            }),
        ]);
    }
}
