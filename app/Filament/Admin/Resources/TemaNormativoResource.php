<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TemaNormativoResource\Pages;
use App\Models\TemaNormativo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TemaNormativoResource extends Resource
{
    protected static ?string $model = TemaNormativo::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Temas Normativos';

    protected static ?string $modelLabel = 'Tema Normativo';

    protected static ?string $pluralModelLabel = 'Temas Normativos';

    protected static ?string $navigationGroup = 'Configuración Informes';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Ej: Teletrabajo y trabajo remoto/híbrido'),

                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción')
                            ->required()
                            ->rows(3)
                            ->helperText('Se usa para que la IA clasifique con criterio - sea específico, no solo repita el nombre.')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Desactive para dejar de usarlo en clasificaciones nuevas, sin borrar el historial ya asignado'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(80)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('reglamentosInternos_count')
                    ->label('RIT')
                    ->counts('reglamentosInternos')
                    ->badge()
                    ->color('primary'),
            ])
            ->defaultSort('nombre')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTemasNormativos::route('/'),
            'create' => Pages\CreateTemaNormativo::route('/create'),
            'edit'   => Pages\EditTemaNormativo::route('/{record}/edit'),
        ];
    }
}
