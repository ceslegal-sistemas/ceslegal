<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoRecordHeroWidget;
use App\Services\PlazoContratoService;
use App\Services\SolicitudContratoIAService;
use Filament\Actions;
use Filament\Notifications\Notification;
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

    /**
     * Solo tiene sentido ofrecer la decisión renovar/no-renovar mientras el
     * contrato está dentro de la ventana de alerta (45 días) y nadie ha
     * decidido todavía - ver PlazoContratoService, diseño confirmado con el
     * usuario para el módulo de vencimiento de contratos a término fijo.
     */
    private function enVentanaDeDecision(): bool
    {
        return $this->record->tipo_contrato === 'Contrato a Término Fijo'
            && app(PlazoContratoService::class)->estaEnVentanaDeAlerta($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('renovarContrato')
                ->label('Sí, renovar')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn () => $this->enVentanaDeDecision())
                ->url(fn () => ModificacionContractualResource::getUrl('create', [
                    'solicitud_contrato_id' => $this->record->id,
                    'tipo_modificacion' => 'plazo',
                ])),

            Actions\Action::make('noRenovarContrato')
                ->label('No renovar')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->visible(fn () => $this->enVentanaDeDecision())
                ->requiresConfirmation()
                ->modalHeading('Generar Preaviso de no renovación')
                ->modalDescription('Se generará el documento de preaviso y quedará registrado que decidió no renovar este contrato. Esta decisión se puede revertir manualmente si cambia de opinión antes del vencimiento.')
                ->modalSubmitActionLabel('Sí, generar Preaviso')
                ->action(function () {
                    app(SolicitudContratoIAService::class)->generarPreavisoPDF($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Preaviso generado')
                        ->body('Descárguelo y entréguelo al trabajador con al menos 30 días de anticipación al vencimiento.')
                        ->send();
                }),

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
