<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Login con diseño split-screen de marca CES Legal (panel con gradiente + formulario).
 * Solo cambia la vista; toda la lógica de autenticación es la de Filament.
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.admin.pages.auth.login';
}
