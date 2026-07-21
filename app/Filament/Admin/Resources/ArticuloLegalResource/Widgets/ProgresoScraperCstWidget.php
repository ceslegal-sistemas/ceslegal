<?php

namespace App\Filament\Admin\Resources\ArticuloLegalResource\Widgets;

use App\Jobs\ActualizarArticulosCstJob;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Barra de progreso de la actualización de artículos legales (ActualizarArticulosCstJob),
 * mostrada sobre la tabla de ArticuloLegalResource. Sondea la caché en la que el job
 * reporta su avance; no muestra nada si no hay ninguna corrida reciente.
 */
class ProgresoScraperCstWidget extends Widget
{
    protected static string $view = 'filament.admin.resources.articulo-legal-resource.widgets.progreso-scraper-cst';

    protected int | string | array $columnSpan = 'full';

    public function getProgreso(): ?array
    {
        return Cache::get(ActualizarArticulosCstJob::CACHE_KEY);
    }
}
