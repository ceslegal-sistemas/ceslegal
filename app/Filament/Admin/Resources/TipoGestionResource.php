<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TipoGestionResource\Pages;
use App\Models\TipoGestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoGestionResource extends Resource
{
    protected static ?string $model = TipoGestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Tipos de Gestión';

    protected static ?string $modelLabel = 'Tipo de Gestión';

    protected static ?string $pluralModelLabel = 'Tipos de Gestión';

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
                            ->maxLength(100)
                            ->placeholder('Ej: Oficio, Memorando, Contrato...'),

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

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('subtipos_count')
                    ->label('Subtipos')
                    ->counts('subtipos')
                    ->badge()
                    ->color('info'),

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
                    ->before(function (Tables\Actions\DeleteAction $action, TipoGestion $record) {
                        if ($record->informesJuridicos()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body("Este tipo tiene {$record->informesJuridicos()->count()} informes asociados.")
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
            'index' => Pages\ListTiposGestion::route('/'),
            'create' => Pages\CreateTipoGestion::route('/create'),
            'edit' => Pages\EditTipoGestion::route('/{record}/edit'),
        ];
    }
}
