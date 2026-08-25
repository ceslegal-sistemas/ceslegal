<?php

namespace App\Filament\Admin\Resources\TemaNormativoResource\Pages;

use App\Filament\Admin\Resources\TemaNormativoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemaNormativo extends EditRecord
{
    protected static string $resource = TemaNormativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
