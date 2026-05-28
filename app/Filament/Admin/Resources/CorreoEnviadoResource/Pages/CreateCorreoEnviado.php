<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use App\Jobs\EnviarCorreoOficialJob;
use App\Models\CorreoEnviado;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCorreoEnviado extends CreateRecord
{
    protected static string $resource = CorreoEnviadoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token']       = CorreoEnviado::generarToken();
        $data['enviado_por'] = Auth::id();
        $data['estado']      = 'pendiente';

        return $data;
    }

    protected function afterCreate(): void
    {
        $correo = $this->record;

        EnviarCorreoOficialJob::dispatch($correo);

        Notification::make()
            ->success()
            ->title('Correo en cola de envío')
            ->body("El correo para {$correo->destinatario_nombre} ha sido programado para envío.")
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Notificación manejada en afterCreate()
    }
}
