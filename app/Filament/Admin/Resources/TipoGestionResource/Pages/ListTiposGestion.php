<?php

namespace App\Filament\Admin\Resources\TipoGestionResource\Pages;

use App\Filament\Admin\Resources\TipoGestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiposGestion extends ListRecords
{
    protected static string $resource = TipoGestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
