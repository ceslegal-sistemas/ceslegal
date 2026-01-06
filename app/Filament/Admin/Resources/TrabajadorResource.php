<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrabajadorResource\Pages;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrabajadorResource extends Resource
{
    protected static ?string $model = Trabajador::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Trabajadores';

    protected static ?string $modelLabel = 'Trabajador';

    protected static ?string $pluralModelLabel = 'Trabajadores';

    protected static ?string $navigationGroup = 'Gestión Laboral';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Seleccione la Empresa')
                    ->description('Primero seleccione la empresa a la que pertenece el trabajador')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'razon_social')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(function () {
                                $user = auth()->user();
                                return $user && $user->isCliente() ? $user->empresa_id : null;
                            })
                            ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                            ->dehydrated()
                            ->helperText(function () {
                                $user = auth()->user();
                                if ($user && $user->isCliente()) {
                                    return 'Empresa asignada automáticamente';
                                }
                                return 'Busque y seleccione la empresa';
                            })
                            ->placeholder('Seleccione una empresa...')
                            ->suffixIcon('heroicon-o-building-office')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('razon_social')
                                    ->label('Razón Social')
                                    ->required()
                                    ->placeholder('Ej: EMPRESA ABC S.A.S'),
                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required()
                                    ->placeholder('Ej: 900123456-7'),
                            ])
                            ->createOptionModalHeading('Crear Nueva Empresa')
                            ->visible(fn() => !auth()->user()?->isCliente() || auth()->user()?->empresa_id !== null),
                    ]),

                Forms\Components\Section::make('Información Personal')
                    ->description('Datos de identificación del trabajador')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\Select::make('tipo_documento')
                            ->label('Tipo de Documento')
                            ->options([
                                'CC' => 'Cédula de Ciudadanía',
                                'CE' => 'Cédula de Extranjería',
                                'TI' => 'Tarjeta de Identidad',
                                'PASS' => 'Pasaporte',
                            ])
                            ->required()
                            ->default('CC')
                            ->native(false)
                            ->live()
                            ->helperText('Seleccione el tipo de documento de identidad')
                            ->suffixIcon('heroicon-o-identification'),

                        Forms\Components\TextInput::make('numero_documento')
                            ->label('Número de Documento')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, Get $get) {
                                return $rule->where('tipo_documento', $get('tipo_documento'));
                            })
                            ->maxLength(50)
                            ->placeholder(fn(Get $get) => match ($get('tipo_documento')) {
                                'CC' => 'Ej: 1234567890',
                                'CE' => 'Ej: 9876543210',
                                'TI' => 'Ej: 1234567890123',
                                'PASS' => 'Ej: AB123456',
                                default => 'Ingrese el número de documento',
                            })
                            ->mask(fn(Get $get) => match ($get('tipo_documento')) {
                                'CC' => '9999999999',
                                'CE' => '9999999999',
                                'TI' => '9999999999999',
                                default => null,
                            })
                            ->helperText('Este número debe ser único en el sistema'),

                        Forms\Components\Select::make('genero')
                            ->label('Género')
                            ->options([
                                'masculino' => 'Masculino',
                                'femenino' => 'Femenino',
                                // 'otro' => 'Otro',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Seleccione el género del trabajador para el formato correcto de documentos')
                            ->suffixIcon('heroicon-o-user')
                            ->placeholder('Seleccione el género...'),

                        Forms\Components\TextInput::make('nombres')
                            ->label('Nombres')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Juan Carlos')
                            ->live(onBlur: true)
                            // ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                            //     // Auto-generar email si está vacío
                            //     if (empty($get('email')) && !empty($state) && !empty($get('apellidos'))) {
                            //         $nombres = Str::slug(Str::lower($state));
                            //         $apellidos = Str::slug(Str::lower($get('apellidos')));
                            //         $empresa = \App\Models\Empresa::find($get('empresa_id'));
                            //         $dominio = $empresa ? Str::slug($empresa->razon_social) : 'empresa';
                            //         $set('email', "{$nombres}.{$apellidos}@{$dominio}.com");
                            //     }
                            // })
                            ->helperText('Nombres completos del trabajador'),

                        Forms\Components\TextInput::make('apellidos')
                            ->label('Apellidos')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Pérez García')
                            ->live(onBlur: true)
                            // ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                            //     // Auto-generar email si está vacío
                            //     if (empty($get('email')) && !empty($state) && !empty($get('nombres'))) {
                            //         $nombres = Str::slug(Str::lower($get('nombres')));
                            //         $apellidos = Str::slug(Str::lower($state));
                            //         $empresa = \App\Models\Empresa::find($get('empresa_id'));
                            //         $dominio = $empresa ? Str::slug($empresa->razon_social) : 'empresa';
                            //         $set('email', "{$nombres}.{$apellidos}@{$dominio}.com");
                            //     }
                            // })
                            ->helperText('Apellidos completos del trabajador'),

                        Forms\Components\Select::make('departamento_nacimiento')
                            ->label('Departamento de Nacimiento (Opcional)')
                            ->options(fn() => DB::table('departamentos')->pluck('nombre', 'nombre')->toArray())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Seleccione el departamento...')
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('ciudad_nacimiento', null))
                            ->helperText('Departamento donde nació el trabajador')
                            ->suffixIcon('heroicon-o-map'),

                        Forms\Components\Select::make('ciudad_nacimiento')
                            ->label('Ciudad / Municipio de Nacimiento (Opcional)')
                            ->options(function (Get $get): array {
                                $departamento = $get('departamento_nacimiento');
                                if (!$departamento) return [];

                                return DB::table('municipios')
                                    ->join('departamentos', 'municipios.departamento_id', '=', 'departamentos.id')
                                    ->where('departamentos.nombre', $departamento)
                                    ->pluck('municipios.nombre', 'municipios.nombre')
                                    ->toArray();
                            })
                            ->searchable()
                            ->native(false)
                            ->placeholder('Seleccione la ciudad...')
                            ->disabled(fn(Get $get) => !$get('departamento_nacimiento'))
                            ->helperText('Ciudad o municipio donde nació el trabajador (1,122 municipios disponibles)')
                            ->suffixIcon('heroicon-o-map-pin'),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('trabajador@empresa.com')
                            ->helperText('Correo electrónico del trabajador')
                            ->suffixIcon('heroicon-o-envelope'),
                    ])->columns(2),

                Forms\Components\Section::make('Información Laboral')
                    ->description('Cargo y Área')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Forms\Components\Select::make('cargo_select')
                            ->label('Cargo')
                            ->searchable()
                            ->options(function () {
                                $cargos = [];
                                foreach (self::getCargos() as $cargo) {
                                    $cargos[$cargo] = $cargo;
                                }
                                $cargos['__otro__'] = '--- Otro (personalizado) ---';
                                return $cargos;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                if ($state !== '__otro__') {
                                    $set('cargo', $state);
                                    $set('cargo_otro', null);
                                } else {
                                    $set('cargo', null);
                                }
                            })
                            ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                // Al cargar el registro (edición), establecer cargo_select basado en el valor de cargo
                                $cargo = $get('cargo');
                                if ($cargo && in_array($cargo, self::getCargos())) {
                                    $set('cargo_select', $cargo);
                                } elseif ($cargo) {
                                    $set('cargo_select', '__otro__');
                                }
                            })
                            ->dehydrated(false)
                            ->helperText('Seleccione un cargo de la lista o elija "Otro" para personalizar')
                            ->placeholder('Seleccione el cargo...')
                            ->suffixIcon('heroicon-o-briefcase')
                            ->required(fn(Get $get) => empty($get('cargo_otro'))),

                        Forms\Components\TextInput::make('cargo_otro')
                            ->label('Especifique el Cargo')
                            ->visible(fn(Get $get) => $get('cargo_select') === '__otro__')
                            ->required(fn(Get $get) => $get('cargo_select') === '__otro__')
                            ->placeholder('Ej: Jefe de Proyectos Especiales')
                            ->helperText('Escriba el nombre del cargo personalizado')
                            ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                // Al cargar el registro (edición), si el cargo no está en la lista, establecer cargo_otro
                                $cargo = $get('cargo');
                                if ($cargo && !in_array($cargo, self::getCargos())) {
                                    $set('cargo_otro', $cargo);
                                }
                            }),

                        Forms\Components\Hidden::make('cargo')
                            ->required()
                            ->dehydrateStateUsing(function (Get $get) {
                                return $get('cargo_select') === '__otro__'
                                    ? $get('cargo_otro')
                                    : $get('cargo_select');
                            }),

                        Forms\Components\Select::make('area_select')
                            ->label('Área / Departamento')
                            ->searchable()
                            ->options(function () {
                                $areas = [];
                                foreach (self::getAreas() as $area) {
                                    $areas[$area] = $area;
                                }
                                $areas['__otro__'] = '--- Otro (personalizado) ---';
                                return $areas;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                if ($state !== '__otro__') {
                                    $set('area', $state);
                                    $set('area_otro', null);
                                } else {
                                    $set('area', null);
                                }
                            })
                            ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                // Al cargar el registro (edición), establecer area_select basado en el valor de area
                                $area = $get('area');
                                if ($area && in_array($area, self::getAreas())) {
                                    $set('area_select', $area);
                                } elseif ($area) {
                                    $set('area_select', '__otro__');
                                }
                            })
                            ->dehydrated(false)
                            ->helperText('Seleccione un área de la lista o elija "Otro" para personalizar')
                            ->placeholder('Seleccione el área...')
                            ->suffixIcon('heroicon-o-building-office-2'),

                        Forms\Components\TextInput::make('area_otro')
                            ->label('Especifique el Área')
                            ->visible(fn(Get $get) => $get('area_select') === '__otro__')
                            ->required(fn(Get $get) => $get('area_select') === '__otro__')
                            ->placeholder('Ej: Innovación y Desarrollo')
                            ->helperText('Escriba el nombre del área personalizada')
                            ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                // Al cargar el registro (edición), si el área no está en la lista, establecer area_otro
                                $area = $get('area');
                                if ($area && !in_array($area, self::getAreas())) {
                                    $set('area_otro', $area);
                                }
                            }),

                        Forms\Components\Hidden::make('area')
                            ->dehydrateStateUsing(function (Get $get) {
                                return $get('area_select') === '__otro__'
                                    ? $get('area_otro')
                                    : $get('area_select');
                            }),

                        // Forms\Components\DatePicker::make('fecha_ingreso')
                        //     ->label('Fecha de Ingreso')
                        //     ->required()
                        //     ->maxDate(now())
                        //     ->default(now())
                        //     ->native(false)
                        //     ->displayFormat('d/m/Y')
                        //     ->helperText('Fecha en que el trabajador ingresó a la empresa')
                        //     ->placeholder('Seleccione la fecha...')
                        //     ->suffixIcon('heroicon-o-calendar'),

                        Forms\Components\Toggle::make('active')
                            ->label('Trabajador Activo')
                            ->default(true)
                            ->helperText('Desactive si el trabajador ya no labora en la empresa')
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Datos de Contacto (Opcional)')
                    ->description('Teléfono y dirección')
                    ->icon('heroicon-o-phone')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono / Celular (Opcional)')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('Ej: +57 300 123 4567')
                            ->mask('(999) 999-9999')
                            ->helperText('Número de contacto del trabajador (Opcional)')
                            ->suffixIcon('heroicon-o-phone'),

                        Forms\Components\Textarea::make('direccion')
                            ->label('Dirección de Residencia (Opcional)')
                            ->rows(2)
                            ->placeholder('Ej: Calle 123 # 45-67, Barrio Centro')
                            ->helperText('Dirección completa')
                            // ->columnSpanFull()
                            ,
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_completo')
                    ->label('Trabajador')
                    ->searchable(['nombres', 'apellidos'])
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-user')
                    ->description(
                        fn(Trabajador $record): string =>
                        "{$record->tipo_documento}: {$record->numero_documento}"
                    ),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-briefcase'),

                Tables\Columns\TextColumn::make('area')
                    ->label('Área')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-building-office-2')
                    ->placeholder('Sin asignar'),

                // Tables\Columns\TextColumn::make('fecha_ingreso')
                //     ->label('Fecha Ingreso')
                //     ->date('d/m/Y')
                //     ->sortable()
                //     ->description(
                //         fn(Trabajador $record): ?string =>
                //         $record->fecha_ingreso?->diffForHumans()
                //     )
                //     ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-phone'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('procesos_disciplinarios_count')
                    ->label('Procesos')
                    ->badge()
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->procesos_disciplinarios_count ?? 0)
                    ->formatStateUsing(fn($state) => (string) ($state ?? 0))
                    ->color(fn($state): string => ($state > 0) ? 'warning' : 'success')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('tipo_documento')
                    ->label('Tipo Documento')
                    ->options([
                        'CC' => 'Cédula de Ciudadanía',
                        'CE' => 'Cédula de Extranjería',
                        'TI' => 'Tarjeta de Identidad',
                        'PASS' => 'Pasaporte',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos los trabajadores')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (Tables\Actions\DeleteAction $action, \App\Models\Trabajador $record) {
                        // Verificar si tiene procesos disciplinarios
                        if ($record->procesosDisciplinarios()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar el trabajador')
                                ->body("El trabajador '{$record->nombre_completo}' ({$record->tipo_documento}: {$record->numero_documento}) tiene {$record->procesosDisciplinarios()->count()} procesos disciplinarios asociados. Debe eliminar o reasignar esos procesos primero.")
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->action(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Support\Collection $records) {
                            $bloqueados = [];
                            $eliminados = 0;

                            foreach ($records as $record) {
                                // Verificar si tiene procesos disciplinarios
                                if ($record->procesosDisciplinarios()->count() > 0) {
                                    $bloqueados[] = $record->nombre_completo;
                                } else {
                                    $record->delete();
                                    $eliminados++;
                                }
                            }

                            if (count($bloqueados) > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Algunos trabajadores no se pudieron eliminar')
                                    ->body('Los siguientes trabajadores tienen procesos disciplinarios asociados: ' . implode(', ', $bloqueados))
                                    ->persistent()
                                    ->send();
                            }

                            if ($eliminados > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Trabajadores eliminados')
                                    ->body("{$eliminados} trabajador(es) eliminado(s) correctamente.")
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
            'index' => Pages\ListTrabajadors::route('/'),
            'create' => Pages\CreateTrabajador::route('/create'),
            'view' => Pages\ViewTrabajador::route('/{record}'),
            'edit' => Pages\EditTrabajador::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    protected static function getCargos(): array
    {
        return [
            'Gerente General',
            'Gerente Administrativo',
            'Gerente de Recursos Humanos',
            'Gerente Financiero',
            'Gerente Comercial',
            'Gerente de Operaciones',
            'Coordinador',
            'Supervisor',
            'Jefe de Área',
            'Asistente Administrativo',
            'Auxiliar Administrativo',
            'Secretaria',
            'Recepcionista',
            'Contador',
            'Auxiliar Contable',
            'Tesorero',
            'Analista Financiero',
            'Conductor',
            'Mensajero',
            'Operario',
            'Técnico',
            'Ingeniero',
            'Analista',
            'Desarrollador',
            'Programador',
            'Diseñador',
            'Vendedor',
            'Asesor Comercial',
            'Ejecutivo de Ventas',
            'Servicio al Cliente',
            'Call Center',
            'Soporte Técnico',
            'Logística',
            'Almacenista',
            'Bodeguero',
            'Vigilante',
            'Aseador',
            'Servicios Generales',
        ];
    }

    protected static function getAreas(): array
    {
        return [
            'Administración',
            'Recursos Humanos',
            'Contabilidad y Finanzas',
            'Tesorería',
            'Ventas',
            'Mercadeo',
            'Comercial',
            'Operaciones',
            'Producción',
            'Logística',
            'Almacén',
            'Tecnología',
            'Sistemas',
            'TI',
            'Compras',
            'Servicio al Cliente',
            'Calidad',
            'Mantenimiento',
            'Seguridad',
            'Jurídica',
            'Legal',
            'Auditoría',
            'Gerencia',
            'Dirección',
        ];
    }

    protected static function getDepartamentos(): array
    {
        return [
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
        ];
    }

    protected static function getCiudades(?string $departamento): array
    {
        if (!$departamento) {
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

        return array_combine(
            $ciudades[$departamento] ?? [],
            $ciudades[$departamento] ?? []
        );
    }
}
