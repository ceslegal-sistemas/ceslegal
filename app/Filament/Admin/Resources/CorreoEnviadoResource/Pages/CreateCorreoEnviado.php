<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use App\Models\CorreoEnviado;
use App\Services\CorreoOficialSender;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * Envío SÍNCRONO: primero se envía el correo y SOLO si el envío fue exitoso
     * se registra en la base de datos. Si el correo está mal o el envío falla,
     * NO se crea ningún registro (el usuario permanece en el formulario).
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Modelo en memoria (aún NO persistido) para construir y enviar el correo.
        $correo = new CorreoEnviado($data);

        try {
            $via = app(CorreoOficialSender::class)->send($correo);
        } catch (\Throwable $e) {
            Log::error('Envío síncrono de correo oficial falló — no se registra', [
                'destinatario' => $data['email_destinatario'] ?? null,
                'error'        => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se pudo enviar el correo')
                ->body(
                    'El correo NO fue enviado y NO se registró. Verifica que el email del '
                    . 'destinatario sea correcto e inténtalo de nuevo. Detalle: '
                    . Str::limit($e->getMessage(), 160)
                )
                ->persistent()
                ->send();

            // Detiene la creación y revierte la transacción: el registro nunca se guarda.
            $this->halt(true);
        }

        // Envío exitoso → recién ahora persistimos el correo.
        $correo->enviado_en = now('America/Bogota');
        $correo->save();

        Log::info('Correo oficial enviado (síncrono)', [
            'correo_id'    => $correo->id,
            'destinatario' => $correo->email_destinatario,
            'via'          => $via,
        ]);

        return $correo;
    }

    protected function afterCreate(): void
    {
        $correo = $this->record;

        Notification::make()
            ->success()
            ->title('Correo enviado')
            ->body("El correo para {$correo->destinatario_nombre} fue enviado correctamente.")
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Notificación manejada en afterCreate()
    }
}
