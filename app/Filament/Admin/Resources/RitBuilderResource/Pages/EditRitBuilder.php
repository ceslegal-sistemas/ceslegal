<?php

namespace App\Filament\Admin\Resources\RitBuilderResource\Pages;

use App\Filament\Admin\Resources\RitBuilderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRitBuilder extends EditRecord
{
    protected static string $resource = RitBuilderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
