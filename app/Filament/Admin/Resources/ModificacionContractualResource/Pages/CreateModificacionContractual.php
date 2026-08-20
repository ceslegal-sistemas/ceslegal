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
}
