<?php

namespace App\Filament\Admin\Resources\SancionLaboralResource\Pages;

use App\Filament\Admin\Resources\SancionLaboralResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSancionLaboral extends EditRecord
{
    protected static string $resource = SancionLaboralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
