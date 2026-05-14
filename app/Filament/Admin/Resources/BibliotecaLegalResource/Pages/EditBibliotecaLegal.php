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
