<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInformesJuridicos extends ListRecords
{
    protected static string $resource = InformeJuridicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Informe'),
        ];
    }
}
