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

            // Invitar una empresa ya registrada a ser gestionada por el bufete.
            Actions\Action::make('invitar_empresa')
                ->label('Invitar empresa')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn() => auth()->user()?->esAbogadoDeBufete())
                ->form([
                    \Filament\Forms\Components\TextInput::make('nit')
                        ->label('NIT de la empresa')
                        ->required()
                        ->helperText('La empresa debe estar registrada en la plataforma y no pertenecer a otro bufete.'),
                ])
                ->action(function (array $data) {
                    try {
                        $inv = \App\Models\BufeteInvitacion::crearPara(auth()->user()->bufete, $data['nit']);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Invitación creada')
                            ->body('Comparta este enlace con la empresa para que la acepte: ' . route('bufete.invitacion.aceptar', $inv->token))
                            ->persistent()
                            ->send();
                    } catch (\RuntimeException $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('No se pudo crear la invitación')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
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
