<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
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
