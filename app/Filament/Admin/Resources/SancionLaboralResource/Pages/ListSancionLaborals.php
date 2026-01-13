<?php

namespace App\Filament\Admin\Resources\SancionLaboralResource\Pages;

use App\Filament\Admin\Resources\SancionLaboralResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSancionLaborals extends ListRecords
{
    protected static string $resource = SancionLaboralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
