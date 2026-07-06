<?php

namespace App\Filament\Admin\Widgets;

use App\Services\GuiaProcesoService;
use Filament\Widgets\Widget;

/**
 * Guía "Tu proceso": muestra el paso a paso real del usuario (cliente/bufete) y la
 * acción siguiente. La lógica vive en GuiaProcesoService; este widget solo pinta.
 */
class ProcesoGuiaWidget extends Widget
{
    protected static string $view = 'filament.widgets.proceso-guia';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        // Solo cliente y bufete; el staff interno (super_admin/abogado) no la ve.
        return $user && in_array($user->role, ['cliente', 'bufete'], true);
    }

    protected function getViewData(): array
    {
        return [
            'guia' => app(GuiaProcesoService::class)->para(auth()->user()),
        ];
    }
}
