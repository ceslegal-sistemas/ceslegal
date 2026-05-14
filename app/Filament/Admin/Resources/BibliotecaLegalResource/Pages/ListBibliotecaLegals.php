<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBibliotecaLegals extends ListRecords
{
    protected static string $resource = BibliotecaLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Subir documento')
                ->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}
