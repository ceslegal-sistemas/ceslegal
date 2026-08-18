<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Concerns\CompletaDetallesCargoConIA;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

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
}
