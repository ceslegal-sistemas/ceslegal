<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContratoLaboral extends CreateRecord
{
    protected static string $resource = ContratoLaboralResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::can('create'), 403);
    }
}
