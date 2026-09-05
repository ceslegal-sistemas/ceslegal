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

    // Vista custom: oculta el stepper nativo de Filament (el wizard usa su
    // propio encabezado de paso con barra de progreso, el Paso Bienvenida ya
    // cumple el rol del hero) - mismo patrón que
    // CreateProcesoDisciplinario (wizard de Crear Citación de Descargos).
    protected static string $view = 'filament.admin.resources.solicitud-contrato-resource.pages.create-solicitud-contrato';

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

            $resultado = $service->generarContratoPDF($this->record, borrador: true);

            Notification::make()
                ->success()
                ->title('Borrador generado')
                ->body(SolicitudContratoResource::mensajeOrigenFaltasGraves($resultado['faltas_graves_origen'])
                    . ' Apruébalo o recházalo desde la fila resaltada abajo.')
                ->send();
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

    /**
     * Tras crear, Filament por defecto redirige a la página "Ver" del
     * contrato - ahí el único botón visible es "Editar" (Aprobar/Rechazar
     * solo existen como Table Actions en el listado), así que el cliente
     * quedaba sin ninguna pista de qué hacer y terminaba entrando a
     * "Editar" por descarte (hallazgo real del jefe, 2026-09-04). Se
     * redirige directo al listado, donde sí puede aprobar o rechazar, y se
     * deja el id en sesión para resaltar la fila nueva - ver
     * SolicitudContratoResource::table()::recordClasses().
     */
    protected function getRedirectUrl(): string
    {
        session()->flash('solicitud_contrato_recien_creada', $this->record->id);

        return static::getResource()::getUrl('index');
    }
}
