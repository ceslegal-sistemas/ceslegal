<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoRecordHeroWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSolicitudContrato extends ViewRecord
{
    protected static string $resource = SolicitudContratoResource::class;

    // Vista custom: mismo lenguaje visual (.rit-info-card/.rit-viewer) que
    // "Mi Reglamento Interno" - los Sections genéricos de Filament Infolist
    // no se parecían en nada al resto del ecosistema (reportado por el
    // usuario con captura). El cuerpo se arma a mano en la vista, leyendo
    // $record directo, sin pasar por infolist()/form().
    protected static string $view = 'filament.admin.resources.solicitud-contrato-resource.pages.view-solicitud-contrato';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SolicitudContratoRecordHeroWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }
}
