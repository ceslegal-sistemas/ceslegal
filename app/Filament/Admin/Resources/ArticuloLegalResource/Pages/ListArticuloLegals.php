<?php

namespace App\Filament\Admin\Resources\ArticuloLegalResource\Pages;

use App\Filament\Admin\Resources\ArticuloLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticuloLegals extends ListRecords
{
    protected static string $resource = ArticuloLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
