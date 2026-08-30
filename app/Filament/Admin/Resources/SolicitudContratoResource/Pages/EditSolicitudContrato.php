<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Concerns\CompletaDetallesCargoConIA;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoRecordHeroWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSolicitudContrato extends EditRecord
{
    use CompletaDetallesCargoConIA;

    protected static string $resource = SolicitudContratoResource::class;

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

    /**
     * Rechazar/Finalizar (hoy Aprobar/Rechazar/Regenerar Borrador) viven
     * como Table Actions en el listado desde este mismo día - ver
     * SolicitudContratoResource::table(). redactarObjetoConIA() y
     * generarContratoAction() (con su helper obtenerEstadoValidadoOFallar())
     * se retiraron: el flujo es automático ahora
     * (CreateSolicitudContrato::afterCreate()).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
