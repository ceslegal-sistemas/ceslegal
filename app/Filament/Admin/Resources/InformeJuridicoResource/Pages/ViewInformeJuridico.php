<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInformeJuridico extends ViewRecord
{
    protected static string $resource = InformeJuridicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
