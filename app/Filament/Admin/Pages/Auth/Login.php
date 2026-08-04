<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Login con diseño split-screen de marca CES Legal (panel con gradiente + formulario).
 * Un solo formulario de login (siempre vive en el panel 'admin', enlazado desde
 * landing.blade.php y suscripcion/retorno.blade.php) que redirige según el rol
 * tras autenticar: cliente -> panel 'empresa', cualquier otro -> panel de
 * siempre (admin). El cliente nunca ve /admin en la barra de direcciones ni
 * necesita conocer /empresa/login - solo entra por aquí.
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.admin.pages.auth.login';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended($this->urlSegunRol());
            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response && auth()->user()?->role === 'cliente') {
            $url = $this->urlSegunRol();

            return new class ($url) implements LoginResponse {
                public function __construct(private string $url) {}

                public function toResponse($request)
                {
                    return redirect()->intended($this->url);
                }
            };
        }

        return $response;
    }

    protected function urlSegunRol(): string
    {
        return auth()->user()?->role === 'cliente'
            ? Filament::getPanel('empresa')->getUrl()
            : Filament::getUrl();
    }
}
