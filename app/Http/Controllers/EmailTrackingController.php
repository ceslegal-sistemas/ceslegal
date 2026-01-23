<?php

namespace App\Http\Controllers;

use App\Models\EmailTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailTrackingController extends Controller
{
    /**
     * User agents conocidos de bots/proxies de email que precargan imágenes
     * Estos NO deben contarse como aperturas reales
     */
    private const BOT_USER_AGENTS = [
        'GoogleImageProxy',
        'Google-Read-Aloud',
        'YahooMailProxy',
        'Outlook-iOS-Android',
        'Microsoft Office',
        'ms-office',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', // Bot genérico
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'WhatsApp',
        'TelegramBot',
        'bot',
        'crawler',
        'spider',
        'curl',
        'wget',
        'python-requests',
        'Java/',
        'Apache-HttpClient',
    ];

    /**
     * Verificar si el user agent es un bot conocido
     */
    private function esBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true; // Sin user agent = probablemente bot
        }

        $userAgentLower = strtolower($userAgent);

        foreach (self::BOT_USER_AGENTS as $botPattern) {
            if (str_contains($userAgentLower, strtolower($botPattern))) {
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
                // Verificar si es un bot/proxy que precarga imágenes
                $esBot = $this->esBot($userAgent);

                if ($esBot) {
                    Log::info('Email tracking ignorado (bot/proxy detectado)', [
                        'token' => substr($token, 0, 10) . '...',
                        'user_agent' => $userAgent,
                        'ip' => $ip,
                    ]);
                } else {
                    // Es una apertura real del usuario
                    $tracking->registrarApertura($ip, $userAgent);

                    Log::info('Email abierto por trabajador', [
                        'token' => substr($token, 0, 10) . '...',
                        'tipo_correo' => $tracking->tipo_correo,
                        'proceso_id' => $tracking->proceso_id,
                        'trabajador_email' => $tracking->email_destinatario,
                        'veces_abierto' => $tracking->veces_abierto,
                        'primera_apertura' => $tracking->veces_abierto === 1,
                        'ip' => $ip,
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
