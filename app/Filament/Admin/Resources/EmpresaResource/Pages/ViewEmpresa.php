<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Services\GoogleOAuthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmpresa extends ViewRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('conectar_gmail')
                ->label('Conectar Gmail')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->url(fn () => route('google.oauth.iniciar', $this->record->id))
                ->visible(fn () => !$this->record->tieneGmailConectado()),

            Actions\Action::make('desconectar_gmail')
                ->label(fn () => 'Gmail: ' . $this->record->google_oauth_email)
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
                ->visible(fn () => $this->record->tieneGmailConectado()),
        ];
    }
}
