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
        $documento = $this->record;

        // Procesar automáticamente al crear
        try {
            app(BibliotecaLegalService::class)->procesarDocumento($documento);
            $documento->refresh();

            Notification::make()
                ->success()
                ->title('Documento procesado')
                ->body("{$documento->total_fragmentos} fragmentos generados correctamente.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->warning()
                ->title('Documento subido con error de procesamiento')
                ->body('El archivo fue guardado pero ocurrió un error al procesar: ' . $e->getMessage() . '. Puede intentar de nuevo desde la lista.')
                ->send();
        }
    }
}
