<?php

namespace App\Filament\Admin\Resources\SolicitudCambioEmpresaResource\Pages;

use App\Filament\Admin\Resources\SolicitudCambioEmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListSolicitudesCambioEmpresa extends ListRecords
{
    protected static string $resource = SolicitudCambioEmpresaResource::class;

    // BUG REAL CONFIRMADO: la clase base Filament\Resources\Pages\ListRecords
    // define authorizeAccess() VACÍO por defecto - canViewAny() solo controla
    // si el ítem de navegación se muestra, NO si la página en sí bloquea el
    // acceso directo por URL. Sin este override, un cliente podía cargar esta
    // página con Livewire::test() (y por URL directa) aunque no tenga el
    // permiso 'view_any_solicitud::cambio::empresa'. Confirmado que ningún
    // otro Resource de este proyecto sobreescribe authorizeAccess() - este es
    // el primer caso donde de verdad se necesitaba bloquear la página entera,
    // no solo esconder el ítem del menú.
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }
}
