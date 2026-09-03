<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Widgets;

use App\Models\ModificacionContractual;
use Filament\Widgets\Widget;

/**
 * Hero de marca sobre Crear/Editar/Ver un Otrosí - mismo patrón que
 * SolicitudContratoRecordHeroWidget. $record es null en "Crear" (aún no
 * existe el registro).
 */
class ModificacionContractualRecordHeroWidget extends Widget
{
    protected static string $view = 'filament.admin.resources.modificacion-contractual-resource.widgets.record-hero';

    protected int | string | array $columnSpan = 'full';

    /** Sin esto Filament difiere el render a una 2da petición AJAX - el hero aparecería con un parpadeo vacío. */
    protected static bool $isLazy = false;

    public ?ModificacionContractual $record = null;
}
