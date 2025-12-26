<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiligenciaDescargo extends EditRecord
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
