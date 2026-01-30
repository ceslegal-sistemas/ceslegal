<?php

namespace App\Filament\Admin\Resources\AreaPracticaResource\Pages;

use App\Filament\Admin\Resources\AreaPracticaResource;
use App\Models\AreaPractica;
use Filament\Resources\Pages\CreateRecord;

class CreateAreaPractica extends CreateRecord
{
    protected static string $resource = AreaPracticaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = AreaPractica::max('orden') + 1;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
