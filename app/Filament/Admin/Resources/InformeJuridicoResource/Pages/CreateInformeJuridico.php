<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInformeJuridico extends CreateRecord
{
    protected static string $resource = InformeJuridicoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
