<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCorreosEnviados extends ListRecords
{
    protected static string $resource = CorreoEnviadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Redactar correo'),
        ];
    }
}
