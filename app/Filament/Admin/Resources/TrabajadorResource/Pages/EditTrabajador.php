<?php

namespace App\Filament\Admin\Resources\TrabajadorResource\Pages;

use App\Filament\Admin\Resources\TrabajadorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrabajador extends EditRecord
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => static::getResource()::getUrl('index')),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
