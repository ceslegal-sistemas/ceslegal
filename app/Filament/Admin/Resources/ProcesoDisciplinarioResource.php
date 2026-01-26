<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;
use App\Models\DisponibilidadAbogado;
use App\Models\ProcesoDisciplinario;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use HusamTariq\FilamentTimePicker\Forms\Components\TimePickerField;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcesoDisciplinarioResource extends Resource
{
    protected static ?string $model = ProcesoDisciplinario::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Historial de Descargos';

    protected static ?string $modelLabel = 'DESCARGOS';

    protected static ?string $pluralModelLabel = 'Historial de Descargos';

    // protected static ?string $navigationGroup = 'Gestión Laboral';

    protected static ?int $navigationSort = 1;

    /**
     * Registrar los ítems de navegación personalizados
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Crear Descargos')
                ->icon('heroicon-o-plus-circle')
                ->url(static::getUrl('create'))
                // ->color('success')
                ->sort(0),
            // ->badge(fn() => 'Nuevo', 'success'),

            NavigationItem::make('Historial de Descargos')
                ->icon(static::getNavigationIcon())
                ->url(static::getUrl('index'))
                ->sort(1)
                ->isActiveWhen(fn() => request()->routeIs(static::getRouteBaseName() . '.*') && !request()->routeIs(static::getRouteBaseName() . '.create')),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente')
                            ->helperText('El código se asigna automáticamente al crear el proceso')
                            ->hidden(),

                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'razon_social')
                            ->searchable()
                            ->extraAttributes([
                                'data-tour' => 'empresa-select',
                            ])
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
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('trabajador_id', null);
                                $set('abogado_id', null);
                            }),

                        Forms\Components\Select::make('trabajador_id')
                            ->label('Trabajador')
                            ->options(
                                fn(Get $get): array =>
                                Trabajador::query()
                                    ->where('empresa_id', $get('empresa_id'))
                                    ->where('active', true)
                                    ->get()
                                    ->pluck('nombre_completo', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->required()
                            ->disabled(fn(Get $get) => !$get('empresa_id'))
                            ->helperText('Seleccione primero la empresa')
                            ->suffixIcon('heroicon-o-user-group')
                            ->extraAttributes([
                                'data-tour' => 'trabajador-select',
                            ])
                            ->createOptionForm(function (Get $get) {
                                return [
                                    Forms\Components\Hidden::make('empresa_id')
                                        ->default(fn() => $get('../../empresa_id')),

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
                                        ->live(),

                                    Forms\Components\TextInput::make('numero_documento')
                                        ->label('Número de Documento')
                                        ->required()
                                        ->numeric()
                                        ->maxLength(50)
                                        ->placeholder('Ej: 1234567890'),

                                    Forms\Components\Select::make('genero')
                                        ->label('Género')
                                        ->options([
                                            'masculino' => 'Masculino',
                                            'femenino' => 'Femenino',
                                        ])
                                        ->required()
                                        ->native(false),

                                    Forms\Components\TextInput::make('nombres')
                                        ->label('Nombres')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Juan Carlos'),

                                    Forms\Components\TextInput::make('apellidos')
                                        ->label('Apellidos')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Pérez García'),

                                    Forms\Components\Select::make('cargo_select')
                                        ->label('Cargo')
                                        ->searchable()
                                        ->options([
                                            'Gerente General' => 'Gerente General',
                                            'Gerente Administrativo' => 'Gerente Administrativo',
                                            'Coordinador' => 'Coordinador',
                                            'Supervisor' => 'Supervisor',
                                            'Jefe de Área' => 'Jefe de Área',
                                            'Asistente Administrativo' => 'Asistente Administrativo',
                                            'Auxiliar Administrativo' => 'Auxiliar Administrativo',
                                            'Secretaria' => 'Secretaria',
                                            'Recepcionista' => 'Recepcionista',
                                            'Contador' => 'Contador',
                                            'Auxiliar Contable' => 'Auxiliar Contable',
                                            'Conductor' => 'Conductor',
                                            'Mensajero' => 'Mensajero',
                                            'Operario' => 'Operario',
                                            'Técnico' => 'Técnico',
                                            'Vendedor' => 'Vendedor',
                                            '__otro__' => '--- Otro (personalizado) ---',
                                        ])
                                        ->live()
                                        ->required()
                                        ->placeholder('Seleccione el cargo...'),

                                    Forms\Components\TextInput::make('cargo_otro')
                                        ->label('Especifique el Cargo')
                                        ->visible(fn(Get $get) => $get('cargo_select') === '__otro__')
                                        ->required(fn(Get $get) => $get('cargo_select') === '__otro__')
                                        ->placeholder('Ej: Jefe de Proyectos Especiales'),

                                    Forms\Components\TextInput::make('email')
                                        ->label('Correo Electrónico')
                                        ->helperText('Este correo se usará para notificar al trabajador sobre los descargos y sanciones')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: juan.perez@empresa.com'),
                                ];
                            })
                            ->createOptionUsing(function (array $data, Get $get) {
                                // Procesar cargo
                                if (isset($data['cargo_select'])) {
                                    $data['cargo'] = $data['cargo_select'] === '__otro__'
                                        ? $data['cargo_otro']
                                        : $data['cargo_select'];
                                }

                                // Limpiar campos temporales
                                unset($data['cargo_select'], $data['cargo_otro']);

                                // Asignar empresa y estado activo
                                $empresaId = $get('empresa_id');
                                $data['empresa_id'] = $empresaId;
                                $data['active'] = true;

                                // Verificar si el trabajador ya existe EN LA MISMA EMPRESA
                                $trabajadorMismaEmpresa = Trabajador::where('tipo_documento', $data['tipo_documento'])
                                    ->where('numero_documento', $data['numero_documento'])
                                    ->where('empresa_id', $empresaId)
                                    ->first();

                                if ($trabajadorMismaEmpresa) {
                                    // Si existe en la misma empresa, actualizar su información
                                    $trabajadorMismaEmpresa->update([
                                        'nombres' => $data['nombres'],
                                        'apellidos' => $data['apellidos'],
                                        'email' => $data['email'],
                                        'cargo' => $data['cargo'],
                                        'genero' => $data['genero'],
                                        'active' => true,
                                    ]);

                                    \Filament\Notifications\Notification::make()
                                        ->info()
                                        ->title('Trabajador Actualizado')
                                        ->body("El trabajador {$trabajadorMismaEmpresa->nombre_completo} ya existía. Su información ha sido actualizada.")
                                        ->duration(5000)
                                        ->send();

                                    return $trabajadorMismaEmpresa->id;
                                }

                                // Verificar si existe en OTRA empresa (solo para informar)
                                $trabajadorOtraEmpresa = Trabajador::where('tipo_documento', $data['tipo_documento'])
                                    ->where('numero_documento', $data['numero_documento'])
                                    ->where('empresa_id', '!=', $empresaId)
                                    ->exists();

                                // Crear nuevo trabajador (sea primera vez o para otra empresa)
                                try {
                                    $trabajador = Trabajador::create($data);

                                    if ($trabajadorOtraEmpresa) {
                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Trabajador Creado')
                                            ->body("El trabajador {$trabajador->nombre_completo} fue registrado exitosamente.")
                                            ->duration(5000)
                                            ->send();
                                    } else {
                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Trabajador Creado')
                                            ->body("El trabajador {$trabajador->nombre_completo} fue creado exitosamente.")
                                            ->duration(3000)
                                            ->send();
                                    }

                                    return $trabajador->id;
                                } catch (\Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Error al Crear Trabajador')
                                        ->body('Ocurrió un error al intentar crear el trabajador. Por favor, intente nuevamente.')
                                        ->duration(5000)
                                        ->send();

                                    throw $e;
                                }
                            })
                            ->createOptionAction(
                                fn(\Filament\Forms\Components\Actions\Action $action) => $action
                                    ->extraAttributes([
                                        'data-tour' => 'trabajador-create',
                                    ])
                            )
                            ->createOptionModalHeading('Crear Nuevo Trabajador'),

                        Forms\Components\Select::make('modalidad_descargos')
                            ->label('¿Cómo se realizará la diligencia de descargos?')
                            ->options([
                                // 'presencial' => 'Presencial - El trabajador viene a la oficina',
                                'virtual' => 'Virtual - El trabajador responde por internet desde su casa',
                                // 'telefonico' => 'Telefónico - Se hará por llamada telefónica',
                            ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('fecha_temp_descargos', null);
                                $set('hora_temp_descargos', null);
                                $set('fecha_descargos_programada', null);
                                $set('abogado_id', null);
                            })
                            ->extraAttributes([
                                'data-tour' => 'modalidad-select',
                            ])
                            ->helperText('Seleccione cómo se realizará la diligencia'),

                        Forms\Components\Select::make('abogado_id')
                            ->label('Abogado Asignado')
                            ->relationship('abogado', 'name', fn($query) => $query->role('abogado'))
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => in_array($get('modalidad_descargos'), ['presencial', 'telefonico']))
                            ->visible(fn(Get $get) => in_array($get('modalidad_descargos'), ['presencial', 'telefonico']))
                            ->helperText('Seleccione el abogado que llevará el proceso')
                            ->suffixIcon('heroicon-o-user')
                            ->extraAttributes([
                                'data-tour' => 'abogado-select',
                            ])
                            ->live(),
                    ])->columns(2),

                Forms\Components\Section::make('Programación de Descargos')
                    ->schema([
                        // PARA PRESENCIAL Y TELEFÓNICO: Selector de fecha + hora dinámica
                        Forms\Components\DatePicker::make('fecha_temp_descargos')
                            ->label('Seleccione la Fecha')
                            ->required()
                            ->minDate(now()->addDays(5)->startOfDay())
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Set $set) {
                                $set('hora_temp_descargos', null);
                                $set('fecha_descargos_programada', null);
                            })
                            ->visible(fn(Get $get) => in_array($get('modalidad_descargos'), ['presencial', 'telefonico']))
                            ->disabled(fn(Get $get) => !$get('abogado_id'))
                            ->helperText(function (Get $get) {
                                if (!$get('abogado_id')) {
                                    return 'Primero selecciona un abogado para ver su disponibilidad.';
                                }
                                return 'Selecciona el día para ver las horas disponibles.';
                            }),

                        // Mensaje cuando es fin de semana o festivo
                        Forms\Components\Placeholder::make('mensaje_fecha_no_laboral')
                            ->label('')
                            ->content(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                if (!$fecha) return '';

                                $fechaCarbon = \Carbon\Carbon::parse($fecha);

                                // Verificar fin de semana
                                if ($fechaCarbon->isWeekend()) {
                                    return '⚠️ Los fines de semana no hay atención. Selecciona un día hábil (lunes a viernes).';
                                }

                                // Verificar festivos de Colombia
                                $festivos = self::getFestivos($fechaCarbon->year);
                                if (in_array($fechaCarbon->format('Y-m-d'), $festivos)) {
                                    return '⚠️ Este día es festivo. Selecciona un día hábil.';
                                }

                                return '';
                            })
                            ->visible(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                if (!$fecha) return false;

                                $fechaCarbon = \Carbon\Carbon::parse($fecha);

                                // Mostrar si es fin de semana
                                if ($fechaCarbon->isWeekend()) return true;

                                // Mostrar si es festivo
                                $festivos = self::getFestivos($fechaCarbon->year);
                                return in_array($fechaCarbon->format('Y-m-d'), $festivos);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('hora_temp_descargos')
                            ->label('Seleccione la Hora Disponible')
                            ->options(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                $modalidad = $get('modalidad_descargos');
                                $abogadoId = $get('abogado_id');

                                if (!$fecha || !$abogadoId || !in_array($modalidad, ['presencial', 'telefonico'])) {
                                    return [];
                                }

                                // Verificar si es día no laboral
                                $fechaCarbon = \Carbon\Carbon::parse($fecha);
                                if ($fechaCarbon->isWeekend()) {
                                    return [];
                                }

                                $festivos = self::getFestivos($fechaCarbon->year);
                                if (in_array($fechaCarbon->format('Y-m-d'), $festivos)) {
                                    return [];
                                }

                                $slots = \App\Services\DisponibilidadHelper::obtenerSlotsDisponibles(
                                    $abogadoId,
                                    $fecha,
                                    $modalidad
                                );

                                return \App\Services\DisponibilidadHelper::formatearSlotsParaSelector($slots);
                            })
                            ->required()
                            ->live()
                            ->visible(function (Get $get) {
                                if (!in_array($get('modalidad_descargos'), ['presencial', 'telefonico'])) return false;
                                if (!$get('fecha_temp_descargos')) return false;
                                if (!$get('abogado_id')) return false;

                                // No mostrar si es día no laboral
                                $fechaCarbon = \Carbon\Carbon::parse($get('fecha_temp_descargos'));
                                if ($fechaCarbon->isWeekend()) return false;

                                $festivos = self::getFestivos($fechaCarbon->year);
                                if (in_array($fechaCarbon->format('Y-m-d'), $festivos)) return false;

                                return true;
                            })
                            ->helperText('Horario de oficina: 8:00 AM - 5:00 PM. Cada cita dura 45 minutos.')
                            ->descriptions(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                $modalidad = $get('modalidad_descargos');
                                $abogadoId = $get('abogado_id');

                                if (!$fecha || !$abogadoId || !in_array($modalidad, ['presencial', 'telefonico'])) {
                                    return [];
                                }

                                $slots = \App\Services\DisponibilidadHelper::obtenerSlotsDisponibles(
                                    $abogadoId,
                                    $fecha,
                                    $modalidad
                                );

                                // Generar descripciones para cada slot
                                $descriptions = [];
                                foreach ($slots as $slot) {
                                    $key = $slot['datetime_inicio'];
                                    $descriptions[$key] = '45 minutos';
                                }
                                return $descriptions;
                            })
                            ->columns(3)
                            ->inline()
                            ->columnSpanFull(),

                        // PARA VIRTUAL: Selector manual de fecha y hora
                        Forms\Components\DatePicker::make('fecha_descargos_programada')
                            ->label('Fecha Programada de Descargos')
                            ->required()
                            ->minDate(now()->addDays(5)->startOfDay())
                            ->native(false)
                            ->live()
                            ->visible(fn(Get $get) => $get('modalidad_descargos') === 'virtual')
                            ->helperText('Seleccione la fecha para la audiencia virtual'),


                        TimePickerField::make('hora_descargos_programada')
                            ->okLabel("Confirmar")
                            ->cancelLabel("Cancelar")
                            ->label('Hora Programada de Descargos')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $fecha = $get('fecha_descargos_programada');
                                $hora = $state;

                                if ($fecha && $hora) {
                                    // Combinar fecha y hora en un datetime para fecha_descargos_programada
                                    $datetime = \Carbon\Carbon::parse($fecha)->setTimeFromTimeString($hora);
                                    $set('fecha_descargos_programada', $datetime->format('Y-m-d H:i:s'));
                                }
                            })
                            ->visible(fn(Get $get) => $get('modalidad_descargos') === 'virtual')
                            ->helperText('Seleccione la hora para la audiencia virtual'),

                        // Forms\Components\Select::make('estado')
                        //     ->label('Estado')
                        //     ->options([
                        //         'apertura' => 'Apertura',
                        //         'descargos_pendientes' => 'Descargos Pendientes',
                        //         'descargos_realizados' => 'Descargos Realizados',
                        //         'sancion_emitida' => 'Sanción Emitida',
                        //         'impugnacion_realizada' => 'Impugnación Realizada',
                        //         'cerrado' => 'Cerrado',
                        //     ])
                        //     ->default('apertura')
                        //     ->required()
                        //     ->live(),

                        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                        // Forms\Components\Select::make('articulos_legales_ids')
                        //     ->label('Artículos Legales Incumplidos')
                        //     ->multiple()
                        //     ->searchable()
                        //     ->preload()
                        //     ->options(function () {
                        //         return \App\Models\ArticuloLegal::activos()
                        //             ->ordenado()
                        //             ->get()
                        //             ->mapWithKeys(fn($articulo) => [
                        //                 $articulo->id => $articulo->texto_completo
                        //             ]);
                        //     })
                        //     ->placeholder('Seleccione uno o más artículos...')
                        //     ->helperText('Seleccione los artículos del Código Sustantivo del Trabajo que presuntamente incumplió el trabajador')
                        //     ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'abogado']))
                        //     ->columnSpanFull(),

                        // Motivo de los descargos - Sanciones Laborales
                        Forms\Components\Select::make('sanciones_laborales_ids')
                            ->label('Motivo de los descargos')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\SancionLaboral::activas()
                                    ->ordenado()
                                    ->get()
                                    ->mapWithKeys(fn($sancion) => [
                                        $sancion->id => $sancion->nombre_con_descripcion
                                    ]);
                            })
                            ->placeholder('Seleccione una o más motivos...')
                            ->helperText('Seleccione los motivos de los descargos a citar al trabajador')
                            ->extraAttributes([
                                'data-tour' => 'motivos-select',
                            ])
                            // ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'abogado']))
                            ->columnSpanFull(),

                    ])->columns(2),

                Forms\Components\Section::make('Detalles del Proceso')
                    ->schema([
                        // Forms\Components\DateTimePicker::make('fecha_solicitud')
                        //     ->label('Fecha de Solicitud')
                        //     ->default(now())
                        //     ->required()
                        //     ->displayFormat('d/m/Y')
                        //     ->disabled()
                        //     ->dehydrated(false)
                        //     ->native(false),

                        Forms\Components\DatePicker::make('fecha_ocurrencia')
                            ->label('Fecha de Ocurrencia de los Hechos')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required()
                            ->minDate(now()->subMonths(2)->startOfDay())
                            ->maxDate(now()->endOfDay())
                            ->extraAttributes([
                                'data-tour' => 'fecha-ocurrencia',
                            ])
                            ->helperText('Fecha en que ocurrieron los hechos que motivan el proceso'),

                        Forms\Components\RichEditor::make('hechos')
                            ->label('Motivo de la citacion a diligencia de descargos')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'italic',
                                'orderedList',
                                'redo',
                                'undo',
                            ])
                            ->extraAttributes([
                                'data-tour' => 'hechos-editor',
                            ])
                            ->helperText('Describa detalladamente los hechos que motivan el proceso disciplinario')
                            // ->hintIcon('heroicon-o-sparkles')
                            ->hintColor('primary')
                            // ->hint('Generar con IA')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('generarMotivo')
                                    ->icon('heroicon-o-sparkles')
                                    ->label('Generar redacción con IA')
                                    ->extraAttributes([
                                        'data-tour' => 'ia-button',
                                    ])
                                    ->requiresConfirmation()
                                    ->modalHeading('Generar Motivo con IA')
                                    ->modalDescription('La IA generará una redacción profesional del motivo basándose en la información ingresada. Puede editarla después.')
                                    ->modalSubmitActionLabel('Generar')
                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                        try {
                                            $trabajadorId = $get('trabajador_id');
                                            $empresaId = $get('empresa_id');
                                            $fechaOcurrencia = $get('fecha_ocurrencia');
                                            $fechaProgramada = $get('fecha_descargos_programada');
                                            $horaProgramada = $get('hora_descargos_programada');
                                            $modalidadDescargos = $get('modalidad_descargos');
                                            $hechos = $get('hechos');

                                            if (!$trabajadorId || !$empresaId) {
                                                \Filament\Notifications\Notification::make()
                                                    ->warning()
                                                    ->title('Datos incompletos')
                                                    ->body('Por favor, seleccione primero el trabajador.')
                                                    ->send();
                                                return;
                                            }

                                            if (!$hechos) {
                                                \Filament\Notifications\Notification::make()
                                                    ->warning()
                                                    ->title('Datos incompletos')
                                                    ->body('Por favor, describe un poco acerca de los hechos que motivan el proceso disciplinario.')
                                                    ->send();
                                                return;
                                            }

                                            $trabajador = \App\Models\Trabajador::find($trabajadorId);
                                            $empresa = \App\Models\Empresa::find($empresaId);

                                            // Obtener las sanciones laborales seleccionadas
                                            $sancionesLaboralesIds = $get('sanciones_laborales_ids') ?? [];
                                            $sancionesLaborales = 'No especificado';

                                            if (!empty($sancionesLaboralesIds)) {
                                                $sanciones = \App\Models\SancionLaboral::whereIn('id', $sancionesLaboralesIds)
                                                    ->ordenado()
                                                    ->get();

                                                if ($sanciones->isNotEmpty()) {
                                                    $textoCompleto = [];
                                                    foreach ($sanciones as $sancion) {
                                                        $emoji = $sancion->tipo_falta === 'leve' ? '🟢' : '🔴';
                                                        $textoSancion = "{$emoji} {$sancion->nombre_claro}";
                                                        if (!empty($sancion->descripcion)) {
                                                            $textoSancion .= "\n" . $sancion->descripcion;
                                                        }
                                                        $textoCompleto[] = $textoSancion;
                                                    }
                                                    $sancionesLaborales = implode("\n\n", $textoCompleto);
                                                }
                                            }

                                            // Obtener configuración del proveedor de IA
                                            $provider = config('services.ia.provider', 'openai');
                                            $config = config("services.ia.{$provider}", []);

                                            $apiKey = $config['api_key'];
                                            $model = $config['model'];

                                            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                                            $prompt = "Eres asistente de Recursos Humanos en Colombia especializado en procesos disciplinarios laborales.\n\n" .
                                                "CONTEXTO DEL CASO:\n" .
                                                "- Empresa: {$empresa->razon_social}\n" .
                                                "- Representante Legal: {$empresa->representante_legal}\n" .
                                                "- Trabajador: {$trabajador->nombre_completo}\n" .
                                                "- Cargo: {$trabajador->cargo}\n" .
                                                "- Sanción Laboral: {$sancionesLaborales}\n" .
                                                "- Hechos que motivan el proceso: {$hechos}\n" .
                                                ($fechaOcurrencia ? "- Fecha de los hechos: {$fechaOcurrencia}\n" : "") .
                                                ($fechaProgramada ? "- Fecha de audiencia de descargos: {$fechaProgramada}\n" : "") .
                                                ($horaProgramada ? "- Hora de audiencia: {$horaProgramada}\n" : "") .
                                                ($modalidadDescargos ? "- Modalidad: {$modalidadDescargos}\n" : "") .
                                                "\nTU TAREA:\n" .
                                                "Genera ÚNICAMENTE una descripción detallada de los hechos que motivan el proceso disciplinario en base a la informacion suministrada.\n\n" .
                                                "REQUISITOS IMPORTANTES:\n" .
                                                "1. NO escribas formato de correo, saludos ni despedidas\n" .
                                                "2. Redacta SOLO la narrativa de los hechos en 2-4 párrafos\n" .
                                                "3. Usa lenguaje profesional de RR.HH., claro y objetivo\n" .
                                                "4. Escribe dirigido al trabajador o en tercera persona\n" .
                                                "5. Menciona que estos hechos podrían constituir incumplimiento laboral\n" .
                                                "6. Formato: HTML simple usando solo etiquetas <p> para separar párrafos\n\n" .
                                                "EJEMPLO de lo que DEBES generar:\n" .
                                                "<p>El día [fecha específica], el trabajador {$trabajador->nombre_completo}, quien se desempeña como {$trabajador->cargo}, " .
                                                "{$sancionesLaborales}" . "{$hechos}. Esta situación generó [detallar la consecuencia o impacto en la operación].</p>\n\n" .
                                                "<p>Los hechos descritos podrían constituir un incumplimiento de las obligaciones laborales establecidas en el " .
                                                "Reglamento Interno de Trabajo y el contrato laboral vigente.</p>";

                                            $response = Http::withHeaders([
                                                'Content-Type' => 'application/json',
                                            ])->timeout(30)->post($url, [
                                                'contents' => [
                                                    [
                                                        'parts' => [
                                                            [
                                                                'text' => "Eres asistente para personal de Recursos Humanos y empleadores en Colombia. Ayuda a redactar de manera profesional y clara el motivo de citación a audiencia de descargos.\n\n" . $prompt
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                                'generationConfig' => [
                                                    'temperature' => 0.7,
                                                    'maxOutputTokens' => $config['max_tokens'],
                                                    'topP' => 0.95,
                                                ],
                                            ]);

                                            if (!$response->successful()) {
                                                throw new \Exception("Error en API Gemini: " . $response->body());
                                            }

                                            $responseData = $response->json();

                                            // Verificar si hay contenido en la respuesta
                                            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                                                throw new \Exception("Respuesta de Gemini sin contenido válido");
                                            }

                                            // Verificar si la respuesta fue truncada por límite de tokens
                                            $finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
                                            if ($finishReason === 'MAX_TOKENS') {
                                                Log::warning('Respuesta de Gemini truncada por límite de tokens', [
                                                    'finish_reason' => $finishReason,
                                                    'max_tokens' => $config['max_tokens'],
                                                    'respuesta_parcial' => substr($responseData['candidates'][0]['content']['parts'][0]['text'], 0, 200),
                                                ]);
                                            }


                                            // Extraer el texto de la respuesta de Gemini
                                            $motivoGenerado = $responseData['candidates'][0]['content']['parts'][0]['text'];

                                            // Establecer el valor generado en el campo
                                            $set('hechos', $motivoGenerado);

                                            \Filament\Notifications\Notification::make()
                                                ->success()
                                                ->title('Descripción de hechos generada')
                                                ->body('La IA ha generado una sugerencia. Revise y complete los detalles según el caso específico.')
                                                ->duration(8000)
                                                ->send();
                                        } catch (\Exception $e) {
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('Error al generar motivo')
                                                ->body('No se pudo generar el motivo con IA: ' . $e->getMessage())
                                                ->persistent()
                                                ->send();

                                            \Illuminate\Support\Facades\Log::error('Error al generar motivo con IA', [
                                                'error' => $e->getMessage(),
                                            ]);
                                        }
                                    })
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Decisión y Sanción')
                    ->schema([
                        // Forms\Components\Toggle::make('decision_sancion')
                        //     ->label('¿Procede Sanción?')
                        //     ->live(),

                        // Forms\Components\Toggle::make('impugnado')
                        //     ->label('¿Procede Impugnación?')
                        //     ->live(),

                        // Forms\Components\Select::make('tipo_sancion')
                        //     ->label('Tipo de Sanción')
                        //     ->options([
                        //         'llamado_atencion' => 'Llamado de Atención',
                        //         'suspension' => 'Suspensión',
                        //         'terminacion' => 'Terminación de Contrato',
                        //     ])
                        //     ->visible(fn(Get $get) => $get('decision_sancion') === true),

                        // Forms\Components\Textarea::make('motivo_archivo')
                        //     ->label('Motivo de Archivo')
                        //     ->rows(3)
                        //     ->visible(fn(Get $get) => $get('decision_sancion') === false)
                        //     ->columnSpanFull(),

                        // Forms\Components\DateTimePicker::make('fecha_notificacion')
                        //     ->label('Fecha de Notificación')
                        //     ->displayFormat('d/m/Y H:i')
                        //     ->native(false),

                        Forms\Components\DateTimePicker::make('fecha_limite_impugnacion')
                            ->label('Fecha Límite para Impugnación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->timezone('America/Bogota')
                            ->minDate(now()->addDays(3)->startOfDay())
                            ->helperText('3 días hábiles desde la notificación')
                            ->visible(fn(Get $get) => $get('impugnado') === true),

                        // Forms\Components\DateTimePicker::make('fecha_impugnacion')
                        //     ->label('Fecha de Impugnación')
                        //     ->displayFormat('d/m/Y H:i')
                        //     ->native(false)
                        //     ->visible(fn(Get $get) => $get('impugnado') === true),<

                        Forms\Components\DateTimePicker::make('fecha_cierre')
                            ->label('Fecha de Cierre')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->timezone('America/Bogota')
                            ->visible(fn(Get $get) => in_array($get('estado'), ['cerrado', 'archivado'])),
                    ])->columns(2)
                    ->visible(fn() => Auth::user()->hasRole(['super_admin']))
                    ->disabled(fn() => !Auth::user()->hasRole(['super_admin'])),
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
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn() => Auth::user()->hasRole(['super_admin', 'abogado'])),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(['nombres', 'apellidos'])
                    ->sortable()
                    ->description(
                        fn(ProcesoDisciplinario $record): string =>
                        $record->trabajador->cargo ?? ''
                    ),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'apertura' => 'gray',
                        'descargos_pendientes' => 'warning',
                        'descargos_realizados' => 'info',
                        'descargos_no_realizados' => 'danger',
                        'impugnacion_realizada' => 'danger',
                        'sancion_emitida' => 'primary',
                        'cerrado' => 'success',
                        'archivado' => 'secondary',
                    })
                    // ->colors([
                    //     'gray' => 'apertura',
                    //     'warning' => ['descargos_pendientes'],
                    //     'info' => ['descargos_realizados'],
                    //     'danger' => ['descargos_no_realizados', 'impugnacion_realizada'],
                    //     'primary' => ['sancion_emitida'],
                    //     'success' => 'cerrado',
                    //     'secondary' => 'archivado',
                    // ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'apertura' => 'Apertura',
                        'descargos_pendientes' => 'Descargo Pendiente',
                        'descargos_realizados' => 'Descargo Realizado',
                        'descargos_no_realizados' => 'Descargo No Realizado',
                        'sancion_emitida' => 'Sanción Emitida',
                        'cerrado' => 'Cerrado',
                        'impugnacion_realizada' => 'Impugnación Realizada',
                        'archivado' => 'Archivado',
                        default => $state,
                    }),

                // Tables\Columns\TextColumn::make('tipo_sancion')
                //     ->label('Tipo Sanción')
                //     ->formatStateUsing(fn(?string $state): string => match ($state) {
                //         'llamado_atencion' => 'Llamado de Atención',
                //         'suspension' => 'Suspensión',
                //         'terminacion' => 'Terminación',
                //         default => 'N/A',
                //     })
                //     ->toggleable(),

                Tables\Columns\IconColumn::make('impugnado')
                    ->label('Impugnado')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fecha_descargos_programada')
                    ->label('Descargos Programados')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                // Columna de acuse de recibido (email tracking)
                // Estados: Pendiente (0) -> Entregado (1) -> Leído (2+)
                Tables\Columns\TextColumn::make('email_tracking_citacion')
                    ->label('Acuse Citación')
                    ->getStateUsing(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'citacion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'No enviado';
                        }

                        return $tracking->getEstadoLectura();
                    })
                    ->badge()
                    ->color(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'citacion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'gray';
                        }

                        return $tracking->getColorEstado();
                    })
                    ->tooltip(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'citacion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'No se ha enviado la citación por correo';
                        }

                        $info = "Enviado: " . $tracking->enviado_en->format('d/m/Y H:i');

                        if ($tracking->fueEntregado()) {
                            $info .= "\nCorreo Entregado: Sí";
                        }

                        if ($tracking->fueAbierto()) {
                            $info .= "\nLeído: " . $tracking->abierto_en->format('d/m/Y H:i');
                            $info .= "\nVeces leído: " . ($tracking->veces_abierto - 1);
                            if ($tracking->ip_apertura) {
                                $info .= "\nIP: " . $tracking->ip_apertura;
                            }
                        }

                        return $info;
                    })
                    ->toggleable(),

                // Columna de acuse de sanción
                Tables\Columns\TextColumn::make('email_tracking_sancion')
                    ->label('Acuse Sanción')
                    ->getStateUsing(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'sancion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'No enviado';
                        }

                        return $tracking->getEstadoLectura();
                    })
                    ->badge()
                    ->color(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'sancion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'gray';
                        }

                        return $tracking->getColorEstado();
                    })
                    ->tooltip(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_correo', 'sancion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'No se ha enviado la sanción por correo';
                        }

                        $info = "Enviado: " . $tracking->enviado_en->format('d/m/Y H:i');

                        if ($tracking->fueEntregado()) {
                            $info .= "\nCorreo Entregado: Sí";
                        }

                        if ($tracking->fueAbierto()) {
                            $info .= "\nLeído: " . $tracking->abierto_en->format('d/m/Y H:i');
                            $info .= "\nVeces leído: " . ($tracking->veces_abierto - 1);
                            if ($tracking->ip_apertura) {
                                $info .= "\nIP: " . $tracking->ip_apertura;
                            }
                        }

                        return $info;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Tables\Columns\TextColumn::make('modalidad_descargos')
                //     ->label('Modalidad Descargos')
                //     ->sortable()
                //     ->searchable()
                //     ->badge()
                //     ->color(fn(string $state): string => match ($state) {
                //         'presencial' => 'primary',
                //         'telefonico' => 'success',
                //         'virtual' => 'gray',
                //     })
                //     ->formatStateUsing(fn(string $state): string => match ($state) {
                //         'presencial' => 'Presencial',
                //         'telefonico' => 'Telefónico',
                //         'virtual' => 'Virtual',
                //         default => $state,
                //     }),

                Tables\Columns\TextColumn::make('fecha_solicitud')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'apertura' => 'Apertura',
                        'descargos_pendientes' => 'Descargo Pendiente',
                        'descargos_realizados' => 'Descargo Realizado',
                        'descargos_no_realizados' => 'Descargo No Realizado',
                        'sancion_emitida' => 'Sanción Emitida',
                        'cerrado' => 'Cerrado',
                        'impugnacion_realizada' => 'Impugnación Realizada',
                        'archivado' => 'Archivado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('modalidad_descargos')
                    ->label('Modalidad de Descargos')
                    ->options([
                        'presencial' => 'Presencial',
                        'telefonico' => 'Telefónico',
                        'virtual' => 'Virtual',
                    ]),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->visible(Auth::user()->hasRole('super_admin', 'abogado'))
                    ->preload(),

                Tables\Filters\Filter::make('impugnado')
                    ->label('Impugnados')
                    ->query(fn(Builder $query): Builder => $query->where('impugnado', true)),

                // Tables\Filters\TrashedFilter::make()
                //     ->label('Eliminados'),
            ])
            ->actions([
                // Botón 1: Generar Documento (solo para revisar)
                Tables\Actions\Action::make('generar_documento')
                    ->label('Generar Documento')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('¿Generar documento de citación?')
                    ->modalDescription('Se generará el documento de citación para que pueda revisarlo. No se enviará por correo.')
                    ->modalSubmitActionLabel('Generar Documento')
                    ->modalCancelActionLabel('Cancelar')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        !empty($record->fecha_descargos_programada) && $record->estado === 'apertura'
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $service = new \App\Services\DocumentGeneratorService();

                        try {
                            $pdfPath = $service->generarCitacionDescargos($record);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Documento generado!')
                                ->body('El documento de citación se generó exitosamente. Puede revisarlo en: ' . basename($pdfPath))
                                ->duration(8000)
                                ->send();

                            // Opcional: descargar el archivo
                            return response()->download($pdfPath);
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al generar documento')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Botón 2: Enviar Citación (generar y enviar)
                Tables\Actions\Action::make('enviar_citacion')
                    ->label(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_pendientes' ? 'Re-enviar Citación' : 'Enviar Sanción'
                    )
                    // ->label('Enviar Citación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()

                    ->modalHeading(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_pendientes' ? 'Re-enviar Citación' : 'Enviar Citación'
                    )
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) => ($record->estado === 'descargos_pendientes'
                            ? "NOTA: Este proceso ya tiene una citación enviada. Se generará y se enviará un nuevo documento reemplazando el anterior.\n\n"
                            : ""
                        ) .
                            "Se generará la citación a descargos y se enviará por correo electrónico a: " .
                            ($record->trabajador->email ?? '')
                    )
                    ->modalSubmitActionLabel('Generar y Enviar Citación')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('md')



                    // ->modalHeading('¿Generar y enviar citación?')
                    // ->modalDescription(
                    //     fn(ProcesoDisciplinario $record) =>
                    //     "Se generará la citación a descargos y se enviará por correo electrónico a: " .
                    //         ($record->trabajador->email ?? 'No tiene email registrado')
                    // )
                    // ->modalSubmitActionLabel('Sí, Generar y Enviar')
                    // ->modalCancelActionLabel('Cancelar')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        !empty($record->trabajador->email) && !empty($record->fecha_descargos_programada)
                            && $record->estado === 'descargos_pendientes'
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $service = new \App\Services\DocumentGeneratorService();
                        $result = $service->generarYEnviarCitacion($record);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Citación enviada!')
                                ->body('La citación se generó y envió exitosamente al correo del trabajador.')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al enviar citación')
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                // Botón 3: Emitir Sanción (generar con IA y enviar)
                Tables\Actions\Action::make('emitir_sancion')
                    ->label(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'sancion_emitida' ? 'Re-generar Sanción' : 'Emitir Sanción'
                    )
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->form(function (ProcesoDisciplinario $record) {
                        // Analizar proceso con IA para obtener sanciones apropiadas
                        $iaService = new \App\Services\IAAnalisisSancionService();
                        $resultado = $iaService->analizarYSugerirSanciones($record);
                        $analisis = $resultado['analisis'];

                        // Construir opciones de sanción basadas en el análisis
                        $opcionesSancion = [];
                        foreach ($analisis['sanciones_disponibles'] as $sancion) {
                            $opcionesSancion[$sancion] = match ($sancion) {
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension' => 'Suspensión Laboral',
                                'terminacion' => 'Terminación de Contrato',
                                default => ucfirst($sancion),
                            };
                        }

                        // Construir opciones de días de suspensión si aplica
                        $opcionesDiasSuspension = [];
                        if (isset($analisis['dias_suspension_sugeridos'])) {
                            foreach ($analisis['dias_suspension_sugeridos'] as $dias) {
                                $opcionesDiasSuspension[$dias] = "{$dias} día" . ($dias > 1 ? 's' : '');
                            }
                        }

                        return [
                            // Sección informativa con el análisis de la IA
                            Forms\Components\Section::make('Análisis del Caso')
                                ->schema([
                                    Forms\Components\Placeholder::make('gravedad_info')
                                        ->label('Gravedad de la Falta')
                                        ->content(function () use ($analisis) {
                                            $nivel = $analisis['nivel_gravedad'] ?? 'ninguno';

                                            if ($analisis['gravedad'] === 'leve') {
                                                $gravedad = '🟢 Leve';
                                            } elseif ($analisis['gravedad'] === 'grave') {
                                                if ($nivel === 'bajo') {
                                                    $gravedad = '🟡 Grave (Nivel Bajo)';
                                                } elseif ($nivel === 'alto') {
                                                    $gravedad = '🔴 Grave (Nivel Alto)';
                                                } else {
                                                    $gravedad = '🟡 Grave';
                                                }
                                            } else {
                                                $gravedad = ucfirst($analisis['gravedad']);
                                            }

                                            $reincidencia = $analisis['es_reincidencia'] ? ' - REINCIDENCIA' : '';
                                            return $gravedad . $reincidencia;
                                        }),

                                    // Forms\Components\Placeholder::make('justificacion')
                                    //     ->label('Justificación')
                                    //     ->content($analisis['justificacion'] ?? 'N/A'),

                                    Forms\Components\Placeholder::make('sancion_recomendada')
                                        ->label('Sanción Recomendada por la IA')
                                        ->content(function () use ($analisis) {
                                            return match ($analisis['sancion_recomendada']) {
                                                'llamado_atencion' => '📄 Llamado de Atención',
                                                'suspension' => '⏸️ Suspensión Laboral',
                                                'terminacion' => '❌ Terminación de Contrato',
                                                default => $analisis['sancion_recomendada'],
                                            };
                                        }),

                                    // Forms\Components\Placeholder::make('razonamiento_legal')
                                    //     ->label('Razonamiento Legal')
                                    //     ->content($analisis['razonamiento_legal'] ?? 'N/A'),

                                    // Forms\Components\Placeholder::make('consideraciones')
                                    //     ->label('Consideraciones Especiales')
                                    //     ->content($analisis['consideraciones_especiales'] ?? 'N/A')
                                    //     ->hidden(fn() => empty($analisis['consideraciones_especiales'])),
                                ])
                                ->description('Análisis automático basado en los hechos, artículos incumplidos y el historial del trabajador.')
                                ->collapsible(),

                            // Guardar análisis en sesión para uso posterior
                            Forms\Components\Hidden::make('analisis_cache')
                                ->default(json_encode($analisis)),

                            // Campo de selección de sanción (solo opciones apropiadas)
                            Forms\Components\Select::make('tipo_sancion')
                                ->label('Tipo de Sanción a Aplicar')
                                ->options($opcionesSancion)
                                ->required()
                                ->native(false)
                                ->default($analisis['sancion_recomendada'] ?? null)
                                ->helperText('Solo se muestran las opciones apropiadas según el análisis del caso'),
                        ];
                    })
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'sancion_emitida' ? 'Re-generar Sanción' : 'Emitir Sanción'
                    )
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) => ($record->estado === 'sancion_emitida'
                            ? "NOTA: Este proceso ya tiene una sanción emitida. Se generará un nuevo documento reemplazando el anterior.\n\n"
                            : ""
                        ) .
                            "Se generará automáticamente el documento de sanción con IA y se enviará al trabajador: " .
                            ($record->trabajador->nombre_completo ?? '')
                    )
                    ->modalSubmitActionLabel('Continuar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('2xl')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        in_array($record->estado, ['descargos_realizados', 'descargos_no_realizados', 'sancion_emitida']) &&
                            !empty($record->trabajador->email) &&
                            auth()->user()?->hasAnyRole(['super_admin', 'abogado', 'cliente'])
                    )
                    ->action(function (ProcesoDisciplinario $record, array $data, Tables\Actions\Action $action) {
                        // Si es suspensión, pedir días en un segundo modal
                        if ($data['tipo_sancion'] === 'suspension') {
                            // Recuperar análisis del cache
                            $analisis = json_decode($data['analisis_cache'], true);

                            // Construir opciones de días
                            $opcionesDiasSuspension = [];
                            if (isset($analisis['dias_suspension_sugeridos'])) {
                                foreach ($analisis['dias_suspension_sugeridos'] as $dias) {
                                    $opcionesDiasSuspension[$dias] = "{$dias} día" . ($dias > 1 ? 's' : '');
                                }
                            }

                            // Guardar tipo de sanción en sesión
                            session(['tipo_sancion_pendiente_' . $record->id => 'suspension']);
                            session(['opciones_dias_' . $record->id => $opcionesDiasSuspension]);

                            // Mostrar notificación de éxito
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Tipo de sanción seleccionado')
                                ->body('Ahora haz clic en "Confirmar Días de Suspensión" para completar la emisión.')
                                ->persistent()
                                ->send();

                            // Refrescar la página para mostrar el botón de confirmar días
                            return redirect()->to(request()->header('Referer') ?? route('filament.admin.resources.proceso-disciplinarios.index'));
                        }

                        // Si no es suspensión, proceder directamente
                        try {

                            $service = new \App\Services\DocumentGeneratorService();
                            $result = $service->generarYEnviarSancion($record, $data['tipo_sancion']);

                            // Si llegamos aquí, significa que todo fue exitoso
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Sanción emitida!')
                                ->body('El documento de sanción fue generado con IA en lenguaje claro y enviado exitosamente al trabajador.')
                                ->duration(8000)
                                ->send();

                            // Refrescar la página para mostrar el nuevo estado
                            redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                        } catch (\Exception $e) {
                            // Si hay cualquier error, la transacción hizo rollback
                            // y el estado NO se cambió
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al emitir sanción')
                                ->body('No se pudo completar la operación: ' . $e->getMessage() . '. El proceso mantiene su estado original.')
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al emitir sanción', [
                                'proceso_id' => $record->id,
                                'tipo_sancion' => $data['tipo_sancion'] ?? 'N/A',
                                'dias_suspension' => $data['dias_suspension'] ?? 'N/A',
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),

                // Acción secundaria: Confirmar días de suspensión
                Tables\Actions\Action::make('confirmar_dias_suspension')
                    ->label('Confirmar Días de Suspensión')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form(function (ProcesoDisciplinario $record) {
                        // Obtener opciones de días desde la sesión
                        $opcionesDias = session('opciones_dias_' . $record->id, []);

                        if (empty($opcionesDias)) {
                            // Si no hay opciones, cargar desde el análisis en cache
                            $iaService = new \App\Services\IAAnalisisSancionService();
                            $resultado = $iaService->analizarYSugerirSanciones($record);
                            $analisis = $resultado['analisis'];

                            if (isset($analisis['dias_suspension_sugeridos'])) {
                                foreach ($analisis['dias_suspension_sugeridos'] as $dias) {
                                    $opcionesDias[$dias] = "{$dias} día" . ($dias > 1 ? 's' : '');
                                }
                            }
                        }

                        return [
                            Forms\Components\Section::make('Días de Suspensión')
                                ->schema([
                                    Forms\Components\Placeholder::make('info')
                                        ->label('Información')
                                        ->content('Seleccione la cantidad de días de suspensión laboral sin remuneración según la gravedad de la falta.'),

                                    Forms\Components\Select::make('dias_suspension')
                                        ->label('Días de Suspensión')
                                        ->options($opcionesDias)
                                        ->required()
                                        ->native(false)
                                        ->default(fn() => array_key_first($opcionesDias))
                                        ->helperText('Días sugeridos según el análisis del caso y la legislación colombiana'),
                                ])
                                ->description('Complete la información de la suspensión laboral'),
                        ];
                    })
                    ->modalHeading('Confirmar Días de Suspensión')
                    ->modalDescription('Seleccione la cantidad de días para completar la emisión de la sanción')
                    ->modalSubmitActionLabel('Generar y Enviar Sanción')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('md')
                    ->visible(function (ProcesoDisciplinario $record) {
                        $tipoPendiente = session('tipo_sancion_pendiente_' . $record->id);
                        return $tipoPendiente === 'suspension' &&
                            in_array($record->estado, ['descargos_realizados', 'descargos_no_realizados', 'sancion_emitida']) &&
                            !empty($record->trabajador->email) &&
                            auth()->user()?->hasAnyRole(['super_admin', 'abogado', 'cliente']);
                    })
                    ->action(function (ProcesoDisciplinario $record, array $data) {
                        try {
                            // Guardar días de suspensión
                            $record->dias_suspension = $data['dias_suspension'];
                            $record->save();

                            // Generar y enviar sanción
                            $service = new \App\Services\DocumentGeneratorService();
                            $result = $service->generarYEnviarSancion($record, 'suspension');

                            // Limpiar sesión
                            session()->forget('tipo_sancion_pendiente_' . $record->id);
                            session()->forget('opciones_dias_' . $record->id);

                            // Notificar éxito
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Sanción de suspensión emitida!')
                                ->body("El documento de suspensión de {$data['dias_suspension']} día(s) fue generado y enviado exitosamente al trabajador.")
                                ->duration(8000)
                                ->send();

                            // Refrescar la página
                            redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                        } catch (\Exception $e) {
                            // Notificar error
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al emitir sanción')
                                ->body('No se pudo completar la operación: ' . $e->getMessage())
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al confirmar días de suspensión', [
                                'proceso_id' => $record->id,
                                'dias_suspension' => $data['dias_suspension'] ?? 'N/A',
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver')
                        ->icon('heroicon-o-eye')
                        ->color('secondary'),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('primary'),
                    Tables\Actions\ForceDeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Restaurar seleccionados'),
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
            'index' => Pages\ListProcesoDisciplinarios::route('/'),
            'create' => Pages\CreateProcesoDisciplinario::route('/create'),
            'edit' => Pages\EditProcesoDisciplinario::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Si es cliente, solo mostrar procesos de su empresa
        $user = Auth::user();
        if ($user && $user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $query;
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

    /**
     * Obtener festivos de Colombia para un año dado
     */
    protected static function getFestivos(int $year): array
    {
        // Festivos fijos de Colombia
        $festivosFijos = [
            "{$year}-01-01", // Año Nuevo
            "{$year}-05-01", // Día del Trabajo
            "{$year}-07-20", // Día de la Independencia
            "{$year}-08-07", // Batalla de Boyacá
            "{$year}-12-08", // Inmaculada Concepción
            "{$year}-12-25", // Navidad
        ];

        // Calcular Pascua (necesario para festivos móviles)
        $pascua = \Carbon\Carbon::createFromTimestamp(easter_date($year));

        // Festivos basados en Pascua
        $festivosMoviles = [
            $pascua->copy()->subDays(3)->format('Y-m-d'),  // Jueves Santo
            $pascua->copy()->subDays(2)->format('Y-m-d'),  // Viernes Santo
            $pascua->copy()->addDays(43)->format('Y-m-d'), // Ascensión del Señor (lunes siguiente a 39 días después de Pascua)
            $pascua->copy()->addDays(64)->format('Y-m-d'), // Corpus Christi (lunes siguiente a 60 días después de Pascua)
            $pascua->copy()->addDays(71)->format('Y-m-d'), // Sagrado Corazón (lunes siguiente a 68 días después de Pascua)
        ];

        // Festivos que se trasladan al lunes siguiente (Ley Emiliani)
        $festivosEmiliani = [
            "{$year}-01-06" => 'Reyes Magos',
            "{$year}-03-19" => 'San José',
            "{$year}-06-29" => 'San Pedro y San Pablo',
            "{$year}-08-15" => 'Asunción de la Virgen',
            "{$year}-10-12" => 'Día de la Raza',
            "{$year}-11-01" => 'Todos los Santos',
            "{$year}-11-11" => 'Independencia de Cartagena',
        ];

        // Convertir festivos Emiliani al lunes siguiente si no caen en lunes
        $festivosEmilianiAjustados = [];
        foreach ($festivosEmiliani as $fecha => $nombre) {
            $carbon = \Carbon\Carbon::parse($fecha);
            if ($carbon->dayOfWeek !== \Carbon\Carbon::MONDAY) {
                $carbon = $carbon->next(\Carbon\Carbon::MONDAY);
            }
            $festivosEmilianiAjustados[] = $carbon->format('Y-m-d');
        }

        return array_merge($festivosFijos, $festivosMoviles, $festivosEmilianiAjustados);
    }
}
