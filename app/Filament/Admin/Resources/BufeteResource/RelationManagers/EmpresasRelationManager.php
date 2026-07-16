<?php

namespace App\Filament\Admin\Resources\BufeteResource\RelationManagers;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EmpresasRelationManager extends RelationManager
{
    protected static string $relationship = 'empresas';

    protected static ?string $title = 'Empresas del bufete';

    protected static ?string $icon = 'heroicon-o-building-office-2';

    public function form(Form $form): Form
    {
        // La gestión completa de la empresa vive en su propio recurso.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('razon_social')
            ->columns([
                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón social')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office'),
                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dias_laborales_texto')
                    ->label('Días laborales')
                    ->state(fn(Empresa $record) => $record->dias_laborales_texto)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                // Vincular una empresa existente = asignarle el bufete_id de este bufete.
                Tables\Actions\Action::make('vincular')
                    ->label('Vincular empresa')
                    ->icon('heroicon-o-link')
                    ->modalHeading('Vincular una empresa a este bufete')
                    ->modalSubmitActionLabel('Vincular')
                    ->form([
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->options(function () {
                                $bufeteId = $this->getOwnerRecord()->getKey();

                                return Empresa::query()
                                    ->withoutGlobalScope('bufeteOrEmpresa')
                                    ->where(fn($q) => $q->whereNull('bufete_id')->orWhere('bufete_id', '!=', $bufeteId))
                                    ->orderBy('razon_social')
                                    ->get()
                                    ->mapWithKeys(fn(Empresa $e) => [
                                        $e->id => $e->razon_social . ($e->nit ? " - {$e->nit}" : '') . ($e->bufete_id ? ' (ya en otro bufete)' : ''),
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Solo se listan empresas que no están en este bufete. Si la empresa ya pertenece a otro bufete, se reasignará a este.'),
                    ])
                    ->action(function (array $data) {
                        $empresa = Empresa::withoutGlobalScope('bufeteOrEmpresa')->find($data['empresa_id']);
                        if (! $empresa) {
                            return;
                        }

                        $empresa->bufete_id = $this->getOwnerRecord()->getKey();
                        $empresa->save();

                        Notification::make()
                            ->success()
                            ->title('Empresa vinculada')
                            ->body("«{$empresa->razon_social}» ahora pertenece a este bufete.")
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Empresa $record) => EmpresaResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),

                // Desvincular = quitar el bufete_id (la empresa no se elimina).
                Tables\Actions\Action::make('desvincular')
                    ->label('Desvincular')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Desvincular empresa')
                    ->modalDescription('La empresa dejará de pertenecer a este bufete. No se elimina ni se pierde su información.')
                    ->modalSubmitActionLabel('Sí, desvincular')
                    ->action(function (Empresa $record) {
                        $record->bufete_id = null;
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Empresa desvinculada')
                            ->body("«{$record->razon_social}» ya no pertenece a este bufete.")
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin empresas asociadas')
            ->emptyStateDescription('Aún no hay empresas vinculadas a este bufete.');
    }
}
