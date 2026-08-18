<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Concerns;

use App\Models\SolicitudContrato;
use App\Services\SolicitudContratoIAService;
use Filament\Notifications\Notification;

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

        if (empty($this->data['empresa_id']) || empty($cargo)) {
            Notification::make()
                ->danger()
                ->title('Complete primero la empresa y el cargo')
                ->body('Seleccione la empresa (paso 1) y el cargo (paso 3) antes de completar con IA.')
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

        $detalles = app(SolicitudContratoIAService::class)->completarDetallesCargo($solicitudTemporal);

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
