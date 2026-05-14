<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use App\Services\BibliotecaLegalService;
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
        // No se procesa automáticamente aquí para evitar timeouts en hosting compartido.
        // El usuario debe usar el botón "Procesar" en la lista, o el comando artisan:
        //   php artisan biblioteca:procesar {id}
        Notification::make()
            ->success()
            ->title('Documento guardado')
            ->body('Usa el botón "Procesar" en la lista para generar los fragmentos y embeddings.')
            ->send();
    }
}
