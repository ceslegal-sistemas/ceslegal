<?php

namespace App\Services;

use App\Mail\CorreoOficial;
use App\Models\CorreoEnviado;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío SÍNCRONO de un correo oficial (Correos Enviados).
 *
 * Prioridad de canal: Gmail del cliente (OAuth) → SMTP del sistema (fallback).
 * Lanza una excepción si el envío falla por completo, para que quien lo invoque
 * pueda decidir NO registrar el correo.
 */
class CorreoOficialSender
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly GmailApiService $gmail,
    ) {}

    /**
     * @return string Canal usado: 'gmail_oauth' | 'smtp'
     *
     * @throws \Throwable Si ni Gmail ni SMTP logran enviar el correo.
     */
    public function send(CorreoEnviado $correo): string
    {
        $empresaId = $correo->empresa_id
            ?? $correo->trabajador?->empresa_id
            ?? $correo->proceso?->empresa_id;

        $empresa = $empresaId ? Empresa::find($empresaId) : null;

        // 1) Intentar desde el Gmail del cliente, si está conectado.
        if ($empresa && $empresa->tieneGmailConectado()) {
            try {
                $accessToken = $this->oauth->getValidAccessToken($empresa);
                $this->gmail->send($correo, $accessToken);

                return 'gmail_oauth';
            } catch (\Throwable $e) {
                // Gmail falló: lo registramos y caemos a SMTP. Si SMTP también
                // falla, la excepción de abajo se propaga (correo NO enviado).
                Log::warning('Gmail API falló, usando SMTP como fallback', [
                    'correo_id' => $correo->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // 2) Fallback SMTP. Si esto lanza, el correo NO se considera enviado.
        Mail::to($correo->email_destinatario, $correo->destinatario_nombre)
            ->send(new CorreoOficial($correo));

        return 'smtp';
    }
}
