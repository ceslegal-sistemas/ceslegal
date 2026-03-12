<?php

namespace App\Filament\Admin\Resources\ActividadEconomicaResource\Pages;

use App\Filament\Admin\Resources\ActividadEconomicaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActividadesEconomicas extends ListRecords
{
    protected static string $resource = ActividadEconomicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva Actividad'),
        ];
    }
}
