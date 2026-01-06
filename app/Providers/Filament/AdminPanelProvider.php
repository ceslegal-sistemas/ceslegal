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
use MartinPetricko\FilamentSentryFeedback\Enums\ColorScheme;
use MartinPetricko\FilamentSentryFeedback\FilamentSentryFeedbackPlugin;
use MKWebDesign\FilamentWatchdog\FilamentWatchdogPlugin;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;

class AdminPanelProvider extends PanelProvider
{
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
            ->passwordReset()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->userMenuItems([
                'cambiar-password' => \Filament\Navigation\MenuItem::make()
                    ->label('Cambiar Contraseña')
                    ->url(fn () => \App\Filament\Admin\Pages\CambiarPassword::getUrl())
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
                // FilamentWatchdogPlugin::make(),
                FilamentSentryFeedbackPlugin::make()
                    // ->sentryUser(function (): ?SentryUser {
                    //     return new SentryUser(auth()->user()->name, auth()->user()->email);
                    // }),
                    ->colorScheme(ColorScheme::Auto)
                    ->showBranding(false)
                    ->showName(true)
                    ->showEmail(true)
                    ->enableScreenshot(true),

                LightSwitchPlugin::make(),

                // Plugin de cambiar contraseña removido
                // Ahora usamos nuestra página personalizada en español: app/Filament/Admin/Pages/CambiarPassword.php

                FilamentNotificationSoundPlugin::make()
                    ->volume(1.0) // Volume (0.0 to 1.0)
                    ->showAnimation(true) // Show animation on notification badge
                    ->enabled(true),

                // AuthUIEnhancerPlugin::make()
                //     ->showEmptyPanelOnMobile(false)
                //     ->formPanelPosition('left')
                //     ->formPanelWidth('40%')
                //     ->emptyPanelBackgroundImageOpacity('70%')
                //     ->emptyPanelBackgroundImageUrl(asset('storage/consulta-juridica.jpg')),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
