<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use App\Services\GoogleOAuthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListCorreosEnviados extends ListRecords
{
    protected static string $resource = CorreoEnviadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Redactar correo'),

            Actions\Action::make('conectar_gmail')
                ->label('Conecta tu Gmail')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->url(function () {
                    $user = Auth::user();
                    return app(GoogleOAuthService::class)->buildAuthUrl($user?->id);
                })
                ->visible(function () {
                    $user = Auth::user();
                    return !($user?->google_oauth_tokens ?? null);
                }),

            Actions\Action::make('desconectar_gmail')
                ->label(function () {
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
                ->visible(function () {
                    $user = Auth::user();
                    return (bool) ($user?->google_oauth_tokens ?? null);
                }),
        ];
    }

    /**
     * Filtrar correos según el rol del usuario:
     * - Super Admin: ve TODOS los correos
     * - Abogado: ve TODOS los correos
     * - Cliente: ve SOLO los correos que él registró/envió
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();

        // Si es cliente, filtrar solo los correos que él envió
        if ($user->role === 'cliente') {
            return $query->where('enviado_por', $user->id);
        }

        // Super admin y abogado ven todos los correos
        return $query;
    }
}
