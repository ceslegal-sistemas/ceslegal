<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDiligenciaDescargo extends ViewRecord
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
