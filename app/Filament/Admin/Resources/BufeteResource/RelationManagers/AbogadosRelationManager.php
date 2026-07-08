<?php

namespace App\Filament\Admin\Resources\BufeteResource\RelationManagers;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AbogadosRelationManager extends RelationManager
{
    protected static string $relationship = 'abogados';

    protected static ?string $title = 'Abogados del bufete';

    protected static ?string $icon = 'heroicon-o-user-group';

    public function form(Form $form): Form
    {
        // La gestión completa del usuario vive en su propio recurso.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-user-circle'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->icon('heroicon-o-envelope'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn(string $state) => UserResource::etiquetasRoles()[$state] ?? \Illuminate\Support\Str::headline($state)),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(User $record) => UserResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin abogados asociados')
            ->emptyStateDescription('Aún no hay usuarios (rol bufete) vinculados a este bufete.');
    }
}
