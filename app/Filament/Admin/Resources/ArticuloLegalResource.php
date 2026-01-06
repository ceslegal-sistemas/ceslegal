<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ArticuloLegalResource\Pages;
use App\Filament\Admin\Resources\ArticuloLegalResource\RelationManagers;
use App\Models\ArticuloLegal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArticuloLegalResource extends Resource
{
    protected static ?string $model = ArticuloLegal::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Artículos Legales';

    protected static ?string $modelLabel = 'Artículo Legal';

    protected static ?string $pluralModelLabel = 'Artículos Legales';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Artículo')
                    ->description('Datos principales del artículo del Código Sustantivo del Trabajo')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código del Artículo')
                            ->placeholder('Ej: Art. 58, Art. 60 Num. 1')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Ingrese el código oficial del artículo (Ej: "Art. 58" o "Art. 60 Num. 1")')
                            ->suffixIcon('heroicon-o-hashtag')
                            ->columnSpan(1),

                        Forms\Components\Select::make('categoria')
                            ->label('Categoría')
                            ->options([
                                'Obligaciones' => 'Obligaciones del Trabajador',
                                'Prohibiciones' => 'Prohibiciones al Trabajador',
                                'Derechos' => 'Derechos del Trabajador',
                                'Faltas' => 'Faltas Disciplinarias',
                                'Sanciones' => 'Sanciones',
                                'Otros' => 'Otros',
                            ])
                            ->searchable()
                            ->native(false)
                            ->placeholder('Seleccione la categoría...')
                            ->helperText('Categoría a la que pertenece el artículo')
                            ->suffixIcon('heroicon-o-folder')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('titulo')
                            ->label('Título del Artículo')
                            ->placeholder('Ej: Obligaciones especiales del trabajador')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Título descriptivo del artículo')
                            ->suffixIcon('heroicon-o-document')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción Completa')
                            ->placeholder('Ingrese la descripción completa del artículo...')
                            ->required()
                            ->rows(4)
                            ->helperText('Descripción detallada del contenido del artículo')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('activo')
                            ->label('¿Artículo Activo?')
                            ->default(true)
                            ->helperText('Solo los artículos activos aparecerán en el selector de procesos disciplinarios')
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                // Forms\Components\Section::make('Configuración')
                //     ->description('Opciones de visualización y ordenamiento')
                //     ->icon('heroicon-o-cog-6-tooth')
                //     ->schema([
                //         // Forms\Components\TextInput::make('orden')
                //         //     ->label('Orden de Visualización')
                //         //     ->numeric()
                //         //     ->default(0)
                //         //     ->minValue(0)
                //         //     ->helperText('Número para ordenar los artículos en el selector (menor = primero)')
                //         //     ->suffixIcon('heroicon-o-arrows-up-down')
                //         //     ->columnSpan(1),


                //     ])
                //     ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->icon('heroicon-o-hashtag')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    })
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('categoria')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->colors([
                        'danger' => 'Prohibiciones',
                        'warning' => 'Faltas',
                        'success' => 'Obligaciones',
                        'info' => 'Derechos',
                        'primary' => 'Sanciones',
                        'secondary' => 'Otros',
                    ])
                    ->icons([
                        'heroicon-o-x-circle' => 'Prohibiciones',
                        'heroicon-o-exclamation-triangle' => 'Faltas',
                        'heroicon-o-check-circle' => 'Obligaciones',
                        'heroicon-o-information-circle' => 'Derechos',
                        'heroicon-o-shield-exclamation' => 'Sanciones',
                        'heroicon-o-document' => 'Otros',
                    ]),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                // Tables\Columns\TextColumn::make('orden')
                //     ->label('Orden')
                //     ->numeric()
                //     ->sortable()
                //     ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options([
                        'Obligaciones' => 'Obligaciones',
                        'Prohibiciones' => 'Prohibiciones',
                        'Derechos' => 'Derechos',
                        'Faltas' => 'Faltas',
                        'Sanciones' => 'Sanciones',
                        'Otros' => 'Otros',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_activo')
                    ->label(fn(ArticuloLegal $record) => $record->activo ? 'Desactivar' : 'Activar')
                    ->icon(fn(ArticuloLegal $record) => $record->activo ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn(ArticuloLegal $record) => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (ArticuloLegal $record) {
                        $record->activo = !$record->activo;
                        $record->save();

                        $estado = $record->activo ? 'activado' : 'desactivado';
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Estado actualizado')
                            ->body("El artículo ha sido {$estado} exitosamente.")
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activar')
                        ->label('Activar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['activo' => true]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Artículos activados')
                                ->body(count($records) . ' artículo(s) activado(s) exitosamente.')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['activo' => false]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Artículos desactivados')
                                ->body(count($records) . ' artículo(s) desactivado(s) exitosamente.')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('codigo', 'asc')
            ->striped()
            ->emptyStateHeading('No hay artículos legales registrados')
            ->emptyStateDescription('Comience agregando artículos del Código Sustantivo del Trabajo')
            ->emptyStateIcon('heroicon-o-scale')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear primer artículo')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticuloLegals::route('/'),
            'create' => Pages\CreateArticuloLegal::route('/create'),
            'edit' => Pages\EditArticuloLegal::route('/{record}/edit'),
        ];
    }

    /**
     * Verificar si el usuario puede acceder a este recurso
     * Solo super_admin puede gestionar artículos legales
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * Labels personalizados en español
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('activo', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
