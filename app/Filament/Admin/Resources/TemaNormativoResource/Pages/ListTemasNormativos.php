<?php

namespace App\Filament\Admin\Resources\TemaNormativoResource\Pages;

use App\Filament\Admin\Resources\TemaNormativoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTemasNormativos extends ListRecords
{
    protected static string $resource = TemaNormativoResource::class;

    /**
     * REQUERIDO: la clase base ListRecords::authorizeAccess() está VACÍA
     * - canViewAny() de la Policy solo esconde el ítem del menú, NO
     * bloquea la URL directa. A diferencia de CreateRecord/EditRecord
     * (que sí llaman abort_unless() en su propia authorizeAccess()), List
     * necesita esta sobreescritura a mano (gotcha ya documentado en este
     * proyecto, memoria filament-listrecords-authorizeaccess-vacio).
     */
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
