<?php

namespace App\Filament\Admin\Resources\JurisprudenciaResource\Pages;

use App\Filament\Admin\Resources\JurisprudenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJurisprudencia extends EditRecord
{
    protected static string $resource = JurisprudenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
