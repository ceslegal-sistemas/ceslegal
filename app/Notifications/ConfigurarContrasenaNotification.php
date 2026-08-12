<?php

namespace App\Notifications;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Contracts\CanResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

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

    /**
     * Genera un enlace NUEVO (invalida el anterior, si había uno vigente -
     * comportamiento estándar de Password::sendResetLink()) y lo envía por
     * este mismo correo en español. Única fuente de esta lógica - la usan
     * tanto la creación inicial del usuario administrador (CreateEmpresa)
     * como el botón "Reenviar enlace" (UserResource), para no duplicar la
     * construcción de la URL firmada del panel 'empresa'.
     */
    public static function enviarA(User $user, string $nombreEmpresa): void
    {
        Password::sendResetLink(
            ['email' => $user->email],
            function (CanResetPassword $user, string $token) use ($nombreEmpresa): void {
                // Quien lo dispara (bufete/admin) navega en el panel 'admin',
                // pero el enlace lo abre el CLIENTE - debe apuntar al panel
                // 'empresa' explícitamente, no al panel "actual" de quien lo genera.
                $url = Filament::getPanel('empresa')->getResetPasswordUrl($token, $user);
                $minutos = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
                $user->notify(new self($url, $nombreEmpresa, $minutos));
            }
        );
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
