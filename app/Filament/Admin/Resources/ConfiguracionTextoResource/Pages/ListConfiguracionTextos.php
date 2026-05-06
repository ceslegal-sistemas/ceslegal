<?php

namespace App\Filament\Admin\Resources\ConfiguracionTextoResource\Pages;

use App\Filament\Admin\Resources\ConfiguracionTextoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracionTextos extends ListRecords
{
    protected static string $resource = ConfiguracionTextoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
