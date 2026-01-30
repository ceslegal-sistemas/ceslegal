<?php

namespace App\Filament\Admin\Resources\SubtipoGestionResource\Pages;

use App\Filament\Admin\Resources\SubtipoGestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubtiposGestion extends ListRecords
{
    protected static string $resource = SubtipoGestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
