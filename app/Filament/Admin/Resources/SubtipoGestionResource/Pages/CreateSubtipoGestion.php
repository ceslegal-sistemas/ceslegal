<?php

namespace App\Filament\Admin\Resources\SubtipoGestionResource\Pages;

use App\Filament\Admin\Resources\SubtipoGestionResource;
use App\Models\SubtipoGestion;
use Filament\Resources\Pages\CreateRecord;

class CreateSubtipoGestion extends CreateRecord
{
    protected static string $resource = SubtipoGestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = SubtipoGestion::max('orden') + 1;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
