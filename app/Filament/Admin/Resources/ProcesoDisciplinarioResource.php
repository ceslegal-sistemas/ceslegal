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
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
use Illuminate\Support\Facades\Storage;

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
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
                            ->createOptionModalHeading('Crear Nuevo Trabajador')
                            ->live(),

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
                            ->minDate(fn() => auth()->user()?->hasRole('super_admin') ? now()->startOfDay() : now()->addDays(5)->startOfDay())
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
                            ->minDate(function (Get $get) {
                                // Super admin puede seleccionar desde hoy
                                if (auth()->user()?->hasRole('super_admin')) {
                                    return now()->startOfDay();
                                }

                                // Obtener la empresa para verificar si trabajan sábados
                                $empresaId = $get('empresa_id');
                                $empresa = $empresaId ? Empresa::find($empresaId) : null;
                                $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                // Calcular 6 días HÁBILES desde hoy
                                // Si la empresa trabaja sábados, usar lógica personalizada
                                if ($trabajaSabados) {
                                    $fecha = now()->copy();
                                    $diasContados = 0;

                                    // Obtener festivos
                                    $festivos = [];
                                    try {
                                        if (\Illuminate\Support\Facades\Schema::hasTable('dias_no_habiles')) {
                                            $festivos = \App\Models\DiaNoHabil::pluck('fecha')
                                                ->map(fn($f) => \Carbon\Carbon::parse($f)->format('Y-m-d'))
                                                ->toArray();
                                        }
                                    } catch (\Exception $e) {
                                    }

                                    while ($diasContados < 6) {
                                        $fecha->addDay();
                                        // Solo domingo es no laborable + festivos
                                        if (!$fecha->isSunday() && !in_array($fecha->format('Y-m-d'), $festivos)) {
                                            $diasContados++;
                                        }
                                    }
                                    return $fecha->startOfDay();
                                }

                                // Para empresas que trabajan Lunes a Viernes, usar el servicio estándar
                                $terminoService = app(\App\Services\TerminoLegalService::class);
                                return $terminoService->calcularFechaVencimiento(now(), 6)->startOfDay();
                            })
                            ->maxDate(fn() => now()->addMonth()->endOfDay())
                            ->native(false)
                            ->live()
                            ->disabledDates(function (Get $get) {
                                $fechasDeshabilitadas = [];
                                $inicio = now()->startOfDay();
                                $fin = now()->addYears(1);

                                // Obtener la empresa para verificar si trabajan sábados
                                $empresaId = $get('empresa_id');
                                $empresa = $empresaId ? Empresa::find($empresaId) : null;
                                $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                // Obtener festivos de la base de datos
                                $festivos = [];
                                try {
                                    if (\Illuminate\Support\Facades\Schema::hasTable('dias_no_habiles')) {
                                        $festivos = \App\Models\DiaNoHabil::pluck('fecha')
                                            ->map(fn($fecha) => \Carbon\Carbon::parse($fecha)->format('Y-m-d'))
                                            ->toArray();
                                    }
                                } catch (\Exception $e) {
                                    // Si hay error, continuar sin festivos
                                }

                                // Generar días no laborables
                                for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
                                    if ($trabajaSabados) {
                                        // Solo deshabilitar domingos si trabajan sábados
                                        if ($fecha->isSunday()) {
                                            $fechasDeshabilitadas[] = $fecha->format('Y-m-d');
                                        }
                                    } else {
                                        // Deshabilitar sábados y domingos
                                        if ($fecha->isWeekend()) {
                                            $fechasDeshabilitadas[] = $fecha->format('Y-m-d');
                                        }
                                    }
                                }

                                // Agregar festivos
                                $fechasDeshabilitadas = array_merge($fechasDeshabilitadas, $festivos);

                                return array_unique($fechasDeshabilitadas);
                            })
                            ->visible(fn(Get $get) => $get('modalidad_descargos') === 'virtual')
                            ->helperText(function (Get $get) {
                                $empresaId = $get('empresa_id');
                                $empresa = $empresaId ? Empresa::find($empresaId) : null;
                                $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                if ($trabajaSabados) {
                                    return 'Seleccione la fecha para la audiencia virtual (domingos y festivos no disponibles)';
                                }
                                return 'Seleccione la fecha para la audiencia virtual (fines de semana y festivos no disponibles)';
                            }),


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
                            ->live()
                            ->options(function (Get $get, $record) {
                                // Agregar "Otro" como primera opción
                                $opciones = ['otro' => 'Otro (especificar motivo)'];

                                $trabajadorId = $get('trabajador_id');

                                // Obtener todas las sanciones activas
                                $todasLasSanciones = \App\Models\SancionLaboral::activas()
                                    ->ordenado()
                                    ->get();

                                // Obtener sanciones ya usadas por este trabajador en otros procesos
                                $sancionesUsadasIds = [];
                                if ($trabajadorId) {
                                    $query = ProcesoDisciplinario::where('trabajador_id', $trabajadorId)
                                        ->whereNotNull('sanciones_laborales_ids');

                                    if ($record) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    foreach ($query->get() as $proceso) {
                                        if (is_array($proceso->sanciones_laborales_ids)) {
                                            $sancionesUsadasIds = array_merge($sancionesUsadasIds, $proceso->sanciones_laborales_ids);
                                        }
                                    }
                                    $sancionesUsadasIds = array_unique($sancionesUsadasIds);
                                }

                                // Agrupar sanciones por su padre (o por sí mismas si son primera vez)
                                $sancionesPorGrupo = [];
                                $sancionesSinSecuencia = [];

                                foreach ($todasLasSanciones as $sancion) {
                                    if ($sancion->orden_reincidencia !== null) {
                                        // Tiene secuencia de reincidencia
                                        $grupoId = $sancion->sancion_padre_id ?? $sancion->id;
                                        $sancionesPorGrupo[$grupoId][$sancion->orden_reincidencia] = $sancion;
                                    } else {
                                        // No tiene secuencia
                                        $sancionesSinSecuencia[] = $sancion;
                                    }
                                }

                                // Filtrar sanciones a mostrar
                                $sancionesAMostrar = [];

                                // Procesar sanciones con secuencia de reincidencia
                                foreach ($sancionesPorGrupo as $grupoId => $secuencia) {
                                    ksort($secuencia); // Ordenar por orden_reincidencia (1, 2, 3, 4)

                                    // Encontrar la siguiente en la secuencia que debe mostrarse
                                    foreach ($secuencia as $orden => $sancion) {
                                        if (in_array($sancion->id, $sancionesUsadasIds)) {
                                            // Esta ya fue usada, continuar a la siguiente
                                            continue;
                                        }

                                        // Esta es la siguiente disponible en la secuencia
                                        $sancionesAMostrar[$sancion->id] = $sancion->nombre_con_descripcion;
                                        break; // Solo mostrar una de cada secuencia
                                    }
                                }

                                // Agregar sanciones sin secuencia (siempre se muestran)
                                foreach ($sancionesSinSecuencia as $sancion) {
                                    $sancionesAMostrar[$sancion->id] = $sancion->nombre_con_descripcion;
                                }

                                // Ordenar por ID para mantener el orden original
                                ksort($sancionesAMostrar);

                                return $opciones + $sancionesAMostrar;
                            })
                            ->afterStateHydrated(function ($state, $record, Set $set) {
                                // Si el registro tiene otro_motivo_descargos, agregar 'otro' a la selección
                                if ($record && !empty($record->otro_motivo_descargos)) {
                                    $currentIds = $state ?? [];
                                    if (!in_array('otro', $currentIds)) {
                                        $set('sanciones_laborales_ids', array_merge(['otro'], $currentIds));
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                // Filtrar 'otro' antes de guardar, solo guardar IDs numéricos
                                if (is_array($state)) {
                                    return array_values(array_filter($state, fn($id) => $id !== 'otro' && is_numeric($id)));
                                }
                                return $state;
                            })
                            ->placeholder('Seleccione una o más motivos...')
                            ->helperText(function (Get $get, $record) {
                                $trabajadorId = $get('trabajador_id');
                                if (!$trabajadorId) {
                                    return 'Seleccione los motivos de los descargos a citar al trabajador';
                                }

                                // Contar sanciones con secuencia ya usadas por este trabajador
                                $query = ProcesoDisciplinario::where('trabajador_id', $trabajadorId)
                                    ->whereNotNull('sanciones_laborales_ids');

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                $sancionesUsadasIds = [];
                                foreach ($query->get() as $proceso) {
                                    if (is_array($proceso->sanciones_laborales_ids)) {
                                        $sancionesUsadasIds = array_merge($sancionesUsadasIds, $proceso->sanciones_laborales_ids);
                                    }
                                }
                                $sancionesUsadasIds = array_unique($sancionesUsadasIds);

                                if (count($sancionesUsadasIds) > 0) {
                                    // Verificar si hay sanciones con reincidencia configurada
                                    $sancionesConReincidencia = \App\Models\SancionLaboral::whereIn('id', $sancionesUsadasIds)
                                        ->whereNotNull('orden_reincidencia')
                                        ->count();

                                    if ($sancionesConReincidencia > 0) {
                                        return 'Los motivos con reincidencia muestran automáticamente la siguiente vez disponible';
                                    }
                                }

                                return 'Seleccione los motivos de los descargos a citar al trabajador';
                            })
                            ->extraAttributes([
                                'data-tour' => 'motivos-select',
                            ])
                            // ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'abogado']))
                            ->columnSpanFull(),

                        // Campo para describir otro motivo
                        Forms\Components\Textarea::make('otro_motivo_descargos')
                            ->label('Describa el otro motivo')
                            ->placeholder('Describa detalladamente el motivo de los descargos...')
                            ->rows(3)
                            ->required(fn(Get $get) => in_array('otro', $get('sanciones_laborales_ids') ?? []))
                            ->visible(fn(Get $get) => in_array('otro', $get('sanciones_laborales_ids') ?? []))
                            ->helperText('Especifique el motivo que no se encuentra en la lista')
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
                            ->live()
                            ->extraAttributes([
                                'data-tour' => 'fecha-ocurrencia',
                            ])
                            ->helperText('Fecha en que ocurrieron los hechos que motivan el proceso'),

                        Forms\Components\Repeater::make('fechas_ocurrencia_adicionales')
                            ->label('Fechas adicionales')
                            ->schema([
                                Forms\Components\DatePicker::make('fecha')
                                    ->label('Fecha')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->required()
                                    ->minDate(now()->subMonths(2)->startOfDay())
                                    ->maxDate(now()->endOfDay()),
                            ])
                            ->addActionLabel('Agregar otra fecha')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn(array $state): ?string => isset($state['fecha']) ? \Carbon\Carbon::parse($state['fecha'])->format('d/m/Y') : null)
                            ->helperText('Si los hechos ocurrieron en varias fechas, agregue las fechas adicionales aquí')
                            ->columnSpanFull(),

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
                                            $fechasAdicionales = $get('fechas_ocurrencia_adicionales') ?? [];
                                            $fechaProgramada = $get('fecha_descargos_programada');

                                            // Formatear todas las fechas de ocurrencia
                                            $todasLasFechas = [];
                                            if ($fechaOcurrencia) {
                                                $todasLasFechas[] = \Carbon\Carbon::parse($fechaOcurrencia)->format('d/m/Y');
                                            }
                                            foreach ($fechasAdicionales as $item) {
                                                if (isset($item['fecha'])) {
                                                    $todasLasFechas[] = \Carbon\Carbon::parse($item['fecha'])->format('d/m/Y');
                                                }
                                            }
                                            $fechasOcurrenciaTexto = !empty($todasLasFechas)
                                                ? (count($todasLasFechas) === 1
                                                    ? $todasLasFechas[0]
                                                    : implode(', ', array_slice($todasLasFechas, 0, -1)) . ' y ' . end($todasLasFechas))
                                                : null;
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
                                                ($fechasOcurrenciaTexto ? "- Fecha(s) de los hechos: {$fechasOcurrenciaTexto}\n" : "") .
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

                        // Forms\Components\FileUpload::make('evidencias_empleador')
                        //     ->label('Adjuntar Evidencias (Opcional)')
                        //     ->hint('Máximo 10 archivos')
                        //     ->hintColor('warning')
                        //     ->helperText(new \Illuminate\Support\HtmlString(
                        //         '<div class="text-xs space-y-1 mt-2">' .
                        //             '<p><strong>¿Qué puede subir?</strong></p>' .
                        //             '<ul class="list-disc list-inside ml-2">' .
                        //             '<li>Fotos y capturas de pantalla (JPG, PNG, GIF)</li>' .
                        //             '<li>Documentos PDF</li>' .
                        //             '<li>Archivos Word (.doc, .docx) y Excel (.xls, .xlsx)</li>' .
                        //             '<li>Videos cortos (MP4, MOV, AVI) - <span class="text-orange-600 font-semibold">máximo 50 MB</span></li>' .
                        //             '</ul>' .
                        //             '<p class="mt-2"><strong>⚠️ Límites importantes:</strong></p>' .
                        //             '<ul class="list-disc list-inside ml-2">' .
                        //             '<li>Puede subir hasta <strong>10 archivos</strong> en total</li>' .
                        //             '<li>Imágenes y documentos: máximo <strong>10 MB</strong> cada uno</li>' .
                        //             '<li>Videos: máximo <strong>50 MB</strong> cada uno</li>' .
                        //             '</ul>' .
                        //             '<p class="mt-2 text-gray-500">💡 <strong>Tip:</strong> Haga clic en el recuadro punteado o arrastre los archivos desde su computador.</p>' .
                        //             '<p class="text-gray-500"><strong>¿Videos largos?</strong> Si el video es muy pesado, súbalo a YouTube o Google Drive y pegue el enlace en la descripción de los hechos.</p>' .
                        //             '</div>'
                        //     ))
                        //     ->multiple()
                        //     ->maxFiles(10)
                        //     ->maxSize(51200) // 50MB por archivo para permitir videos
                        //     ->acceptedFileTypes([
                        //         // Imágenes
                        //         'image/jpeg',
                        //         'image/png',
                        //         'image/gif',
                        //         'image/webp',
                        //         // Documentos
                        //         'application/pdf',
                        //         'application/msword',
                        //         'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        //         'application/vnd.ms-excel',
                        //         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        //         // Videos
                        //         'video/mp4',
                        //         'video/quicktime', // .mov
                        //         'video/x-msvideo', // .avi
                        //         'video/webm',
                        //     ])
                        //     ->directory('evidencias-empleador')
                        //     ->visibility('private')
                        //     ->downloadable()
                        //     ->openable()
                        //     ->reorderable()
                        //     ->appendFiles()
                        //     ->panelLayout('grid')
                        //     ->imagePreviewHeight('100')
                        //     ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Decisión y Sanción')
                    ->schema([
                        Forms\Components\Toggle::make('decision_sancion')
                            ->label('¿Procede Sanción?')
                            ->live(),

                        Forms\Components\Toggle::make('impugnado')
                            ->label('¿Procede Impugnación?')
                            ->live(),

                        Forms\Components\Select::make('tipo_sancion')
                            ->label('Tipo de Sanción')
                            ->options([
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension' => 'Suspensión',
                                'terminacion' => 'Terminación de Contrato',
                                'no_sancion' => 'No Aplicar Sanción',
                            ])
                            ->visible(fn(Get $get) => $get('decision_sancion') === true),

                        Forms\Components\Textarea::make('motivo_archivo')
                            ->label('Motivo de Archivo')
                            ->rows(3)
                            ->visible(fn(Get $get) => $get('decision_sancion') === false)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('fecha_notificacion')
                            ->label('Fecha de Notificación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('fecha_limite_impugnacion')
                            ->label('Fecha Límite para Impugnación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->timezone('America/Bogota')
                            ->minDate(now()->addDays(3)->startOfDay())
                            ->helperText('3 días hábiles desde la notificación')
                            ->visible(fn(Get $get) => $get('impugnado') === true),

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
                            ->where('tipo_documento', 'citacion')
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
                            ->where('tipo_documento', 'citacion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'gray';
                        }

                        return $tracking->getColorEstado();
                    })
                    ->tooltip(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_documento', 'citacion')
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
                            ->where('tipo_documento', 'sancion')
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
                            ->where('tipo_documento', 'sancion')
                            ->latest('enviado_en')
                            ->first();

                        if (!$tracking) {
                            return 'gray';
                        }

                        return $tracking->getColorEstado();
                    })
                    ->tooltip(function (ProcesoDisciplinario $record) {
                        $tracking = $record->emailTrackings()
                            ->where('tipo_documento', 'sancion')
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
            ->defaultPaginationPageOption(5)
            ->deferLoading()
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

                // Botón 1b: Enviar Citación desde apertura (email falló o nunca se envió)
                Tables\Actions\Action::make('enviar_citacion_apertura')
                    ->label('Enviar Citación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->modalHeading('Enviar citación a descargos')
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) =>
                        'Se generará y enviará la citación al correo: ' . ($record->trabajador?->email ?? '')
                    )
                    ->modalSubmitActionLabel('Generar y enviar citación')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Section::make('Fecha y hora de la audiencia')
                            ->description('Confirme o actualice la fecha y hora antes de enviar.')
                            ->schema([
                                Forms\Components\DatePicker::make('fecha_temp')
                                    ->label('Fecha de la audiencia')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->addDay()),

                                TimePickerField::make('hora_temp')
                                    ->label('Hora de la audiencia')
                                    ->required()
                                    ->okLabel('Confirmar')
                                    ->cancelLabel('Cancelar'),
                            ])
                            ->columns(2),
                    ])
                    ->fillForm(function (ProcesoDisciplinario $record): array {
                        $fecha = $record->fecha_descargos_programada;
                        $esFutura = $fecha && \Carbon\Carbon::parse($fecha)->isFuture();
                        return [
                            'fecha_temp' => $esFutura ? \Carbon\Carbon::parse($fecha)->format('Y-m-d') : null,
                            'hora_temp'  => $esFutura ? \Carbon\Carbon::parse($fecha)->format('H:i')   : null,
                        ];
                    })
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'apertura' && !empty($record->trabajador?->email)
                    )
                    ->action(function (ProcesoDisciplinario $record, array $data): void {
                        // Actualizar fecha_descargos_programada con el valor del modal
                        $record->fecha_descargos_programada = \Carbon\Carbon::parse($data['fecha_temp'])
                            ->setTimeFromTimeString($data['hora_temp']);
                        $record->save();

                        $service = new \App\Services\DocumentGeneratorService();
                        $result  = $service->generarYEnviarCitacion($record);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Citación enviada')
                                ->body('La citación fue generada y enviada al correo del trabajador. El proceso pasó a estado "Citación enviada".')
                                ->send();

                            if (empty($result['preguntas_ia_generadas'])) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Preguntas IA no generadas')
                                    ->body('La IA no pudo generar preguntas en este momento. Use el botón "Regenerar preguntas IA" en el módulo de Descargos para intentarlo de nuevo.')
                                    ->persistent()
                                    ->send();
                            }
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al enviar la citación')
                                ->body($result['message'] ?? 'Ocurrió un error inesperado.')
                                ->send();
                        }
                    }),

                // Botón 2: Enviar Citación (generar y enviar)
                Tables\Actions\Action::make('enviar_citacion')
                    ->label(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_pendientes' ? 'Re-enviar Citación' : 'Enviar Citación'
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

                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        !empty($record->trabajador->email)
                            && !empty($record->fecha_descargos_programada)
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

                            if (empty($result['preguntas_ia_generadas'])) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Preguntas IA no generadas')
                                    ->body('La IA no pudo generar preguntas en este momento. Use el botón "Regenerar preguntas IA" en el módulo de Descargos para intentarlo de nuevo.')
                                    ->persistent()
                                    ->send();
                            }
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al enviar citación')
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                // Botón: Reprogramar Citación (para trabajadores que no realizaron los descargos)
                Tables\Actions\Action::make('reprogramar_citacion')
                    ->label('Reprogramar Citación')
                    ->icon('heroicon-o-calendar')
                    ->color('primary')
                    ->modalHeading('Reprogramar citación a descargos')
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) =>
                        'Se re-enviará la citación original al correo: ' . ($record->trabajador?->email ?? '') . ' con la nueva fecha seleccionada.'
                    )
                    ->modalSubmitActionLabel('Reprogramar y re-enviar citación')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\Section::make('Nueva fecha y hora de la audiencia')
                            ->description('Seleccione la nueva fecha y hora para la diligencia de descargos.')
                            ->schema([
                                Forms\Components\DatePicker::make('fecha_temp')
                                    ->label('Nueva fecha')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->startOfDay())
                                    ->maxDate(now()->addMonth()->endOfDay())
                                    ->disabledDates(function (\App\Models\ProcesoDisciplinario $record) {
                                        $fechasDeshabilitadas = [];
                                        $inicio = now()->startOfDay();
                                        $fin    = now()->addMonth();

                                        $empresa        = $record->empresa;
                                        $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                        $festivos = [];
                                        try {
                                            if (\Illuminate\Support\Facades\Schema::hasTable('dias_no_habiles')) {
                                                $festivos = \App\Models\DiaNoHabil::pluck('fecha')
                                                    ->map(fn($f) => \Carbon\Carbon::parse($f)->format('Y-m-d'))
                                                    ->toArray();
                                            }
                                        } catch (\Exception $e) {
                                        }

                                        for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
                                            if ($trabajaSabados) {
                                                if ($fecha->isSunday()) {
                                                    $fechasDeshabilitadas[] = $fecha->format('Y-m-d');
                                                }
                                            } else {
                                                if ($fecha->isWeekend()) {
                                                    $fechasDeshabilitadas[] = $fecha->format('Y-m-d');
                                                }
                                            }
                                        }

                                        return array_unique(array_merge($fechasDeshabilitadas, $festivos));
                                    }),

                                TimePickerField::make('hora_temp')
                                    ->label('Nueva hora')
                                    ->required()
                                    ->okLabel('Confirmar')
                                    ->cancelLabel('Cancelar'),
                            ])
                            ->columns(2),
                    ])
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_no_realizados' && !empty($record->trabajador?->email)
                    )
                    ->action(function (ProcesoDisciplinario $record, array $data): void {
                        $nuevaFecha = \Carbon\Carbon::parse($data['fecha_temp'])
                            ->setTimeFromTimeString($data['hora_temp']);

                        // 1. Actualizar fecha y hora en el proceso
                        $record->fecha_descargos_programada = $nuevaFecha->toDateString();
                        $record->hora_descargos_programada  = $data['hora_temp'];
                        $record->save();

                        // 2. Actualizar diligencia: nueva fecha, token_expira_en, limpiar intento anterior
                        $diligencia = $record->diligenciaDescargo;
                        if ($diligencia) {
                            // Borrar respuestas del intento anterior para que el trabajador empiece limpio
                            $diligencia->preguntas()->each(function ($pregunta) {
                                $pregunta->respuesta()->delete();
                            });

                            $diligencia->fecha_diligencia       = $nuevaFecha;
                            $diligencia->fecha_acceso_permitida = $nuevaFecha->toDateString();
                            $diligencia->token_expira_en        = $nuevaFecha->copy()->addDay()->endOfDay();
                            $diligencia->acceso_habilitado      = true;
                            $diligencia->trabajador_accedio_en  = null;
                            $diligencia->primer_acceso_en       = null;
                            $diligencia->tiempo_limite          = null;
                            $diligencia->tiempo_expirado        = false;
                            $diligencia->trabajador_asistio     = false;
                            $diligencia->ip_acceso              = null;
                            $diligencia->save();
                        }

                        // 3. Buscar el PDF original de citación (el más reciente)
                        $documento = $record->documentos()
                            ->where('tipo_documento', 'citacion_descargos')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if (!$documento || !file_exists($documento->ruta_archivo)) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Documento no encontrado')
                                ->body('No se encontró el PDF de citación original. Genere una nueva citación desde el estado Apertura.')
                                ->persistent()
                                ->send();
                            return;
                        }

                        // 4. Preparar link de descargos si la modalidad es virtual
                        $linkDescargos        = null;
                        $fechaAccesoPermitida = null;
                        if ($record->modalidad_descargos === 'virtual' && $diligencia) {
                            $linkDescargos        = route('descargos.acceso', ['token' => $diligencia->token_acceso]);
                            $fechaAccesoPermitida = \Carbon\Carbon::parse($diligencia->fecha_acceso_permitida);
                        }

                        // 5. Re-enviar el correo con el PDF existente (sin regenerar)
                        $emailEnviado = false;
                        $emailError   = null;
                        try {
                            $service = new \App\Services\DocumentGeneratorService();
                            $service->enviarCitacionPorEmail($record, $documento->ruta_archivo, $linkDescargos, $fechaAccesoPermitida);
                            $emailEnviado = true;
                        } catch (\Exception $e) {
                            $emailError = $e->getMessage();
                            Log::error('Error al re-enviar citación reprogramada', [
                                'proceso_id' => $record->id,
                                'error'      => $emailError,
                            ]);
                        }

                        // 6. Volver a estado descargos_pendientes y registrar en timeline
                        $record->estado = 'descargos_pendientes';
                        $record->save();

                        app(\App\Services\TimelineService::class)->registrarNotificacion(
                            procesoTipo:      'proceso_disciplinario',
                            procesoId:        $record->id,
                            tipoNotificacion: 'Citación reprogramada',
                            destinatario:     $record->trabajador->email
                        );

                        if ($emailEnviado) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Citación reprogramada')
                                ->body('La nueva fecha fue registrada y se re-envió la citación original al correo del trabajador.')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Fecha actualizada — fallo al enviar correo')
                                ->body('La fecha de la diligencia fue actualizada correctamente, pero no se pudo enviar el correo: ' . $emailError)
                                ->persistent()
                                ->send();
                        }
                    }),

                // Acción: Corregir estado cuando el trabajador sí realizó los descargos pero el proceso quedó en descargos_no_realizados
                Tables\Actions\Action::make('corregir_estado_descargos')
                    ->label('Marcar como Realizado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Corregir estado del proceso')
                    ->modalDescription(fn(ProcesoDisciplinario $record) =>
                        'El trabajador ' . ($record->trabajador?->nombre_completo ?? '') . ' completó la diligencia de descargos. '
                        . '¿Desea actualizar el estado del proceso a "Descargos realizados"?'
                    )
                    ->modalSubmitActionLabel('Sí, corregir estado')
                    ->visible(function (ProcesoDisciplinario $record) {
                        if ($record->estado !== 'descargos_no_realizados') {
                            return false;
                        }
                        $diligencia = $record->diligenciaDescargo;
                        if (!$diligencia) {
                            return false;
                        }
                        // Solo mostrar si el trabajador realmente finalizó la diligencia
                        return $diligencia->trabajador_asistio === true;
                    })
                    ->action(function (ProcesoDisciplinario $record) {
                        $estadoService = app(\App\Services\EstadoProcesoService::class);
                        $resultado = $estadoService->cambiarEstado(
                            $record,
                            'descargos_realizados',
                            'Estado corregido manualmente: el trabajador completó la diligencia'
                        );

                        if ($resultado) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Estado corregido')
                                ->body('El proceso ahora figura como "Descargos realizados".')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se pudo corregir el estado')
                                ->body('Verifica las transiciones válidas del proceso.')
                                ->send();
                        }
                    }),

                // Acción: Ver Citación (descarga el PDF de la citación enviada)
                Tables\Actions\Action::make('ver_citacion')
                    ->label('Ver Citación')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_pendientes'
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $documento = $record->documentos()
                            ->where('tipo_documento', 'citacion_descargos')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($documento && file_exists($documento->ruta_archivo)) {
                            return response()->download($documento->ruta_archivo, $documento->nombre_archivo);
                        }

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Citación no encontrada')
                            ->body('No se encontró el documento de citación para este proceso.')
                            ->send();
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
                        // Cachear el análisis en sesión para evitar re-llamadas a la IA
                        // cuando ->live() en ToggleButtons dispara un re-render de Livewire.
                        // NO se cachea si la IA devolvió datos de fallback (análisis fallido).
                        $cacheKey = 'emitir_sancion_analisis_' . $record->id;
                        $resultado = session($cacheKey);
                        if (!$resultado || !is_array($resultado) || !isset($resultado['analisis'])) {
                            $iaService = new \App\Services\IAAnalisisSancionService();
                            $resultado = $iaService->analizarYSugerirSanciones($record);
                            $esFallback = !($resultado['success'] ?? false)
                                || str_contains($resultado['analisis']['justificacion'] ?? '', 'Análisis manual requerido');
                            if (!$esFallback) {
                                session([$cacheKey => $resultado]);
                            }
                        } else {
                            $esFallback = false; // viene de caché = ya fue exitoso
                        }
                        $analisis = $resultado['analisis'];

                        // ¿Tiene RIT activo?
                        $sinRit = !$record->empresa->reglamentoInterno()->where('activo', true)->exists();

                        $opcionesSancion = $sinRit
                            ? [
                                'terminacion' => 'Terminación de Contrato (Art. 62 CST)',
                                'no_sancion'  => 'No Aplicar Sanción',
                            ]
                            : [
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension'       => 'Suspensión Laboral',
                                'terminacion'      => 'Terminación de Contrato',
                                'no_sancion'       => 'No Aplicar Sanción',
                            ];

                        // Si la IA no estuvo disponible: no preseleccionar ni mostrar análisis engañoso
                        $recomendacionFinal      = $esFallback ? null : ($analisis['recomendacion_final'] ?? null);
                        $iaRecomendada           = $esFallback ? null : ($recomendacionFinal['sancion_principal'] ?? $recomendacionFinal['sancion_sugerida'] ?? $analisis['sancion_recomendada'] ?? null);
                        $iaSancionesRecomendadas = $esFallback ? [] : ($recomendacionFinal['sanciones_sugeridas'] ?? ($iaRecomendada ? [$iaRecomendada] : []));
                        $autoridadRit            = $esFallback ? [] : ($analisis['autoridad_sancion'] ?? []);

                        $labelsMap = ['llamado_atencion' => 'Llamado de Atención', 'suspension' => 'Suspensión Laboral', 'terminacion' => 'Terminación de Contrato', 'no_sancion' => 'Sin Sanción'];

                        // Techo de severidad: el usuario puede elegir la sanción recomendada o una menor,
                        // nunca una más grave. Jerarquía: terminacion(3) > suspension(2) > llamado_atencion(1) > no_sancion(0)
                        $severidadMap = ['terminacion' => 3, 'suspension' => 2, 'llamado_atencion' => 1, 'no_sancion' => 0];
                        $maxSeveridad = $severidadMap[$iaRecomendada] ?? 3;
                        $opcionesPermitidas = (!$sinRit && !$esFallback && $iaRecomendada)
                            ? array_filter($opcionesSancion, fn($k) => ($severidadMap[$k] ?? 0) <= $maxSeveridad, ARRAY_FILTER_USE_KEY)
                            : $opcionesSancion;

                        return [
                            // ── Tarjetas: Análisis + Recomendación (o error IA) ──────────────
                            Forms\Components\Placeholder::make('analisis_recomendacion_cards')
                                ->hiddenLabel()
                                ->content(fn() => $esFallback
                                    ? view('filament.components.emitir-sancion-ia-error')
                                    : view('filament.components.emitir-sancion-analisis', [
                                        'analisis'      => $analisis,
                                        'recomendacion' => $recomendacionFinal,
                                    ])),

                            // ── Aviso sin RIT ─────────────────────────────────────────────────
                            Forms\Components\Section::make('Empresa sin Reglamento Interno de Trabajo')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->schema([
                                    Forms\Components\Placeholder::make('aviso_sin_rit')
                                        ->hiddenLabel()
                                        ->content(function () use ($sinRit) {
                                            if (!$sinRit) return '';
                                            $purchaseUrl = config('ces.rit_purchase_url');
                                            $boton       = $purchaseUrl
                                                ? "<a href=\"{$purchaseUrl}\" target=\"_blank\" rel=\"noopener\" class=\"inline-flex items-center gap-2 px-4 py-2.5 mt-1 rounded-lg bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors shadow-sm\">Adquirir Reglamento Interno</a>"
                                                : '';
                                            $html  = '<div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-400 dark:border-amber-600 space-y-3">';
                                            $html .= '<div class="flex items-start gap-3">';
                                            $html .= '<lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#d97706,secondary:#fbbf24" style="width:36px;height:36px;flex-shrink:0;margin-top:2px"></lord-icon>';
                                            $html .= '<div><p class="font-semibold text-amber-900 dark:text-amber-100 text-base">Esta empresa no tiene Reglamento Interno de Trabajo (RIT)</p>';
                                            $html .= '<p class="text-sm text-amber-700 dark:text-amber-300 mt-1">Los artículos 111 a 115 del Código Sustantivo del Trabajo establecen que el empleador <strong>solo puede imponer las sanciones expresamente previstas en el RIT</strong> debidamente aprobado y registrado ante el Ministerio del Trabajo.</p>';
                                            $html .= '</div></div>';
                                            $html .= '<div class="bg-amber-100 dark:bg-amber-800/30 rounded-lg p-3 border border-amber-300 dark:border-amber-700">';
                                            $html .= '<p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Por esta vez, no es posible aplicar suspensión ni llamado de atención</p>';
                                            $html .= '<p class="text-sm text-amber-700 dark:text-amber-300 mt-1">El descargo a este trabajador fue iniciado cuando la empresa no contaba con un RIT vigente. La única opción legalmente segura para este proceso es la <strong>terminación del contrato con justa causa</strong> (Art. 62 CST).</p>';
                                            $html .= '</div>';
                                            if ($boton) $html .= "<div>{$boton}</div>";
                                            $html .= '</div>';
                                            return new \Illuminate\Support\HtmlString($html);
                                        }),
                                ])
                                ->visible($sinRit)
                                ->collapsible(false),

                            // Hidden: datos para el action handler
                            Forms\Components\Hidden::make('analisis_cache')
                                ->default(json_encode($analisis)),

                            Forms\Components\Hidden::make('sancion_ia_recomendada')
                                ->default($iaRecomendada),

                            Forms\Components\Hidden::make('sanciones_ia_recomendadas')
                                ->default(json_encode($iaSancionesRecomendadas)),

                            Forms\Components\Hidden::make('autoridad_rango_rit_json')
                                ->default(json_encode($autoridadRit)),

                            // ── Decisión de Sanción — botones con color ───────────────────────
                            Forms\Components\ToggleButtons::make('tipo_sancion')
                                ->label('Decisión de Sanción')
                                ->helperText(!$esFallback && !empty($iaSancionesRecomendadas)
                                    ? 'Opciones recomendadas por la IA: ' . implode(', ', array_map(fn($s) => $labelsMap[$s] ?? $s, $iaSancionesRecomendadas)) . '. Elegir una diferente requiere justificación.'
                                    : null)
                                ->options($opcionesPermitidas)
                                ->colors([
                                    'llamado_atencion' => 'info',
                                    'suspension'       => 'warning',
                                    'terminacion'      => 'danger',
                                    'no_sancion'       => 'success',
                                ])
                                ->icons([
                                    'llamado_atencion' => 'heroicon-o-chat-bubble-bottom-center-text',
                                    'suspension'       => 'heroicon-o-clock',
                                    'terminacion'      => 'heroicon-o-x-circle',
                                    'no_sancion'       => 'heroicon-o-check-circle',
                                ])
                                ->columns(['default' => 1, 'sm' => 3])
                                ->default($iaRecomendada)
                                ->live()
                                ->required(),

                            // ── Potestad Disciplinaria según el RIT ───────────────────────────
                            Forms\Components\Placeholder::make('potestad_card')
                                ->hiddenLabel()
                                ->content(fn() => view('filament.components.emitir-sancion-potestad', [
                                    'autoridadRit'   => $autoridadRit,
                                    'opcionesSancion' => $opcionesSancion,
                                ]))
                                ->hidden(empty($autoridadRit)),

                            // ── Sección de Exoneración ─────────────────────────────────────────
                            Forms\Components\Section::make('Decisión Contraria a la Recomendación Jurídica')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->schema([
                                    Forms\Components\Placeholder::make('exoneracion_aviso')
                                        ->hiddenLabel()
                                        ->content(fn() => new \Illuminate\Support\HtmlString(
                                            '<style>:root{--exo-label:rgba(0,0,0,0.45);--exo-text:rgba(17,24,39,0.78);--exo-strong:#b91c1c;}' .
                                            'html.dark{--exo-label:rgba(255,255,255,0.35);--exo-text:rgba(255,255,255,0.70);--exo-strong:rgba(255,160,160,0.95);}</style>' .
                                            '<div style="padding:16px 18px;background:rgba(239,68,68,0.11);border-radius:14px;' .
                                            'border:1px solid rgba(239,68,68,0.22);border-left:3px solid #f87171;">' .
                                            '<div style="display:flex;align-items:flex-start;gap:12px;">' .
                                            '<lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="800" stroke="bold" ' .
                                            'colors="primary:#f87171,secondary:#fca5a5" style="width:36px;height:36px;flex-shrink:0;margin-top:-2px"></lord-icon>' .
                                            '<div style="flex:1;min-width:0;">' .
                                            '<p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--exo-label);margin:0 0 4px;">Advertencia Legal</p>' .
                                            '<p style="font-size:15px;font-weight:800;color:#f87171;margin:0 0 10px;line-height:1.2;">Decisión contraria a la recomendación jurídica</p>' .
                                            '<p style="font-size:13px;color:var(--exo-text);line-height:1.6;margin:0;">' .
                                            'La decisión que está tomando va en contra de la recomendación jurídica emitida por el sistema de inteligencia artificial de CES Legal. ' .
                                            '<strong style="color:var(--exo-strong);">CES Legal no se responsabiliza por las consecuencias legales, laborales o judiciales derivadas de esta decisión.</strong>' .
                                            '</p></div></div></div>'
                                        )),

                                    Forms\Components\Textarea::make('razon_divergencia')
                                        ->label('Razón por la cual se elige esta sanción en lugar de las recomendadas por la IA')
                                        ->rows(3)
                                        ->required(fn(Get $get) => !empty($get('sanciones_ia_recomendadas')) && !in_array($get('tipo_sancion'), json_decode($get('sanciones_ia_recomendadas') ?? '[]', true) ?: [])),

                                    Forms\Components\Toggle::make('exoneracion_aceptada')
                                        ->label('Confirmo que entiendo las recomendaciones jurídicas emitidas por la IA, que aun así decido aplicar una sanción diferente, y que asumo completamente la responsabilidad jurídica, laboral y judicial de esta decisión, exonerando a CES Legal de cualquier consecuencia derivada de la misma.')
                                        ->required(fn(Get $get) => !empty($get('sanciones_ia_recomendadas')) && !in_array($get('tipo_sancion'), json_decode($get('sanciones_ia_recomendadas') ?? '[]', true) ?: []))
                                        ->accepted()
                                        ->onColor('danger'),
                                ])
                                ->hidden(fn(Get $get) =>
                                    empty($get('tipo_sancion')) ||
                                    empty($get('sanciones_ia_recomendadas')) ||
                                    in_array($get('tipo_sancion'), json_decode($get('sanciones_ia_recomendadas') ?? '[]', true) ?: [])
                                )
                                ->collapsible(false),

                            // ── Verificación del Autorizador ──────────────────────────────────
                            Forms\Components\Section::make('Verificación del Autorizador')
                                ->icon('heroicon-o-user-circle')
                                ->description('Registre los datos de quien autoriza esta sanción y tome una foto de verificación.')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('autorizador_nombre')
                                            ->label('Nombre completo')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('autorizador_cargo')
                                            ->label('Cargo')
                                            ->required()
                                            ->maxLength(255),
                                    ]),

                                    Forms\Components\Placeholder::make('declaracion_texto')
                                        ->hiddenLabel()
                                        ->content(fn() => new \Illuminate\Support\HtmlString(
                                            '<style>:root{--decl-bg:rgba(0,0,0,0.03);--decl-border:rgba(0,0,0,0.10);--decl-left:rgba(0,0,0,0.15);--decl-label:rgba(0,0,0,0.45);--decl-text:rgba(17,24,39,0.78);}' .
                                            'html.dark{--decl-bg:rgba(255,255,255,0.04);--decl-border:rgba(255,255,255,0.10);--decl-left:rgba(255,255,255,0.25);--decl-label:rgba(255,255,255,0.38);--decl-text:rgba(255,255,255,0.72);}</style>' .
                                            '<div style="padding:14px 16px;background:var(--decl-bg);border-radius:12px;border:1px solid var(--decl-border);border-left:3px solid var(--decl-left);">' .
                                            '<p style="font-size:10px;font-weight:700;color:var(--decl-label);text-transform:uppercase;letter-spacing:0.1em;margin:0 0 6px;">Declaración del Autorizador</p>' .
                                            '<p style="font-size:13px;color:var(--decl-text);line-height:1.6;margin:0;">Al marcar la casilla a continuación, declaro que tengo la potestad disciplinaria para emitir esta sanción, que he revisado los hechos del proceso y las evidencias aportadas, y que autorizo expresamente la emisión de esta decisión disciplinaria. Entiendo que esta acción queda registrada con fecha, hora e imagen de verificación como parte del expediente del proceso.</p>' .
                                            '</div>'
                                        )),

                                    Forms\Components\Checkbox::make('declaracion_aceptada')
                                        ->label('Acepto y confirmo la declaración anterior como persona autorizada para emitir esta sanción')
                                        ->required()
                                        ->accepted(),

                                    Forms\Components\Placeholder::make('webcam_autorizador')
                                        ->label('Foto de verificación del autorizador')
                                        ->content(fn() => view('filament.components.webcam-autorizador')),

                                    Forms\Components\Hidden::make('foto_autorizador_base64'),
                                ]),
                        ];
                    })
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'sancion_emitida' ? 'Re-generar Sanción' : 'Emitir Sanción'
                    )
                    ->modalDescription(
                        function (ProcesoDisciplinario $record) {
                            $mensaje = '';

                            if ($record->estado === 'sancion_emitida') {
                                $mensaje .= "NOTA: Este proceso ya tiene una sanción emitida. Se generará un nuevo documento reemplazando el anterior.\n\n";
                            }

                            // Detectar si el trabajador no respondió a los descargos
                            if (in_array($record->estado, ['descargos_no_realizados', 'descargos_pendientes'])) {
                                $diligencia = $record->diligenciaDescargo;
                                $respondio = $diligencia && $diligencia->preguntas()->whereHas('respuesta')->count() > 0;

                                if (!$respondio) {
                                    $mensaje .= "AVISO: El trabajador NO respondió al formulario de descargos. El documento de sanción incluirá esta circunstancia, indicando que se le brindó la oportunidad de defensa pero no la ejerció.\n\n";
                                }
                            }

                            $mensaje .= "Se generará automáticamente el documento de sanción con IA y se enviará al trabajador: " .
                                ($record->trabajador->nombre_completo ?? '');

                            return $mensaje;
                        }
                    )
                    ->modalSubmitActionLabel('Continuar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalWidth('2xl')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        in_array($record->estado, ['descargos_realizados', 'descargos_no_realizados']) &&
                            !empty($record->trabajador->email) &&
                            \Carbon\Carbon::parse($record->fecha_descargos_programada)->isPast()
                    )
                    ->action(function (ProcesoDisciplinario $record, array $data, Tables\Actions\Action $action) {
                        // 1. Procesar foto si existe
                        $fotoPath = null;
                        if (!empty($data['foto_autorizador_base64'])) {
                            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $data['foto_autorizador_base64']);
                            $imageData = base64_decode($base64);
                            if ($imageData) {
                                $dir = "fotos-autorizacion/{$record->id}";
                                $filename = uniqid() . '.jpg';
                                Storage::disk('public')->makeDirectory($dir);
                                Storage::disk('public')->put("{$dir}/{$filename}", $imageData);
                                $fotoPath = "{$dir}/{$filename}";
                            }
                        }

                        // 2. Determinar si la decisión es divergente
                        $sancionesRecomendadasArr = json_decode($data['sanciones_ia_recomendadas'] ?? '[]', true) ?: [];
                        $esDivergente = !empty($sancionesRecomendadasArr) &&
                                        !in_array($data['tipo_sancion'], $sancionesRecomendadasArr);

                        // 3. Guardar campos de trazabilidad
                        $record->update([
                            'sancion_ia_recomendada'  => $data['sancion_ia_recomendada'] ?? null,
                            'autoridad_rango_rit'     => json_decode($data['autoridad_rango_rit_json'] ?? '{}', true) ?: null,
                            'autorizador_nombre'      => $data['autorizador_nombre'] ?? null,
                            'autorizador_cargo'       => $data['autorizador_cargo'] ?? null,
                            'exoneracion_aceptada'    => $esDivergente ? (bool)($data['exoneracion_aceptada'] ?? false) : null,
                            'exoneracion_aceptada_en' => $esDivergente ? now() : null,
                            'exoneracion_ip'          => $esDivergente ? request()->ip() : null,
                            'razon_divergencia'       => $esDivergente ? ($data['razon_divergencia'] ?? null) : null,
                            'foto_autorizador_path'   => $fotoPath,
                            'foto_autorizador_en'     => $fotoPath ? now() : null,
                        ]);

                        // Limpiar cache de análisis (próxima apertura del modal recalculará)
                        session()->forget('emitir_sancion_analisis_' . $record->id);

                        // 4. Continuar con el flujo existente
                        if ($data['tipo_sancion'] === 'suspension') {
                            $analisis = json_decode($data['analisis_cache'], true);

                            $opcionesDiasSuspension = [];
                            if (isset($analisis['dias_suspension_sugeridos'])) {
                                foreach ($analisis['dias_suspension_sugeridos'] as $dias) {
                                    $opcionesDiasSuspension[$dias] = "{$dias} día" . ($dias > 1 ? 's' : '');
                                }
                            }

                            session(['tipo_sancion_pendiente_' . $record->id => 'suspension']);
                            session(['opciones_dias_' . $record->id => $opcionesDiasSuspension]);

                            $recordKey = $record->getKey();
                            $action->getLivewire()->js(
                                "setTimeout(() => { \$wire.mountTableAction('confirmar_dias_suspension', '{$recordKey}') }, 300)"
                            );

                            return;
                        }

                        // Sin sanción: cerrar proceso sin generar documento
                        if ($data['tipo_sancion'] === 'no_sancion') {
                            $record->update([
                                'tipo_sancion' => 'no_sancion',
                                'estado'       => 'cerrado',
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Proceso cerrado — Sin sanción')
                                ->body('Se decidió no aplicar sanción al trabajador. El proceso quedó registrado y cerrado.')
                                ->duration(6000)
                                ->send();

                            redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                            return;
                        }

                        // Si no es suspensión, proceder directamente
                        try {
                            $service = new \App\Services\DocumentGeneratorService();
                            $result = $service->generarYEnviarSancion($record, $data['tipo_sancion']);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Sanción emitida!')
                                ->body('El documento de sanción fue generado con IA en lenguaje claro y enviado exitosamente al trabajador.')
                                ->duration(8000)
                                ->send();

                            redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al emitir sanción')
                                ->body('No se pudo completar la operación: ' . $e->getMessage() . '. El proceso mantiene su estado original.')
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al emitir sanción', [
                                'proceso_id' => $record->id,
                                'tipo_sancion' => $data['tipo_sancion'] ?? 'N/A',
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

                // Acción: Descargar Acta de Descargos (visible como botón solo antes de emitir sanción)
                Tables\Actions\Action::make('descargar_acta_descargos')
                    ->label('Descargar Acta de Descargos')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'descargos_realizados' &&
                            $record->diligenciaDescargo !== null
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        try {
                            $actaService = new \App\Services\ActaDescargosService();
                            $resultado = $actaService->generarActaDescargos($record->diligenciaDescargo);

                            if ($resultado['success']) {
                                return response()->download($resultado['path'], $resultado['filename']);
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error al generar acta')
                                    ->body($resultado['error'] ?? 'No se pudo generar el acta de descargos')
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Error al generar el acta: ' . $e->getMessage())
                                ->send();
                        }
                    }),

                // Acción: Ver Sanción (no mostrar si cerrado con impugnación resuelta)
                Tables\Actions\Action::make('ver_sancion')
                    ->label('Ver Sanción')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(function (ProcesoDisciplinario $record) {
                        if (!in_array($record->estado, ['sancion_emitida', 'impugnacion_realizada', 'cerrado'])) {
                            return false;
                        }
                        // Si está cerrado y tiene impugnación resuelta, mostrar "Ver Resolución" en su lugar
                        if ($record->estado === 'cerrado' && $record->impugnacion?->decision_final !== null) {
                            return false;
                        }
                        return true;
                    })
                    ->action(function (ProcesoDisciplinario $record) {
                        // Buscar documento de sanción
                        $documento = $record->documentos()
                            ->where('tipo_documento', 'sancion')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($documento && file_exists($documento->ruta_archivo)) {
                            return response()->download($documento->ruta_archivo, $documento->nombre_archivo);
                        }

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Documento no encontrado')
                            ->body('No se encontró el documento de sanción para este proceso.')
                            ->send();
                    }),

                // Acción: Ver Resolución (solo cuando cerrado con impugnación resuelta)
                Tables\Actions\Action::make('ver_resolucion')
                    ->label('Ver Resolución')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'cerrado' &&
                            $record->impugnacion?->decision_final !== null
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $documento = $record->documentos()
                            ->where('tipo_documento', 'resolucion_impugnacion')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($documento && file_exists($documento->ruta_archivo)) {
                            return response()->download($documento->ruta_archivo, $documento->nombre_archivo);
                        }

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Documento no encontrado')
                            ->body('No se encontró el documento de resolución para este proceso.')
                            ->send();
                    }),

                // Acción: Registrar Impugnación (solo dentro de 3 días hábiles)
                Tables\Actions\Action::make('registrar_impugnacion')
                    ->label('Registrar Impugnación')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(function (ProcesoDisciplinario $record) {
                        if ($record->estado !== 'sancion_emitida' || $record->impugnacion !== null) {
                            return false;
                        }

                        // Verificar plazo de 3 días hábiles desde la notificación
                        $fechaNotificacion = $record->fecha_notificacion;
                        if (!$fechaNotificacion) {
                            return true;
                        }

                        $fechaLimite = \Carbon\Carbon::parse($fechaNotificacion)->copy();
                        $diasContados = 0;
                        while ($diasContados < 3) {
                            $fechaLimite->addDay();
                            if ($fechaLimite->isWeekday()) {
                                $diasContados++;
                            }
                        }

                        return now()->startOfDay()->lte($fechaLimite);
                    })
                    ->form([
                        Forms\Components\DatePicker::make('fecha_impugnacion')
                            ->label('Fecha de Impugnación')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false),

                        Forms\Components\Select::make('medio_recepcion')
                            ->label('Medio de Recepción')
                            ->native(false)
                            ->options([
                                'correo_electronico' => 'Correo electrónico',
                                'carta_fisica' => 'Carta física',
                                'verbal' => 'Verbal',
                                'otro' => 'Otro',
                            ])
                            ->required()
                            ->live()
                            ->helperText('¿Por qué medio el trabajador presentó la impugnación?'),

                        Forms\Components\Radio::make('tipo_contenido')
                            ->label('¿Cómo desea registrar los motivos?')
                            ->options([
                                'sin_motivos' => 'El trabajador NO expresó motivos (solo adjuntó documentos o impugnó sin explicación)',
                                'motivos_predefinidos' => 'Seleccionar motivos comunes de una lista',
                                'motivos_escritos' => 'Transcribir los motivos expresados por el trabajador',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Seleccione la opción que mejor se ajuste a la situación'),

                        Forms\Components\CheckboxList::make('motivos_predefinidos_lista')
                            ->label('Motivos de la Impugnación')
                            ->options([
                                'inconformidad_sancion' => 'Inconformidad con la sanción impuesta',
                                'hechos_no_corresponden' => 'Los hechos no corresponden con la realidad',
                                'debido_proceso' => 'No se respetó el debido proceso',
                                'pruebas_no_tenidas' => 'Presenta pruebas que no fueron tenidas en cuenta',
                                'desproporcion' => 'Desproporción entre la falta y la sanción',
                                'otro_motivo' => 'Otro motivo',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('tipo_contenido') === 'motivos_predefinidos')
                            ->required(fn (Forms\Get $get) => $get('tipo_contenido') === 'motivos_predefinidos')
                            ->live()
                            ->columns(1)
                            ->helperText('Seleccione uno o más motivos'),

                        Forms\Components\Textarea::make('motivo_personalizado')
                            ->label(fn (Forms\Get $get) => $get('tipo_contenido') === 'motivos_escritos'
                                ? 'Motivos de la Impugnación'
                                : 'Especifique el otro motivo')
                            ->visible(fn (Forms\Get $get) =>
                                $get('tipo_contenido') === 'motivos_escritos' ||
                                ($get('tipo_contenido') === 'motivos_predefinidos' && is_array($get('motivos_predefinidos_lista')) && in_array('otro_motivo', $get('motivos_predefinidos_lista')))
                            )
                            ->required(fn (Forms\Get $get) =>
                                $get('tipo_contenido') === 'motivos_escritos' ||
                                ($get('tipo_contenido') === 'motivos_predefinidos' && is_array($get('motivos_predefinidos_lista')) && in_array('otro_motivo', $get('motivos_predefinidos_lista')))
                            )
                            ->rows(5)
                            ->placeholder(fn (Forms\Get $get) => $get('tipo_contenido') === 'motivos_escritos'
                                ? 'Transcriba los motivos expresados por el trabajador...'
                                : 'Describa el otro motivo...')
                            ->helperText(fn (Forms\Get $get) => $get('tipo_contenido') === 'motivos_escritos'
                                ? 'Transcriba los argumentos presentados por el trabajador'
                                : null),

                        Forms\Components\FileUpload::make('pruebas_adicionales')
                            ->label('Pruebas Adicionales')
                            ->multiple()
                            ->directory('impugnaciones')
                            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(10240)
                            ->helperText('Archivos aportados por el trabajador (opcional). Máximo 10MB por archivo.'),
                    ])
                    ->modalHeading('Registrar Impugnación del Trabajador')
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) =>
                        "Registre la impugnación presentada por {$record->trabajador->nombre_completo} contra la sanción emitida."
                    )
                    ->modalSubmitActionLabel('Registrar Impugnación')
                    ->modalWidth('lg')
                    ->action(function (ProcesoDisciplinario $record, array $data) {
                        try {
                            // Construir motivos_impugnacion según tipo_contenido
                            $mediosLabels = [
                                'correo_electronico' => 'correo electrónico',
                                'carta_fisica' => 'carta física',
                                'verbal' => 'comunicación verbal',
                                'otro' => 'otro medio',
                            ];
                            $medioLabel = $mediosLabels[$data['medio_recepcion']] ?? $data['medio_recepcion'];

                            $motivosTexto = '';
                            switch ($data['tipo_contenido']) {
                                case 'sin_motivos':
                                    $motivosTexto = "El trabajador presentó impugnación mediante {$medioLabel} sin expresar motivos escritos. Se adjunta documentación de soporte.";
                                    break;

                                case 'motivos_predefinidos':
                                    $motivosLabels = [
                                        'inconformidad_sancion' => 'Inconformidad con la sanción impuesta',
                                        'hechos_no_corresponden' => 'Los hechos no corresponden con la realidad',
                                        'debido_proceso' => 'No se respetó el debido proceso',
                                        'pruebas_no_tenidas' => 'Presenta pruebas que no fueron tenidas en cuenta',
                                        'desproporcion' => 'Desproporción entre la falta y la sanción',
                                    ];
                                    $seleccionados = $data['motivos_predefinidos_lista'] ?? [];
                                    $lineas = [];
                                    foreach ($seleccionados as $motivo) {
                                        if ($motivo === 'otro_motivo') {
                                            continue;
                                        }
                                        $lineas[] = '• ' . ($motivosLabels[$motivo] ?? $motivo);
                                    }
                                    if (in_array('otro_motivo', $seleccionados) && !empty($data['motivo_personalizado'])) {
                                        $lineas[] = '• ' . $data['motivo_personalizado'];
                                    }
                                    $motivosTexto = "El trabajador presentó impugnación mediante {$medioLabel} expresando los siguientes motivos:\n" . implode("\n", $lineas);
                                    break;

                                case 'motivos_escritos':
                                    $motivosTexto = $data['motivo_personalizado'];
                                    break;
                            }

                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data, $motivosTexto) {
                                // Crear registro de impugnación
                                $impugnacion = \App\Models\Impugnacion::create([
                                    'proceso_id' => $record->id,
                                    'sancion_id' => $record->sancion?->id,
                                    'fecha_impugnacion' => $data['fecha_impugnacion'],
                                    'medio_recepcion' => $data['medio_recepcion'],
                                    'motivos_impugnacion' => $motivosTexto,
                                    'pruebas_adicionales' => $data['pruebas_adicionales'] ?? null,
                                ]);

                                // Actualizar proceso
                                $record->impugnado = true;
                                $record->fecha_impugnacion = $data['fecha_impugnacion'];
                                $record->estado = 'impugnacion_realizada';
                                $record->save();
                            });

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Impugnación Registrada')
                                ->body('La impugnación ha sido registrada exitosamente. El proceso ahora está en estado de revisión.')
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al registrar impugnación')
                                ->body('No se pudo registrar la impugnación: ' . $e->getMessage())
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al registrar impugnación', [
                                'proceso_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }),

                // Acción: Revisar Impugnación
                Tables\Actions\Action::make('revisar_impugnacion')
                    ->label('Ver Impugnación')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'impugnacion_realizada' &&
                            $record->impugnacion !== null
                    )
                    ->modalHeading('Revisión de Impugnación')
                    ->modalContent(fn(ProcesoDisciplinario $record) => view('filament.modals.revisar-impugnacion', [
                        'proceso' => $record,
                        'impugnacion' => $record->impugnacion,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('2xl'),

                // Acción: Resolver Impugnación
                Tables\Actions\Action::make('resolver_impugnacion')
                    ->label('Resolver Impugnación')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        $record->estado === 'impugnacion_realizada' &&
                            $record->impugnacion !== null &&
                            $record->impugnacion->decision_final === null
                    )
                    ->form(function (ProcesoDisciplinario $record) {
                        $impugnacion = $record->impugnacion;

                        // Sanción original
                        $sancionOriginal = match ($record->tipo_sancion) {
                            'llamado_atencion' => 'Llamado de Atención',
                            'suspension' => 'Suspensión Laboral' . ($record->dias_suspension ? " de {$record->dias_suspension} día(s)" : ''),
                            'terminacion' => 'Terminación de Contrato',
                            'no_sancion' => 'Sin Sanción',
                            default => ucfirst(str_replace('_', ' ', $record->tipo_sancion ?? 'N/A')),
                        };

                        return [
                            // ID del proceso para la acción de IA
                            Forms\Components\Hidden::make('proceso_id')
                                ->default($record->id),

                            // Resumen de la impugnación
                            Forms\Components\Section::make('Impugnación del Trabajador')
                                ->schema([
                                    Forms\Components\Placeholder::make('sancion_original_info')
                                        ->label('Sanción Impugnada')
                                        ->content(fn() => new \Illuminate\Support\HtmlString(
                                            "<span class='font-semibold text-red-600'>{$sancionOriginal}</span>"
                                        )),
                                    Forms\Components\Placeholder::make('motivos_impugnacion')
                                        ->label('Motivos expuestos por el trabajador')
                                        ->content(fn() => new \Illuminate\Support\HtmlString(
                                            "<div class='p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border-l-4 border-yellow-500 italic'>" .
                                            nl2br(e($impugnacion->motivos_impugnacion)) .
                                            "</div>"
                                        )),
                                ])
                                ->collapsible()
                                ->collapsed(false),

                            // Cache del análisis de IA (se llena al hacer clic en el botón)
                            Forms\Components\Hidden::make('analisis_cache')
                                ->default('')
                                ->live(),

                            // Sección de análisis de IA (se muestra al generar)
                            Forms\Components\Section::make('Análisis de IA')
                                ->schema([
                                    Forms\Components\Placeholder::make('ia_contenido')
                                        ->label('')
                                        ->content(function (Forms\Get $get) {
                                            $cache = $get('analisis_cache');
                                            if (empty($cache)) {
                                                return new \Illuminate\Support\HtmlString(
                                                    "<div class='p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center text-blue-700 dark:text-blue-300'>" .
                                                    "<p class='text-sm'>Haga clic en <strong>\"Sugerir fundamento con IA\"</strong> en la sección de abajo para generar el análisis automático del expediente.</p>" .
                                                    "</div>"
                                                );
                                            }
                                            $analisis = json_decode($cache, true);
                                            if (!$analisis) return 'Error al cargar análisis.';

                                            $html = '';

                                            // Auditoría del Proceso
                                            $html .= "<div class='mb-4'>";
                                            $html .= "<h4 class='font-semibold text-sm mb-1'>Auditoría del Proceso</h4>";
                                            $html .= "<p class='text-sm text-gray-700 dark:text-gray-300'>" . e($analisis['auditoria_proceso'] ?? 'N/A') . "</p>";
                                            $html .= "</div>";

                                            // Puntos clave
                                            $puntos = $analisis['puntos_clave_impugnacion'] ?? [];
                                            if (!empty($puntos)) {
                                                $html .= "<div class='mb-4'>";
                                                $html .= "<h4 class='font-semibold text-sm mb-1'>Puntos Clave de la Impugnación</h4>";
                                                $html .= "<ul class='list-disc pl-5 space-y-1 text-sm'>";
                                                foreach ($puntos as $punto) {
                                                    $html .= "<li>" . e($punto) . "</li>";
                                                }
                                                $html .= "</ul></div>";
                                            }

                                            // Contraste de argumentos
                                            $html .= "<div class='mb-4'>";
                                            $html .= "<h4 class='font-semibold text-sm mb-1'>Contraste de Argumentos</h4>";
                                            $html .= "<p class='text-sm text-gray-700 dark:text-gray-300'>" . e($analisis['contraste_argumentos'] ?? 'N/A') . "</p>";
                                            $html .= "</div>";

                                            // Recomendación
                                            $decision = $analisis['decision_recomendada'] ?? 'confirma_sancion';
                                            $texto = match ($decision) {
                                                'confirma_sancion' => 'Confirmar Sanción',
                                                'revoca_sancion' => 'Revocar Sanción',
                                                'modifica_sancion' => 'Modificar Sanción',
                                                default => $decision,
                                            };
                                            $confianza = $analisis['confianza'] ?? 'media';
                                            $badgeColor = match ($confianza) {
                                                'alta' => 'bg-green-100 text-green-800',
                                                'media' => 'bg-yellow-100 text-yellow-800',
                                                'baja' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                            $html .= "<div class='mb-4'>";
                                            $html .= "<h4 class='font-semibold text-sm mb-1'>Recomendación</h4>";
                                            $html .= "<p><span class='font-bold'>{$texto}</span> ";
                                            $html .= "<span class='ml-2 px-2 py-1 rounded text-xs {$badgeColor}'>Confianza: " . ucfirst($confianza) . "</span></p>";
                                            $html .= "<p class='text-sm mt-1'>" . e($analisis['justificacion_decision'] ?? '') . "</p>";
                                            $html .= "</div>";

                                            // Riesgos de nulidad
                                            $html .= "<div class='p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 text-sm'>";
                                            $html .= "<strong>Riesgos de nulidad:</strong> " . e($analisis['riesgos_nulidad'] ?? 'Sin información.');
                                            $html .= "</div>";

                                            return new \Illuminate\Support\HtmlString($html);
                                        }),
                                ])
                                ->collapsible(),

                            // Decisión del usuario
                            Forms\Components\Section::make('Decisión sobre la Impugnación')
                                ->schema([
                                    Forms\Components\Radio::make('decision_final')
                                        ->label('Decisión')
                                        ->options([
                                            'confirma_sancion' => 'Confirmar Sanción - Mantener la sanción original',
                                            'revoca_sancion' => 'Revocar Sanción - Dejar sin efecto la sanción',
                                            'modifica_sancion' => 'Modificar Sanción - Cambiar el tipo de sanción',
                                        ])
                                        ->required()
                                        ->live()
                                        ->descriptions([
                                            'confirma_sancion' => 'La sanción original se mantiene vigente',
                                            'revoca_sancion' => 'Se deja sin efecto la sanción impuesta',
                                            'modifica_sancion' => 'Se cambia la sanción por una diferente',
                                        ]),

                                    Forms\Components\Select::make('nueva_sancion_tipo')
                                        ->label('Nueva Sanción')
                                        ->options([
                                            'llamado_atencion' => 'Llamado de Atención',
                                            'suspension' => 'Suspensión Laboral',
                                            'terminacion' => 'Terminación de Contrato',
                                            'no_sancion' => 'Sin Sanción',
                                        ])
                                        ->required()
                                        ->visible(fn(Forms\Get $get) => $get('decision_final') === 'modifica_sancion')
                                        ->live()
                                        ->native(false),
                                ]),

                            // Fundamento con botón de IA
                            Forms\Components\Section::make('Fundamento de la Decisión')
                                ->schema([
                                    Forms\Components\Actions::make([
                                        Forms\Components\Actions\Action::make('sugerir_fundamento_ia')
                                            ->label('Sugerir fundamento con IA')
                                            ->icon('heroicon-o-sparkles')
                                            ->color('info')
                                            ->size('sm')
                                            ->action(function (Forms\Set $set, Forms\Get $get) {
                                                $procesoId = $get('proceso_id');
                                                $record = ProcesoDisciplinario::findOrFail($procesoId);

                                                $iaService = new \App\Services\IAResolucionImpugnacionService();
                                                $resultado = $iaService->analizarImpugnacion($record);
                                                $analisis = $resultado['analisis'];

                                                // Llenar cache de análisis (actualiza la sección de análisis)
                                                $set('analisis_cache', json_encode($analisis));

                                                // Llenar fundamento sugerido
                                                $set('fundamento_decision', $analisis['fundamento_juridico'] ?? '');

                                                // Establecer decisión recomendada
                                                if (isset($analisis['decision_recomendada'])) {
                                                    $set('decision_final', $analisis['decision_recomendada']);
                                                }

                                                // Si sugiere modificación con suspensión
                                                if (
                                                    ($analisis['modificacion_sugerida']['aplica'] ?? false) &&
                                                    ($analisis['modificacion_sugerida']['nueva_sancion'] ?? null) === 'suspension'
                                                ) {
                                                    $set('nueva_sancion_tipo', 'suspension');
                                                }

                                                if ($resultado['success']) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->success()
                                                        ->title('Análisis generado')
                                                        ->body('El análisis y fundamento se generaron correctamente. Revise y edite según corresponda.')
                                                        ->duration(5000)
                                                        ->send();
                                                } else {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Análisis parcial')
                                                        ->body('No se pudo generar el análisis completo. Se recomienda escribir el fundamento manualmente.')
                                                        ->duration(5000)
                                                        ->send();
                                                }
                                            }),
                                    ])->fullWidth(),

                                    Forms\Components\Textarea::make('fundamento_decision')
                                        ->label('Fundamento')
                                        ->required()
                                        ->rows(8)
                                        ->placeholder('Escriba el fundamento jurídico o haga clic en "Sugerir fundamento con IA" para generar uno automáticamente...')
                                        ->helperText('Este texto se incluirá en el documento de resolución.'),
                                ]),
                        ];
                    })
                    ->modalHeading('Resolver Impugnación')
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) =>
                        "Emita la decisión final sobre la impugnación presentada por {$record->trabajador->nombre_completo}."
                    )
                    ->modalSubmitActionLabel('Emitir Resolución')
                    ->modalWidth('2xl')
                    ->action(function (ProcesoDisciplinario $record, array $data, Tables\Actions\Action $action) {
                        // Si es modificación a suspensión, encadenar al modal de días
                        if (
                            ($data['decision_final'] ?? '') === 'modifica_sancion' &&
                            ($data['nueva_sancion_tipo'] ?? '') === 'suspension'
                        ) {
                            session([
                                'resolucion_impugnacion_pendiente_' . $record->id => [
                                    'decision_final' => $data['decision_final'],
                                    'fundamento_decision' => $data['fundamento_decision'],
                                    'nueva_sancion_tipo' => $data['nueva_sancion_tipo'],
                                ],
                            ]);

                            $recordKey = $record->getKey();
                            $action->getLivewire()->js(
                                "setTimeout(() => { \$wire.mountTableAction('confirmar_dias_resolucion', '{$recordKey}') }, 300)"
                            );

                            return;
                        }

                        // Proceder con la resolución directamente
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                                $impugnacion = $record->impugnacion;

                                $impugnacion->decision_final = $data['decision_final'];
                                $impugnacion->fundamento_decision = $data['fundamento_decision'];
                                $impugnacion->fecha_decision = now();
                                $impugnacion->abogado_analisis_id = auth()->id();
                                $impugnacion->fecha_analisis_impugnacion = now();

                                if ($data['decision_final'] === 'modifica_sancion') {
                                    $impugnacion->nueva_sancion_tipo = $data['nueva_sancion_tipo'];
                                }

                                $impugnacion->save();

                                // Generar documento de resolución
                                $documentService = app(\App\Services\DocumentGeneratorService::class);
                                $documentoPath = $documentService->generarDocumentoResolucionImpugnacion($record, $impugnacion);

                                $impugnacion->documento_generado = true;
                                $impugnacion->ruta_documento = $documentoPath;
                                $impugnacion->save();

                                $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
                                \App\Models\Documento::create([
                                    'documentable_type' => ProcesoDisciplinario::class,
                                    'documentable_id' => $record->id,
                                    'tipo_documento' => 'resolucion_impugnacion',
                                    'nombre_archivo' => 'Resolucion_Impugnacion_' . $record->codigo . '.' . $extension,
                                    'ruta_archivo' => $documentoPath,
                                    'formato' => $extension,
                                    'generado_por' => auth()->id() ?? 1,
                                    'version' => 1,
                                    'fecha_generacion' => now(),
                                ]);

                                $documentService->enviarResolucionImpugnacionPorEmail(
                                    $record,
                                    $documentoPath,
                                    $data['decision_final']
                                );

                                $record->estado = 'cerrado';
                                $record->fecha_cierre = now();
                                $record->save();

                                if ($data['decision_final'] === 'modifica_sancion') {
                                    $record->tipo_sancion = $data['nueva_sancion_tipo'];
                                    $record->save();
                                }
                            });

                            $decisionTexto = match ($data['decision_final']) {
                                'confirma_sancion' => 'confirmada',
                                'revoca_sancion' => 'revocada',
                                'modifica_sancion' => 'modificada',
                                default => 'resuelta',
                            };

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Resolución Emitida')
                                ->body("La impugnación ha sido {$decisionTexto}. Se generó el documento y se envió al trabajador. El proceso ha sido cerrado.")
                                ->duration(8000)
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al resolver impugnación')
                                ->body('No se pudo completar la resolución: ' . $e->getMessage())
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al resolver impugnación', [
                                'proceso_id' => $record->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),

                // Acción: Confirmar días de suspensión para resolución de impugnación
                Tables\Actions\Action::make('confirmar_dias_resolucion')
                    ->label('Confirmar Días - Resolución')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Forms\Components\Section::make('Días de Suspensión')
                            ->schema([
                                Forms\Components\Placeholder::make('info_resolucion')
                                    ->label('')
                                    ->content('La resolución de la impugnación modifica la sanción a suspensión laboral. Seleccione la cantidad de días.'),
                                Forms\Components\Select::make('nuevos_dias_suspension')
                                    ->label('Días de Suspensión')
                                    ->options([
                                        1 => '1 día', 2 => '2 días', 3 => '3 días', 4 => '4 días',
                                        5 => '5 días', 6 => '6 días', 7 => '7 días', 8 => '8 días',
                                        15 => '15 días', 30 => '30 días', 60 => '60 días',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default(3)
                                    ->helperText('Días de suspensión sin remuneración según la gravedad de la falta'),
                            ]),
                    ])
                    ->modalHeading('Confirmar Días de Suspensión')
                    ->modalDescription('Seleccione los días de suspensión para completar la resolución de la impugnación')
                    ->modalSubmitActionLabel('Emitir Resolución')
                    ->modalWidth('md')
                    ->visible(function (ProcesoDisciplinario $record) {
                        return session()->has('resolucion_impugnacion_pendiente_' . $record->id) &&
                            $record->estado === 'impugnacion_realizada';
                    })
                    ->action(function (ProcesoDisciplinario $record, array $data) {
                        try {
                            $sessionData = session('resolucion_impugnacion_pendiente_' . $record->id);

                            if (!$sessionData) {
                                throw new \Exception('No se encontraron los datos de la resolución pendiente.');
                            }

                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data, $sessionData) {
                                $impugnacion = $record->impugnacion;

                                $impugnacion->decision_final = $sessionData['decision_final'];
                                $impugnacion->fundamento_decision = $sessionData['fundamento_decision'];
                                $impugnacion->fecha_decision = now();
                                $impugnacion->abogado_analisis_id = auth()->id();
                                $impugnacion->fecha_analisis_impugnacion = now();
                                $impugnacion->nueva_sancion_tipo = 'suspension';
                                $impugnacion->save();

                                $nuevosDias = (int) $data['nuevos_dias_suspension'];

                                // Generar documento de resolución (pasando los días nuevos)
                                $documentService = app(\App\Services\DocumentGeneratorService::class);
                                $documentoPath = $documentService->generarDocumentoResolucionImpugnacion($record, $impugnacion, $nuevosDias);

                                $impugnacion->documento_generado = true;
                                $impugnacion->ruta_documento = $documentoPath;
                                $impugnacion->save();

                                $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
                                \App\Models\Documento::create([
                                    'documentable_type' => ProcesoDisciplinario::class,
                                    'documentable_id' => $record->id,
                                    'tipo_documento' => 'resolucion_impugnacion',
                                    'nombre_archivo' => 'Resolucion_Impugnacion_' . $record->codigo . '.' . $extension,
                                    'ruta_archivo' => $documentoPath,
                                    'formato' => $extension,
                                    'generado_por' => auth()->id() ?? 1,
                                    'version' => 1,
                                    'fecha_generacion' => now(),
                                ]);

                                $documentService->enviarResolucionImpugnacionPorEmail(
                                    $record,
                                    $documentoPath,
                                    $sessionData['decision_final']
                                );

                                $record->estado = 'cerrado';
                                $record->fecha_cierre = now();
                                $record->tipo_sancion = 'suspension';
                                $record->dias_suspension = $nuevosDias;
                                $record->save();
                            });

                            // Limpiar sesión
                            session()->forget('resolucion_impugnacion_pendiente_' . $record->id);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Resolución Emitida')
                                ->body("La impugnación ha sido resuelta. La sanción se modificó a suspensión de {$data['nuevos_dias_suspension']} día(s). Se generó el documento y se envió al trabajador.")
                                ->duration(8000)
                                ->send();

                            redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al resolver impugnación')
                                ->body('No se pudo completar la resolución: ' . $e->getMessage())
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error al confirmar días resolución impugnación', [
                                'proceso_id' => $record->id,
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
                    Tables\Actions\Action::make('descargar_acta_descargos_agrupado')
                        ->label('Descargar Acta de Descargos')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->visible(
                            fn(ProcesoDisciplinario $record) =>
                            in_array($record->estado, ['sancion_emitida', 'impugnacion_realizada', 'cerrado']) &&
                                $record->diligenciaDescargo !== null
                        )
                        ->action(function (ProcesoDisciplinario $record) {
                            try {
                                $actaService = new \App\Services\ActaDescargosService();
                                $resultado = $actaService->generarActaDescargos($record->diligenciaDescargo);

                                if ($resultado['success']) {
                                    return response()->download($resultado['path'], $resultado['filename']);
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Error al generar acta')
                                        ->body($resultado['error'] ?? 'No se pudo generar el acta de descargos')
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Error al generar el acta: ' . $e->getMessage())
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('ver_citacion_agrupado')
                        ->label('Ver Citación')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->visible(
                            fn(ProcesoDisciplinario $record) =>
                            in_array($record->estado, ['descargos_realizados', 'sancion_emitida', 'impugnacion_realizada', 'cerrado', 'archivado'])
                        )
                        ->action(function (ProcesoDisciplinario $record) {
                            $documento = $record->documentos()
                                ->where('tipo_documento', 'citacion_descargos')
                                ->orderBy('created_at', 'desc')
                                ->first();

                            if ($documento && file_exists($documento->ruta_archivo)) {
                                return response()->download($documento->ruta_archivo, $documento->nombre_archivo);
                            }

                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Citación no encontrada')
                                ->body('No se encontró el documento de citación para este proceso.')
                                ->send();
                        }),
                    Tables\Actions\Action::make('regenerar_sancion')
                        ->label('Re-generar Sanción')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('danger')
                        ->form(function (ProcesoDisciplinario $record) {
                            $iaService = new \App\Services\IAAnalisisSancionService();
                            $resultado = $iaService->analizarYSugerirSanciones($record);
                            $analisis = $resultado['analisis'];

                            $opcionesSancion = [
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension' => 'Suspensión Laboral',
                                'terminacion' => 'Terminación de Contrato',
                                'no_sancion' => 'No Aplicar Sanción',
                            ];

                            $recomendacionFinal = $analisis['recomendacion_final'] ?? null;

                            return [
                                Forms\Components\Section::make('🤖 Análisis del Caso')
                                    ->schema([
                                        Forms\Components\Placeholder::make('gravedad_info')
                                            ->label('Gravedad de la Falta')
                                            ->content(function () use ($analisis) {
                                                $gravedad = match ($analisis['gravedad'] ?? '') {
                                                    'leve'  => '🟢 Leve',
                                                    'grave' => '🔴 Grave',
                                                    default => ucfirst($analisis['gravedad'] ?? 'desconocida'),
                                                };
                                                $reincidencia = ($analisis['es_reincidencia'] ?? false) ? ' ⚠️ REINCIDENCIA' : '';
                                                return $gravedad . $reincidencia;
                                            }),
                                        Forms\Components\Placeholder::make('justificacion_ia')
                                            ->label('Justificación')
                                            ->content(fn() => $analisis['justificacion'] ?? 'Sin justificación disponible.'),
                                    ])
                                    ->collapsible(),

                                Forms\Components\Hidden::make('analisis_cache')
                                    ->default(json_encode($analisis)),

                                Forms\Components\Select::make('tipo_sancion')
                                    ->label('Tipo de Sanción a Aplicar')
                                    ->options($opcionesSancion)
                                    ->required()
                                    ->native(false)
                                    ->default($recomendacionFinal['sancion_sugerida'] ?? $analisis['sancion_recomendada'] ?? null)
                                    ->helperText('Seleccione la sanción que considere más apropiada.'),
                            ];
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Re-generar Sanción')
                        ->modalDescription(
                            fn(ProcesoDisciplinario $record) =>
                            "NOTA: Este proceso ya tiene una sanción emitida. Se generará un nuevo documento reemplazando el anterior.\n\n" .
                                "Se generará automáticamente el documento de sanción con IA y se enviará al trabajador: " .
                                ($record->trabajador->nombre_completo ?? '')
                        )
                        ->modalSubmitActionLabel('Continuar')
                        ->modalCancelActionLabel('Cancelar')
                        ->modalWidth('2xl')
                        ->visible(
                            fn(ProcesoDisciplinario $record) =>
                            $record->estado === 'sancion_emitida' &&
                                !empty($record->trabajador->email) &&
                                \Carbon\Carbon::parse($record->fecha_descargos_programada)->isPast()
                        )
                        ->action(function (ProcesoDisciplinario $record, array $data) {
                            if ($data['tipo_sancion'] === 'suspension') {
                                $analisis = json_decode($data['analisis_cache'], true);
                                $opcionesDiasSuspension = [];
                                if (isset($analisis['dias_suspension_sugeridos'])) {
                                    foreach ($analisis['dias_suspension_sugeridos'] as $dias) {
                                        $opcionesDiasSuspension[$dias] = "{$dias} día" . ($dias > 1 ? 's' : '');
                                    }
                                }
                                session(['tipo_sancion_pendiente_' . $record->id => 'suspension']);
                                session(['opciones_dias_' . $record->id => $opcionesDiasSuspension]);

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Tipo de sanción seleccionado')
                                    ->body('Ahora haz clic en "Confirmar Días de Suspensión" para completar la emisión.')
                                    ->persistent()
                                    ->send();

                                return redirect()->to(request()->header('Referer') ?? route('filament.admin.resources.proceso-disciplinarios.index'));
                            }

                            try {
                                $service = new \App\Services\DocumentGeneratorService();
                                $result = $service->generarYEnviarSancion($record, $data['tipo_sancion']);

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('¡Sanción re-generada!')
                                    ->body('El documento de sanción fue generado con IA y enviado exitosamente al trabajador.')
                                    ->duration(8000)
                                    ->send();

                                redirect()->route('filament.admin.resources.proceso-disciplinarios.index');
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error al re-generar sanción')
                                    ->body('No se pudo completar la operación: ' . $e->getMessage())
                                    ->persistent()
                                    ->send();

                                \Illuminate\Support\Facades\Log::error('Error al re-generar sanción', [
                                    'proceso_id' => $record->id,
                                    'tipo_sancion' => $data['tipo_sancion'] ?? 'N/A',
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }),
                    Tables\Actions\Action::make('archivar_proceso')
                        ->label('Archivar')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->visible(fn(ProcesoDisciplinario $record) => $record->estado === 'cerrado')
                        ->requiresConfirmation()
                        ->modalHeading('Archivar Proceso')
                        ->modalDescription(fn(ProcesoDisciplinario $record) =>
                            "¿Está seguro que desea archivar el proceso {$record->codigo}? Esta acción indica que el proceso ha sido completamente finalizado y archivado para histórico."
                        )
                        ->modalSubmitActionLabel('Sí, archivar')
                        ->form([
                            Forms\Components\Textarea::make('motivo_archivo')
                                ->label('Motivo de Archivo')
                                ->placeholder('Ej: Proceso finalizado sin novedades, sanción cumplida, etc.')
                                ->rows(3)
                                ->maxLength(500),
                        ])
                        ->action(function (ProcesoDisciplinario $record, array $data) {
                            try {
                                $motivo = $data['motivo_archivo'] ?? 'Proceso cerrado y archivado';
                                $record->archivarProceso($motivo);

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Proceso archivado')
                                    ->body("El proceso {$record->codigo} ha sido archivado exitosamente.")
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error al archivar')
                                    ->body('No se pudo archivar el proceso: ' . $e->getMessage())
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('ver_evidencias')
                        ->label('Ver Evidencias')
                        ->icon('heroicon-o-paper-clip')
                        ->color('info')
                        ->visible(fn (ProcesoDisciplinario $record) =>
                            $record->diligencias()
                                ->whereNotNull('archivos_evidencia')
                                ->whereRaw("archivos_evidencia NOT IN ('[]', 'null', '')")
                                ->exists()
                        )
                        ->modalHeading('Evidencias del Trabajador')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalContent(function (ProcesoDisciplinario $record) {
                            $archivos = [];
                            foreach ($record->diligencias()->with('preguntas.respuesta')->get() as $d) {
                                foreach (array_merge($d->archivos_evidencia ?? [], ...$d->preguntas->map(fn($p) => $p->respuesta?->archivos_adjuntos ?? [])->all()) as $a) {
                                    $archivos[] = $a;
                                }
                            }
                            if (empty($archivos)) {
                                return new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-400 dark:text-gray-500 italic p-4">Este proceso no tiene archivos de evidencia adjuntos.</p>'
                                );
                            }
                            $imageExts = ['jpg','jpeg','png','gif','webp','bmp'];
                            $items = '';
                            foreach ($archivos as $i => $a) {
                                $url   = e(\Illuminate\Support\Facades\Storage::disk('public')->url($a['path'] ?? ''));
                                $name  = e($a['nombre'] ?? 'Archivo');
                                $ext   = strtolower(pathinfo($a['nombre'] ?? '', PATHINFO_EXTENSION));
                                $isImg = in_array($ext, $imageExts);
                                $icon  = $isImg
                                    ? '<svg class="w-4 h-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
                                    : '<svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>';
                                $items .= "<div class='flex items-center justify-between gap-3 py-2 border-b border-gray-100 dark:border-white/5 last:border-0'>"
                                        . "<span class='flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 truncate'>{$icon}{$name}</span>"
                                        . "<a href='{$url}' target='_blank' rel='noopener' class='shrink-0 inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline'>"
                                        . "<svg class='w-3.5 h-3.5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'/></svg>"
                                        . "Ver / Descargar</a>"
                                        . "</div>";
                            }
                            $count = count($archivos);
                            return new \Illuminate\Support\HtmlString(
                                "<div class='px-1'>"
                                . "<p class='text-xs text-gray-400 dark:text-gray-500 mb-3'>{$count} " . ($count === 1 ? 'archivo' : 'archivos') . "</p>"
                                . "<div>{$items}</div>"
                                . "</div>"
                            );
                        }),
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

    /**
     * Vista de solo lectura del proceso — usada por clientes desde las notificaciones.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Proceso Disciplinario')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('codigo')
                            ->label('Código')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('estado')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'abierto'                  => 'Abierto',
                                'citado'                   => 'Citado',
                                'descargos_pendientes'     => 'Descargos Pendientes',
                                'descargos_realizados'     => 'Descargos Realizados',
                                'descargos_no_realizados'  => 'Sin Descargos',
                                'sancion_emitida'          => 'Sanción Emitida',
                                'impugnado'                => 'Impugnado',
                                'cerrado'                  => 'Cerrado',
                                'archivado'                => 'Archivado',
                                default                    => ucfirst($state),
                            })
                            ->color(fn($state) => match ($state) {
                                'abierto'                  => 'warning',
                                'citado'                   => 'info',
                                'descargos_pendientes'     => 'warning',
                                'descargos_realizados'     => 'success',
                                'descargos_no_realizados'  => 'danger',
                                'sancion_emitida'          => 'danger',
                                'impugnado'                => 'warning',
                                'cerrado'                  => 'success',
                                'archivado'                => 'gray',
                                default                    => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('trabajador.nombre_completo')
                            ->label('Trabajador'),

                        Infolists\Components\TextEntry::make('empresa.razon_social')
                            ->label('Empresa'),
                    ])->columns(2),

                Infolists\Components\Section::make('Detalles del Proceso')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Infolists\Components\TextEntry::make('fecha_ocurrencia')
                            ->label('Fecha de los Hechos')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('fecha_descargos_programada')
                            ->label('Fecha de Descargos')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('modalidad_descargos')
                            ->label('Modalidad')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'presencial' => 'Presencial',
                                'virtual'    => 'Virtual',
                                default      => $state ?? '—',
                            }),

                        Infolists\Components\TextEntry::make('tipo_sancion')
                            ->label('Sanción Aplicada')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension'       => 'Suspensión Laboral',
                                'terminacion'      => 'Terminación de Contrato',
                                'no_sancion'       => 'Sin Sanción',
                                default            => $state ?? '—',
                            })
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'llamado_atencion' => 'info',
                                'suspension'       => 'warning',
                                'terminacion'      => 'danger',
                                'no_sancion'       => 'success',
                                default            => 'gray',
                            })
                            ->placeholder('Sin sanción aún'),

                        Infolists\Components\TextEntry::make('hechos')
                            ->label('Descripción de los Hechos')
                            ->columnSpanFull()
                            ->prose(),
                    ])->columns(2),

                Infolists\Components\Section::make('Evidencias del trabajador')
                    ->icon('heroicon-o-paper-clip')
                    ->description('Archivos adjuntados por el trabajador durante los descargos')
                    ->schema(function ($record) {
                        $archivos = [];
                        foreach ($record->diligencias()->with('preguntas.respuesta')->get() as $d) {
                            foreach (array_merge($d->archivos_evidencia ?? [], ...$d->preguntas->map(fn($p) => $p->respuesta?->archivos_adjuntos ?? [])->all()) as $a) {
                                $archivos[] = $a;
                            }
                        }
                        if (empty($archivos)) {
                            return [Infolists\Components\TextEntry::make('_sin_archivos')->label('')->getStateUsing(fn () => 'Sin archivos adjuntos.')];
                        }
                        return collect($archivos)->map(function ($a, $i) {
                            $url  = \Illuminate\Support\Facades\Storage::disk('public')->url($a['path'] ?? '');
                            $name = $a['nombre'] ?? 'Archivo';
                            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $icon = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'])
                                ? 'heroicon-o-photo'
                                : ($ext === 'pdf' ? 'heroicon-o-document-text' : 'heroicon-o-paper-clip');
                            return Infolists\Components\Actions::make([
                                \Hugomyb\FilamentMediaAction\Infolists\Components\Actions\MediaAction::make("ver_{$i}")
                                    ->label($name)
                                    ->icon($icon)
                                    ->color('gray')
                                    ->media($url)
                                    ->modalHeading($name)
                                    ->extraModalFooterActions([
                                        \Filament\Infolists\Components\Actions\Action::make("dl_{$i}")
                                            ->label('Descargar')
                                            ->icon('heroicon-o-arrow-down-tray')
                                            ->color('gray')
                                            ->url($url)
                                            ->openUrlInNewTab(),
                                    ]),
                            ]);
                        })->all();
                    })
                    ->collapsible()
                    ->collapsed(false),

                // ── Evidencia de la Decisión Disciplinaria ────────────────────────
                Infolists\Components\Section::make('Evidencia de la Decisión Disciplinaria')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Infolists\Components\TextEntry::make('sancion_ia_recomendada')
                            ->label('Recomendación IA')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'llamado_atencion' => 'info',
                                'suspension'       => 'warning',
                                'terminacion'      => 'danger',
                                'no_sancion'       => 'success',
                                default            => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension'       => 'Suspensión',
                                'terminacion'      => 'Terminación',
                                'no_sancion'       => 'Sin Sanción',
                                default            => $state ?? '—',
                            })
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('tipo_sancion')
                            ->label('Sanción Aplicada')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'llamado_atencion' => 'info',
                                'suspension'       => 'warning',
                                'terminacion'      => 'danger',
                                'no_sancion'       => 'success',
                                default            => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension'       => 'Suspensión Laboral',
                                'terminacion'      => 'Terminación de Contrato',
                                'no_sancion'       => 'Sin Sanción',
                                default            => $state ?? '—',
                            })
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('_divergencia')
                            ->label('¿Siguió la recomendación?')
                            ->getStateUsing(fn($record) =>
                                $record->sancion_ia_recomendada && $record->tipo_sancion !== $record->sancion_ia_recomendada
                                    ? 'No — exoneración requerida'
                                    : 'Sí'
                            )
                            ->badge()
                            ->color(fn($state) =>
                                str_starts_with($state, 'No') ? 'danger' : 'success'
                            ),

                        Infolists\Components\TextEntry::make('razon_divergencia')
                            ->label('Razón de la decisión alternativa')
                            ->columnSpanFull()
                            ->hidden(fn($record) => empty($record->razon_divergencia)),

                        Infolists\Components\IconEntry::make('exoneracion_aceptada')
                            ->label('Exoneración aceptada')
                            ->boolean()
                            ->hidden(fn($record) => $record->exoneracion_aceptada === null),

                        Infolists\Components\TextEntry::make('exoneracion_aceptada_en')
                            ->label('Fecha y hora de aceptación')
                            ->dateTime('d/m/Y H:i:s')
                            ->hidden(fn($record) => !$record->exoneracion_aceptada_en),

                        Infolists\Components\TextEntry::make('exoneracion_ip')
                            ->label('IP de aceptación')
                            ->hidden(fn($record) => empty($record->exoneracion_ip)),

                        Infolists\Components\TextEntry::make('autorizador_nombre')
                            ->label('Autorizador'),

                        Infolists\Components\TextEntry::make('autorizador_cargo')
                            ->label('Cargo del autorizador'),

                        Infolists\Components\ImageEntry::make('foto_autorizador')
                            ->label('Foto del autorizador')
                            ->getStateUsing(fn($record) => $record->foto_autorizador_path
                                ? Storage::disk('public')->url($record->foto_autorizador_path)
                                : null)
                            ->height(200)
                            ->columnSpanFull()
                            ->hidden(fn($record) => empty($record->foto_autorizador_path)),
                    ])
                    ->columns(2)
                    ->hidden(fn($record) => empty($record->autorizador_nombre))
                    ->collapsible(),

                // ── Señales de Comportamiento — Capa 2 ───────────────────────────
                Infolists\Components\Section::make('Señales de Comportamiento durante los Descargos')
                    ->icon('heroicon-o-eye')
                    ->description('Indicios técnicos captados automáticamente durante el formulario del trabajador. Son señales de apoyo, no prueba definitiva.')
                    ->schema([
                        Infolists\Components\TextEntry::make('comportamiento_card')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(fn($record) => view(
                                'filament.components.comportamiento-descargos',
                                ['diligencia' => $record->diligenciaDescargo]
                            )->render())
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->hidden(fn($record) => !$record->diligenciaDescargo)
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProcesoDisciplinarios::route('/'),
            'create' => Pages\CreateProcesoDisciplinario::route('/create'),
            'view'   => Pages\ViewProcesoDisciplinario::route('/{record}'),
            'edit'   => Pages\EditProcesoDisciplinario::route('/{record}/edit'),
        ];
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
