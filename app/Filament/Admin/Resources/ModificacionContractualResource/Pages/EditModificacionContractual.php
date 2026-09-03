<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Filament\Admin\Resources\ModificacionContractualResource\Widgets\ModificacionContractualRecordHeroWidget;
use App\Services\SolicitudContratoIAService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditModificacionContractual extends EditRecord
{
    protected static string $resource = ModificacionContractualResource::class;

    // Vista custom: oculta el stepper nativo de Filament - mismo patrón que
    // EditSolicitudContrato.
    protected static string $view = 'filament.admin.resources.modificacion-contractual-resource.pages.edit-modificacion-contractual';

    protected function getHeaderWidgets(): array
    {
        return [
            ModificacionContractualRecordHeroWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * El Wizard ya trae su propio botón de envío (submitAction en
     * ModificacionContractualResource::form()) - sin esto, Filament añade
     * ADEMÁS "Guardar cambios"/"Cancelar" por fuera del wizard, duplicando
     * la acción de envío. Mismo fix que CreateModificacionContractual ya
     * tenía; faltaba en Editar (bug preexistente, no reportado hasta ahora
     * porque nadie había puesto la vista custom aquí todavía).
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /** Mismo patrón ya documentado en EditSolicitudContrato.php - $this->form->getState() valida TODO el formulario. */
    private function obtenerEstadoValidadoOFallar(): ?array
    {
        try {
            return $this->form->getState();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Complete los campos requeridos primero')
                ->body('Hay campos obligatorios sin diligenciar (Contrato a Modificar o El Cambio).')
                ->send();

            return null;
        }
    }

    /**
     * El Wizard completo (incluido tipo_modificacion) es editable en esta
     * página - sin recalcular acá, valor_anterior quedaba pegado al tipo
     * con el que se creó el registro originalmente, aunque el usuario
     * cambiara de "salario" a "cargo" antes de guardar. Ese dato
     * desactualizado terminaba redactado tal cual en el otrosí real.
     */
    private function recalcularValorAnterior(array $data): array
    {
        $data['valor_anterior'] = ModificacionContractualResource::calcularValorAnterior(
            $data['solicitud_contrato_id'] ?? $this->record->solicitud_contrato_id,
            $data['tipo_modificacion'] ?? $this->record->tipo_modificacion,
        );

        return $data;
    }

    public function redactarOtrosiConIA(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($this->recalcularValorAnterior($data));

        try {
            $texto = app(SolicitudContratoIAService::class)->redactarOtrosi($this->record);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo redactar el otrosí')
                ->body('Intente de nuevo en unos minutos. Si el problema persiste, contacte a soporte.')
                ->send();

            return;
        }

        $this->data['texto_otrosi_redactado'] = $texto;

        Notification::make()->success()->title('Otrosí redactado')->send();
    }

    public function generarOtrosiAction(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($this->recalcularValorAnterior($data));

        try {
            app(SolicitudContratoIAService::class)->generarOtrosiPDF($this->record);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo generar el PDF del otrosí')
                ->body('Intente de nuevo en unos minutos. Si el problema persiste, contacte a soporte.')
                ->send();

            return;
        }

        $this->refreshFormData(['estado', 'ruta_otrosi', 'fecha_generacion_otrosi', 'valor_anterior']);

        Notification::make()->success()->title('Otrosí generado')->send();
    }
}
