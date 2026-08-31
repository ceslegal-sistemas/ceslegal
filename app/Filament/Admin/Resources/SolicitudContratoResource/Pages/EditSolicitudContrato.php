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

    // Vista custom: oculta el stepper nativo de Filament (mismo motivo/
    // mecanismo que CreateSolicitudContrato) - el wizard usa su propio
    // step-header de marca, mostrar ambos era redundante y no se veía como
    // el resto del ecosistema (bug real reportado por el usuario).
    protected static string $view = 'filament.admin.resources.solicitud-contrato-resource.pages.edit-solicitud-contrato';

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
     * El Wizard ya trae su propio botón de envío (submitAction en
     * SolicitudContratoResource::form(), ahora dice "Guardar Cambios" al
     * editar) - sin esto, Filament añade ADEMÁS "Guardar cambios"/"Cancelar"
     * por fuera del wizard, duplicando la acción de envío. Mismo fix que ya
     * tiene CreateSolicitudContrato::getFormActions() para el mismo problema.
     */
    protected function getFormActions(): array
    {
        return [];
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
