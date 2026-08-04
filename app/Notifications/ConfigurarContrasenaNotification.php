<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reemplaza la notificación de reseteo de contraseña por defecto de Laravel/Filament
 * (Illuminate\Auth\Notifications\ResetPassword / Filament\Notifications\Auth\ResetPassword),
 * cuyo contenido va en inglés vía Lang::get() sobre frases literales que nunca se
 * tradujeron en este proyecto (no existe lang/es.json). Aquí el texto va directo en
 * español, sin depender de la configuración de locale del servidor.
 */
class ConfigurarContrasenaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly string $nombreEmpresa,
        public readonly int $minutosExpiracion = 60,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Configure su contraseña de acceso - {$this->nombreEmpresa}")
            ->view('emails.restablecer-contrasena', [
                'nombre'             => $notifiable->name,
                'nombreEmpresa'      => $this->nombreEmpresa,
                'url'                => $this->url,
                'minutosExpiracion'  => $this->minutosExpiracion,
            ]);
    }
}
