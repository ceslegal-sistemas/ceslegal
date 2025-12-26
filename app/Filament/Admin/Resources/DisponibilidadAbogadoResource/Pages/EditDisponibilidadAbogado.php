<?php

namespace App\Filament\Admin\Resources\DisponibilidadAbogadoResource\Pages;

use App\Filament\Admin\Resources\DisponibilidadAbogadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDisponibilidadAbogado extends EditRecord
{
    protected static string $resource = DisponibilidadAbogadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
