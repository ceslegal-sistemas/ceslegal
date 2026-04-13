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
            Actions\DeleteAction::make()
                ->label('Eliminar documento'),
        ];
    }
}
