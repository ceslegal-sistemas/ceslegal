<?php

namespace App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;

use App\Filament\Admin\Resources\ReglamentoInternoResource;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\RITGeneratorService;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use HusamTariq\FilamentTimePicker\Forms\Components\TimePickerField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class CreateReglamentoInterno extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ReglamentoInternoResource::class;

    public function mount(): void
    {
        parent::mount();
    }

    /**
     * Sobrescribir fillForm() para pasar los datos guardados en el PRIMER
     * y único fill(), evitando que Alpine/mdtimepicker ya esté inicializado
     * con estado vacío cuando llegue el segundo fill() desde mount().
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $empresa = $this->getEmpresa();
        $saved   = [];

        if ($empresa) {
            $rit = ReglamentoInterno::where('empresa_id', $empresa->id)
                ->orderByDesc('updated_at')
                ->first();

            if ($rit?->respuestas_cuestionario) {
                $saved = $this->normalizarCuestionario($rit->respuestas_cuestionario);
            }

            // Si no hay IDs guardados en el cuestionario, tomarlos de la empresa
            if (empty($saved['actividad_economica_id']) && $empresa->actividad_economica_id) {
                $saved['actividad_economica_id'] = $empresa->actividad_economica_id;
            }
            if (empty($saved['actividades_secundarias_ids'])) {
                $ids = $empresa->actividadesSecundarias()->pluck('actividades_economicas.id')->toArray();
                if (!empty($ids)) {
                    $saved['actividades_secundarias_ids'] = $ids;
                }
            }
        }

        $this->form->fill($saved);

        $this->callHook('afterFill');
    }

    /**
     * Normaliza los datos del cuestionario antes de pasarlos al form->fill():
     * - Convierte booleanos 0/1 a false/true en items del Repeater (Toggle)
     * - Asegura que los arrays de Repeater tengan la estructura correcta
     */
    private function normalizarCuestionario(array $data): array
    {
        // Normalizar booleanos en el Repeater de cargos
        if (isset($data['cargos']) && is_array($data['cargos'])) {
            $data['cargos'] = array_map(function ($item) {
                if (isset($item['puede_sancionar'])) {
                    $item['puede_sancionar'] = (bool) $item['puede_sancionar'];
                }
                return $item;
            }, $data['cargos']);
        }

        return $data;
    }

    protected function getSteps(): array
    {
        $empresa = $this->getEmpresa();

        return [

            // ─────────────────────────────────────────────────────────────────
            // STEP 1: Empresa y Actividad Económica
            // ─────────────────────────────────────────────────────────────────
            Step::make('empresa')
                ->label('Empresa')
                ->description('Datos generales')
                ->icon('heroicon-o-building-office-2')
                ->schema([

                    Forms\Components\Section::make('Datos de la empresa')
                        ->schema([
                            Forms\Components\TextInput::make('razon_social')
                                ->label('Razón Social')
                                ->default($empresa?->razon_social ?? '')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nit')
                                ->label('NIT')
                                ->default($empresa?->nit ?? '')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('domicilio')
                                ->label('Domicilio principal')
                                ->default(trim(
                                    ($empresa?->direccion ?? '') . ' ' .
                                    ($empresa?->ciudad ?? '') . ', ' .
                                    ($empresa?->departamento ?? '')
                                ))
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Actividad económica')
                        ->schema([
                            Forms\Components\Select::make('actividad_economica_id')
                                ->label('Actividad económica principal')
                                ->searchable()
                                ->getSearchResultsUsing(fn(string $search) =>
                                    ActividadEconomica::query()
                                        ->where('activo', true)
                                        ->where(fn($q) => $q
                                            ->where('codigo', 'like', "%{$search}%")
                                            ->orWhere('nombre', 'like', "%{$search}%")
                                        )
                                        ->orderBy('codigo')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                        ->all()
                                )
                                ->getOptionLabelUsing(fn($value) =>
                                    ($a = ActividadEconomica::find($value))
                                        ? "{$a->codigo} — {$a->nombre}"
                                        : $value
                                )
                                ->default($empresa?->actividad_economica_id)
                                ->nullable()
                                ->placeholder('Buscar por código o nombre...')
                                ->helperText('Actividad principal según el RUT de la empresa')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('actividades_secundarias_ids')
                                ->label('Actividades secundarias')
                                ->searchable()
                                ->multiple()
                                ->getSearchResultsUsing(fn(string $search) =>
                                    ActividadEconomica::query()
                                        ->where('activo', true)
                                        ->where(fn($q) => $q
                                            ->where('codigo', 'like', "%{$search}%")
                                            ->orWhere('nombre', 'like', "%{$search}%")
                                        )
                                        ->orderBy('codigo')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                        ->all()
                                )
                                ->getOptionLabelUsing(fn($value) =>
                                    ($a = ActividadEconomica::find($value))
                                        ? "{$a->codigo} — {$a->nombre}"
                                        : $value
                                )
                                ->getOptionLabelsUsing(fn(array $values) =>
                                    ActividadEconomica::whereIn('id', $values)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                        ->all()
                                )
                                ->default($empresa?->actividadesSecundarias?->pluck('id')->toArray() ?? [])
                                ->nullable()
                                ->placeholder('Buscar por código o nombre...')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Trabajadores y sucursales')
                        ->schema([
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('Número de trabajadores')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->placeholder('Ej: 15'),

                            Forms\Components\Radio::make('tiene_sucursales')
                                ->label('¿Tiene sucursales o sedes adicionales?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live(),
                        ])
                        ->columns(2),

                    Forms\Components\Repeater::make('sucursales')
                        ->label('Sucursales / Sedes')
                        ->schema([
                            Forms\Components\TextInput::make('ciudad')
                                ->label('Ciudad')
                                ->required()
                                ->placeholder('Ej: Medellín'),
                            Forms\Components\TextInput::make('direccion')
                                ->label('Dirección')
                                ->placeholder('Ej: Calle 50 # 40-20'),
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('N.° trabajadores')
                                ->numeric()
                                ->required()
                                ->placeholder('Ej: 5'),
                        ])
                        ->columns(3)
                        ->addActionLabel('Agregar sucursal')
                        ->minItems(1)
                        ->visible(fn(Get $get) => $get('tiene_sucursales') === 'si')
                        ->columnSpanFull(),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 2: Estructura y Contratos
            // ─────────────────────────────────────────────────────────────────
            Step::make('estructura')
                ->label('Estructura')
                ->description('Cargos y contratos')
                ->icon('heroicon-o-users')
                ->schema([

                    Forms\Components\Section::make('Cargos de la empresa')
                        ->description('Liste cada cargo que existe en su empresa y marque cuáles tienen facultad para imponer sanciones disciplinarias.')
                        ->schema([
                            Forms\Components\Repeater::make('cargos')
                                ->label('')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre_cargo')
                                        ->label('Nombre del cargo')
                                        ->required()
                                        ->placeholder('Ej: Gerente General'),
                                    Forms\Components\Toggle::make('puede_sancionar')
                                        ->label('¿Puede sancionar?')
                                        ->default(false)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state((bool) $state)),
                                ])
                                ->columns(2)
                                ->addActionLabel('Agregar cargo')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Documentación y contratos')
                        ->schema([
                            Forms\Components\Select::make('tiene_manual_funciones')
                                ->label('¿Tiene manual de funciones?')
                                ->options([
                                    'si'              => 'Sí',
                                    'no'              => 'No',
                                    'en_construccion' => 'En construcción',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\CheckboxList::make('tipos_contrato')
                                ->label('Tipos de contrato que maneja la empresa')
                                ->options([
                                    'indefinido'  => 'Término indefinido',
                                    'fijo'        => 'Término fijo',
                                    'obra_labor'  => 'Obra o labor',
                                    'aprendizaje' => 'Aprendizaje SENA',
                                ])
                                ->default(['indefinido'])
                                ->columns(2),

                            Forms\Components\Radio::make('tiene_trabajadores_mision')
                                ->label('¿Tiene trabajadores en misión o temporales?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline(),
                        ])
                        ->columns(1),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 3: Jornada Laboral
            // ─────────────────────────────────────────────────────────────────
            Step::make('jornada')
                ->label('Jornada')
                ->description('Horarios y turnos')
                ->icon('heroicon-o-clock')
                ->schema([

                    Forms\Components\Section::make('Modalidades de jornada')
                        ->description('Seleccione todas las modalidades que aplican a su empresa.')
                        ->schema([
                            Forms\Components\CheckboxList::make('modalidades_jornada')
                                ->label('¿Qué modalidades de jornada maneja la empresa?')
                                ->options([
                                    'jornada_fija_diurna'     => 'Jornada fija diurna',
                                    'turnos_rotativos'        => 'Turnos rotativos (día/noche)',
                                    'turno_nocturno_regular'  => 'Turno nocturno regular',
                                    'operacion_continua_247'  => 'Operación continua 24/7',
                                    'jornada_flexible'        => 'Jornada flexible o variable',
                                    'teletrabajo'             => 'Teletrabajo / trabajo en casa',
                                    'trabajo_campo_obra'      => 'Trabajo en campo / obra / mina',
                                    'vigilancia_guardias'     => 'Vigilancia / guardias de seguridad',
                                ])
                                ->default(['jornada_fija_diurna'])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Horario principal o administrativo')
                        ->description('Si la empresa opera únicamente en turnos variables, puede dejar estos campos en blanco y describir los turnos en la sección siguiente.')
                        ->schema([
                            TimePickerField::make('horario_entrada')
                                ->label('Hora de entrada')
                                ->id('rit_horario_entrada'),

                            TimePickerField::make('horario_salida')
                                ->label('Hora de salida (lunes a viernes)')
                                ->id('rit_horario_salida'),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Turnos y rotaciones')
                        ->schema([
                            Forms\Components\Select::make('opera_en_turnos')
                                ->label('¿Opera en múltiples turnos o jornadas?')
                                ->options([
                                    'no'           => 'No — jornada única',
                                    '2_turnos'     => 'Sí — 2 turnos',
                                    '3_turnos'     => 'Sí — 3 turnos',
                                    '4_mas_turnos' => 'Sí — 4 o más turnos',
                                    'continuo_247' => 'Operación continua 24/7',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\Select::make('rotacion_turnos')
                                ->label('¿Cómo rotan los trabajadores entre turnos?')
                                ->options([
                                    'turno_fijo'          => 'No aplica — turno fijo por cargo',
                                    'rotacion_semanal'    => 'Rotación semanal',
                                    'rotacion_quincenal'  => 'Rotación quincenal',
                                    'rotacion_mensual'    => 'Rotación mensual',
                                    'por_programacion'    => 'Según programación del supervisor',
                                ])
                                ->default('turno_fijo')
                                ->native(false),

                            Forms\Components\Textarea::make('descripcion_turnos')
                                ->label('Descripción completa de los turnos')
                                ->rows(3)
                                ->placeholder('Ej: Turno A (operarios planta): 06:00 a 14:00 / Turno B (operarios planta): 14:00 a 22:00 / Turno C (vigilantes y mantenimiento): 22:00 a 06:00 / Turno adm: 08:00 a 17:30 L-V')
                                ->helperText('Incluya nombre del turno, horario exacto y cargos que lo operan')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('cargos_nocturnos')
                                ->label('Cargos que trabajan regularmente en horario nocturno (21:00 – 06:00)')
                                ->placeholder('Ej: Vigilantes, operadores planta turno C, conductores de larga distancia, enfermeros nocturnos')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Fines de semana y régimen especial')
                        ->schema([
                            Forms\Components\Select::make('jornada_sabado')
                                ->label('¿Trabaja los sábados?')
                                ->options([
                                    'no'             => 'No',
                                    'media_jornada'  => 'Sí, media jornada (hasta el mediodía)',
                                    'dia_completo'   => 'Sí, día completo',
                                    'algunos_cargos' => 'Sí, algunos cargos (operativos/guardia)',
                                    'en_turnos'      => 'Sí, incluido en turnos rotativos',
                                ])
                                ->default($empresa?->dias_laborales === 'lunes_sabado' ? 'media_jornada' : 'no')
                                ->native(false),

                            Forms\Components\Select::make('trabaja_dominicales')
                                ->label('¿Trabaja domingos o festivos?')
                                ->options([
                                    'no'             => 'No',
                                    'ocasionalmente' => 'Ocasionalmente (con compensatorio)',
                                    'algunos_cargos' => 'Sí, regularmente — algunos cargos',
                                    'regularmente'   => 'Sí, operación completa (recargo 75%)',
                                    'en_turnos'      => 'Sí, incluido en turnos rotativos',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\TextInput::make('cargos_exentos_jornada')
                                ->label('Cargos de dirección, confianza o manejo (exentos jornada máxima — Art. 162 CST)')
                                ->placeholder('Ej: Gerente General, Director Financiero, Jefe de Operaciones, Supervisor de turno')
                                ->helperText('Déjelo en blanco si no aplica.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Control y horas extras')
                        ->schema([
                            Forms\Components\Select::make('control_asistencia')
                                ->label('¿Cómo controla la asistencia?')
                                ->options([
                                    'biometrico'         => 'Reloj biométrico',
                                    'planilla'           => 'Planilla manual por turno',
                                    'app'                => 'App móvil',
                                    'supervision_rondas' => 'Rondas de supervisión',
                                    'sin_control'        => 'Sin control formal',
                                ])
                                ->default('planilla')
                                ->native(false),

                            Forms\Components\Select::make('politica_horas_extras')
                                ->label('Política de horas extras')
                                ->options([
                                    'recargo_legal'  => 'Se pagan con recargo legal (previa autorización escrita)',
                                    'no_autorizadas' => 'No se autorizan',
                                    'compensatorio'  => 'Compensatorio en tiempo libre',
                                ])
                                ->default('recargo_legal')
                                ->native(false),
                        ])
                        ->columns(2),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 4: Salario y Beneficios
            // ─────────────────────────────────────────────────────────────────
            Step::make('salario')
                ->label('Salario')
                ->description('Pago y beneficios')
                ->icon('heroicon-o-banknotes')
                ->schema([

                    Forms\Components\Section::make('Forma y periodicidad de pago')
                        ->schema([
                            Forms\Components\Select::make('forma_pago')
                                ->label('Forma de pago del salario')
                                ->options([
                                    'transferencia' => 'Transferencia bancaria',
                                    'cheque'        => 'Cheque',
                                    'efectivo'      => 'Efectivo',
                                    'mixto'         => 'Mixto (transferencia y efectivo)',
                                ])
                                ->required()
                                ->native(false),

                            Forms\Components\CheckboxList::make('periodicidad_pago')
                                ->label('Periodicidad de pago del salario')
                                ->helperText('Puede seleccionar varias si distintos cargos tienen diferentes periodicidades.')
                                ->options([
                                    'mensual'   => 'Mensual (último día hábil)',
                                    'quincenal' => 'Quincenal (15 y último día)',
                                    'semanal'   => 'Semanal',
                                    'diario'    => 'Diario / jornaleros',
                                    'destajo'   => 'Por obra o destajo',
                                ])
                                ->default(['mensual'])
                                ->columns(3)
                                ->columnSpanFull()
                                ->required(),

                            Forms\Components\Textarea::make('periodicidad_detalle')
                                ->label('Si hay múltiples periodicidades, indique cuáles cargos aplican a cada una')
                                ->rows(2)
                                ->placeholder('Ej: Conductores y operativos: semanal (viernes) / Personal administrativo: quincenal (15 y último día hábil)')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Comisiones y beneficios')
                        ->schema([
                            Forms\Components\Radio::make('maneja_comisiones')
                                ->label('¿Maneja comisiones o bonos?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live(),

                            Forms\Components\Select::make('tipo_comisiones')
                                ->label('Tipo de comisiones / bonos')
                                ->options([
                                    'comisiones_ventas' => 'Comisiones de ventas',
                                    'bonos_desempeno'   => 'Bonos por desempeño',
                                    'ambos'             => 'Ambos',
                                ])
                                ->native(false)
                                ->visible(fn(Get $get) => $get('maneja_comisiones') === 'si'),

                            Forms\Components\Radio::make('tiene_beneficios_extralegales')
                                ->label('¿Otorga beneficios extralegales?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('beneficios_extralegales')
                                ->label('Beneficios extralegales')
                                ->schema([
                                    Forms\Components\TextInput::make('descripcion')
                                        ->label('Descripción')
                                        ->required()
                                        ->placeholder('Ej: Auxilio de alimentación $150.000/mes'),
                                ])
                                ->addActionLabel('Agregar beneficio')
                                ->visible(fn(Get $get) => $get('tiene_beneficios_extralegales') === 'si')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Permisos y licencias')
                        ->schema([
                            Forms\Components\Textarea::make('politica_permisos')
                                ->label('¿Cómo maneja los permisos personales?')
                                ->placeholder('Ej: El trabajador solicita el permiso por escrito con 24 horas de anticipación al jefe inmediato...')
                                ->rows(2)
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('tiene_licencias_especiales')
                                ->label('¿Otorga licencias especiales adicionales a las legales?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('descripcion_licencias')
                                ->label('Describa las licencias especiales')
                                ->rows(2)
                                ->placeholder('Ej: Licencia de matrimonio 1 día remunerado, calamidad doméstica 3 días')
                                ->visible(fn(Get $get) => $get('tiene_licencias_especiales') === 'si')
                                ->columnSpanFull(),
                        ]),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 5: Régimen Disciplinario
            // ─────────────────────────────────────────────────────────────────
            Step::make('disciplina')
                ->label('Disciplina')
                ->description('Faltas y sanciones')
                ->icon('heroicon-o-scale')
                ->schema([

                    Forms\Components\Section::make('Clasificación de faltas')
                        ->description('Escriba cada falta y presione Enter para agregarla. Puede seleccionar de las sugerencias o escribir las propias.')
                        ->schema([
                            Forms\Components\TagsInput::make('faltas_leves')
                                ->label('Faltas leves')
                                ->suggestions([
                                    'Impuntualidad',
                                    'No registrar asistencia',
                                    'No usar el uniforme',
                                    'Uso de celular en horario laboral',
                                    'Desorden en el puesto de trabajo',
                                ])
                                ->placeholder('Escriba una falta y presione Enter')
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('faltas_graves')
                                ->label('Faltas graves')
                                ->suggestions([
                                    'Agresión verbal a compañero',
                                    'Ausentismo sin justificación',
                                    'Incumplir normas de seguridad',
                                    'Desobedecer órdenes del superior',
                                    'Daño a bienes de la empresa',
                                ])
                                ->placeholder('Escriba una falta y presione Enter')
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('faltas_muy_graves')
                                ->label('Faltas muy graves')
                                ->suggestions([
                                    'Hurto',
                                    'Agresión física',
                                    'Acoso sexual',
                                    'Divulgación de secretos empresariales',
                                    'Presentarse en estado de embriaguez',
                                    'Falsificación de documentos',
                                ])
                                ->placeholder('Escriba una falta y presione Enter')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Sanciones a aplicar')
                        ->schema([
                            Forms\Components\CheckboxList::make('sanciones_contempladas')
                                ->label('Sanciones que contempla aplicar')
                                ->options([
                                    'llamado_verbal'  => 'Llamado de atención verbal',
                                    'llamado_escrito' => 'Llamado de atención escrito',
                                    'suspension_1_8'  => 'Suspensión 1-8 días',
                                    'suspension_1_15'  => 'Suspensión 1-15 días',
                                    'suspension_1_30' => 'Suspensión 1-30 días',
                                    'suspension_1_40' => 'Suspensión 1-40 días',
                                    'suspension_1_60' => 'Suspensión 1-60 días',
                                    'terminacion'     => 'Terminación con justa causa',
                                ])
                                ->default(['llamado_verbal', 'llamado_escrito', 'suspension_1_8', 'suspension_1_15', 'terminacion'])
                                ->columns(2),
                        ]),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 6: SST y Conducta
            // ─────────────────────────────────────────────────────────────────
            Step::make('sst_conducta')
                ->label('SST y Conducta')
                ->description('Seguridad y comportamiento')
                ->icon('heroicon-o-shield-check')
                ->schema([

                    Forms\Components\Section::make('Seguridad y Salud en el Trabajo')
                        ->schema([
                            Forms\Components\Select::make('tiene_sg_sst')
                                ->label('¿Tiene implementado el SG-SST?')
                                ->options([
                                    'implementado' => 'Sí, implementado',
                                    'en_proceso'   => 'En proceso de implementación',
                                    'no'           => 'No',
                                ])
                                ->default('en_proceso')
                                ->native(false),

                            Forms\Components\CheckboxList::make('riesgos_principales')
                                ->label('Principales riesgos en su operación')
                                ->options([
                                    'ergonomico'  => 'Ergonómico (pantallas, postura)',
                                    'psicosocial' => 'Psicosocial (estrés, turnos)',
                                    'mecanico'    => 'Mecánico (maquinaria)',
                                    'electrico'   => 'Eléctrico',
                                    'publico'     => 'Público (atención al cliente)',
                                    'otro'        => 'Otro',
                                ])
                                ->default(['ergonomico'])
                                ->live()
                                ->columns(2),

                            Forms\Components\TextInput::make('riesgos_otros')
                                ->label('Describa el otro riesgo')
                                ->placeholder('Ej: Riesgo químico por manejo de solventes')
                                ->visible(fn(Get $get) => in_array('otro', (array) $get('riesgos_principales'))),

                            Forms\Components\Radio::make('tiene_epp')
                                ->label('¿Requiere elementos de protección personal (EPP)?')
                                ->options([
                                    'no' => 'No aplica — trabajo de oficina',
                                    'si' => 'Sí, se requieren',
                                ])
                                ->default('no')
                                ->inline()
                                ->live(),

                            Forms\Components\TextInput::make('epp_descripcion')
                                ->label('EPP requeridos')
                                ->placeholder('Ej: Casco, guantes, botas de seguridad')
                                ->visible(fn(Get $get) => $get('tiene_epp') === 'si'),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Conducta y uso de recursos')
                        ->schema([
                            Forms\Components\Select::make('politica_celular')
                                ->label('Política de uso de celular personal en horario laboral')
                                ->options([
                                    'libre'     => 'Libre uso',
                                    'descansos' => 'Solo en descansos',
                                    'prohibido' => 'Prohibido salvo emergencias',
                                ])
                                ->default('descansos')
                                ->native(false),

                            Forms\Components\Radio::make('usa_uniforme')
                                ->label('¿La empresa entrega uniforme o dotación?')
                                ->options([
                                    'no'              => 'No',
                                    'uniforme'        => 'Sí, uniforme completo',
                                    'dotacion_basica' => 'Sí, dotación básica',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\Radio::make('tiene_codigo_etica')
                                ->label('¿Tiene código de ética o conducta?')
                                ->options([
                                    'si'              => 'Sí',
                                    'no'              => 'No',
                                    'en_construccion' => 'En construcción',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\Select::make('politica_confidencialidad')
                                ->label('Cláusula de confidencialidad')
                                ->options([
                                    'por_contrato' => 'Sí, incluida en el contrato',
                                    'solo_verbal'  => 'Solo verbal',
                                    'no'           => 'No',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\Textarea::make('que_quiere_prevenir')
                                ->label('¿Qué conductas quiere prevenir principalmente? (opcional)')
                                ->rows(2)
                                ->placeholder('Ej: Impuntualidad crónica, conflictos entre compañeros, uso indebido de información')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 7: Revisión y Generar
            // ─────────────────────────────────────────────────────────────────
            Step::make('revision')
                ->label('Generar')
                ->description('Revisar y construir')
                ->icon('heroicon-o-cpu-chip')
                ->schema([

                    Forms\Components\Placeholder::make('revision_rit')
                        ->label('')
                        ->content(fn(Get $get) => new HtmlString(
                            view('filament.components.rit-revision-resumen', [
                                'empresa'               => $this->getEmpresa(),
                                'num_trabajadores'      => $get('num_trabajadores'),
                                'tiene_sucursales'      => $get('tiene_sucursales'),
                                'sucursales'            => $get('sucursales') ?? [],
                                'cargos'                => $get('cargos') ?? [],
                                'tipos_contrato'        => $get('tipos_contrato') ?? [],
                                'modalidades_jornada'   => $get('modalidades_jornada') ?? [],
                                'horario_entrada'       => $get('horario_entrada'),
                                'horario_salida'        => $get('horario_salida'),
                                'opera_en_turnos'       => $get('opera_en_turnos'),
                                'descripcion_turnos'    => $get('descripcion_turnos'),
                                'cargos_nocturnos'      => $get('cargos_nocturnos'),
                                'jornada_sabado'        => $get('jornada_sabado'),
                                'trabaja_dominicales'   => $get('trabaja_dominicales'),
                                'cargos_exentos_jornada' => $get('cargos_exentos_jornada'),
                                'control_asistencia'    => $get('control_asistencia'),
                                'forma_pago'            => $get('forma_pago'),
                                'periodicidad_pago'     => $get('periodicidad_pago') ?? [],
                                'periodicidad_detalle'  => $get('periodicidad_detalle'),
                                'faltas_leves'          => $get('faltas_leves') ?? [],
                                'faltas_graves'         => $get('faltas_graves') ?? [],
                                'faltas_muy_graves'     => $get('faltas_muy_graves') ?? [],
                                'sanciones'             => $get('sanciones_contempladas') ?? [],
                                'tiene_sg_sst'          => $get('tiene_sg_sst'),
                                'riesgos_principales'   => $get('riesgos_principales') ?? [],
                            ])->render()
                        ))
                        ->columnSpanFull(),
                ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Creación del registro
    // ─────────────────────────────────────────────────────────────────────────

    protected function handleRecordCreation(array $data): Model
    {
        $empresa = $this->getEmpresa();

        if (!$empresa) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se encontró la empresa asociada a su cuenta.')
                ->send();
            throw new Halt();
        }

        // 1. Resolver actividad_economica_id → texto legible (conservar ID para re-edición)
        $actividadId = $data['actividad_economica_id'] ?? null;
        if ($actividadId) {
            $actividad = ActividadEconomica::find($actividadId);
            $data['actividad_economica'] = $actividad
                ? "{$actividad->codigo} — {$actividad->nombre}"
                : '';
            // Mantener el ID para que fillForm() lo restaure al editar
            $data['actividad_economica_id'] = $actividadId;
        } else {
            unset($data['actividad_economica_id']);
        }

        // 2. Resolver actividades secundarias → texto (conservar IDs para re-edición)
        $actividadesIds = $data['actividades_secundarias_ids'] ?? [];
        if (!empty($actividadesIds)) {
            $data['actividades_secundarias'] = ActividadEconomica::whereIn('id', $actividadesIds)
                ->get()
                ->map(fn($a) => "{$a->codigo} — {$a->nombre}")
                ->join(', ');
            // Mantener los IDs para que fillForm() los restaure al editar
            $data['actividades_secundarias_ids'] = $actividadesIds;
        } else {
            unset($data['actividades_secundarias_ids']);
        }

        // 3. Añadir datos base de la empresa
        $data['razon_social'] = $empresa->razon_social ?? '';
        $data['nit']          = $empresa->nit ?? '';
        $data['domicilio']    = trim(
            ($empresa->direccion ?? '') . ' ' .
            ($empresa->ciudad ?? '') . ', ' .
            ($empresa->departamento ?? '')
        );

        // 4. Guardar cuestionario PRIMERO (así no se pierde nada si la IA falla)
        $record = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'nombre'                  => 'Reglamento Interno — ' . now()->format('d/m/Y'),
                'texto_completo'          => '',
                'activo'                  => false,
                'respuestas_cuestionario' => $data,
                'fuente'                  => 'construido_ia',
            ]
        );

        // 5. Llamar a la IA (puede tardar hasta 90s)
        $service = app(RITGeneratorService::class);
        try {
            $textoRIT = $service->generarTextoRIT($data, $empresa);
        } catch (\Exception $e) {
            Log::error('Error generando RIT con IA', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            Notification::make()
                ->danger()
                ->title('Error al generar el texto con IA')
                ->body('Sus respuestas quedaron guardadas. Puede intentar de nuevo con el botón "Crear".')
                ->persistent()
                ->send();
            throw new Halt();
        }

        // 6. Post-procesar: reemplazar cualquier placeholder que haya dejado la IA
        $representante = $empresa->representante_legal ?? '_______________';
        $textoRIT = str_replace(
            ['[DÍA]', '[MES]', '[AÑO]', '[NOMBRE DEL REPRESENTANTE LEGAL]', '[NOMBRE REPRESENTANTE LEGAL]',
             '[NÚMERO DE CÉDULA]', '[NÚMERO CÉDULA]', '[NIT]', '[RAZÓN SOCIAL]', '[DOMICILIO]'],
            [now()->day, now()->locale('es')->translatedFormat('F'), now()->year,
             $representante, $representante,
             '_______________', '_______________',
             $empresa->nit ?? '_______________',
             $empresa->razon_social ?? '_______________',
             trim(($empresa->direccion ?? '') . ' ' . ($empresa->ciudad ?? ''))],
            $textoRIT
        );

        // 7. Actualizar con el texto generado
        $record->update([
            'nombre'         => 'Reglamento Interno generado con IA — ' . now()->format('d/m/Y'),
            'texto_completo' => $textoRIT,
            'activo'         => true,
        ]);

        // 8. Guardar DOCX en disco público para adjunto nativo (no fatal si falla)
        $rutaDocx = $service->guardarDocxPublico($textoRIT, $empresa);
        if ($rutaDocx) {
            $record->update(['ruta_docx' => $rutaDocx]);
        }

        Notification::make()
            ->success()
            ->title('¡Reglamento Interno generado!')
            ->body('Su RIT fue redactado con IA y guardado. Puede descargarlo desde Configuración → Construir RIT.')
            ->send();

        return $record;
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.dashboard') => 'Panel',
            'Construir Reglamento Interno de Trabajo',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.pages.mi-reglamento-interno');
    }

    private function getEmpresa(): ?Empresa
    {
        $user = Auth::user();
        if (!$user) return null;
        if ($user->hasRole('super_admin') || $user->hasRole('abogado')) {
            return Empresa::first();
        }
        return $user->empresa ?? null;
    }
}
