<?php

namespace App\Filament\Admin\Widgets\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Reemplaza el spinner de carga (lazy) de un StatsOverviewWidget por un skeleton
 * de tarjetas que imita el layout real. El número de tarjetas se deriva de
 * getColumns() del propio widget. Úsese en widgets que extiendan
 * Filament\Widgets\StatsOverviewWidget.
 */
trait HasStatsSkeleton
{
    public function placeholder(): View
    {
        $columns = method_exists($this, 'getColumns') ? (int) $this->getColumns() : 4;

        return view('filament.widgets.stats-skeleton', [
            'columns' => max(1, min(4, $columns)),
        ]);
    }
}
