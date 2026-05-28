<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCorreosEnviados extends ListRecords
{
    protected static string $resource = CorreoEnviadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Redactar correo'),
        ];
    }

    /**
     * Filtrar empresas según el rol del usuario:
     * - Super Admin: ve TODAS las empresas
     * - Abogado: ve TODAS las empresas
     * - Cliente: ve SOLO su empresa asignada
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();

        // Si es cliente, filtrar solo su empresa
        if ($user->role === 'cliente' && $user->empresa_id) {
            return $query->where('id', $user->empresa_id);
        }

        // Super admin y abogado ven todas las empresas
        return $query;
    }
}
