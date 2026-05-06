<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ConfiguracionTextoResource\Pages;
use App\Models\ConfiguracionTexto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConfiguracionTextoResource extends Resource
{
    protected static ?string $model = ConfiguracionTexto::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Textos Configurables';

    protected static ?string $modelLabel = 'Texto Configurable';

    protected static ?string $pluralModelLabel = 'Textos Configurables';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información')
                    ->schema([
                        Forms\Components\TextInput::make('clave')
                            ->label('Clave')
                            ->disabled()
                            ->helperText('Identificador interno — no se puede modificar.'),

                        Forms\Components\TextInput::make('grupo')
                            ->label('Grupo')
                            ->disabled(),

                        Forms\Components\TextInput::make('descripcion')
                            ->label('Descripción')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Contenido')
                    ->description(function ($record) {
                        if ($record?->clave === 'disclaimer_descargos') {
                            return 'Use los marcadores :nombre, :cedula y :empresa — se reemplazan automáticamente con los datos del trabajador y la empresa al mostrar el formulario.';
                        }
                        return null;
                    })
                    ->schema([
                        Forms\Components\Textarea::make('valor')
                            ->label('Texto')
                            ->rows(20)
                            ->required()
                            ->columnSpanFull()
                            ->helperText(function ($record) {
                                if ($record?->clave === 'disclaimer_descargos') {
                                    return 'Marcadores disponibles: :nombre (nombre completo del trabajador), :cedula (número de documento), :empresa (razón social de la empresa).';
                                }
                                return null;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clave')
                    ->label('Clave')
                    ->searchable()
                    ->weight('bold')
                    ->copyable()
                    ->icon('heroicon-o-key'),

                Tables\Columns\TextColumn::make('grupo')
                    ->label('Grupo')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\TextColumn::make('valor')
                    ->label('Vista previa')
                    ->limit(60)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('grupo')
                    ->label('Grupo')
                    ->options(fn () => ConfiguracionTexto::distinct()->pluck('grupo', 'grupo')->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Texto actualizado')
                            ->body('El texto configurable fue guardado correctamente.')
                    ),
            ])
            ->bulkActions([])        // Sin acciones masivas — los registros son clave-valor únicos
            ->defaultSort('grupo')
            ->striped()
            ->emptyStateHeading('Sin textos configurables')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfiguracionTextos::route('/'),
            'edit'  => Pages\EditConfiguracionTexto::route('/{record}/edit'),
        ];
    }

    /** Solo super_admin puede gestionar textos configurables. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
