<?php

namespace App\Providers\Filament;

use Awcodes\LightSwitch\LightSwitchPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Hardikkhorasiya09\ChangePassword\ChangePasswordPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Lordicon — iconos animados usados en modales y tarjetas
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<script src="https://cdn.lordicon.com/lordicon.js"></script>',
        );

        // Incluir Driver.js para tours de onboarding
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css"/>',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => '<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script><script src="' . asset('js/tour-descargos.js') . '"></script>',
        );

        // Fix: Mac browsers fire native HTML form validation before Livewire intercepts,
        // throwing "An invalid form control with name='' is not focusable" for hidden
        // inputs created by Tom Select (native:false) and conditional repeaters.
        // Three-layer fix (global — applies to all admin pages):
        //   1. novalidate on <form> applied IMMEDIATELY on script parse (BODY_END =
        //      DOM already loaded, DOMContentLoaded never re-fires)
        //   2. MutationObserver to re-apply after Livewire DOM morphing removes the attr
        //   3. Capture-phase invalid listener as absolute final safety net
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => <<<'HTML'
            <script>
            (function () {
                function patchForms() {
                    document.querySelectorAll('form').forEach(function (f) {
                        f.setAttribute('novalidate', '');
                    });
                }
                // Run immediately — DOM is already parsed at BODY_END
                patchForms();
                // Re-apply after Livewire updates (morphing can reset attributes)
                document.addEventListener('livewire:update', patchForms);
                document.addEventListener('livewire:updated', patchForms);
                // MutationObserver catches any DOM restructuring Livewire does
                if (window.MutationObserver) {
                    new MutationObserver(function (mutations) {
                        for (var i = 0; i < mutations.length; i++) {
                            if (mutations[i].addedNodes.length) { patchForms(); break; }
                        }
                    }).observe(document.body, { childList: true, subtree: true });
                }
                // Absolute safety net: suppress browser invalid events in capture phase
                document.addEventListener('invalid', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }, true);
            })();
            </script>
            HTML,
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            // ->brandName('CES Legal')
            // ->brandLogo(asset('storage/logo_3.png'))
            ->favicon(asset('storage/logo_2.png'))
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(\App\Filament\Admin\Pages\Auth\Register::class)
            ->passwordReset()
            ->colors([
                'primary' => Color::Blue,
            ])
            // ->theme() removido — se usa el tema por defecto de Filament que incluye todos los estilos fi-*
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
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->userMenuItems([
                'cambiar-password' => \Filament\Navigation\MenuItem::make()
                    ->label('Cambiar Contraseña')
                    ->url(fn() => \App\Filament\Admin\Pages\CambiarPassword::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // Widgets personalizados se cargan desde el Dashboard
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
            ->plugins([
                FilamentShieldPlugin::make(),
                LightSwitchPlugin::make(),
                FilamentNotificationSoundPlugin::make()
                    ->volume(1.0) // Volume (0.0 to 1.0)
                    ->showAnimation(true) // Show animation on notification badge
                    ->enabled(true),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
