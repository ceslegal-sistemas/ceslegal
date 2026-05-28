<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
     * Filtrar correos según el rol del usuario:
     * - Super Admin: ve TODOS los correos
     * - Abogado: ve TODOS los correos
     * - Cliente: ve SOLO los correos que él registró/envió
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();

        // Si es cliente, filtrar solo los correos que él envió
        if ($user->role === 'cliente') {
            return $query->where('enviado_por', $user->id);
        }

        // Super admin y abogado ven todos los correos
        return $query;
    }
}
