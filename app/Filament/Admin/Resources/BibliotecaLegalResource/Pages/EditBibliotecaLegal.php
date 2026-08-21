<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBibliotecaLegal extends EditRecord
{
    protected static string $resource = BibliotecaLegalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Si se reemplaza el archivo de un documento ya procesado, resetear estado a
     * 'pendiente' - sin esto, DocumentoLegalObserver nunca se entera del cambio
     * (exige wasChanged('estado')) y el contenido nuevo queda huérfano: nunca se
     * re-extrae, re-fragmenta, ni se vuelve a comparar contra ningún RIT. Mismo
     * reseteo que ya hace la acción explícita "Reprocesar" de la tabla/listado.
     */
    protected bool $archivoReemplazado = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            array_key_exists('archivo_path', $data)
            && $data['archivo_path'] !== $this->record->archivo_path
        ) {
            $this->archivoReemplazado = true;
            $data['estado'] = 'pendiente';
            $data['error_mensaje'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->archivoReemplazado) {
            \App\Jobs\ProcesarBibliotecaLegal::dispatch($this->record);

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Documento actualizado - reprocesando')
                ->body('Se reemplazó el archivo, así que se está volviendo a extraer y analizar el contenido en segundo plano.')
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previsualizar')
                ->label('Previsualizar')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn() => !empty($this->record->archivo_path))
                ->modalHeading(fn() => $this->record->titulo)
                ->modalWidth(\Filament\Support\Enums\MaxWidth::SevenExtraLarge)
                ->modalContent(fn() => BibliotecaLegalResource::buildPreviewContent($this->record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            Actions\Action::make('descargar')
                ->label('Descargar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn() => !empty($this->record->archivo_path))
                ->url(fn() => route('biblioteca.descargar', $this->record))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->label('Eliminar documento'),
        ];
    }
}
