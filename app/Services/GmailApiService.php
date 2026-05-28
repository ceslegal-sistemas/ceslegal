<?php

namespace App\Services;

use App\Models\CorreoEnviado;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

class GmailApiService
{
    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function send(CorreoEnviado $correo, string $accessToken): void
    {
        $email = (new Email())
            ->from($correo->empresa->google_oauth_email)
            ->to($correo->email_destinatario)
            ->subject($this->buildSubject($correo))
            ->html($this->buildHtmlBody($correo));

        foreach ($correo->email_cc ?? [] as $cc) {
            $email->addCc($cc);
        }

        foreach ($correo->adjuntos ?? [] as $path) {
            $absolutePath = Storage::disk('local')->path($path);
            if (file_exists($absolutePath)) {
                $email->attachFromPath($absolutePath, basename($path));
            }
        }

        $encoded = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->post(self::SEND_URL, ['raw' => $encoded]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Gmail API send failed (' . $response->status() . '): ' . $response->body()
            );
        }
    }

    private function buildSubject(CorreoEnviado $correo): string
    {
        return match ($correo->prioridad) {
            'urgente' => '[URGENTE] ' . $correo->asunto,
            'alta'    => '[IMPORTANTE] ' . $correo->asunto,
            default   => $correo->asunto,
        };
    }

    private function buildHtmlBody(CorreoEnviado $correo): string
    {
        $trackingUrl = route('correo.tracking.pixel', $correo->token);

        return view('mail.correo-oficial', [
            'correo'      => $correo,
            'trackingUrl' => $trackingUrl,
        ])->render();
    }
}
