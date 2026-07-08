<?php

namespace App\Filament\Admin\Resources\BufeteResource\RelationManagers;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\Empresa;
use Filament\Forms\Form;
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
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Empresa $record) => EmpresaResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin empresas asociadas')
            ->emptyStateDescription('Aún no hay empresas vinculadas a este bufete.');
    }
}
