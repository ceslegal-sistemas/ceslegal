<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDiligenciaDescargo extends CreateRecord
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Para diligencias virtuales, fecha_diligencia = fecha_acceso_permitida
        if (($data['lugar_diligencia'] ?? '') === 'virtual' && empty($data['fecha_diligencia'])) {
            $data['fecha_diligencia'] = $data['fecha_acceso_permitida'] ?? now();
        }

        return $data;
    }
}
