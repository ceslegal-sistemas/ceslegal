<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Concerns;

use App\Models\SolicitudContrato;
use App\Services\SolicitudContratoIAService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Compartido entre CreateSolicitudContrato y EditSolicitudContrato: el
 * botón "Completar con IA" del paso "Detalles del Cargo" debe funcionar
 * también en creación, ANTES de que exista un $this->record - por eso lee
 * directo de $this->data (estado vivo del formulario) en vez de usar
 * $this->form->getState(), que validaría TODO el formulario y fallaría
 * exactamente en los 3 campos que esta acción va a llenar (son
 * ->required() y están vacíos en el momento de llamar la IA).
 */
trait CompletaDetallesCargoConIA
{
    public function completarDetallesConIA(): void
    {
        $cargo = $this->data['cargo_contrato'] ?? null;
        if ($cargo === '__otro__') {
            $cargo = $this->data['cargo_otro'] ?? null;
        }

        $faltaEmpresa = empty($this->data['empresa_id']);
        $faltaCargo   = empty($cargo);

        if ($faltaEmpresa || $faltaCargo) {
            // Mensaje dinámico según lo que REALMENTE falte - antes siempre
            // decía "empresa y cargo" aunque solo faltara uno de los dos,
            // obligando al cliente a adivinar cuál de los 2 pasos revisar.
            [$titulo, $cuerpo] = match (true) {
                $faltaEmpresa && $faltaCargo => [
                    'Falta la empresa y el cargo',
                    'Vuelva al Paso 1 y seleccione la empresa, y al Paso 3 elija el cargo. Luego podrá usar "Completar con IA".',
                ],
                $faltaEmpresa => [
                    'Falta seleccionar la empresa',
                    'Vuelva al Paso 1 y seleccione la empresa para la cual se solicita el contrato. Luego podrá usar "Completar con IA".',
                ],
                default => [
                    'Falta seleccionar el cargo',
                    'Elija un cargo de la lista (o escriba uno personalizado en "Otro") antes de usar "Completar con IA".',
                ],
            };

            Notification::make()
                ->danger()
                ->title($titulo)
                ->body($cuerpo)
                ->send();

            return;
        }

        $solicitudTemporal = new SolicitudContrato([
            'empresa_id'           => $this->data['empresa_id'],
            'tipo_contrato'        => $this->data['tipo_contrato'] ?? null,
            'cargo_contrato'       => $cargo,
            'trabajador_nombres'   => $this->data['trabajador_nombres'] ?? null,
            'trabajador_apellidos' => $this->data['trabajador_apellidos'] ?? null,
        ]);

        // Sin este try/catch, CUALQUIER falla de la IA (límite de gasto de
        // Gemini agotado, red caída, timeout) reventaba con la pantalla de
        // error cruda de Laravel en producción en vez de un aviso legible -
        // bug real reportado por el usuario (RESOURCE_EXHAUSTED de Gemini).
        // Mismo criterio de manejo de errores que ya usan guardar()/
        // afterCreate() para las otras 2 llamadas a IA de este mismo wizard.
        try {
            $detalles = app(SolicitudContratoIAService::class)->completarDetallesCargo($solicitudTemporal);
        } catch (\Throwable $e) {
            Log::error('SolicitudContrato: falló "Completar con IA"', [
                'empresa_id' => $this->data['empresa_id'],
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se pudo completar con IA')
                ->body('El servicio de IA no respondió correctamente. Intente de nuevo en unos minutos, o complete estos campos manualmente.')
                ->send();

            return;
        }

        foreach (['responsabilidades', 'objeto_comercial', 'manual_funciones'] as $campo) {
            if (filled($detalles[$campo] ?? null)) {
                $this->data[$campo] = $detalles[$campo];
            }
        }

        Notification::make()
            ->success()
            ->title('Detalles del cargo completados con IA')
            ->body('Revise y edite el contenido antes de continuar.')
            ->send();
    }
}
