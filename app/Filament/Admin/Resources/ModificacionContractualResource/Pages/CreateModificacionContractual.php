<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModificacionContractual extends CreateRecord
{
    protected static string $resource = ModificacionContractualResource::class;

    /**
     * El Wizard no trae su propio botón de envío en este Resource (a
     * diferencia de SolicitudContratoResource) - Filament añade además los
     * botones "Crear"/"Cancelar" por fuera del wizard por defecto, mismo
     * fix ya aplicado a CreateSolicitudContrato esta sesión.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * valor_anterior no lo edita el usuario directamente - se captura del
     * dato vigente en el contrato seleccionado, según el tipo_modificacion
     * elegido (ej. "salario" -> salario_propuesto). Sin esto quedaría
     * siempre null y "Antes"/"Después" en la tabla no tendría sentido.
     * abogado_id: quien crea la modificación, para el rastro de auditoría
     * (no hay campo en el form - se captura solo, igual que en otros
     * flujos de este proyecto donde el usuario actual = responsable).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['valor_anterior'] = ModificacionContractualResource::calcularValorAnterior(
            $data['solicitud_contrato_id'] ?? null,
            $data['tipo_modificacion'] ?? null,
        );
        $data['abogado_id'] = auth()->id();

        return $data;
    }
}
