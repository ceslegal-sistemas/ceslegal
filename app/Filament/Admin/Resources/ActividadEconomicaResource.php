<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActividadEconomicaResource\Pages;
use App\Models\ActividadEconomica;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActividadEconomicaResource extends Resource
{
    protected static ?string $model = ActividadEconomica::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Actividades Económicas (CIIU)';

    protected static ?string $modelLabel = 'Actividad Económica';

    protected static ?string $pluralModelLabel = 'Actividades Económicas';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Código CIIU')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código CIIU')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ej: 4711')
                            ->helperText('Código de 4 dígitos de la clasificación CIIU Rev. 4 A.C.'),

                        Forms\Components\Select::make('seccion')
                            ->label('Sección')
                            ->required()
                            ->options(self::getSecciones())
                            ->native(false)
                            ->searchable(),

                        Forms\Components\TextInput::make('nombre_seccion')
                            ->label('Nombre de la Sección')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre de la Actividad')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activa')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),
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
                    ->width('80px'),

                Tables\Columns\BadgeColumn::make('seccion')
                    ->label('Sección')
                    ->sortable()
                    ->colors([
                        'primary' => fn ($state) => in_array($state, ['A', 'B', 'C']),
                        'success' => fn ($state) => in_array($state, ['D', 'E', 'F']),
                        'warning' => fn ($state) => in_array($state, ['G', 'H', 'I']),
                        'info'    => fn ($state) => in_array($state, ['J', 'K', 'L', 'M', 'N']),
                        'danger'  => fn ($state) => in_array($state, ['O', 'P', 'Q', 'R', 'S', 'T', 'U']),
                    ]),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->wrap()
                    ->description(fn (ActividadEconomica $record): string => $record->nombre_seccion),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('empresas_principales_count')
                    ->label('Empresas (principal)')
                    ->counts('empresasPrincipales')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('seccion')
                    ->label('Sección')
                    ->options(self::getSecciones())
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('codigo');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActividadesEconomicas::route('/'),
            'create' => Pages\CreateActividadEconomica::route('/create'),
            'edit'   => Pages\EditActividadEconomica::route('/{record}/edit'),
        ];
    }

    public static function getSecciones(): array
    {
        return [
            'A' => 'A - Agricultura, ganadería, caza, silvicultura y pesca',
            'B' => 'B - Explotación de minas y canteras',
            'C' => 'C - Industrias manufactureras',
            'D' => 'D - Suministro de electricidad, gas, vapor y aire acondicionado',
            'E' => 'E - Distribución de agua; evacuación y tratamiento de aguas residuales',
            'F' => 'F - Construcción',
            'G' => 'G - Comercio al por mayor y al por menor; reparación de vehículos',
            'H' => 'H - Transporte y almacenamiento',
            'I' => 'I - Alojamiento y servicios de comida',
            'J' => 'J - Información y comunicaciones',
            'K' => 'K - Actividades financieras y de seguros',
            'L' => 'L - Actividades inmobiliarias',
            'M' => 'M - Actividades profesionales, científicas y técnicas',
            'N' => 'N - Actividades de servicios administrativos y de apoyo',
            'O' => 'O - Administración pública y defensa; seguridad social',
            'P' => 'P - Educación',
            'Q' => 'Q - Actividades de atención de la salud humana y asistencia social',
            'R' => 'R - Actividades artísticas, de entretenimiento y recreación',
            'S' => 'S - Otras actividades de servicios',
            'T' => 'T - Actividades de los hogares individuales',
            'U' => 'U - Actividades de organizaciones extraterritoriales',
        ];
    }
}
