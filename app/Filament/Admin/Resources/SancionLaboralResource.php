<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SancionLaboralResource\Pages;
use App\Models\SancionLaboral;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SancionLaboralResource extends Resource
{
    protected static ?string $model = SancionLaboral::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Sanciones Laborales';

    protected static ?string $modelLabel = 'Sanción Laboral';

    protected static ?string $pluralModelLabel = 'Sanciones Laborales';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\Select::make('tipo_falta')
                            ->label('Tipo de Falta')
                            ->options([
                                'leve' => '🟢 Leve',
                                'grave' => '🔴 Grave',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Clasificación según el reglamento interno de trabajo'),

                        Forms\Components\TextInput::make('nombre_claro')
                            ->label('Nombre Claro')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nombre corto y comprensible (ej: "Retardo de 15 minutos (1ra vez)")'),

                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción Completa')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Descripción detallada de la conducta sancionable'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tipo de Sanción')
                    ->schema([
                        Forms\Components\Select::make('tipo_sancion')
                            ->label('Sanción Aplicable')
                            ->options([
                                'llamado_atencion' => '📄 Llamado de Atención',
                                'suspension' => '⏸️ Suspensión Laboral',
                                'terminacion' => '❌ Terminación de Contrato',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Tipo de sanción que corresponde a esta falta'),

                        Forms\Components\TextInput::make('dias_suspension_min')
                            ->label('Días Mínimos de Suspensión')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('días')
                            ->visible(fn(Forms\Get $get) => $get('tipo_sancion') === 'suspension')
                            ->helperText('Cantidad mínima de días (opcional)'),

                        Forms\Components\TextInput::make('dias_suspension_max')
                            ->label('Días Máximos de Suspensión')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('días')
                            ->visible(fn(Forms\Get $get) => $get('tipo_sancion') === 'suspension')
                            ->helperText('Cantidad máxima de días (si aplica rango)'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Reincidencia')
                    ->schema([
                        Forms\Components\Select::make('sancion_padre_id')
                            ->label('¿Es reincidencia de otra sanción?')
                            ->placeholder('No es reincidencia (o es la primera vez)')
                            ->options(function ($record) {
                                // Solo mostrar sanciones que son "primera vez" (orden_reincidencia = 1)
                                // o que no tienen reincidencia configurada
                                return SancionLaboral::where('orden_reincidencia', 1)
                                    ->orWhereNull('orden_reincidencia')
                                    ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                    ->orderBy('orden')
                                    ->get()
                                    ->mapWithKeys(fn($s) => [$s->id => $s->nombre_claro]);
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Si esta sanción es una reincidencia (2da vez, 3ra vez, etc.), seleccione la sanción de "primera vez"'),

                        Forms\Components\Select::make('orden_reincidencia')
                            ->label('Número de vez')
                            ->options([
                                1 => '1ra vez (primera)',
                                2 => '2da vez',
                                3 => '3ra vez',
                                4 => '4ta vez',
                            ])
                            ->native(false)
                            ->visible(fn(Forms\Get $get, $record) => $get('sancion_padre_id') || ($record && $record->orden_reincidencia === 1))
                            ->required(fn(Forms\Get $get) => $get('sancion_padre_id') !== null)
                            ->helperText('Indica qué número de vez es esta sanción en la secuencia'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(fn($record) => $record && !$record->esReincidencia()),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Toggle::make('activa')
                            ->label('Activa')
                            ->default(true)
                            ->helperText('Si está desactivada, no aparecerá en los selectores'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('orden')
                    ->label('#')
                    ->sortable()
                    ->width(60),

                Tables\Columns\BadgeColumn::make('tipo_falta')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'leve' => 'Leve',
                        'grave' => 'Grave',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'leve' => 'success',
                        'grave' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'leve' => 'heroicon-o-check-circle',
                        'grave' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre_claro')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('orden_reincidencia')
                    ->label('Vez')
                    ->formatStateUsing(fn($state) => match ($state) {
                        1 => '1ra vez',
                        2 => '2da vez',
                        3 => '3ra vez',
                        4 => '4ta vez',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        1 => 'success',
                        2 => 'warning',
                        3 => 'danger',
                        4 => 'gray',
                        default => null,
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('tipo_sancion')
                    ->label('Sanción')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'llamado_atencion' => 'Llamado Atención',
                        'suspension' => 'Suspensión',
                        'terminacion' => 'Terminación',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'llamado_atencion' => 'warning',
                        'suspension' => 'info',
                        'terminacion' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_suspension_texto')
                    ->label('Días Suspensión')
                    ->placeholder('N/A')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_falta')
                    ->label('Tipo de Falta')
                    ->options([
                        'leve' => '🟢 Leve',
                        'grave' => '🔴 Grave',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('tipo_sancion')
                    ->label('Tipo de Sanción')
                    ->options([
                        'llamado_atencion' => 'Llamado de Atención',
                        'suspension' => 'Suspensión',
                        'terminacion' => 'Terminación',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('activa')
                    ->label('Activa')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_activa')
                    ->label(fn(SancionLaboral $record) => $record->activa ? 'Desactivar' : 'Activar')
                    ->icon(fn(SancionLaboral $record) => $record->activa ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn(SancionLaboral $record) => $record->activa ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (SancionLaboral $record) {
                        $record->activa = !$record->activa;
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activar')
                        ->label('Activar seleccionadas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['activa' => true])),

                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar seleccionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn($records) => $records->each->update(['activa' => false])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('orden', 'asc')
            ->striped();
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
            'index' => Pages\ListSancionLaborals::route('/'),
            'create' => Pages\CreateSancionLaboral::route('/create'),
            'edit' => Pages\EditSancionLaboral::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
