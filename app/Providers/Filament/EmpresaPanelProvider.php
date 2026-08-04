<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguraPanelCompartido;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Panel del rol 'cliente' - mismas pantallas/recursos que 'admin' (comparte
 * las mismas clases de Resource/Page/Widget vía discover*()), pero en la URL
 * /empresa en vez de /admin (pedido explícito: el cliente nunca debe ver
 * "/admin" en la barra de direcciones).
 *
 * No tiene ->registration() propio: el registro de cuentas nuevas sigue
 * viviendo solo en el panel admin (un solo formulario de registro/login,
 * ver AdminPanelProvider) - este panel solo necesita ->login() como red de
 * seguridad para que una sesión vencida en /empresa no truene, nunca se
 * enlaza a /empresa/login desde ningún lado del sistema.
 *
 * Sin FilamentShieldPlugin a propósito: Shield administra roles/permisos y
 * registra su propio Resource - vive solo en 'admin' (ver
 * ConfiguraPanelCompartido, que deja los plugins compartidos afuera de Shield).
 */
class EmpresaPanelProvider extends PanelProvider
{
    use ConfiguraPanelCompartido;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('empresa')
            ->path('empresa')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // Widgets personalizados se cargan desde el Dashboard
            ]);

        return $this->aplicarConfigComun($panel);
    }
}
