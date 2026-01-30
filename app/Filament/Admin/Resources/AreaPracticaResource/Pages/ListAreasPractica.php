<?php

namespace App\Filament\Admin\Resources\AreaPracticaResource\Pages;

use App\Filament\Admin\Resources\AreaPracticaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAreasPractica extends ListRecords
{
    protected static string $resource = AreaPracticaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
