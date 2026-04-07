<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProcesoDisciplinario extends ViewRecord
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    protected function getHeaderActions(): array
    {
        // Solo admins y abogados pueden editar
        return [
            Actions\EditAction::make()
                ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'abogado'])),
        ];
    }
}
