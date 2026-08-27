<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages;
use App\Models\SolicitudContrato;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SolicitudContratoResource extends Resource
{
    protected static ?string $model = SolicitudContrato::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Solicitudes de Contrato';

    protected static ?string $modelLabel = 'Solicitud de Contrato';

    protected static ?string $pluralModelLabel = 'Solicitudes de Contrato';

    protected static ?string $navigationGroup = 'Gestión de Contratos';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user && $user->hasRole('cliente')) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Información Básica')
                        ->description('Datos generales de la solicitud')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Select::make('empresa_id')
                                ->label('Empresa')
                                ->relationship(
                                    name: 'empresa',
                                    titleAttribute: 'razon_social',
                                    modifyQueryUsing: fn (Builder $query, ?\Illuminate\Database\Eloquent\Model $record) => $query->paraAsignar($record?->empresa_id),
                                )
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
                                    return 'Seleccione la empresa para la cual se solicita el contrato';
                                })
                                ->placeholder('Busque y seleccione la empresa...')
                                ->suffixIcon('heroicon-o-building-office')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('tipo_contrato')
                                ->label('Tipo de Contrato')
                                ->required()
                                ->options([
                                    'Contrato a Término Fijo' => 'Contrato a Término Fijo - Duración determinada',
                                    'Contrato a Término Indefinido' => 'Contrato a Término Indefinido - Sin fecha de terminación',
                                    'Contrato de Obra o Labor' => 'Contrato de Obra o Labor - Por proyecto específico',
                                    'Contrato de Prestación de Servicios' => 'Contrato de Prestación de Servicios - Independiente',
                                    'Contrato de Aprendizaje' => 'Contrato de Aprendizaje - Estudiante/Aprendiz',
                                    'Contrato Ocasional o Transitorio' => 'Contrato Ocasional o Transitorio - Máximo 30 días',
                                ])
                                ->native(false)
                                ->searchable()
                                // live(): la visibilidad de "Orden de Compra" (paso
                                // Documentos) depende de este valor.
                                ->live()
                                ->helperText('Tipo de contrato laboral a generar')
                                ->placeholder('Seleccione el tipo de contrato...')
                                ->suffixIcon('heroicon-o-document-duplicate'),

                            Forms\Components\DateTimePicker::make('fecha_solicitud')
                                ->label('Fecha de Solicitud')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->helperText('Fecha y hora en que se realiza la solicitud')
                                ->suffixIcon('heroicon-o-calendar'),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('Datos del Trabajador')
                        ->description('Información del trabajador')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Toggle::make('_usar_trabajador_existente')
                                ->label('¿Usar trabajador existente?')
                                ->helperText('Active si el trabajador ya está registrado en el sistema')
                                ->live()
                                ->default(false)
                                ->inline(false)
                                ->columnSpanFull(),

                            Forms\Components\Select::make('trabajador_id')
                                ->label('Seleccionar Trabajador Existente')
                                ->relationship('trabajador', 'nombres')
                                ->searchable(['nombres', 'apellidos', 'numero_documento'])
                                ->preload()
                                ->getOptionLabelFromRecordUsing(
                                    fn(Trabajador $record): string =>
                                    "{$record->nombres} {$record->apellidos} - {$record->tipo_documento}: {$record->numero_documento}"
                                )
                                ->visible(fn(Get $get) => $get('_usar_trabajador_existente'))
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?int $state) {
                                    if ($state) {
                                        $trabajador = Trabajador::find($state);
                                        if ($trabajador) {
                                            $set('trabajador_nombres', $trabajador->nombres);
                                            $set('trabajador_apellidos', $trabajador->apellidos);
                                            $set('trabajador_documento_tipo', $trabajador->tipo_documento);
                                            $set('trabajador_documento_numero', $trabajador->numero_documento);
                                            $set('trabajador_email', $trabajador->email);
                                            $set('trabajador_telefono', $trabajador->telefono);
                                            $set('trabajador_direccion', $trabajador->direccion);
                                        }
                                    }
                                })
                                ->helperText('Busque por nombre, apellidos o número de documento')
                                ->placeholder('Busque el trabajador...')
                                ->suffixIcon('heroicon-o-magnifying-glass')
                                ->columnSpanFull(),

                            Forms\Components\Section::make('Datos Personales del Trabajador')
                                ->visible(fn(Get $get) => !$get('_usar_trabajador_existente'))
                                // ->dehydratedWhenHidden() AQUÍ, en la Section, no solo en los
                                // campos hijos: CanBeValidated::getValidationRules() (y por
                                // extensión getState()) recorre los componentes de nivel
                                // superior del Step y hace `continue` si el propio componente
                                // (aquí la Section) es isHiddenAndNotDehydrated() - eso pasa
                                // ANTES de siquiera mirar sus hijos, así que el
                                // dehydratedWhenHidden() de los TextInput internos nunca se
                                // llega a evaluar si la Section misma no lo tiene también.
                                // Confirmado leyendo vendor/filament/forms/src/Concerns/
                                // CanBeValidated.php y reproduciendo con Livewire::test() el
                                // INSERT real que fallaba en producción (un intento previo con
                                // solo ->dehydrated() en los campos, y luego uno con solo
                                // ->dehydratedWhenHidden() en los campos sin tocar la Section,
                                // ambos seguían sin incluir trabajador_nombres en getState()).
                                ->dehydratedWhenHidden()
                                ->schema([
                                    // dehydratedWhenHidden() en cada campo también, por
                                    // consistencia con la Section - sin esto, al usar un
                                    // trabajador existente esta Section queda oculta y
                                    // trabajador_id se guardaba pero trabajador_nombres/
                                    // apellidos/etc. quedaban fuera del INSERT: esas columnas
                                    // no admiten NULL, SQLSTATE 1364 "Field 'trabajador_nombres'
                                    // doesn't have a default value" en cualquier creación con
                                    // trabajador existente.
                                    Forms\Components\TextInput::make('trabajador_nombres')
                                        ->label('Nombres')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Juan Carlos')
                                        ->helperText('Nombres completos del trabajador')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\TextInput::make('trabajador_apellidos')
                                        ->label('Apellidos')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Pérez García')
                                        ->helperText('Apellidos completos del trabajador')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\Select::make('trabajador_documento_tipo')
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
                                        ->suffixIcon('heroicon-o-identification')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\TextInput::make('trabajador_documento_numero')
                                        ->label('Número de Documento')
                                        ->required()
                                        ->numeric()
                                        ->integer()
                                        ->extraInputAttributes(['min' => 0, 'onkeydown' => "return event.key !== '-'"])
                                        ->maxLength(50)
                                        ->placeholder(fn(Get $get) => match ($get('trabajador_documento_tipo')) {
                                            'CC' => 'Ej: 1234567890',
                                            'CE' => 'Ej: 9876543210',
                                            'TI' => 'Ej: 1234567890123',
                                            'PASS' => 'Ej: AB123456',
                                            default => 'Ingrese el número',
                                        })
                                        ->helperText('Número de identificación del trabajador')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\TextInput::make('trabajador_email')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('trabajador@empresa.com')
                                        ->helperText('Email de contacto del trabajador')
                                        ->suffixIcon('heroicon-o-envelope')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\TextInput::make('trabajador_telefono')
                                        ->label('Teléfono / Celular')
                                        ->tel()
                                        ->maxLength(50)
                                        ->placeholder('Ej: +57 300 123 4567')
                                        ->helperText('Número de contacto')
                                        ->suffixIcon('heroicon-o-phone')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\Textarea::make('trabajador_direccion')
                                        ->label('Dirección de Residencia')
                                        ->rows(2)
                                        ->placeholder('Ej: Calle 123 # 45-67')
                                        ->helperText('Dirección completa (opcional)')
                                        ->columnSpanFull()
                                        ->dehydratedWhenHidden(),
                                ])->columns(2),
                        ]),

                    Forms\Components\Wizard\Step::make('Detalles del Cargo')
                        ->description('Información del puesto y responsabilidades')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            // Forms\Components\Select::make('cargo_contrato')
                            //     ->label('Cargo')
                            //     ->required()
                            //     ->searchable()
                            //     ->options(self::getCargos())
                            //     ->getSearchResultsUsing(
                            //         fn(string $search): array =>
                            //         collect(self::getCargos())
                            //             ->filter(fn($cargo) => Str::contains(Str::lower($cargo), Str::lower($search)))
                            //             ->take(10)
                            //             ->mapWithKeys(fn($cargo) => [$cargo => $cargo])
                            //             ->toArray()
                            //     )
                            //     ->createOptionUsing(fn(string $value) => $value)
                            //     ->helperText('Seleccione o escriba el cargo para el contrato')
                            //     ->placeholder('Busque o escriba el cargo...')
                            //     ->suffixIcon('heroicon-o-briefcase')
                            //     ->columnSpanFull(),

                            // CORRECCIÓN: la tabla solicitudes_contrato NO tiene columna
                            // 'cargo' (solo 'cargo_contrato') - el Hidden::make('cargo') de
                            // abajo (ya retirado) intentaba guardar en una columna
                            // inexistente y reventaba con "Unknown column 'cargo'" en
                            // CUALQUIER intento real de guardar la solicitud (crear o
                            // editar, con cualquier cargo). cargo_contrato pasa a ser el
                            // único campo real: sigue mostrando la lista + "Otro", pero
                            // ahora si dehidrata (guarda) directo a la columna que sí
                            // existe.
                            Forms\Components\Select::make('cargo_contrato')
                                ->label('Cargo')
                                ->searchable()
                                ->suffixIcon('heroicon-o-briefcase')
                                ->columnSpanFull()
                                ->options(function () {
                                    $cargos = [];
                                    foreach (self::getCargos() as $cargo) {
                                        $cargos[$cargo] = $cargo;
                                    }
                                    $cargos['__otro__'] = '--- Otro (personalizado) ---';
                                    return $cargos;
                                })
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('cargo_otro', null))
                                ->afterStateHydrated(function (Set $set, ?string $state) {
                                    // $state ya es el valor real guardado en cargo_contrato.
                                    // Si no está en la lista predefinida, es un cargo
                                    // personalizado: mostrar el selector en "Otro" y
                                    // precargar el texto en cargo_otro.
                                    if ($state && !in_array($state, self::getCargos())) {
                                        $set('cargo_otro', $state);
                                        $set('cargo_contrato', '__otro__');
                                    }
                                })
                                ->dehydrateStateUsing(fn(Get $get, ?string $state) => $state === '__otro__' ? $get('cargo_otro') : $state)
                                ->helperText('Seleccione un cargo de la lista o elija "Otro" para personalizar')
                                ->placeholder('Seleccione el cargo...')
                                ->required(fn(Get $get) => empty($get('cargo_otro'))),

                            Forms\Components\TextInput::make('cargo_otro')
                                ->label('Especifique el Cargo')
                                ->columnSpanFull()
                                ->visible(fn(Get $get) => $get('cargo_contrato') === '__otro__')
                                ->required(fn(Get $get) => $get('cargo_contrato') === '__otro__')
                                ->placeholder('Ej: Jefe de Proyectos Especiales')
                                ->helperText('Escriba el nombre del cargo personalizado')
                                ->dehydrated(false),

                            Forms\Components\View::make('filament.components.solicitud-contrato-detalles-cargo-ia-boton')
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('responsabilidades')
                                ->label('Responsabilidades del Cargo')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'orderedList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Liste las responsabilidades principales del cargo...')
                                ->helperText('Describa las funciones y responsabilidades principales')
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('objeto_comercial')
                                ->label('Objeto Comercial')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Describa el objeto comercial del contrato...')
                                ->helperText('Objetivo comercial y alcance del contrato')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('descripcion_obra_labor')
                                ->label('Descripción de la obra o labor contratada')
                                ->placeholder('Ej: Construcción de la bodega de almacenamiento ubicada en...')
                                ->helperText('Describa la obra o labor específica - la IA la usará para redactar las cláusulas de Duración y Terminación de este contrato.')
                                ->rows(3)
                                ->visible(fn (Get $get) => $get('tipo_contrato') === 'Contrato de Obra o Labor')
                                ->required(fn (Get $get) => $get('tipo_contrato') === 'Contrato de Obra o Labor')
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('manual_funciones')
                                ->label('Manual de Funciones')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'orderedList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Detalle el manual de funciones...')
                                ->helperText('Descripción detallada de funciones del puesto')
                                ->columnSpanFull(),

                            Forms\Components\DatePicker::make('fecha_inicio_propuesta')
                                ->label('Fecha de Inicio Propuesta')
                                ->native(false)
                                // today() en vez de now(): now() trae hora exacta
                                // (H:i:s), y el mensaje de validación terminaba
                                // mostrando "...posterior o igual a 2026-08-18
                                // 10:16:14" en un campo que solo pide una fecha.
                                // Solo al crear: si se evalúa siempre, cualquier
                                // solicitud ya guardada se vuelve "inválida" apenas
                                // pasa su fecha propuesta, mostrando el mensaje de
                                // validación incluso en modo Ver (bug real
                                // reportado por el usuario, 2026-08-25).
                                ->minDate(fn (string $operation) => $operation === 'create' ? today() : null)
                                ->displayFormat('d/m/Y')
                                ->helperText('Fecha propuesta para iniciar el contrato')
                                ->placeholder('Seleccione la fecha...')
                                ->suffixIcon('heroicon-o-calendar'),

                            Forms\Components\TextInput::make('salario_propuesto')
                                ->label('Salario Propuesto')
                                // Sin ->numeric(): fuerza <input type="number">, que
                                // rechaza puntos de mil como separador (solo admite un
                                // punto decimal). El punto de mil se agrega vía
                                // afterStateUpdated() abajo - x-mask/$money (la forma
                                // documentada por Filament) NO está compilado en los
                                // assets JS de este proyecto (verificado: 0 ocurrencias
                                // de "money"/"mask" en public/js/filament/support/support.js
                                // incluso tras `artisan filament:assets`), así que en vez
                                // de usarlo silenciosamente sin efecto, se resuelve con
                                // el mismo mecanismo Livewire ya usado en este archivo.
                                ->rule('numeric')
                                ->minValue(0)
                                // 150ms (no 500ms como antes) - a pedido del usuario, para
                                // que el separador de miles aparezca prácticamente en tiempo
                                // real mientras digita, no tras una pausa notoria.
                                ->live(debounce: '150ms')
                                ->afterStateHydrated(fn(Set $set, $state) => $set('salario_propuesto', \App\Support\FormateoNumerico::miles($state)))
                                ->afterStateUpdated(fn(Set $set, ?string $state) => $set('salario_propuesto', \App\Support\FormateoNumerico::miles($state)))
                                ->stripCharacters('.')
                                ->extraInputAttributes(['min' => 0, 'onkeydown' => "return event.key !== '-'"])
                                ->prefix('$')
                                ->placeholder('Ej: 2.500.000')
                                ->helperText('Salario mensual propuesto para el cargo')
                                ->suffixIcon('heroicon-o-currency-dollar'),

                            Forms\Components\DatePicker::make('fecha_fin_contrato')
                                ->label('Fecha de Terminación del Contrato')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->afterOrEqual('fecha_inicio_propuesta')
                                // "Contrato Ocasional o Transitorio" tiene un límite legal de
                                // máximo 30 días (Art. 6 C.S.T.) - se acota la fecha máxima
                                // seleccionable en vez de dejar que el abogado escriba
                                // cualquier fecha y descubrir el error después. Sin fecha de
                                // inicio todavía, no hay base para calcular el límite - se
                                // comporta igual que los otros 5 tipos hasta que se elija.
                                ->maxDate(function (Get $get) {
                                    if ($get('tipo_contrato') !== 'Contrato Ocasional o Transitorio') {
                                        return null;
                                    }

                                    $fechaInicio = $get('fecha_inicio_propuesta');

                                    return $fechaInicio ? \Carbon\Carbon::parse($fechaInicio)->addDays(30) : null;
                                })
                                ->helperText(fn(Get $get) => $get('tipo_contrato') === 'Contrato Ocasional o Transitorio'
                                    ? 'Este tipo de contrato tiene un máximo legal de 30 días desde la fecha de inicio'
                                    : 'Fecha en que termina el contrato')
                                ->placeholder('Seleccione la fecha...')
                                ->suffixIcon('heroicon-o-calendar'),

                            // Mismas opciones/iconos/colores que el Select de
                            // periodicidad de pago del wizard del RIT
                            // (CreateReglamentoInterno.php:904-928) - se
                            // reutilizan tal cual para no inventar un segundo
                            // vocabulario de periodicidad en la misma app. A
                            // diferencia del RIT (que permite varias
                            // periodicidades por empresa con ->multiple()),
                            // acá es una sola opción: un contrato individual
                            // tiene un solo período de pago. Se usa
                            // ToggleButtons y no Select porque ->colors()/
                            // ->icons() no existen en Select (verificado
                            // contra el código de Filament).
                            Forms\Components\ToggleButtons::make('periodo_pago')
                                ->label('Período de Pago')
                                ->options([
                                    'mensual'   => 'Mensual (último día hábil del mes)',
                                    'quincenal' => 'Quincenal (días 15 y último)',
                                    'semanal'   => 'Semanal',
                                    'diario'    => 'Diario / jornaleros',
                                    'destajo'   => 'Por obra o destajo (según producción)',
                                ])
                                ->colors([
                                    'mensual'   => 'primary',
                                    'quincenal' => 'success',
                                    'semanal'   => 'warning',
                                    'diario'    => 'danger',
                                    'destajo'   => 'info',
                                ])
                                ->icons([
                                    'mensual'   => 'heroicon-o-calendar',
                                    'quincenal' => 'heroicon-o-calendar-date-range',
                                    'semanal'   => 'heroicon-o-calendar-days',
                                    'diario'    => 'heroicon-o-calendar-days',
                                    'destajo'   => 'heroicon-o-cube-transparent',
                                ])
                                ->default('quincenal')
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('lugar_labores')
                                ->label('Lugar Donde Desempeñará Labores')
                                ->maxLength(255)
                                ->default(function (Get $get) {
                                    $empresa = \App\Models\Empresa::find($get('empresa_id'));

                                    return collect([$empresa?->ciudad, $empresa?->departamento])->filter()->implode(', ');
                                })
                                ->helperText('Por defecto, la ciudad y departamento de la empresa - edítelo si el trabajador labora en otro sitio')
                                ->suffixIcon('heroicon-o-map-pin'),

                            // Mismas 3 opciones que ya usa ModificacionContractualResource.php
                            // para "Nueva Jornada/Modalidad" (tipo_modificacion='jornada') -
                            // se reutilizan tal cual para que el valor inicial y el valor tras
                            // una modificación posterior hablen el mismo vocabulario.
                            // ToggleButtons (no Select) a pedido del usuario, mismo patrón
                            // visual que periodo_pago arriba.
                            Forms\Components\ToggleButtons::make('jornada')
                                ->label('Jornada')
                                ->options([
                                    'Tiempo completo' => 'Tiempo completo',
                                    'Medio tiempo'    => 'Medio tiempo',
                                    'Por horas'       => 'Por horas',
                                    '__otro__'        => '--- Otro (personalizado) ---',
                                ])
                                ->colors([
                                    'Tiempo completo' => 'primary',
                                    'Medio tiempo'    => 'warning',
                                    'Por horas'       => 'info',
                                    '__otro__'        => 'gray',
                                ])
                                ->icons([
                                    'Tiempo completo' => 'heroicon-o-clock',
                                    'Medio tiempo'    => 'heroicon-o-clock',
                                    'Por horas'       => 'heroicon-o-calendar-days',
                                    '__otro__'        => 'heroicon-o-ellipsis-horizontal-circle',
                                ])
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('jornada_otro', null))
                                // Igual round-trip que cargo_contrato/cargo_otro (arriba en este
                                // mismo archivo): jornada YA puede tener un valor de texto libre
                                // arbitrario guardado por ModificacionContractualResource
                                // (tipo_modificacion='jornada', campo jornada_otro_temp) - sin
                                // este afterStateHydrated(), editar una Solicitud con ese tipo de
                                // valor mostraría el ToggleButtons sin ninguna opción marcada.
                                ->afterStateHydrated(function (Set $set, ?string $state) {
                                    if ($state && !in_array($state, ['Tiempo completo', 'Medio tiempo', 'Por horas'], true)) {
                                        $set('jornada_otro', $state);
                                        $set('jornada', '__otro__');
                                    }
                                })
                                ->dehydrateStateUsing(fn(Get $get, ?string $state) => $state === '__otro__' ? $get('jornada_otro') : $state)
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('jornada_otro')
                                ->label('Especifique la Jornada')
                                ->columnSpanFull()
                                ->visible(fn(Get $get) => $get('jornada') === '__otro__')
                                ->required(fn(Get $get) => $get('jornada') === '__otro__')
                                ->placeholder('Ej: Turnos rotativos')
                                ->helperText('Escriba la jornada personalizada')
                                ->dehydrated(false),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('Documentos')
                        ->description('Archivos adjuntos')
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Forms\Components\FileUpload::make('ruta_orden_compra')
                                ->label('Orden de Compra')
                                ->directory('solicitudes-contratos/ordenes-compra')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->helperText('Adjunte la orden de compra o autorización (PDF, JPG, PNG - Máx. 5MB)')
                                ->downloadable()
                                ->openable()
                                // En la práctica una orden de compra autoriza el gasto
                                // ante un proveedor externo (Prestación de Servicios,
                                // Obra o Labor) - no aplica a un empleado directo a
                                // Término Fijo/Indefinido/Aprendizaje.
                                ->visible(fn(Get $get) => in_array($get('tipo_contrato'), [
                                    'Contrato de Prestación de Servicios',
                                    'Contrato de Obra o Labor',
                                ]))
                                ->dehydrated()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('ruta_manual_funciones')
                                ->label('Manual de Funciones')
                                ->directory('solicitudes-contratos/manuales-funciones')
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->maxSize(10240)
                                ->helperText('Adjunte el manual de funciones (PDF, DOC, DOCX - Máx. 10MB)')
                                ->downloadable()
                                ->openable()
                                ->columnSpanFull(),
                        ]),
                ])
                    ->columnSpanFull()
                    ->persistStepInQueryString()
                    // ->disabled() de la página "Ver" NO neutraliza este botón: es un
                    // HtmlString crudo dentro de submitAction(), no un Componente real
                    // de Filament, así que no responde a hiddenOn()/disabled(). Bug real
                    // reportado por el usuario viendo /admin/solicitud-contratos/1 -
                    // "Crear Solicitud" aparecía clicable en el último paso de una
                    // solicitud que ya existe. $form->getOperation() (misma fuente que ya
                    // usa hiddenOn() internamente) sí está disponible en este punto,
                    // porque la Page (View/Edit/CreateRecord) configura el operation
                    // ANTES de llamar a este form() estático.
                    ->submitAction(
                        $form->getOperation() === 'view'
                            ? null
                            : new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset dark:focus:ring-offset-0 min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Crear Solicitud</button>')
                    ),

                // Oculta a pedido del usuario (2026-08-25) mientras se retira el
                // rol "abogado" del sistema (tarea aparte, todavía sin agendar) -
                // no se borró el campo/relación para no perder la asignación ya
                // guardada en registros existentes.
                Forms\Components\Section::make('Asignación interna')
                    ->description('Solo visible para el equipo de CES Legal')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\Select::make('abogado_id')
                            ->label('Abogado Asignado')
                            ->relationship('abogado', 'name', fn(Builder $query) => $query->where('role', 'abogado'))
                            ->searchable()
                            ->preload()
                            ->helperText('Abogado responsable del análisis')
                            ->placeholder('Seleccione un abogado...')
                            ->suffixIcon('heroicon-o-scale'),
                    ])
                    ->collapsed()
                    ->hidden(),

                Forms\Components\Section::make('Análisis Jurídico')
                    ->description('Observaciones y objeto jurídico')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\RichEditor::make('objeto_juridico_redactado')
                            ->label('Objeto Jurídico Redactado')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'orderedList',
                                'italic',
                                'undo',
                                'redo',
                            ])
                            ->placeholder('Redacte el objeto jurídico del contrato...')
                            ->helperText('Redacción jurídica del objeto del contrato')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('observaciones_juridicas')
                            ->label('Observaciones Jurídicas')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'orderedList',
                                'italic',
                                'undo',
                                'redo',
                            ])
                            ->placeholder('Ingrese observaciones jurídicas...')
                            ->helperText('Notas y observaciones del análisis jurídico')
                            ->columnSpanFull(),
                    ])
                    // Sección de uso interno (redacción/observaciones jurídicas) -
                    // no aplica a la experiencia de creación/visualización simple
                    // del cliente, que ahora ve el borrador automático directamente.
                    ->hiddenOn(['create', 'view'])
                    ->collapsed(),
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
                    ->icon('heroicon-o-hashtag')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'gray' => 'borrador',
                        'success' => 'aprobado',
                        'danger' => 'rechazado',
                    ])
                    ->icons([
                        'heroicon-o-document-text' => 'borrador',
                        'heroicon-o-check-circle' => 'aprobado',
                        'heroicon-o-x-circle' => 'rechazado',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'borrador' => 'Borrador',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn(string $state): string => explode(' - ', $state)[0] ?? $state)
                    ->icon('heroicon-o-document-duplicate'),

                Tables\Columns\TextColumn::make('trabajador_nombres')
                    ->label('Trabajador')
                    ->searchable(['trabajador_nombres', 'trabajador_apellidos'])
                    ->sortable()
                    ->description(
                        fn(SolicitudContrato $record): string =>
                        "{$record->trabajador_documento_tipo}: {$record->trabajador_documento_numero}"
                    )
                    ->formatStateUsing(
                        fn(SolicitudContrato $record): string =>
                        "{$record->trabajador_nombres} {$record->trabajador_apellidos}"
                    )
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('cargo_contrato')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-briefcase'),

                // Oculta a pedido del usuario (2026-08-25), mismo motivo que la
                // Section "Asignación interna" del formulario.
                Tables\Columns\TextColumn::make('abogado.name')
                    ->label('Abogado')
                    ->searchable()
                    ->sortable()
                    ->default('Sin asignar')
                    ->icon('heroicon-o-scale')
                    ->hidden(),

                Tables\Columns\TextColumn::make('salario_propuesto')
                    ->label('Salario')
                    ->numeric()
                    ->money('COP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fecha_solicitud')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->description(
                        fn(SolicitudContrato $record): string =>
                        $record->fecha_solicitud->diffForHumans()
                    )
                    ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->options([
                        'Contrato a Término Fijo' => 'Término Fijo',
                        'Contrato a Término Indefinido' => 'Término Indefinido',
                        'Contrato de Obra o Labor' => 'Obra o Labor',
                        'Contrato de Prestación de Servicios' => 'Prestación de Servicios',
                        'Contrato de Aprendizaje' => 'Aprendizaje',
                        'Contrato Ocasional o Transitorio' => 'Ocasional',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social', modifyQueryUsing: fn (Builder $query) => $query->paraAsignar())
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Oculto a pedido del usuario (2026-08-25), mismo motivo que la
                // columna y la Section "Asignación interna".
                Tables\Filters\SelectFilter::make('abogado')
                    ->label('Abogado')
                    ->relationship('abogado', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->hidden(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),

                // Faltaba tras retirar la Section "Progreso de la Solicitud"
                // (que tenía el único enlace de descarga) - sin esto, Aprobar/
                // Regenerar Borrador generaban el PDF correctamente pero no
                // había ninguna forma de verlo desde la interfaz (bug real
                // reportado por el usuario, 2026-08-25). Muestra tanto el
                // borrador con marca de agua como el aprobado ya protegido,
                // según el estado actual.
                Tables\Actions\Action::make('verContrato')
                    ->label('Ver Contrato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn (SolicitudContrato $record) => filled($record->ruta_contrato))
                    ->url(fn (SolicitudContrato $record) => route('solicitud-contrato.descargar', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('regenerarBorrador')
                    ->label('Regenerar Borrador')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (SolicitudContrato $record) => $record->estado === 'borrador')
                    ->requiresConfirmation()
                    ->modalDescription('Se generará un nuevo borrador del contrato con los datos actuales.')
                    ->action(function (SolicitudContrato $record) {
                        $service = app(\App\Services\SolicitudContratoIAService::class);

                        if (empty($record->objeto_juridico_redactado)) {
                            $texto = $service->redactarObjetoJuridico($record);
                            $record->update(['objeto_juridico_redactado' => $texto]);
                        }

                        $service->generarContratoPDF($record, borrador: true);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Borrador regenerado')
                            ->send();
                    }),

                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SolicitudContrato $record) => $record->estado === 'borrador')
                    ->requiresConfirmation()
                    ->modalDescription('Se generará el contrato final (protegido, sin marca de agua) y quedará aprobado.')
                    ->action(function (SolicitudContrato $record) {
                        app(\App\Services\SolicitudContratoIAService::class)->generarContratoPDF($record, borrador: false);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Solicitud aprobada')
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SolicitudContrato $record) => $record->estado === 'borrador')
                    ->requiresConfirmation()
                    ->modalDescription('¿Está seguro de rechazar esta solicitud? Esta acción no se puede deshacer desde la interfaz.')
                    ->action(function (SolicitudContrato $record) {
                        $record->update(['estado' => 'rechazado']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Solicitud rechazada')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ])
            ->defaultSort('fecha_solicitud', 'desc');
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
            'index' => Pages\ListSolicitudContratos::route('/'),
            'create' => Pages\CreateSolicitudContrato::route('/create'),
            'view' => Pages\ViewSolicitudContrato::route('/{record}'),
            'edit' => Pages\EditSolicitudContrato::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('estado', 'borrador')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getCargos(): array
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
}
