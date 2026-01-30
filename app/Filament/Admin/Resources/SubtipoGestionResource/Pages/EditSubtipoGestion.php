<?php

namespace App\Filament\Admin\Resources\SubtipoGestionResource\Pages;

use App\Filament\Admin\Resources\SubtipoGestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubtipoGestion extends EditRecord
{
    protected static string $resource = SubtipoGestionResource::class;

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
