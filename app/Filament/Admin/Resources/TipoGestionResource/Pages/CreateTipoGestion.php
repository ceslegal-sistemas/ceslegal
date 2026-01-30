<?php

namespace App\Filament\Admin\Resources\TipoGestionResource\Pages;

use App\Filament\Admin\Resources\TipoGestionResource;
use App\Models\TipoGestion;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoGestion extends CreateRecord
{
    protected static string $resource = TipoGestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = TipoGestion::max('orden') + 1;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
