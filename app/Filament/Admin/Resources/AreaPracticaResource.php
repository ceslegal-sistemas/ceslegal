<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AreaPracticaResource\Pages;
use App\Models\AreaPractica;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AreaPracticaResource extends Resource
{
    protected static ?string $model = AreaPractica::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Áreas de Práctica';

    protected static ?string $modelLabel = 'Área de Práctica';

    protected static ?string $pluralModelLabel = 'Áreas de Práctica';

    protected static ?string $navigationGroup = 'Configuración Informes';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Disciplinario'),

                        Forms\Components\Select::make('color')
                            ->label('Color Etiqueta')
                            ->options([
                                'gray' => 'Gris',
                                'primary' => 'Azul',
                                'success' => 'Verde',
                                'warning' => 'Amarillo',
                                'danger' => 'Rojo',
                                'info' => 'Celeste',
                            ])
                            ->default('gray')
                            ->native(false),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Desactive para ocultar de los formularios'),
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

                Tables\Columns\TextColumn::make('color')
                    ->label('Color Etiqueta')
                    ->badge()
                    ->color(fn ($state) => $state),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('informesJuridicos_count')
                    ->label('Informes')
                    ->counts('informesJuridicos')
                    ->badge()
                    ->color('primary'),
            ])
            ->defaultSort('orden')
            ->reorderable('orden')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, AreaPractica $record) {
                        if ($record->informesJuridicos()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body("Esta área tiene {$record->informesJuridicos()->count()} informes asociados.")
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
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
            'index' => Pages\ListAreasPractica::route('/'),
            'create' => Pages\CreateAreaPractica::route('/create'),
            'edit' => Pages\EditAreaPractica::route('/{record}/edit'),
        ];
    }
}
