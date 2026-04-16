<?php

namespace App\Filament\Admin\Resources\ActaInspeccionResource\Pages;

use App\Filament\Admin\Resources\ActaInspeccionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActaInspeccions extends ListRecords
{
    protected static string $resource = ActaInspeccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Acta'),
        ];
    }
}
