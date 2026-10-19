<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarWebhookWhatsAppJob;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de WhatsApp Business (Meta Cloud API). Ver
 * docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md
 * sección 2 - sin esto no hay forma de enterarse de un "STOP" entrante ni
 * de que la calidad del número está cayendo, que es justamente lo que
 * termina en un baneo si nadie lo nota a tiempo.
 */
class WhatsappWebhookController extends Controller
{
    /**
     * Meta llama esto (GET) una sola vez, al configurar o editar el
     * webhook en su panel, para confirmar que la URL es real.
     */
    public function verificar(Request $request)
    {
        $modo = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $tokenEsperado = Configuracion::obtener('whatsapp_webhook_verify_token')
            ?: config('services.whatsapp.webhook_verify_token');

        if ($modo === 'subscribe' && $token !== null && hash_equals((string) $tokenEsperado, (string) $token)) {
            return response((string) $challenge, 200);
        }

        Log::warning('WhatsApp webhook: verificación fallida', ['ip' => $request->ip()]);

        return response('Forbidden', 403);
    }

    /**
     * Meta llama esto (POST) cada vez que hay un evento real: mensaje
     * entrante, status de entrega, o cambio de calidad del número.
     */
    public function recibir(Request $request)
    {
        if (!$this->firmaValida($request)) {
            Log::warning('WhatsApp webhook: firma inválida o ausente', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        // Se despacha a un Job y se responde de inmediato - Meta reintenta
        // si no recibe un 200 rápido, y procesar aquí mismo (consultas a
        // BD, posibles notificaciones) alargaría la respuesta sin
        // necesidad.
        ProcesarWebhookWhatsAppJob::dispatch($request->all());

        return response('OK', 200);
    }

    /**
     * HMAC-SHA256 del body crudo con el App Secret de la app de Meta (no
     * el access token) - confirma que el evento realmente vino de Meta y
     * no de alguien falsificando un "STOP" o una alerta de calidad falsa.
     */
    private function firmaValida(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');
        if (empty($appSecret)) {
            Log::error('WhatsApp webhook: falta WHATSAPP_APP_SECRET - no se puede verificar la firma.');

            return false;
        }

        $firmaRecibida = (string) $request->header('X-Hub-Signature-256', '');
        if ($firmaRecibida === '') {
            return false;
        }

        $firmaCalculada = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($firmaCalculada, $firmaRecibida);
    }
}
