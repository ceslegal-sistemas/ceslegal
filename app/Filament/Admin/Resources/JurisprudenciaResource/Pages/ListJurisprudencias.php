<?php

namespace App\Filament\Admin\Resources\JurisprudenciaResource\Pages;

use App\Filament\Admin\Resources\JurisprudenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJurisprudencias extends ListRecords
{
    protected static string $resource = JurisprudenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Agregar manualmente'),
        ];
    }
}
