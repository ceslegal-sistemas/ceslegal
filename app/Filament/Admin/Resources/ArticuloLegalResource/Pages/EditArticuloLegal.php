<?php

namespace App\Filament\Admin\Resources\ArticuloLegalResource\Pages;

use App\Filament\Admin\Resources\ArticuloLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticuloLegal extends EditRecord
{
    protected static string $resource = ArticuloLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
