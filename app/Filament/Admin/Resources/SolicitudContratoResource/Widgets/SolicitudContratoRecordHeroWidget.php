<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Widgets;

use App\Models\SolicitudContrato;
use Filament\Widgets\Widget;

/**
 * Hero de marca sobre Ver/Editar/Crear una Solicitud de Contrato - mismo
 * lenguaje visual (.rit-*) que "Mi Reglamento Interno". $record es null en
 * "Crear" (aún no existe el registro): la vista muestra una introducción
 * genérica en ese caso, en vez de intentar leer datos de un registro que
 * todavía no existe.
 */
class SolicitudContratoRecordHeroWidget extends Widget
{
    protected static string $view = 'filament.admin.resources.solicitud-contrato-resource.widgets.record-hero';

    protected int | string | array $columnSpan = 'full';

    /** Sin esto Filament difiere el render a una 2da petición AJAX (lazy widget por defecto) - el hero aparecería con un parpadeo vacío en vez de estar listo de inmediato. */
    protected static bool $isLazy = false;

    public ?SolicitudContrato $record = null;
}
