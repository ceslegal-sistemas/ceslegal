<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModificacionContractuals extends ListRecords
{
    protected static string $resource = ModificacionContractualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
