<?php

namespace App\Filament\Admin\Resources\ActaInspeccionResource\Pages;

use App\Filament\Admin\Resources\ActaInspeccionResource;
use App\Models\ActaInspeccion;
use Filament\Resources\Pages\CreateRecord;

class CreateActaInspeccion extends CreateRecord
{
    protected static string $resource = ActaInspeccionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id']     = auth()->id();
        $data['numero_acta'] = ActaInspeccion::generarNumero($data['empresa_id']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
