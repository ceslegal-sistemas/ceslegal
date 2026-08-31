<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudContratos extends ListRecords
{
    protected static string $resource = SolicitudContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    // Sin header widgets: el hero "Borrador con IA" (SolicitudContratoListHeroWidget)
    // se retiró a pedido del usuario - aparecía y desaparecía al cargar la
    // página (reportado, nunca reproducido de forma concluyente; ver
    // list-hero.blade.php si se retoma la investigación del parpadeo). Ahora
    // "Crear Solicitud de Contrato"/"Historial de Contratos" son 2 items de
    // navegación separados (ver SolicitudContratoResource::getNavigationItems()),
    // el hero ya no aporta nada que esos 2 enlaces no den por sí solos.
}
