<?php

namespace App\Filament\Admin\Resources\TrabajadorResource\Pages;

use App\Filament\Admin\Resources\TrabajadorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTrabajadors extends ListRecords
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Modifica la query para:
     * 1. Incluir el conteo de procesos disciplinarios
     * 2. Filtrar por empresa según el rol del usuario:
     *    - Super Admin: ve TODOS los trabajadores
     *    - Abogado: ve TODOS los trabajadores
     *    - Cliente: ve SOLO trabajadores de su empresa
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->withCount('procesosDisciplinarios');

        $user = auth()->user();

        // Si es cliente, filtrar solo trabajadores de su empresa
        if ($user->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        // Super admin y abogado ven todos los trabajadores
        return $query;
    }
}
