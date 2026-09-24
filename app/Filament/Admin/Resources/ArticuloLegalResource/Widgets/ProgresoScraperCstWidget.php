<?php

namespace App\Filament\Admin\Resources\ArticuloLegalResource\Widgets;

use App\Jobs\ActualizarArticulosCstJob;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

/**
 * Barra de progreso de la actualización de artículos legales (ActualizarArticulosCstJob),
 * mostrada sobre la tabla de ArticuloLegalResource. Sondea la caché en la que el job
 * reporta su avance; no muestra nada si no hay ninguna corrida reciente.
 *
 * El wire:poll de la vista solo está activo mientras estado === 'procesando' (para no
 * bombardear /livewire/update indefinidamente en cada visita a la página, sin importar
 * si hay o no un scraper corriendo). Como esta acción de iniciar el scraper vive en la
 * página (ListArticuloLegals), un Livewire component distinto a este widget, se necesita
 * el evento 'scraper-cst-iniciado' para forzar un re-render aquí y así "despertar" el
 * wire:poll apenas arranca el job, en vez de esperar a que el usuario recargue la página.
 */
class ProgresoScraperCstWidget extends Widget
{
    protected static string $view = 'filament.admin.resources.articulo-legal-resource.widgets.progreso-scraper-cst';

    protected int | string | array $columnSpan = 'full';

    public function getProgreso(): ?array
    {
        return Cache::get(ActualizarArticulosCstJob::CACHE_KEY);
    }

    #[On('scraper-cst-iniciado')]
    public function refrescar(): void
    {
        //
    }
}
