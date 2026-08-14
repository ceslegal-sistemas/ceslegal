<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContratoLaboral extends ViewRecord
{
    protected static string $resource = ContratoLaboralResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::can('view', $this->record), 403);
    }
}
