<?php

namespace App\Filament\Admin\Resources\TrabajadorResource\Pages;

use App\Filament\Admin\Resources\TrabajadorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTrabajador extends CreateRecord
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tutorial')
                ->label('¿Necesitas ayuda?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'data-tour' => 'help-button-trabajadores',
                    'onclick' => 'window.iniciarTour(); return false;',
                ]),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->hidden();
    }
}
