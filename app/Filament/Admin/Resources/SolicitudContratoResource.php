<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SolicitudContratoResource extends Resource
{
    protected static ?string $model = SolicitudContrato::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Historial de Contratos';

    protected static ?string $modelLabel = 'Solicitud de Contrato';

    protected static ?string $pluralModelLabel = 'Historial de Contratos';

    protected static ?string $navigationGroup = 'Gestión de Contratos';

    protected static ?int $navigationSort = 1;

    /**
     * Mismo patrón ya usado por ProcesoDisciplinarioResource ("Crear Citación
     * de Descargos" + "Historial de Descargos") e InformeJuridicoResource:
     * "Crear" como enlace directo al wizard, separado de "Historial" (el
     * listado). static::canCreate() consulta la misma Policy que ya protege
     * la Action de crear, una sola fuente de verdad de permisos.
     */
    public static function getNavigationItems(): array
    {
        $items = [];

        if (static::canCreate()) {
            $items[] = NavigationItem::make('Crear Solicitud de Contrato')
                ->icon('heroicon-o-plus-circle')
                ->group(static::getNavigationGroup())
                ->url(static::getUrl('create'))
                ->sort(0);
        }

        $items[] = NavigationItem::make('Historial de Contratos')
            ->icon(static::getNavigationIcon())
            ->group(static::getNavigationGroup())
            ->url(static::getUrl('index'))
            ->sort(1)
            ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.*') && ! request()->routeIs(static::getRouteBaseName() . '.create'));

        return $items;
    }

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
                    // Paso 0: Bienvenida (pantalla propia, no cuenta en "Paso X de 4") -
                    // mismo patrón que CreateProcesoDisciplinario::getSteps().
                    Forms\Components\Wizard\Step::make('bienvenida')
                        ->label('Bienvenida')
                        ->description('Lea antes de empezar')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\View::make('filament.components.bienvenida-solicitud-contrato')
                                ->key('sc_bienvenida_contenido')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Wizard\Step::make('Información Básica')
                        ->description('Datos generales de la solicitud')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\View::make('filament.components.step-header')
                                ->key('sc_step_header_1')
                                ->viewData([
                                    'step' => 1,
                                    'total' => 5,
                                    'title' => 'Información Básica',
                                    'accent' => '#e11d48',
                                    'lord' => 'https://cdn.lordicon.com/moedrfvp.json',
                                    'subtitle' => 'Empresa, tipo de contrato y fecha de la solicitud.',
                                ])
                                ->columnSpanFull(),

                            Forms\Components\Select::make('empresa_id')
                                ->label('Empresa')
                                ->relationship(
                                    name: 'empresa',
                                    titleAttribute: 'razon_social',
                                    modifyQueryUsing: fn(
                                        Builder $query,
                                        ?\Illuminate\Database\Eloquent\Model $record
                                    ) => $query->paraAsignar($record?->empresa_id),
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(function () {
                                    $user = auth()->user();

                                    return $user && $user->isCliente()
                                        ? $user->empresa_id
                                        : null;
                                })
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state): void {

                                    $empresa = $state
                                        ? Empresa::find($state)
                                        : null;

                                    if (!$empresa) {
                                        $set('departamento', null);
                                        $set('ciudad', null);
                                        $set('lugar_labores', null);

                                        return;
                                    }

                                    $set('departamento', $empresa->departamento);
                                    $set('ciudad', $empresa->ciudad);

                                    $set(
                                        'lugar_labores',
                                        collect([
                                            $empresa->ciudad,
                                            $empresa->departamento,
                                        ])
                                            ->filter()
                                            ->implode(', ')
                                    );
                                })
                                ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                                ->dehydrated()
                                ->helperText(function () {
                                    $user = auth()->user();

                                    return $user && $user->isCliente()
                                        ? 'Empresa asignada automáticamente'
                                        : 'Seleccione la empresa para la cual se solicita el contrato';
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
                                // Documentos) y del Fieldset "Duración del Contrato"
                                // (paso Condiciones del Contrato) depende de este valor.
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    // Término Indefinido y Obra o Labor no usan fecha de
                                    // terminación (ver self::tiposConFechaTerminacion()) -
                                    // si el usuario ya la había puesto y cambia a uno de
                                    // estos 2 tipos, se limpia. Sin esto, en una EDICIÓN
                                    // la fecha vieja quedaría huérfana en la base de datos:
                                    // el campo, al ocultarse, no se dehidrata en el update
                                    // y nadie tendría forma de verla ni corregirla.
                                    if (!in_array($state, self::tiposConFechaTerminacion(), true)) {
                                        $set('fecha_fin_contrato', null);
                                        $set('duracion_cantidad', null);
                                        $set('duracion_unidad', 'dia');
                                        $set('duracion_cantidad_2', null);
                                        $set('duracion_unidad_2', null);
                                        $set('duracion_cantidad_3', null);
                                    }
                                })
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
                            Forms\Components\View::make('filament.components.step-header')
                                ->key('sc_step_header_2')
                                ->viewData([
                                    'step' => 2,
                                    'total' => 5,
                                    'title' => 'Datos del Trabajador',
                                    'accent' => '#f97316',
                                    'lord' => 'https://cdn.lordicon.com/bushiqea.json',
                                    'subtitle' => 'Seleccione un trabajador existente o registre uno nuevo.',
                                ])
                                ->columnSpanFull(),

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
                                // Si se usa un trabajador existente, la sección se oculta y sus
                                // campos se autocompletan (ver afterStateUpdated de trabajador_id)
                                // - PERO si ese trabajador quedó registrado antes de que
                                // teléfono/dirección fueran obligatorios, no puede quedar
                                // oculta con datos vacíos: el usuario no vería dónde
                                // completarlos y el envío fallaría sin explicación visible.
                                ->visible(
                                    fn(Get $get) =>
                                    !$get('_usar_trabajador_existente')
                                    || empty($get('trabajador_telefono'))
                                    || empty($get('trabajador_direccion'))
                                )
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
                                        ->extraInputAttributes(['min' => 0, 'onkeydown' => "return !['-','+','e','E','.'].includes(event.key)"])
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
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('Ej: +57 300 123 4567')
                                        ->helperText('Número de contacto')
                                        ->suffixIcon('heroicon-o-phone')
                                        ->dehydratedWhenHidden(),

                                    Forms\Components\Textarea::make('trabajador_direccion')
                                        ->label('Dirección de Residencia')
                                        ->required()
                                        ->rows(2)
                                        ->placeholder('Ej: Calle 123 # 45-67')
                                        ->helperText('Dirección completa')
                                        ->columnSpanFull()
                                        ->dehydratedWhenHidden(),
                                ])->columns(2),
                        ]),

                    Forms\Components\Wizard\Step::make('Detalles del Cargo')
                        ->description('Información del puesto y responsabilidades')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Forms\Components\View::make('filament.components.step-header')
                                ->key('sc_step_header_3')
                                ->viewData([
                                    'step' => 3,
                                    'total' => 5,
                                    'title' => 'Detalles del Cargo',
                                    'accent' => '#0ea5e9',
                                    'lord' => 'https://cdn.lordicon.com/bpptgtfr.json',
                                    'subtitle' => 'Cargo, responsabilidades y funciones - use "Completar con IA" para agilizar.',
                                ])
                                ->columnSpanFull(),

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
                                ->options(fn(Get $get) => self::getCargosParaSelect($get('empresa_id')))
                                ->helperText(fn(Get $get) => filled($get('empresa_id')) && filled(self::getOrganigramaDeEmpresa($get('empresa_id')))
                                    ? 'Cargos tomados del organigrama de su Reglamento Interno. Elija "Otro" si no está en la lista.'
                                    : 'Seleccione un cargo de la lista o elija "Otro" para personalizar')
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('cargo_otro', null))
                                ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                    // $state ya es el valor real guardado en cargo_contrato.
                                    // Si no está en la lista disponible para esta empresa
                                    // (organigrama del RIT o el listado fijo de respaldo), es
                                    // un cargo personalizado: mostrar el selector en "Otro" y
                                    // precargar el texto en cargo_otro.
                                    $disponibles = self::getCargosParaSelect($get('empresa_id'));
                                    if ($state && !array_key_exists($state, $disponibles)) {
                                        $set('cargo_otro', $state);
                                        $set('cargo_contrato', '__otro__');
                                    }
                                })
                                ->dehydrateStateUsing(fn(Get $get, ?string $state) => $state === '__otro__' ? $get('cargo_otro') : $state)
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
                                ->visible(fn(Get $get) => $get('tipo_contrato') === 'Contrato de Obra o Labor')
                                ->required(fn(Get $get) => $get('tipo_contrato') === 'Contrato de Obra o Labor')
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
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('Condiciones del Contrato')
                        ->description('Fechas, salario, ubicación y jornada')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Forms\Components\View::make('filament.components.step-header')
                                ->key('sc_step_header_4')
                                ->viewData([
                                    'step' => 4,
                                    'total' => 5,
                                    'title' => 'Condiciones del Contrato',
                                    'accent' => '#a855f7',
                                    'lord' => 'https://cdn.lordicon.com/vgwutnhw.json',
                                    'subtitle' => 'Fecha de inicio y terminación, salario, período de pago, ubicación y jornada.',
                                ])
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
                                ->minDate(fn(string $operation) => $operation === 'create' ? today() : null)
                                ->displayFormat('d/m/Y')
                                ->live()
                                // Si ya hay una duración puesta (ej. "6 meses"), mover la fecha
                                // de inicio debe recalcular la fecha de fin manteniendo esa
                                // misma duración - no dejarla desactualizada apuntando al
                                // inicio anterior.
                                ->afterStateUpdated(fn(Set $set, Get $get) => $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get)))
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
                                ->extraInputAttributes(['min' => 0, 'onkeydown' => "return !['-','+','e','E'].includes(event.key)"])
                                ->prefix('$')
                                ->placeholder('Ej: 2.500.000')
                                ->helperText('Salario mensual propuesto para el cargo')
                                ->suffixIcon('heroicon-o-currency-dollar'),

                            Forms\Components\Fieldset::make('Duración del Contrato')
                                ->columnSpanFull()
                                // Término Indefinido no tiene fecha de terminación por
                                // definición; Obra o Labor se rige por la finalización de
                                // la obra/labor contratada (descripcion_obra_labor +
                                // redacción de IA en duracion_terminacion_obra_redactada),
                                // no por una fecha calendario - ver self::tiposConFechaTerminacion().
                                ->visible(fn(Get $get) => in_array($get('tipo_contrato'), self::tiposConFechaTerminacion(), true))
                                ->schema([
                                    // Sin fecha de inicio, calcularFechaFinDesdeDuracion() no
                                    // tiene desde dónde contar y no hace nada - antes esto
                                    // pasaba en silencio (bug real reportado por el usuario:
                                    // "no está calculando", cuando en realidad solo faltaba
                                    // seleccionar la Fecha de Inicio Propuesta arriba). Ahora
                                    // se avisa explícitamente en vez de quedar callado.
                                    Forms\Components\Placeholder::make('duracion_ayuda')
                                        ->hiddenLabel()
                                        ->content(function (Get $get) {
                                            if (blank($get('fecha_inicio_propuesta'))) {
                                                // Mismo icono/tono ámbar ya usado para avisos de "falta
                                                // algo" en emitir-sancion-pasos.blade.php.
                                                return new \Illuminate\Support\HtmlString(
                                                    '<div style="display:flex;align-items:center;gap:.5rem;color:#d97706" class="dark:text-amber-400">'
                                                    . '<lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#d97706,secondary:#fbbf24" style="width:22px;height:22px;flex-shrink:0"></lord-icon>'
                                                    . '<span>Primero seleccione la Fecha de Inicio Propuesta (arriba) para poder calcular la fecha de terminación.</span>'
                                                    . '</div>'
                                                );
                                            }

                                            return 'Indique la duración (ej: 6 meses) y la fecha de terminación se calcula sola. También puede editar la fecha directamente - la duración se ajustará automáticamente.';
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('duracion_cantidad')
                                        ->label('Duración')
                                        ->numeric()
                                        ->minValue(1)
                                        ->placeholder('Ej: 6')
                                        // Bloqueo a nivel de tecla: <input type="number"> (forzado por
                                        // ->numeric()) acepta nativamente '-'/'+'/'e'/'E' como parte de
                                        // la gramática de notación científica de un número - sin esto,
                                        // el navegador deja escribir "1e5" o "-3" aunque minValue(1) las
                                        // rechace después. Bug real reportado por el usuario.
                                        ->extraInputAttributes(['min' => 1, 'onkeydown' => "return !['-','+','e','E','.'].includes(event.key)"])
                                        ->disabled(fn(Get $get) => blank($get('fecha_inicio_propuesta')))
                                        ->live(debounce: '500ms')
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn(Set $set, Get $get) => $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get))),

                                    Forms\Components\Select::make('duracion_unidad')
                                        ->label('Unidad')
                                        ->options(['dia' => 'Día(s)', 'mes' => 'Mes(es)', 'anio' => 'Año(s)'])
                                        ->default('dia')
                                        ->disabled(fn(Get $get) => blank($get('fecha_inicio_propuesta')))
                                        ->live()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(function (Set $set, Get $get) {
                                            // Al cambiar la unidad principal, los campos anidados
                                            // (que dependían de la unidad anterior) ya no aplican.
                                            $set('duracion_cantidad_2', null);
                                            $set('duracion_unidad_2', null);
                                            $set('duracion_cantidad_3', null);
                                            $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get));
                                        }),

                                    // Fila 2: "años" pide una unidad adicional (meses o días);
                                    // "meses" solo permite agregar días (sin selector, una sola opción).
                                    Forms\Components\TextInput::make('duracion_cantidad_2')
                                        ->label(fn(Get $get) => $get('duracion_unidad') === 'mes' ? 'Días adicionales' : 'Cantidad adicional')
                                        ->numeric()
                                        ->minValue(0)
                                        ->extraInputAttributes(['min' => 0, 'onkeydown' => "return !['-','+','e','E','.'].includes(event.key)"])
                                        ->visible(fn(Get $get) => in_array($get('duracion_unidad'), ['anio', 'mes'], true))
                                        ->live(debounce: '500ms')
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn(Set $set, Get $get) => $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get))),

                                    Forms\Components\Select::make('duracion_unidad_2')
                                        ->label('Unidad adicional')
                                        ->options(['mes' => 'Mes(es)', 'dia' => 'Día(s)'])
                                        ->visible(fn(Get $get) => $get('duracion_unidad') === 'anio')
                                        ->live()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(function (Set $set, Get $get) {
                                            $set('duracion_cantidad_3', null);
                                            $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get));
                                        }),

                                    // Fila 3: solo cuando "años" + "meses" ya están puestos, se
                                    // puede afinar con días sueltos (ej: 1 año, 2 meses y 10 días).
                                    Forms\Components\TextInput::make('duracion_cantidad_3')
                                        ->label('Días adicionales')
                                        ->numeric()
                                        ->minValue(0)
                                        ->extraInputAttributes(['min' => 0, 'onkeydown' => "return !['-','+','e','E','.'].includes(event.key)"])
                                        ->visible(fn(Get $get) => $get('duracion_unidad') === 'anio' && $get('duracion_unidad_2') === 'mes')
                                        ->live(debounce: '500ms')
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn(Set $set, Get $get) => $set('fecha_fin_contrato', self::calcularFechaFinDesdeDuracion($get))),

                                    Forms\Components\DatePicker::make('fecha_fin_contrato')
                                        ->label('Fecha de Terminación del Contrato')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->live()
                                        // Antes no era obligatoria para ningún tipo de contrato,
                                        // ni siquiera Término Fijo - donde es legalmente esencial
                                        // (un contrato a término fijo sin fecha de terminación es
                                        // una contradicción). Solo aplica cuando el Fieldset
                                        // completo es visible (ver self::tiposConFechaTerminacion()).
                                        ->required(fn(Get $get) => in_array($get('tipo_contrato'), self::tiposConFechaTerminacion(), true))
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
                                        // Edición manual de la fecha: descompone hacia atrás en
                                        // duración (años/meses/días) para que ambos lados del
                                        // calculador se mantengan sincronizados.
                                        ->afterStateUpdated(fn(Set $set, Get $get) => self::descomponerDuracionDesdeFecha($set, $get))
                                        ->afterStateHydrated(fn(Set $set, Get $get) => self::descomponerDuracionDesdeFecha($set, $get))
                                        ->helperText(fn(Get $get) => $get('tipo_contrato') === 'Contrato Ocasional o Transitorio'
                                            ? 'Este tipo de contrato tiene un máximo legal de 30 días desde la fecha de inicio'
                                            : 'Se calcula sola con la duración de arriba, o edítela directamente')
                                        ->placeholder('Seleccione la duración o la fecha...')
                                        ->suffixIcon('heroicon-o-calendar')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

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
                                ->default('mensual')
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\Select::make('departamento')
                                ->label('Departamento')
                                ->required()
                                ->searchable()
                                ->options(fn() => self::getDepartamentos())
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get): void {
                                    // Si el departamento cambia manualmente,
                                    // se limpia la ciudad anterior.
                                    $set('ciudad', null);

                                    $set(
                                        'lugar_labores',
                                        collect([
                                            $get('ciudad'),
                                            $get('departamento'),
                                        ])
                                            ->filter()
                                            ->implode(', ')
                                    );
                                })
                                ->helperText('Seleccione el departamento'),

                            Forms\Components\Select::make('ciudad')
                                ->label('Ciudad')
                                ->required()
                                ->searchable()
                                ->options(function (Get $get) {

                                    $departamento = $get('departamento');
                                    $ciudadActual = $get('ciudad');

                                    if (blank($departamento)) {
                                        return [];
                                    }

                                    $ciudades = self::getCiudadesPorDepartamento($departamento);

                                    if (filled($ciudadActual) && ! array_key_exists($ciudadActual, $ciudades)) {
                                        $ciudades[$ciudadActual] = $ciudadActual;
                                    }

                                    return $ciudades;
                                })
                                ->disabled(fn(Get $get) => blank($get('departamento')))
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get): void {
                                    $set(
                                        'lugar_labores',
                                        collect([
                                            $get('ciudad'),
                                            $get('departamento'),
                                        ])
                                            ->filter()
                                            ->implode(', ')
                                    );
                                })
                                ->helperText('Seleccione primero el departamento')
                                ->placeholder('Seleccione una ciudad...')
                                ->afterStateHydrated(function (Get $get, Set $set): void {

                                    $empresaId = $get('empresa_id');

                                    if (blank($empresaId)) {
                                        return;
                                    }

                                    $empresa = Empresa::find($empresaId);

                                    if (!$empresa) {
                                        return;
                                    }

                                    if (blank($get('departamento'))) {
                                        $set('departamento', $empresa->departamento);
                                    }

                                    if (blank($get('ciudad'))) {
                                        $set('ciudad', $empresa->ciudad);
                                    }

                                    if (blank($get('lugar_labores'))) {
                                        $set(
                                            'lugar_labores',
                                            collect([
                                                $empresa->ciudad,
                                                $empresa->departamento,
                                            ])
                                                ->filter()
                                                ->implode(', ')
                                        );
                                    }
                                }),

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
                                ->default('Tiempo completo')
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
                            Forms\Components\View::make('filament.components.step-header')
                                ->key('sc_step_header_5')
                                ->viewData([
                                    'step' => 5,
                                    'total' => 5,
                                    'title' => 'Documentos',
                                    'accent' => '#22c55e',
                                    'lord' => 'https://cdn.lordicon.com/fikcyfpp.json',
                                    'subtitle' => 'Adjunte los archivos de soporte y finalice para generar el contrato con IA.',
                                ])
                                ->columnSpanFull(),

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
                    // "Ver" ya no renderiza form() en absoluto (vista custom propia,
                    // ver ViewSolicitudContrato::$view) - esta rama solo sigue viva
                    // por si algún día se vuelve a invocar form() en modo view. El
                    // bug real que sí sigue vigente: el texto estaba fijo en "Crear
                    // Solicitud" sin importar la operación, así que al EDITAR una
                    // solicitud ya existente, el botón del último paso decía "Crear
                    // Solicitud" en vez de "Guardar Cambios".
                    ->submitAction(
                        $form->getOperation() === 'view'
                            ? null
                            : new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset dark:focus:ring-offset-0 min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">' . ($form->getOperation() === 'edit' ? 'Guardar Cambios' : 'Crear Solicitud') . '</button>')
                    ),

                // Oculta mientras se retira el rol "abogado" del sistema (tarea aparte, todavía sin agendar) -
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
                    ->copyable(),

                Tables\Columns\TextColumn::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn(string $state): string => explode(' - ', $state)[0] ?? $state),

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
                    ->icon('heroicon-o-user')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-building-office')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('cargo_contrato')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('primary'),

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
                    ->icon('heroicon-o-calendar')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Al final a pedido del usuario (antes iba justo después de
                // "Código") - el motivo de rechazo se muestra como
                // descripción debajo del badge, no queda escondido.
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aprobado' => 'success',
                        'rechazado' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'aprobado' => 'heroicon-o-check-circle',
                        'rechazado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-document-text',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'borrador' => 'Borrador',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        default => $state,
                    })
                    ->description(fn(SolicitudContrato $record): ?string => $record->estado === 'rechazado' ? $record->motivo_rechazo : null)
                    ->sortable(),
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
                    ->relationship('empresa', 'razon_social', modifyQueryUsing: fn(Builder $query) => $query->paraAsignar())
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
                // Lo más importante según el estado actual, visible directo en
                // la fila (mismo criterio que Historial de Descargos: los
                // botones cambian según en qué etapa está el registro, en vez
                // de mostrar siempre los mismos 7). El resto (Ver/Editar/
                // Eliminar/Regenerar) va en el menú "..." de abajo.

                // Faltaba tras retirar la Section "Progreso de la Solicitud"
                // (que tenía el único enlace de descarga) - sin esto, Aprobar/
                // Regenerar Borrador generaban el PDF correctamente pero no
                // había ninguna forma de verlo desde la interfaz (bug real
                // reportado por el usuario, 2026-08-25). Muestra tanto el
                // borrador con marca de agua como el aprobado ya protegido,
                // según el estado actual. Siempre visible en línea (no en el
                // menú) porque es la acción más consultada sin importar el
                // estado.
                Tables\Actions\Action::make('verContrato')
                    ->label('Ver Contrato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn(SolicitudContrato $record) => filled($record->ruta_contrato))
                    ->url(fn(SolicitudContrato $record) => route('solicitud-contrato.descargar', $record))
                    ->openUrlInNewTab(),

                // Decisión pendiente: lo más urgente mientras está en borrador,
                // por eso Aprobar/Rechazar van en línea solo en ese estado -
                // una vez decidido, desaparecen (ya no hay nada que decidir).
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(SolicitudContrato $record) => $record->estado === 'borrador')
                    ->requiresConfirmation()
                    ->modalDescription('Se generará el contrato final (protegido, sin marca de agua) y quedará aprobado.')
                    ->action(function (SolicitudContrato $record) {
                        $resultado = app(\App\Services\SolicitudContratoIAService::class)->generarContratoPDF($record, borrador: false);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Solicitud aprobada')
                            ->body(static::mensajeOrigenFaltasGraves($resultado['faltas_graves_origen']))
                            ->send();
                    }),

                // Vía general para cualquier cambio a un contrato ya
                // aprobado (salario, cargo, jornada, tipo de contrato o
                // plazo), directo desde la fila del listado - sin navegar a
                // ningún wizard de página completa. A pedido explícito del
                // usuario: "prefiero mil veces que aparezca una acción en el
                // listado del historial de contratos que este wizard".
                Tables\Actions\Action::make('solicitarCambio')
                    ->label('Solicitar un Cambio')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (SolicitudContrato $record) => $record->estado === 'aprobado')
                    ->modalWidth('lg')
                    // El stepper nativo de Filament (pestañas "El Cambio" /
                    // "Revisar y Confirmar" arriba) quedaba duplicado con el
                    // step-header de marca de cada paso - reportado por el
                    // usuario con captura. Misma clase CSS ya usada para
                    // ocultarlo en los wizards de página completa
                    // (PanelBrandingServiceProvider).
                    ->extraModalWindowAttributes(['class' => 'ces-hide-wizard-steps'])
                    ->steps(fn (SolicitudContrato $record) => ModificacionContractualResource::pasosSolicitarCambio($record))
                    ->modalSubmitActionLabel('Confirmar y Generar Otrosí')
                    ->action(function (SolicitudContrato $record, array $data) {
                        ModificacionContractualResource::crearYGenerarOtrosi($record, $data);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Otrosí generado')
                            ->body('El documento quedó registrado en el historial de cambios del contrato.')
                            ->send();
                    }),

                // Caso especial de "Solicitar un Cambio" (tipo=plazo),
                // directo en la ventana de 45 días de alerta - mismo pedido
                // del usuario de tener las acciones en la fila, sin entrar a
                // Ver Contrato primero.
                Tables\Actions\Action::make('renovarContrato')
                    ->label('Sí, renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (SolicitudContrato $record) => static::enVentanaDeDecisionRenovacion($record))
                    ->url(fn (SolicitudContrato $record) => ModificacionContractualResource::getUrl('create', [
                        'solicitud_contrato_id' => $record->id,
                        'tipo_modificacion' => 'plazo',
                    ])),

                Tables\Actions\Action::make('noRenovarContrato')
                    ->label('No renovar')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->visible(fn (SolicitudContrato $record) => static::enVentanaDeDecisionRenovacion($record))
                    ->requiresConfirmation()
                    ->modalHeading('Generar Preaviso de no renovación')
                    ->modalDescription('Se generará el documento de preaviso y quedará registrado que decidió no renovar este contrato. Esta decisión se puede revertir manualmente si cambia de opinión antes del vencimiento.')
                    ->modalSubmitActionLabel('Sí, generar Preaviso')
                    ->action(function (SolicitudContrato $record) {
                        app(\App\Services\SolicitudContratoIAService::class)->generarPreavisoPDF($record);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Preaviso generado')
                            ->body('Descárguelo y entréguelo al trabajador con al menos 30 días de anticipación al vencimiento.')
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(SolicitudContrato $record) => $record->estado === 'borrador')
                    ->modalHeading('Rechazar solicitud')
                    ->modalDescription('Esta acción no se puede deshacer desde la interfaz. Indique el motivo para dejar constancia de por qué se rechazó.')
                    ->modalSubmitActionLabel('Rechazar')
                    // Pedir el motivo AQUÍ (en vez de solo requiresConfirmation())
                    // - antes se rechazaba sin dejar ningún rastro de por qué,
                    // a pedido explícito del usuario para no perder esa
                    // trazabilidad en un sistema legal.
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo del rechazo')
                            ->required()
                            ->minLength(5)
                            ->rows(3)
                            ->placeholder('Ej: El cargo propuesto no está aprobado en el presupuesto de este trimestre.'),
                    ])
                    ->action(function (SolicitudContrato $record, array $data) {
                        $record->update([
                            'estado' => 'rechazado',
                            'motivo_rechazo' => $data['motivo'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Solicitud rechazada')
                            ->send();
                    }),

                // El resto: consultas/mantenimiento, no decisiones urgentes -
                // se agrupan en un menú desplegable (mismo patrón ya usado en
                // ProcesoDisciplinarioResource).
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver'),
                    // Hallazgo real (2026-09-02): sin este ->visible(), un
                    // contrato ya 'aprobado' se podía editar directo (salario,
                    // cargo, jornada...) sin dejar rastro, saltándose por
                    // completo el flujo formal de Otrosí
                    // (ModificacionContractualResource). Solo tiene sentido
                    // editar libremente mientras sigue en 'borrador'.
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->visible(fn (SolicitudContrato $record) => $record->estado === 'borrador'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar'),

                    Tables\Actions\Action::make('regenerarBorrador')
                        ->label('Regenerar Borrador')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn(SolicitudContrato $record) => $record->estado === 'borrador')
                        ->requiresConfirmation()
                        ->modalDescription('Se generará un nuevo borrador del contrato con los datos actuales.')
                        ->action(function (SolicitudContrato $record) {
                            $service = app(\App\Services\SolicitudContratoIAService::class);

                            if (empty($record->objeto_juridico_redactado)) {
                                $texto = $service->redactarObjetoJuridico($record);
                                $record->update(['objeto_juridico_redactado' => $texto]);
                            }

                            if (
                                $record->tipo_contrato === 'Contrato de Obra o Labor'
                                && empty($record->duracion_terminacion_obra_redactada)
                            ) {
                                $duracionTerminacion = $service->redactarDuracionTerminacionObraLabor($record);
                                $record->update(['duracion_terminacion_obra_redactada' => $duracionTerminacion]);
                            }

                            // generarContratoPDF() ya registra en el timeline
                            // cada vez que corre (creación, regenerar,
                            // aprobar) - no hace falta duplicar esa llamada
                            // aquí.
                            $resultado = $service->generarContratoPDF($record, borrador: true);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Borrador regenerado')
                                ->body(static::mensajeOrigenFaltasGraves($resultado['faltas_graves_origen']))
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('Aún no hay solicitudes de contrato')
            ->emptyStateDescription('Cree la primera solicitud y la IA generará el borrador del contrato automáticamente.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear Solicitud de Contrato'),
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

    /**
     * Mensaje para el body de la notificación tras generar/regenerar el
     * contrato, según el origen resuelto por
     * SolicitudContratoIAService::obtenerFaltasGravesRit() - pedido
     * explícito del usuario: no dejar al cliente sin saber si sus faltas
     * graves salieron de SU reglamento o de un listado genérico.
     */
    public static function mensajeOrigenFaltasGraves(string $origen): string
    {
        return match ($origen) {
            'rit' => 'Las faltas graves de este contrato incluyen las conductas específicas del Reglamento Interno de Trabajo de la empresa.',
            'sin_rit' => 'La empresa no tiene un Reglamento Interno de Trabajo registrado, así que se usó el listado general de faltas graves de la ley. Suba el RIT de la empresa para personalizar esta cláusula.',
            default => 'El Reglamento Interno de Trabajo de la empresa no tiene faltas graves identificadas, así que se usó el listado general de la ley.',
        };
    }

    /**
     * Solo tiene sentido ofrecer la decisión renovar/no-renovar mientras el
     * contrato está dentro de la ventana de alerta (45 días) y nadie ha
     * decidido todavía - ver PlazoContratoService. Compartido entre la
     * tabla de este Resource y ViewSolicitudContrato::enVentanaDeDecision(),
     * para no duplicar la condición en 2 lugares.
     */
    public static function enVentanaDeDecisionRenovacion(SolicitudContrato $record): bool
    {
        return $record->tipo_contrato === 'Contrato a Término Fijo'
            && app(\App\Services\PlazoContratoService::class)->estaEnVentanaDeAlerta($record);
    }

    /**
     * Tipos de contrato que sí tienen fecha de terminación / duración
     * determinada bajo el CST. Término Indefinido no la tiene por definición;
     * Obra o Labor se rige por la finalización de la obra/labor contratada
     * (descripcion_obra_labor + redacción de IA), no por una fecha calendario
     * fija - análisis empírico pedido por el usuario contra las 3 plantillas
     * reales y los 6 tipos de contrato del wizard (2026-09-01).
     */
    protected static function tiposConFechaTerminacion(): array
    {
        return [
            'Contrato a Término Fijo',
            'Contrato de Prestación de Servicios',
            'Contrato de Aprendizaje',
            'Contrato Ocasional o Transitorio',
        ];
    }

    /**
     * Calcula fecha_fin_contrato = fecha_inicio_propuesta + duración (años/
     * meses/días compuestos, ver los campos duracion_* del Fieldset "Duración
     * del Contrato"). Si falta la fecha de inicio o la cantidad principal,
     * devuelve la fecha_fin_contrato actual sin tocarla (el usuario pudo
     * haberla escrito directo, sin pasar por el calculador).
     */
    protected static function calcularFechaFinDesdeDuracion(Get $get): ?string
    {
        $inicio = $get('fecha_inicio_propuesta');
        $cantidad = $get('duracion_cantidad');

        // is_numeric() por sí solo no basta: el bloqueo de teclas en el
        // campo (ver extraInputAttributes de duracion_cantidad) es solo UX -
        // un valor negativo o "1e5" podría llegar igual pegado desde el
        // portapapeles. is_numeric("-5") es true, así que se exige además
        // que sea un entero positivo real - un valor inválido se trata igual
        // que "vacío" (no se toca fecha_fin_contrato) en vez de producir una
        // fecha hacia atrás en silencio.
        if (blank($inicio) || blank($cantidad) || !is_numeric($cantidad) || (int) $cantidad < 1) {
            return $get('fecha_fin_contrato');
        }

        $anios = 0;
        $meses = 0;
        $dias = 0;
        $unidad = $get('duracion_unidad') ?? 'dia';

        match ($unidad) {
            'anio' => $anios = (int) $cantidad,
            'mes' => $meses = (int) $cantidad,
            default => $dias = (int) $cantidad,
        };

        if ($unidad === 'anio') {
            $unidad2 = $get('duracion_unidad_2');
            $cantidad2 = max(0, (int) ($get('duracion_cantidad_2') ?? 0));

            if ($unidad2 === 'mes') {
                $meses = $cantidad2;
                $dias = max(0, (int) ($get('duracion_cantidad_3') ?? 0));
            } elseif ($unidad2 === 'dia') {
                $dias = $cantidad2;
            }
        } elseif ($unidad === 'mes') {
            $dias = max(0, (int) ($get('duracion_cantidad_2') ?? 0));
        }

        return \Carbon\Carbon::parse($inicio)
            ->addYears($anios)
            ->addMonths($meses)
            ->addDays($dias)
            ->toDateString();
    }

    /**
     * Camino inverso: al editar fecha_fin_contrato directamente, descompone
     * la diferencia contra fecha_inicio_propuesta en años/meses/días y
     * rellena los campos duracion_* del calculador - para que ambos lados
     * (duración y fecha) se mantengan sincronizados sin importar cuál editó
     * el usuario. Usa Carbon::diff() (DateInterval) como única fuente de la
     * descomposición, no una fórmula propia - evita divergencias de
     * redondeo entre esta función y calcularFechaFinDesdeDuracion().
     */
    protected static function descomponerDuracionDesdeFecha(Set $set, Get $get): void
    {
        $inicio = $get('fecha_inicio_propuesta');
        $fin = $get('fecha_fin_contrato');

        if (blank($inicio) || blank($fin)) {
            return;
        }

        $inicioC = \Carbon\Carbon::parse($inicio);
        $finC = \Carbon\Carbon::parse($fin);

        if ($finC->lessThan($inicioC)) {
            // Fecha inválida (fin antes que inicio) - se deja que la regla
            // afterOrEqual() del propio campo la marque en la validación, no
            // se intenta "corregir" la duración con un valor negativo.
            return;
        }

        $diff = $inicioC->diff($finC);

        if ($diff->y > 0) {
            $set('duracion_unidad', 'anio');
            $set('duracion_cantidad', $diff->y);

            if ($diff->m > 0) {
                $set('duracion_unidad_2', 'mes');
                $set('duracion_cantidad_2', $diff->m);
                $set('duracion_cantidad_3', $diff->d > 0 ? $diff->d : null);
            } elseif ($diff->d > 0) {
                $set('duracion_unidad_2', 'dia');
                $set('duracion_cantidad_2', $diff->d);
            } else {
                $set('duracion_unidad_2', null);
                $set('duracion_cantidad_2', null);
            }
        } elseif ($diff->m > 0) {
            $set('duracion_unidad', 'mes');
            $set('duracion_cantidad', $diff->m);
            $set('duracion_cantidad_2', $diff->d > 0 ? $diff->d : null);
        } else {
            $set('duracion_unidad', 'dia');
            $set('duracion_cantidad', $diff->d);
            $set('duracion_cantidad_2', null);
            $set('duracion_unidad_2', null);
        }
    }

    /**
     * Organigrama vigente de la empresa (ver ReglamentoInternoService::
     * cargosDeEmpresa()) - vacío si no hay empresa seleccionada, no tiene RIT,
     * o el RIT es de texto libre y aún no se generó el organigrama con IA
     * (botón "Generar organigrama" en Mi Reglamento Interno).
     */
    public static function getOrganigramaDeEmpresa(?int $empresaId): array
    {
        if (!$empresaId) {
            return [];
        }

        return app(\App\Services\ReglamentoInternoService::class)->cargosDeEmpresa($empresaId);
    }

    /**
     * Opciones del Select 'cargo_contrato': el organigrama del RIT de la
     * empresa (para que el ecosistema completo -RIT, sanciones, contratos-
     * hable del mismo listado de cargos) si existe, si no el catálogo fijo
     * genérico (getCargos()) como respaldo - nunca deja el campo sin
     * opciones. Siempre incluye "Otro" al final.
     */
    public static function getCargosParaSelect(?int $empresaId): array
    {
        $organigrama = self::getOrganigramaDeEmpresa($empresaId);

        $cargos = [];
        if (!empty($organigrama)) {
            foreach ($organigrama as $item) {
                $nombre = trim((string) ($item['nombre_cargo'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $tieneAutoridad = ($item['instancia_sancionatoria'] ?? 'ninguna') !== 'ninguna';
                $cargos[$nombre] = $tieneAutoridad ? "{$nombre} (con facultad disciplinaria)" : $nombre;
            }
        }

        if (empty($cargos)) {
            foreach (self::getCargos() as $cargo) {
                $cargos[$cargo] = $cargo;
            }
        }

        $cargos['__otro__'] = '--- Otro (personalizado) ---';

        return $cargos;
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

    public static function getDepartamentos(): array
    {
        return DB::table('departamentos')
            ->orderBy('nombre')
            ->pluck('nombre', 'nombre')
            ->toArray();
    }

    public static function getCiudadesPorDepartamento(?string $departamento): array
    {
        if (empty($departamento)) {
            return [];
        }

        $municipios = DB::table('municipios')
            ->join('departamentos', 'municipios.departamento_id', '=', 'departamentos.id')
            ->where('departamentos.nombre', $departamento)
            ->orderBy('municipios.nombre')
            ->pluck('municipios.nombre')
            ->toArray();

        if (empty($municipios)) {
            return [$departamento => $departamento];
        }

        return array_combine($municipios, $municipios);
    }
}
