<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDiligenciaDescargos extends ListRecords
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
