<?php

namespace App\Filament\Admin\Resources\ActividadEconomicaResource\Pages;

use App\Filament\Admin\Resources\ActividadEconomicaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActividadEconomica extends CreateRecord
{
    protected static string $resource = ActividadEconomicaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
