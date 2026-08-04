<?php

namespace App\Filament\Admin\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Login con diseño split-screen de marca CES Legal (panel con gradiente + formulario).
 * Un solo formulario de login (siempre vive en el panel 'admin', enlazado desde
 * landing.blade.php y suscripcion/retorno.blade.php) que redirige según el rol
 * tras autenticar: cliente -> panel 'empresa', cualquier otro -> panel de
 * siempre (admin). El cliente nunca ve /admin en la barra de direcciones ni
 * necesita conocer /empresa/login - solo entra por aquí.
 *
 * authenticate() se reescribe completo (no se puede llamar a
 * parent::authenticate() y luego decidir el panel encima): la versión base de
 * Filament valida canAccessPanel() contra Filament::getCurrentPanel() - que
 * aquí SIEMPRE es 'admin' (es donde vive este formulario) - y si falla,
 * desloguea y lanza la MISMA excepción de "credenciales incorrectas" que una
 * contraseña mal escrita. Como User::canAccessPanel() ahora niega 'admin' a
 * cliente a propósito, esa validación de la clase base bloquearía a CUALQUIER
 * cliente con contraseña correcta, mostrándole un error de credenciales
 * falso. Se reimplementa el mismo flujo (rate limit, intento de credenciales,
 * mensaje de error idéntico si fallan) pero validando canAccessPanel() contra
 * el panel de DESTINO según el rol, no el panel donde vive el formulario.
 *
 * Para cliente el redirect NUNCA usa redirect()->intended(): esa función
 * manda a la URL que el usuario intentó visitar ANTES de que lo mandaran a
 * loguearse - si esa URL quedó guardada en su sesión de una visita vieja a
 * algo bajo /admin (de cuando el cliente sí podía navegar ahí, antes de este
 * cambio), lo reenviaría a una página que ya no le corresponde en vez de a
 * /empresa. Se descarta cualquier valor guardado y siempre se manda directo
 * al panel calculado por rol.
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.admin.pages.auth.login';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirigirSegunRol(auth()->user());
            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        // Mismo mensaje de error que la clase base ante credenciales
        // incorrectas - solo cambia qué panel se valida después.
        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        $panelDestino = ($user->role ?? null) === 'cliente'
            ? Filament::getPanel('empresa')
            : Filament::getCurrentPanel();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel($panelDestino))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        $esCliente = ($user->role ?? null) === 'cliente';
        $url = $this->urlSegunRol($user);

        return new class ($url, $esCliente) implements LoginResponse {
            public function __construct(private string $url, private bool $esCliente) {}

            public function toResponse($request)
            {
                if ($this->esCliente) {
                    // Nunca ->intended(): cualquier URL "intentada" guardada
                    // de antes de este cambio podría apuntar a /admin.
                    session()->forget('url.intended');

                    return redirect($this->url);
                }

                return redirect()->intended($this->url);
            }
        };
    }

    protected function redirigirSegunRol($user): void
    {
        if (($user->role ?? null) === 'cliente') {
            session()->forget('url.intended');
            redirect($this->urlSegunRol($user));
            return;
        }

        redirect()->intended($this->urlSegunRol($user));
    }

    protected function urlSegunRol($user): string
    {
        return ($user?->role ?? null) === 'cliente'
            ? Filament::getPanel('empresa')->getUrl()
            : Filament::getUrl();
    }
}
