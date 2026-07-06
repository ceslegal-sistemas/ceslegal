<?php

namespace App\Filament\Admin\Pages;

use AlexSyvolap\FilamentConfetti\Confetti;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Widgets\ProcesoGuiaWidget;
use App\Filament\Admin\Widgets\StatsOverviewWidget;
use App\Filament\Admin\Widgets\RecentProcessesWidget;
use App\Filament\Admin\Widgets\ProcessesByStatusChart;
use App\Filament\Admin\Widgets\RecentActivityWidget;
use App\Services\GoogleOAuthService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.admin.pages.dashboard';

    protected static ?string $title = 'Panel de Control';

    protected static ?string $navigationLabel = 'Inicio';

    public function mount(): void
    {
        // Confeti de bienvenida cuando el cliente elige "lo haré después" al registrarse
        // y aterriza aquí (Panel de control). Flag de un solo uso.
        // class_exists evita fatal si el paquete aún no está instalado en vendor/.
        if (session()->pull('celebrar_registro_rit') && class_exists(Confetti::class)) {
            Confetti::fireworks()->shoot();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Bufete sin empresa: guía a crear la primera empresa.
            Actions\Action::make('empezar_bufete')
                ->label('Comenzar: cree su empresa')
                ->icon('heroicon-o-building-office-2')
                ->color('primary')
                ->url(\App\Filament\Admin\Resources\EmpresaResource::getUrl('create'))
                ->visible(fn() => auth()->user()?->bufeteNecesitaEmpresa() ?? false),

            Actions\Action::make('tutorial')
                ->label('¿Necesitas ayuda?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'data-tour' => 'help-button-dashboard',
                    'onclick' => 'window.iniciarTour(); return false;',
                ])
                // El tour es del flujo de descargos: no aplica al bufete.
                ->visible(fn() => ! (auth()->user()?->esAbogadoDeBufete() ?? false)),

            Actions\Action::make('Crear Descargos')
                ->label('Crear Descargos')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                // Oculto para el bufete hasta seleccionar una empresa específica.
                ->visible(fn() => ! (auth()->user()?->bufeteSinEmpresaActiva() ?? false))
                ->url(ProcesoDisciplinarioResource::getUrl('create')),

            Actions\Action::make('conectar_gmail')
                ->label('Conecta tu Gmail')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->url(function() {
                    $user = Auth::user();
                    return app(GoogleOAuthService::class)->buildAuthUrl($user?->id);
                })
                // Oculto por ahora.
                ->visible(false),

            Actions\Action::make('desconectar_gmail')
                ->label(function() {
                    $user = Auth::user();
                    return 'Desconecta tu Gmail: ' . ($user?->google_oauth_email ?? '');
                })
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Desconectar Gmail')
                ->modalDescription('¿Está seguro de que desea desconectar la cuenta de Gmail de esta empresa? Los correos futuros se enviarán por SMTP.')
                ->action(function () {
                    $user = Auth::user();
                    app(GoogleOAuthService::class)->disconnect($user);
                    $user?->refresh();

                    Notification::make()
                        ->success()
                        ->title('Gmail desconectado')
                        ->body('La cuenta ha sido desvinculada. Los correos futuros se enviarán por SMTP.')
                        ->send();
                })
                // Oculto por ahora.
                ->visible(false),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ProcesoGuiaWidget::class,
            StatsOverviewWidget::class,
            ProcessesByStatusChart::class,
            RecentProcessesWidget::class,
            // ExpiringTermsWidget::class,
            RecentActivityWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
