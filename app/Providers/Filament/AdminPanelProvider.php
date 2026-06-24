<?php

namespace App\Providers\Filament;

use AlexSyvolap\FilamentConfetti\FilamentConfettiPlugin;
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

        // Oculta el stepper nativo de Filament SOLO donde exista la clase
        // `rit-hide-wizard-steps` (la añade la página CreateReglamentoInterno, que
        // usa su propio encabezado de paso). El CSS va en el <head> via render hook,
        // por lo que no agrega un segundo nodo raíz al componente Livewire (un
        // <style> inline en la vista de la página rompe el wizard).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<style>.rit-hide-wizard-steps .fi-fo-wizard-header{display:none}</style>',
        );

        // ── Rebrand CES Legal ────────────────────────────────────────────────
        // Gradiente de marca (rojo→naranja), Space Grotesk en títulos y el ítem
        // de sidebar activo en gradiente. Inyectado por render hook → no requiere
        // build de npm (clave para el despliegue en Hostinger).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => <<<'HTML'
            <style>
            @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
            :root{ --ces-grad: linear-gradient(135deg,#E11D48 0%,#F97316 100%); }
            .fi-header-heading,.fi-section-header-heading,.fi-modal-heading,
            .fi-ta-header-heading,.fi-simple-header-heading{
                font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;
                letter-spacing:-0.01em;
            }
            /* Sidebar: el ítem activo usa el gradiente de marca */
            .fi-sidebar-item-active > .fi-sidebar-item-button{ background-image:var(--ces-grad); }
            .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-icon{ color:#fff !important; }
            .fi-sidebar-item-active > .fi-sidebar-item-button:hover{ filter:brightness(1.04); }
            /* Login con fondo cálido (stone) */
            .fi-simple-layout{ background:#FAFAF9; }
            html.dark .fi-simple-layout{ background:#0C0A09; }

            /* ── Login split-screen CES Legal ── */
            .ces-auth-root{ position:fixed; inset:0; display:flex; background:#FAFAF9; z-index:10; }
            html.dark .ces-auth-root{ background:#0C0A09; }
            .ces-auth-brand{
                width:42%; max-width:640px; flex-shrink:0; color:#fff;
                background-image:var(--ces-grad);
                display:flex; flex-direction:column; justify-content:space-between;
                padding:72px 64px;
            }
            .ces-auth-logo{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:64px; line-height:1; }
            .ces-auth-sub{ font-weight:600; letter-spacing:5px; font-size:16px; opacity:.92; margin-top:6px; }
            .ces-auth-tag{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:30px; line-height:1.25; margin:0 0 14px; }
            .ces-auth-cap{ font-size:15px; line-height:1.55; opacity:.85; margin:0; max-width:34ch; }
            .ces-auth-main{ flex:1; display:flex; align-items:center; justify-content:center; overflow:auto; padding:40px; }
            .ces-auth-card{ width:100%; max-width:400px; }
            .ces-auth-logo-img{ height:44px; width:auto; margin-bottom:28px; display:block; }
            .ces-auth-title{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:30px; color:#1C1917; margin:0 0 6px; }
            html.dark .ces-auth-title{ color:#E7E5E4; }
            .ces-auth-lead{ color:#78716C; font-size:15px; margin:0 0 26px; }
            .ces-auth-foot{ margin-top:18px; font-size:13.5px; color:#78716C; }
            @media (max-width:900px){ .ces-auth-brand{ display:none; } .ces-auth-main{ padding:24px; } }
            </style>
            HTML,
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
            ->brandName('CES Legal')
            ->brandLogo(asset('images/ces-legal-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('images/ces-legal-favicon.png'))
            ->font('Inter')
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->registration(\App\Filament\Admin\Pages\Auth\Register::class)
            ->passwordReset()
            // Rebrand CES Legal — rojo de marca + neutros cálidos (stone) + semánticos
            ->colors([
                'primary' => Color::hex('#E11D48'),
                'gray'    => Color::Stone,
                'danger'  => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Green,
                'info'    => Color::Blue,
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
            ->plugins(array_filter([
                FilamentShieldPlugin::make(),
                LightSwitchPlugin::make(),
                FilamentNotificationSoundPlugin::make()
                    ->volume(1.0) // Volume (0.0 to 1.0)
                    ->showAnimation(true) // Show animation on notification badge
                    ->enabled(true),
                // Confeti (fireworks) tras registrarse, al aterrizar en auditar/generar RIT.
                // Cosmético: solo se registra si el paquete está instalado en vendor/,
                // para que un composer install pendiente no tumbe todo el panel.
                class_exists(FilamentConfettiPlugin::class)
                    ? FilamentConfettiPlugin::make()
                    : null,
            ]))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
