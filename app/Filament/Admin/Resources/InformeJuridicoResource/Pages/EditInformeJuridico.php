<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInformeJuridico extends EditRecord
{
    protected static string $resource = InformeJuridicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
