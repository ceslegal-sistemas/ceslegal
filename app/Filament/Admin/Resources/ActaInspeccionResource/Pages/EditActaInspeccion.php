<?php

namespace App\Filament\Admin\Resources\ActaInspeccionResource\Pages;

use App\Filament\Admin\Resources\ActaInspeccionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActaInspeccion extends EditRecord
{
    protected static string $resource = ActaInspeccionResource::class;

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
