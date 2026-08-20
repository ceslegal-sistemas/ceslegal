<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Services\SolicitudContratoIAService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditModificacionContractual extends EditRecord
{
    protected static string $resource = ModificacionContractualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
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

    public function redactarOtrosiConIA(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($data);

        $texto = app(SolicitudContratoIAService::class)->redactarOtrosi($this->record);
        $this->data['texto_otrosi_redactado'] = $texto;

        Notification::make()->success()->title('Otrosí redactado')->send();
    }

    public function generarOtrosiAction(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($data);

        app(SolicitudContratoIAService::class)->generarOtrosiPDF($this->record);

        $this->refreshFormData(['estado', 'ruta_otrosi', 'fecha_generacion_otrosi']);

        Notification::make()->success()->title('Otrosí generado')->send();
    }
}
