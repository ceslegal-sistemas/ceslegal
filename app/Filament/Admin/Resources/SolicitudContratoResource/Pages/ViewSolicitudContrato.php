<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoRecordHeroWidget;
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

    /** Ver SolicitudContratoResource::enVentanaDeDecisionRenovacion() - compartido con la tabla del listado. */
    private function enVentanaDeDecision(): bool
    {
        return SolicitudContratoResource::enVentanaDeDecisionRenovacion($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Vía general para cualquier cambio (salario, cargo, jornada,
            // tipo de contrato o plazo) mientras el contrato está aprobado.
            // Reemplaza a "Editar" como la única forma de modificar un
            // contrato ya aprobado, sin saltarse el rastro del Otrosí
            // (hallazgo real, 2026-09-02).
            //
            // Dentro de la ventana de 45 días de alerta, se relabela a "Sí,
            // renovar" (mismo modal, "Plazo" es una de las 5 opciones
            // adentro) - antes existía un botón "renovarContrato" separado
            // que apuntaba al wizard viejo de página completa, quedó
            // redundante con este mismo modal y se retiró (hallazgo del
            // propio usuario: "¿Osea Solicitar cambio y Sí, renovar es lo
            // mismo?").
            Actions\Action::make('solicitarCambio')
                ->label(fn () => $this->enVentanaDeDecision() ? 'Sí, renovar' : 'Solicitar un Cambio')
                ->icon(fn () => $this->enVentanaDeDecision() ? 'heroicon-o-arrow-path' : 'heroicon-o-pencil-square')
                ->color(fn () => $this->enVentanaDeDecision() ? 'success' : 'primary')
                ->visible(fn () => $this->record->estado === 'aprobado')
                ->modalWidth('lg')
                ->extraModalWindowAttributes(['class' => 'ces-hide-wizard-steps'])
                ->steps(fn () => ModificacionContractualResource::pasosSolicitarCambio($this->record))
                ->modalSubmitActionLabel('Confirmar y Generar Otrosí')
                ->action(function (array $data) {
                    ModificacionContractualResource::crearYGenerarOtrosi($this->record, $data);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Otrosí generado')
                        ->body('El documento quedó registrado en el historial de cambios del contrato.')
                        ->send();
                }),

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

            // Mismo hallazgo de la tabla de "Historial de Contratos"
            // (SolicitudContratoResource::table()): solo tiene sentido
            // editar libremente mientras sigue en 'borrador' - una vez
            // aprobado, "Solicitar un Cambio" (arriba) es el único camino.
            Actions\EditAction::make()
                ->label('Editar')
                ->visible(fn () => $this->record->estado === 'borrador'),

            // Aprobar/Rechazar (pedido explícito del usuario, 2026-09-28):
            // antes solo existían como Table Actions en el listado - al
            // entrar a "Ver" (ej. desde el menú "Ver" del listado), el
            // cliente solo veía "Editar" y no tenía forma de decidir sobre
            // el borrador sin volver atrás. Mismo cuerpo exacto que
            // SolicitudContratoResource::table() (aprobar/rechazar), para
            // no duplicar comportamiento con matices distintos.
            Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->estado === 'borrador')
                ->requiresConfirmation()
                ->modalDescription('Se generará el contrato final (protegido, sin marca de agua) y quedará aprobado.')
                ->action(function () {
                    $resultado = app(SolicitudContratoIAService::class)->generarContratoPDF($this->record, borrador: false);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Solicitud aprobada')
                        ->body(SolicitudContratoResource::mensajeOrigenFaltasGraves($resultado['faltas_graves_origen']))
                        ->send();
                }),

            Actions\Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->estado === 'borrador')
                ->modalHeading('Rechazar solicitud')
                ->modalDescription('Esta acción no se puede deshacer desde la interfaz. Indique el motivo para dejar constancia de por qué se rechazó.')
                ->modalSubmitActionLabel('Rechazar')
                ->form([
                    \Filament\Forms\Components\Textarea::make('motivo')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->minLength(5)
                        ->rows(3)
                        ->placeholder('Ej: El cargo propuesto no está aprobado en el presupuesto de este trimestre.'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'estado' => 'rechazado',
                        'motivo_rechazo' => $data['motivo'],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Solicitud rechazada')
                        ->send();
                }),
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
