<?php

namespace App\Filament\Admin\Resources\TipoGestionResource\Pages;

use App\Filament\Admin\Resources\TipoGestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoGestion extends EditRecord
{
    protected static string $resource = TipoGestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
