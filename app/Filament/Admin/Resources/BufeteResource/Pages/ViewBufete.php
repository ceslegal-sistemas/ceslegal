<?php

namespace App\Filament\Admin\Resources\BufeteResource\Pages;

use App\Filament\Admin\Resources\BufeteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBufete extends ViewRecord
{
    protected static string $resource = BufeteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
