<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubtipoGestionResource\Pages;
use App\Models\SubtipoGestion;
use App\Models\TipoGestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubtipoGestionResource extends Resource
{
    protected static ?string $model = SubtipoGestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Subtipos de Gestión';

    protected static ?string $modelLabel = 'Subtipo de Gestión';

    protected static ?string $pluralModelLabel = 'Subtipos de Gestión';

    protected static ?string $navigationGroup = 'Configuración Informes';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('tipo_gestion_id')
                            ->label('Tipo de Gestión (opcional)')
                            ->options(fn () => TipoGestion::activos()->ordenado()->pluck('nombre', 'id'))
                            ->placeholder('Aplica a todos los tipos...')
                            ->native(false)
                            ->searchable()
                            ->helperText('Deje vacío si el subtipo aplica a cualquier tipo'),

                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Documento, Especial, Acta...'),

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
                Tables\Columns\TextColumn::make('tipoGestion.nombre')
                    ->label('Tipo de Gestión')
                    ->placeholder('Todos')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

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
                    ->before(function (Tables\Actions\DeleteAction $action, SubtipoGestion $record) {
                        if ($record->informesJuridicos()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body("Este subtipo tiene {$record->informesJuridicos()->count()} informes asociados.")
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
            'index' => Pages\ListSubtiposGestion::route('/'),
            'create' => Pages\CreateSubtipoGestion::route('/create'),
            'edit' => Pages\EditSubtipoGestion::route('/{record}/edit'),
        ];
    }
}
