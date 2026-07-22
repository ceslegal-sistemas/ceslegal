<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use App\Jobs\ProcesarBibliotecaLegal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBibliotecaLegal extends CreateRecord
{
    protected static string $resource = BibliotecaLegalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['estado'] = 'pendiente';
        return $data;
    }

    protected function afterCreate(): void
    {
        // Se encola de inmediato (cola 'gemini', misma que la auditoría de RIT) en vez de
        // esperar al próximo ciclo del cron (hasta 5 min) - el listado ya tiene ->poll('10s')
        // así que el usuario ve el cambio de estado sin recargar la página.
        ProcesarBibliotecaLegal::dispatch($this->record);

        Notification::make()
            ->success()
            ->title('Documento guardado - procesando')
            ->body('Extrayendo texto y generando fragmentos. El estado se actualizará solo en la lista.')
            ->send();
    }
}
