<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Concerns\CompletaDetallesCargoConIA;
use App\Services\SolicitudContratoIAService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSolicitudContrato extends EditRecord
{
    use CompletaDetallesCargoConIA;

    protected static string $resource = SolicitudContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * $this->form->getState() valida el formulario COMPLETO (los 4 pasos de
     * la solicitud, no solo "Análisis Jurídico") - si algo falta en
     * cualquier sección, lanza ValidationException. Livewire la maneja
     * internamente (la convierte en estado de errores del form, NO la deja
     * propagar como excepción normal - mismo patrón ya documentado en este
     * proyecto para abort()), así que sin este catch los botones de IA
     * fallarían en silencio: no pasa nada visible, sin aviso al abogado.
     */
    private function obtenerEstadoValidadoOFallar(): ?array
    {
        try {
            return $this->form->getState();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Complete los campos requeridos primero')
                ->body('Hay campos obligatorios sin diligenciar en la solicitud (Información Básica, Datos del Trabajador, Detalles del Cargo o Documentos).')
                ->send();

            return null;
        }
    }

    /**
     * Guarda primero cualquier edición pendiente del formulario (para que el
     * borrador de la IA parta de los datos que el abogado realmente ve en
     * pantalla, no de la última versión guardada) y redacta un borrador del
     * objeto jurídico. El abogado sigue pudiendo editarlo libremente en el
     * RichEditor antes de guardar.
     */
    public function redactarObjetoConIA(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($data);

        $texto = app(SolicitudContratoIAService::class)->redactarObjetoJuridico($this->record);

        // $this->data (no $this->form->fill()) es el patrón directo ya
        // confirmado en este proyecto para actualizar un solo campo del
        // form en runtime sin re-hidratar todo el form completo.
        $this->data['objeto_juridico_redactado'] = $texto;

        Notification::make()
            ->success()
            ->title('Objeto jurídico redactado')
            ->send();
    }

    /**
     * Mismo principio: guarda primero cualquier edición pendiente (incluido
     * el objeto jurídico que el abogado haya ajustado a mano) y genera el
     * PDF final con esos datos - nunca con una versión desactualizada.
     */
    public function generarContratoAction(): void
    {
        $data = $this->obtenerEstadoValidadoOFallar();
        if ($data === null) {
            return;
        }
        $this->record->update($data);

        app(SolicitudContratoIAService::class)->generarContratoPDF($this->record);

        $this->refreshFormData(['estado', 'ruta_contrato', 'fecha_generacion_contrato']);

        Notification::make()
            ->success()
            ->title('Contrato generado')
            ->send();
    }
}
