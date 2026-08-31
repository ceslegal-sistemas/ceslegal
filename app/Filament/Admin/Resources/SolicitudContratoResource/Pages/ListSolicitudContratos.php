<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoListHeroWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudContratos extends ListRecords
{
    protected static string $resource = SolicitudContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Crear Solicitud de Contrato')
                ->icon('heroicon-o-plus')
                ->url(fn () => \App\Filament\Admin\Pages\CrearSolicitudContrato::getUrl()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SolicitudContratoListHeroWidget::class,
        ];
    }
}
