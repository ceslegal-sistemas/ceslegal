<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Models\SolicitudContrato;
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
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $solicitud = SolicitudContrato::find($data['solicitud_contrato_id']);

        $campoPorTipo = [
            'salario'       => 'salario_propuesto',
            'cargo'         => 'cargo_contrato',
            'jornada'       => 'jornada',
            'tipo_contrato' => 'tipo_contrato',
        ];

        $campo = $campoPorTipo[$data['tipo_modificacion']] ?? null;
        $data['valor_anterior'] = $campo ? (string) $solicitud?->{$campo} : null;

        return $data;
    }
}
