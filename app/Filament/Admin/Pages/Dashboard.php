<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Widgets\StatsOverviewWidget;
use App\Filament\Admin\Widgets\RecentProcessesWidget;
use App\Filament\Admin\Widgets\ExpiringTermsWidget;
use App\Filament\Admin\Widgets\ProcessesByStatusChart;
use App\Filament\Admin\Widgets\RecentActivityWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions;
use Filament\Actions\Action;
use App\Services\GoogleOAuthService;
use Filament\Notifications\Notification;
use App\Filament\Admin\Resources\EmpresaResource;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.admin.pages.dashboard';

    protected static ?string $title = 'Panel de Control';

    protected static ?string $navigationLabel = 'Inicio';


    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tutorial')
                ->label('¿Necesitas ayuda?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'data-tour' => 'help-button-dashboard',
                    'onclick' => 'window.iniciarTour(); return false;',
                ]),

            Actions\Action::make('Crear DESCARGOS')
                ->label('Crear DESCARGOS')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(ProcesoDisciplinarioResource::getUrl('create')),

            Actions\Action::make('conectar_gmail')
                ->label('Conectar Gmail')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->url(fn() => app(GoogleOAuthService::class)->buildAuthUrl($this->record->id))
                ->visible(fn() => !$this->record->tieneGmailConectado()),

            Actions\Action::make('desconectar_gmail')
                ->label(fn() => 'Desconectar Gmail: ' . $this->record->google_oauth_email)
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Desconectar Gmail')
                ->modalDescription('¿Está seguro de que desea desconectar la cuenta de Gmail de esta empresa? Los correos futuros se enviarán por SMTP.')
                ->action(function () {
                    app(GoogleOAuthService::class)->disconnect($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Gmail desconectado')
                        ->body('La cuenta ha sido desvinculada. Los correos futuros se enviarán por SMTP.')
                        ->send();
                })
                ->visible(fn() => $this->record->tieneGmailConectado()),
        ];
    }

    public function tieneGmailConectado(): bool
    {
        return !empty($this->google_oauth_tokens);
    }

    public function getWidgets(): array
    {
        return [
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
