<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmpresaResource\Pages;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Empresa')
                    ->description('Datos básicos de identificación')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: EMPRESA ABC S.A.S')
                            ->helperText('Nombre legal completo de la empresa')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nit')
                            ->label('NIT')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ej: 900123456-7')
                            ->mask('999999999-9')
                            ->helperText('Número de Identificación Tributaria')
                            ->suffixIcon('heroicon-o-identification'),

                        Forms\Components\TextInput::make('representante_legal')
                            ->label('Representante Legal')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Juan Pérez García')
                            ->helperText('Nombre del representante legal')
                            ->suffixIcon('heroicon-o-user'),

                        Forms\Components\Toggle::make('active')
                            ->label('Empresa Activa')
                            ->default(true)
                            ->helperText('Desactive si la empresa ya no está en servicio')
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->description('Datos para comunicación')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            // ->required()
                            ->maxLength(50)
                            ->placeholder('Ej: +57 300 123 4567')
                            ->helperText('Número de contacto principal')
                            ->suffixIcon('heroicon-o-phone'),

                        Forms\Components\TextInput::make('email_contacto')
                            ->label('Email de Contacto')
                            ->email()
                            // ->required()
                            ->maxLength(255)
                            ->placeholder('contacto@empresa.com')
                            ->helperText('Correo electrónico principal')
                            ->suffixIcon('heroicon-o-envelope'),

                        Forms\Components\Textarea::make('direccion')
                            ->label('Dirección')
                            // ->required()
                            ->rows(2)
                            ->placeholder('Ej: Calle 123 # 45-67, Edificio ABC, Piso 3')
                            ->helperText('Dirección completa de la empresa')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Ubicación')
                    ->description('Ciudad y departamento')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\Select::make('departamento')
                            ->label('Departamento')
                            ->required()
                            ->searchable()
                            ->options([
                                'Amazonas' => 'Amazonas',
                                'Antioquia' => 'Antioquia',
                                'Arauca' => 'Arauca',
                                'Atlántico' => 'Atlántico',
                                'Bolívar' => 'Bolívar',
                                'Boyacá' => 'Boyacá',
                                'Caldas' => 'Caldas',
                                'Caquetá' => 'Caquetá',
                                'Casanare' => 'Casanare',
                                'Cauca' => 'Cauca',
                                'Cesar' => 'Cesar',
                                'Chocó' => 'Chocó',
                                'Córdoba' => 'Córdoba',
                                'Cundinamarca' => 'Cundinamarca',
                                'Guainía' => 'Guainía',
                                'Guaviare' => 'Guaviare',
                                'Huila' => 'Huila',
                                'La Guajira' => 'La Guajira',
                                'Magdalena' => 'Magdalena',
                                'Meta' => 'Meta',
                                'Nariño' => 'Nariño',
                                'Norte de Santander' => 'Norte de Santander',
                                'Putumayo' => 'Putumayo',
                                'Quindío' => 'Quindío',
                                'Risaralda' => 'Risaralda',
                                'San Andrés y Providencia' => 'San Andrés y Providencia',
                                'Santander' => 'Santander',
                                'Sucre' => 'Sucre',
                                'Tolima' => 'Tolima',
                                'Valle del Cauca' => 'Valle del Cauca',
                                'Vaupés' => 'Vaupés',
                                'Vichada' => 'Vichada',
                            ])
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('ciudad', null))
                            ->helperText('Seleccione el departamento'),

                        Forms\Components\Select::make('ciudad')
                            ->label('Ciudad')
                            ->required()
                            ->searchable()
                            ->options(function (Get $get) {
                                $departamento = $get('departamento');
                                return self::getCiudadesPorDepartamento($departamento);
                            })
                            ->disabled(fn(Get $get) => empty($get('departamento')))
                            ->helperText('Seleccione primero el departamento')
                            ->placeholder('Seleccione una ciudad...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->description(fn(Empresa $record): string => $record->departamento),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email_contacto')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('representante_legal')
                    ->label('Representante')
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('trabajadores_count')
                    ->label('Trabajadores')
                    ->counts('trabajadores')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('departamento')
                    ->label('Departamento')
                    ->options([
                        'Antioquia' => 'Antioquia',
                        'Atlántico' => 'Atlántico',
                        'Bogotá D.C.' => 'Bogotá D.C.',
                        'Cundinamarca' => 'Cundinamarca',
                        'Valle del Cauca' => 'Valle del Cauca',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todas las empresas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Empresa $record): bool => !auth()->user()->hasRole('cliente') || auth()->user()->empresa_id === $record->id),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (Tables\Actions\DeleteAction $action, \App\Models\Empresa $record) {
                        // Verificar si tiene procesos disciplinarios
                        if ($record->procesosDisciplinarios()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar la empresa')
                                ->body("La empresa '{$record->razon_social}' tiene {$record->procesosDisciplinarios()->count()} procesos disciplinarios asociados. Debe eliminar o reasignar esos procesos primero.")
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }

                        // Verificar si tiene trabajadores
                        if ($record->trabajadores()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar la empresa')
                                ->body("La empresa '{$record->razon_social}' tiene {$record->trabajadores()->count()} trabajadores asociados. Debe eliminar o reasignar esos trabajadores primero.")
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->action(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Support\Collection $records) {
                            $bloqueadas = [];
                            $eliminadas = 0;

                            foreach ($records as $record) {
                                // Verificar si tiene relaciones
                                if ($record->procesosDisciplinarios()->count() > 0 || $record->trabajadores()->count() > 0) {
                                    $bloqueadas[] = $record->razon_social;
                                } else {
                                    $record->delete();
                                    $eliminadas++;
                                }
                            }

                            if (count($bloqueadas) > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Algunas empresas no se pudieron eliminar')
                                    ->body('Las siguientes empresas tienen procesos o trabajadores asociados: ' . implode(', ', $bloqueadas))
                                    ->persistent()
                                    ->send();
                            }

                            if ($eliminadas > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Empresas eliminadas')
                                    ->body("{$eliminadas} empresa(s) eliminada(s) correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'view' => Pages\ViewEmpresa::route('/{record}'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if ($user->hasRole('cliente')) {
            return static::getModel()::where('active', true)
                ->where('id', $user->empresa_id)
                ->count();
        }

        return static::getModel()::where('active', true)->count();
    }

    protected static function getCiudadesPorDepartamento(?string $departamento): array
    {
        if (empty($departamento)) {
            return [];
        }

        $ciudades = [
            'Amazonas' => ['Leticia', 'Puerto Nariño', 'El Encanto', 'La Chorrera'],
            'Antioquia' => ['Medellín', 'Bello', 'Itagüí', 'Envigado', 'Rionegro', 'Sabaneta', 'Apartadó', 'Turbo', 'Caucasia', 'Yarumal'],
            'Arauca' => ['Arauca', 'Arauquita', 'Saravena', 'Tame', 'Fortul'],
            'Atlántico' => ['Barranquilla', 'Soledad', 'Malambo', 'Sabanalarga', 'Puerto Colombia', 'Galapa'],
            'Bolívar' => ['Cartagena', 'Magangué', 'Turbaco', 'El Carmen de Bolívar', 'Arjona', 'Mompós'],
            'Boyacá' => ['Tunja', 'Duitama', 'Sogamoso', 'Chiquinquirá', 'Puerto Boyacá', 'Paipa', 'Villa de Leyva', 'Moniquirá'],
            'Caldas' => ['Manizales', 'Villamaría', 'Chinchiná', 'La Dorada', 'Riosucio', 'Anserma'],
            'Caquetá' => ['Florencia', 'San Vicente del Caguán', 'Puerto Rico', 'El Doncello', 'Belén de los Andaquíes'],
            'Casanare' => ['Yopal', 'Aguazul', 'Villanueva', 'Monterrey', 'Paz de Ariporo'],
            'Cauca' => ['Popayán', 'Santander de Quilichao', 'Puerto Tejada', 'Guapi', 'Patía'],
            'Cesar' => ['Valledupar', 'Aguachica', 'Codazzi', 'Bosconia', 'Chiriguaná', 'La Jagua de Ibirico'],
            'Chocó' => ['Quibdó', 'Istmina', 'Condoto', 'Tadó', 'Acandí', 'Bahía Solano'],
            'Córdoba' => ['Montería', 'Cereté', 'Lorica', 'Sahagún', 'Planeta Rica', 'Montelíbano'],
            'Cundinamarca' => ['Bogotá D.C.', 'Soacha', 'Facatativá', 'Chía', 'Zipaquirá', 'Fusagasugá', 'Madrid', 'Girardot', 'Cajicá', 'La Calera'],
            'Guainía' => ['Inírida', 'Barranco Minas', 'Mapiripana', 'San Felipe'],
            'Guaviare' => ['San José del Guaviare', 'Calamar', 'El Retorno', 'Miraflores'],
            'Huila' => ['Neiva', 'Pitalito', 'Garzón', 'La Plata', 'Campoalegre', 'Gigante'],
            'La Guajira' => ['Riohacha', 'Maicao', 'Uribia', 'Manaure', 'Villanueva', 'Fonseca'],
            'Magdalena' => ['Santa Marta', 'Ciénaga', 'Fundación', 'Zona Bananera', 'Plato', 'El Banco'],
            'Meta' => ['Villavicencio', 'Acacías', 'Granada', 'Puerto López', 'San Martín', 'Cumaral'],
            'Nariño' => ['Pasto', 'Tumaco', 'Ipiales', 'Túquerres', 'La Unión', 'Sandoná'],
            'Norte de Santander' => ['Cúcuta', 'Ocaña', 'Pamplona', 'Villa del Rosario', 'Los Patios', 'Tibú'],
            'Putumayo' => ['Mocoa', 'Puerto Asís', 'Orito', 'Valle del Guamuez', 'Villagarzón'],
            'Quindío' => ['Armenia', 'Calarcá', 'La Tebaida', 'Montenegro', 'Circasia', 'Quimbaya'],
            'Risaralda' => ['Pereira', 'Dosquebradas', 'La Virginia', 'Santa Rosa de Cabal', 'Marsella'],
            'San Andrés y Providencia' => ['San Andrés', 'Providencia'],
            'Santander' => ['Bucaramanga', 'Floridablanca', 'Girón', 'Piedecuesta', 'Barrancabermeja', 'San Gil', 'Socorro'],
            'Sucre' => ['Sincelejo', 'Corozal', 'San Marcos', 'Tolú', 'Sampués'],
            'Tolima' => ['Ibagué', 'Espinal', 'Melgar', 'Honda', 'Chaparral', 'Líbano'],
            'Valle del Cauca' => ['Cali', 'Palmira', 'Buenaventura', 'Tuluá', 'Cartago', 'Buga', 'Jamundí', 'Yumbo'],
            'Vaupés' => ['Mitú', 'Carurú', 'Taraira'],
            'Vichada' => ['Puerto Carreño', 'La Primavera', 'Cumaribo'],
        ];

        $ciudadesDepartamento = $ciudades[$departamento] ?? [$departamento];

        // Convertir array a formato clave => valor para que Filament guarde el nombre de la ciudad
        // En lugar de [0 => 'Tunja', 1 => 'Duitama'] se convierte a ['Tunja' => 'Tunja', 'Duitama' => 'Duitama']
        return array_combine($ciudadesDepartamento, $ciudadesDepartamento);
    }
}
