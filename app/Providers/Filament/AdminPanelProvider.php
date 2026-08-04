<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguraPanelCompartido;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguraPanelCompartido;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->registration(\App\Filament\Admin\Pages\Auth\Register::class)
            ->passwordReset()
            // ->theme() removido - se usa el tema por defecto de Filament que incluye todos los estilos fi-*
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // Widgets personalizados se cargan desde el Dashboard
            ]);

        $panel = $this->aplicarConfigComun($panel);

        // Un cliente que entre a /admin por costumbre/enlace viejo ya no tiene
        // permiso aquí (ver User::canAccessPanel()) - en vez de dejarlo con un
        // 403 seco, se le redirige a su panel real. Solo en 'admin': en
        // 'empresa' nunca aplica (el que entra ahí YA es cliente).
        $panel = $panel->middleware([
            \App\Http\Middleware\RedirigirClienteAlPanelEmpresa::class,
        ]);

        // FilamentShieldPlugin solo vive en 'admin': administra roles/permisos y
        // registra su propio Resource - no debe duplicarse en el panel 'empresa'.
        return $panel->plugins([
            FilamentShieldPlugin::make(),
        ]);
    }
}
