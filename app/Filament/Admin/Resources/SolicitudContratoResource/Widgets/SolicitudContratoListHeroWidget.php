<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Widgets;

use App\Models\SolicitudContrato;
use Filament\Widgets\Widget;

/**
 * Hero de marca sobre el listado de Solicitudes de Contrato - mismo lenguaje
 * visual (.rit-*) que "Mi Reglamento Interno", a pedido explícito del
 * usuario. Los conteos respetan el scope global de SolicitudContrato
 * (ScopedToBufeteOrEmpresa) automáticamente, igual que el resto de la tabla.
 */
class SolicitudContratoListHeroWidget extends Widget
{
    protected static string $view = 'filament.admin.resources.solicitud-contrato-resource.widgets.list-hero';

    protected int | string | array $columnSpan = 'full';

    /** Sin esto Filament difiere el render a una 2da petición AJAX (lazy widget por defecto) - el hero aparecería con un parpadeo vacío en vez de estar listo de inmediato. */
    protected static bool $isLazy = false;

    public function getConteos(): array
    {
        return [
            'total'     => SolicitudContrato::count(),
            'borrador'  => SolicitudContrato::where('estado', 'borrador')->count(),
            'aprobado'  => SolicitudContrato::where('estado', 'aprobado')->count(),
            'rechazado' => SolicitudContrato::where('estado', 'rechazado')->count(),
        ];
    }
}
