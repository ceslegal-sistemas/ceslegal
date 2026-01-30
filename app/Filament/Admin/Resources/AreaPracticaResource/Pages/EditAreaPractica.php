<?php

namespace App\Filament\Admin\Resources\AreaPracticaResource\Pages;

use App\Filament\Admin\Resources\AreaPracticaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAreaPractica extends EditRecord
{
    protected static string $resource = AreaPracticaResource::class;

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
