<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Concerns\CompletaDetallesCargoConIA;
use App\Services\SolicitudContratoIAService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateSolicitudContrato extends CreateRecord
{
    use CompletaDetallesCargoConIA;

    protected static string $resource = SolicitudContratoResource::class;

    /**
     * El Wizard ya trae su propio botón "Crear Solicitud" (submitAction en
     * SolicitudContratoResource::form()) - sin esto, Filament añade además
     * los botones "Crear"/"Cancelar" por fuera del wizard, duplicando la
     * acción de envío.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Genera el borrador automáticamente apenas se crea la solicitud - el
     * cliente no debe tener que entrar a "Editar" ni presionar botones
     * manuales para ver su contrato (pedido explícito del usuario:
     * "pongámonos en los zapatos del cliente... nuestro trabajo es
     * guiarlo paso a paso, sin tener que hacerlo ir de un lado a otro").
     *
     * Los 4 pasos del wizard ya están completos y validados en este punto
     * (Filament no permite crear el registro si el form no pasa
     * validación), así que todos los datos que necesitan
     * redactarObjetoJuridico()/generarContratoPDF() ya existen.
     */
    protected function afterCreate(): void
    {
        try {
            $service = app(SolicitudContratoIAService::class);

            if (empty($this->record->objeto_juridico_redactado)) {
                $texto = $service->redactarObjetoJuridico($this->record);
                $this->record->update(['objeto_juridico_redactado' => $texto]);
            }

            if ($this->record->tipo_contrato === 'Contrato de Obra o Labor'
                && empty($this->record->duracion_terminacion_obra_redactada)) {
                $duracionTerminacion = $service->redactarDuracionTerminacionObraLabor($this->record);
                $this->record->update(['duracion_terminacion_obra_redactada' => $duracionTerminacion]);
            }

            $service->generarContratoPDF($this->record, borrador: true);
        } catch (\Throwable $e) {
            Log::error('SolicitudContrato: falló la generación automática del borrador', [
                'solicitud_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);

            // CRÍTICO: sin este update(), el registro se queda con el
            // DEFAULT viejo de la columna ('pendiente' antes de la Task 1
            // de este plan) - un valor retirado, invisible a las 3 Table
            // Actions nuevas (todas exigen 'borrador'). Sin esto, el
            // registro queda en un callejón sin salida solo resoluble
            // editando la BD a mano.
            $this->record->update(['estado' => 'borrador']);

            Notification::make()
                ->warning()
                ->title('La solicitud se creó, pero el borrador no se pudo generar automáticamente')
                ->body('Use "Regenerar Borrador" desde el listado para intentarlo de nuevo.')
                ->send();
        }
    }
}
