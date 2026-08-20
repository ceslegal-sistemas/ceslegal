<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewModificacionContractual extends ViewRecord
{
    protected static string $resource = ModificacionContractualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar'),
        ];
    }
}
