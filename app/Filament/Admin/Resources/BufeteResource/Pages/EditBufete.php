<?php

namespace App\Filament\Admin\Resources\BufeteResource\Pages;

use App\Filament\Admin\Resources\BufeteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBufete extends EditRecord
{
    protected static string $resource = BufeteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
