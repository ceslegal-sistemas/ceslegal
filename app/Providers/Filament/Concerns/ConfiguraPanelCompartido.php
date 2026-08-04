<?php

namespace App\Providers\Filament\Concerns;

use Awcodes\LightSwitch\LightSwitchPlugin;
use AlexSyvolap\FilamentConfetti\FilamentConfettiPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;

/**
 * Configuración de panel compartida entre 'admin' (AdminPanelProvider) y
 * 'empresa' (EmpresaPanelProvider, rol cliente) - marca, colores, middleware
 * y plugins que no dependen de cuál sea el panel. FilamentShieldPlugin
 * queda AFUERA de este trait a propósito: Shield registra sus propios
 * recursos de roles/permisos y debe vivir en un solo panel (admin), no
 * duplicarse.
 */
trait ConfiguraPanelCompartido
{
    protected function aplicarConfigComun(Panel $panel): Panel
    {
        return $panel
            ->brandName('CES Legal')
            ->brandLogo(asset('images/ces-legal-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('images/ces-legal-favicon.png'))
            ->font('Inter')
            ->colors([
                'primary' => Color::hex('#E11D48'),
                'gray'    => Color::Stone,
                'danger'  => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Green,
                'info'    => Color::Blue,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('Gestión Laboral'),
                NavigationGroup::make('Gestión de Contratos'),
                NavigationGroup::make('Gestión Jurídica'),
                NavigationGroup::make('Configuración Informes')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make('Administración'),
            ])
            ->userMenuItems([
                'cambiar-password' => \Filament\Navigation\MenuItem::make()
                    ->label('Cambiar Contraseña')
                    ->url(fn() => \App\Filament\Admin\Pages\CambiarPassword::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins(array_filter([
                LightSwitchPlugin::make(),
                FilamentNotificationSoundPlugin::make()
                    ->volume(1.0)
                    ->showAnimation(true)
                    ->enabled(true),
                class_exists(FilamentConfettiPlugin::class)
                    ? FilamentConfettiPlugin::make()
                    : null,
            ]))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
