<?php

namespace App\Filament\Admin\Resources\BufeteResource\Pages;

use App\Filament\Admin\Resources\BufeteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBufetes extends ListRecords
{
    protected static string $resource = BufeteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Crear bufete'),
        ];
    }
}
