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

                    Forms\Components\Placeholder::make('info_paso_empresa')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-empresa-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Datos de su empresa')
                        ->description('Estos datos vienen de su registro y aparecerán en el encabezado oficial del Reglamento.')
                        ->schema([
                            Forms\Components\TextInput::make('razon_social')
                                ->label('Razón social / nombre de la empresa')
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
                                ->label('Dirección y ciudad principal')
                                ->default(trim(
                                    ($empresa?->direccion ?? '') . ' ' .
                                    ($empresa?->ciudad ?? '') . ', ' .
                                    ($empresa?->departamento ?? '')
                                ))
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('¿A qué se dedica su empresa?')
                        ->description('La actividad económica define los riesgos laborales específicos que el RIT debe cubrir. Si no sabe el código, busque por nombre (ej: "servicios", "construcción").')
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
                                ->placeholder('Buscar por código CIIU o nombre...')
                                ->helperText('Actividad principal según el RUT. Ej: 4711 — Comercio al por menor en establecimientos no especializados')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('actividades_secundarias_ids')
                                ->label('¿Tiene otras actividades secundarias?')
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
                                ->placeholder('Opcional — buscar actividades adicionales...')
                                ->helperText('Solo si su empresa realiza actividades muy diferentes entre sí.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Tamaño y sedes')
                        ->schema([
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('¿Cuántos empleados tiene actualmente?')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->placeholder('Ej: 15')
                                ->helperText('Cuente todos los trabajadores, incluyendo los de tiempo parcial.'),

                            Forms\Components\Radio::make('tiene_sucursales')
                                ->label('¿Tiene sucursales o sedes en otras ciudades?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live(),
                        ])
                        ->columns(2),

                    Forms\Components\Repeater::make('sucursales')
                        ->label('Sucursales / Sedes adicionales')
                        ->schema([
                            Forms\Components\TextInput::make('ciudad')
                                ->label('Ciudad')
                                ->required()
                                ->placeholder('Ej: Medellín'),
                            Forms\Components\TextInput::make('direccion')
                                ->label('Dirección')
                                ->placeholder('Ej: Calle 50 # 40-20'),
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('N.° trabajadores en esa sede')
                                ->numeric()
                                ->required()
                                ->placeholder('Ej: 5'),
                        ])
                        ->columns(3)
                        ->addActionLabel('Agregar otra sede')
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

                    Forms\Components\Placeholder::make('info_paso_estructura')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-estructura-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('¿Qué cargos existen en su empresa?')
                        ->description('Liste cada cargo que existe. Puede ser Gerente, Operario, Vendedor, Auxiliar... el nombre que use internamente. Solo marque "puede sancionar" en los que realmente toman decisiones disciplinarias.')
                        ->schema([
                            Forms\Components\Repeater::make('cargos')
                                ->label('')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre_cargo')
                                        ->label('Nombre del cargo')
                                        ->required()
                                        ->placeholder('Ej: Gerente General, Operario planta, Vendedor externo'),
                                    Forms\Components\Toggle::make('puede_sancionar')
                                        ->label('¿Puede imponer sanciones a otros?')
                                        ->default(false)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state((bool) $state)),
                                ])
                                ->columns(2)
                                ->addActionLabel('Agregar otro cargo')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Contratos y documentación')
                        ->description('Esto determina qué cláusulas aplican en el Reglamento para cada tipo de empleado.')
                        ->schema([
                            Forms\Components\Select::make('tiene_manual_funciones')
                                ->label('¿Tiene escrito qué hace cada cargo? (manual de funciones)')
                                ->options([
                                    'si'              => 'Sí, tenemos manual de funciones',
                                    'no'              => 'No',
                                    'en_construccion' => 'Lo estamos construyendo',
                                ])
                                ->default('no')
                                ->native(false)
                                ->helperText('No es obligatorio para el RIT, pero es buena práctica tenerlo.'),

                            Forms\Components\CheckboxList::make('tipos_contrato')
                                ->label('¿Qué tipos de contrato usa con sus empleados?')
                                ->options([
                                    'indefinido'  => 'Término indefinido (sin fecha de fin)',
                                    'fijo'        => 'Término fijo (con fecha de vencimiento)',
                                    'obra_labor'  => 'Obra o labor (hasta terminar el proyecto)',
                                    'aprendizaje' => 'Aprendizaje SENA',
                                ])
                                ->default(['indefinido'])
                                ->columns(2)
                                ->helperText('Seleccione todos los que usa actualmente.'),

                            Forms\Components\Radio::make('tiene_trabajadores_mision')
                                ->label('¿Tiene temporales o trabajadores de una empresa de servicios?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->helperText('Ej: Personal enviado por una temporal o empresa de outsourcing.'),
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

                    Forms\Components\Placeholder::make('info_paso_jornada')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-jornada-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('¿Cómo trabajan sus empleados?')
                        ->description('Seleccione todo lo que aplique a su empresa. Si es oficina pura, solo marque "Jornada fija diurna".')
                        ->schema([
                            Forms\Components\CheckboxList::make('modalidades_jornada')
                                ->label('Tipos de jornada en su empresa')
                                ->options([
                                    'jornada_fija_diurna'     => 'Jornada fija diurna (oficina, lunes a viernes)',
                                    'turnos_rotativos'        => 'Turnos rotativos (el empleado cambia entre día/noche)',
                                    'turno_nocturno_regular'  => 'Turno nocturno fijo (siempre de noche)',
                                    'operacion_continua_247'  => 'Operación continua 24/7 (nunca para)',
                                    'jornada_flexible'        => 'Horario flexible o variable',
                                    'teletrabajo'             => 'Teletrabajo / trabajo desde casa',
                                    'trabajo_campo_obra'      => 'Trabajo en campo, obra o mina',
                                    'vigilancia_guardias'     => 'Vigilancia / guardias de seguridad',
                                ])
                                ->default(['jornada_fija_diurna'])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('¿A qué hora entran y salen? (horario habitual)')
                        ->description('Si todos sus empleados trabajan el mismo horario, regístrelo aquí. Si tiene turnos variables, puede dejar esto en blanco y usar la sección de turnos más abajo.')
                        ->schema([
                            TimePickerField::make('horario_entrada')
                                ->label('Hora de entrada')
                                ->id('rit_horario_entrada')
                                ->helperText('Ej: 8:00 AM para jornada de oficina estándar'),

                            TimePickerField::make('horario_salida')
                                ->label('Hora de salida (lunes a viernes)')
                                ->id('rit_horario_salida')
                                ->helperText('Ej: 5:30 PM (incluye pausa de almuerzo)'),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('¿Tiene turnos o rotaciones?')
                        ->description('Solo si tiene empleados con horarios diferentes: mañana, tarde, noche u operación continua.')
                        ->schema([
                            Forms\Components\Select::make('opera_en_turnos')
                                ->label('¿Opera en múltiples turnos?')
                                ->options([
                                    'no'           => 'No — todos trabajan el mismo horario',
                                    '2_turnos'     => 'Sí — 2 turnos (ej: mañana y tarde)',
                                    '3_turnos'     => 'Sí — 3 turnos (ej: mañana, tarde y noche)',
                                    '4_mas_turnos' => 'Sí — 4 o más turnos',
                                    'continuo_247' => 'Operación continua 24/7 (sin parar)',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\Select::make('rotacion_turnos')
                                ->label('¿Cómo cambia el turno de cada empleado?')
                                ->options([
                                    'turno_fijo'          => 'No rota — cada cargo tiene su turno fijo',
                                    'rotacion_semanal'    => 'Rotación semanal (cambia cada semana)',
                                    'rotacion_quincenal'  => 'Rotación quincenal (cada 15 días)',
                                    'rotacion_mensual'    => 'Rotación mensual',
                                    'por_programacion'    => 'Según programación del jefe',
                                ])
                                ->default('turno_fijo')
                                ->native(false),

                            Forms\Components\Textarea::make('descripcion_turnos')
                                ->label('Describa cada turno: nombre, horario y qué cargos lo tienen')
                                ->rows(3)
                                ->placeholder('Ej: Turno A (operarios planta): 06:00 a 14:00 / Turno B (operarios planta): 14:00 a 22:00 / Turno C (vigilantes): 22:00 a 06:00 / Administrativos: 08:00 a 17:30')
                                ->helperText('Sea específico — el sistema usará esta descripción tal cual en el texto del RIT.')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('cargos_nocturnos')
                                ->label('¿Qué cargos trabajan regularmente de noche (después de las 9 PM)?')
                                ->placeholder('Ej: Vigilantes, operadores planta turno C, conductores de larga distancia')
                                ->helperText('El trabajo nocturno tiene recargo del 35% según el Art. 168 CST.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('¿Trabaja sábados, domingos o festivos?')
                        ->description('Esto determina si el RIT debe incluir los artículos de recargos dominicales y compensatorios.')
                        ->schema([
                            Forms\Components\Select::make('jornada_sabado')
                                ->label('¿Trabaja los sábados?')
                                ->options([
                                    'no'             => 'No',
                                    'media_jornada'  => 'Sí — media jornada (hasta el mediodía)',
                                    'dia_completo'   => 'Sí — día completo',
                                    'algunos_cargos' => 'Sí — solo algunos cargos (operativos, guardia)',
                                    'en_turnos'      => 'Sí — incluido en los turnos rotativos',
                                ])
                                ->default($empresa?->dias_laborales === 'lunes_sabado' ? 'media_jornada' : 'no')
                                ->native(false),

                            Forms\Components\Select::make('trabaja_dominicales')
                                ->label('¿Trabaja domingos o festivos?')
                                ->options([
                                    'no'             => 'No',
                                    'ocasionalmente' => 'Ocasionalmente (con día compensatorio)',
                                    'algunos_cargos' => 'Sí — regularmente, algunos cargos',
                                    'regularmente'   => 'Sí — toda la operación (recargo 75%)',
                                    'en_turnos'      => 'Sí — incluido en los turnos rotativos',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\TextInput::make('cargos_exentos_jornada')
                                ->label('¿Tiene cargos de confianza sin horario fijo? (Gerentes, directores, jefes)')
                                ->placeholder('Ej: Gerente General, Director Financiero, Jefe de Operaciones')
                                ->helperText('Estos cargos están exentos del límite de 8 horas diarias (Art. 162 CST). Déjelo en blanco si no aplica.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Control de asistencia y horas extras')
                        ->description('¿Cómo sabe quién llegó y a qué hora? ¿Qué pasa si alguien trabaja tiempo adicional?')
                        ->schema([
                            Forms\Components\Select::make('control_asistencia')
                                ->label('¿Cómo controla la asistencia?')
                                ->options([
                                    'biometrico'         => 'Reloj biométrico (huella o facial)',
                                    'planilla'           => 'Planilla o registro manual',
                                    'app'                => 'App móvil o sistema digital',
                                    'supervision_rondas' => 'Supervisión directa / rondas',
                                    'sin_control'        => 'Sin sistema formal aún',
                                ])
                                ->default('planilla')
                                ->native(false),

                            Forms\Components\Select::make('politica_horas_extras')
                                ->label('¿Qué hace con las horas extras?')
                                ->options([
                                    'recargo_legal'  => 'Se pagan con el recargo legal (previa autorización escrita)',
                                    'no_autorizadas' => 'No se autorizan — nadie trabaja horas extra',
                                    'compensatorio'  => 'Se compensan con tiempo libre',
                                ])
                                ->default('recargo_legal')
                                ->native(false)
                                ->helperText('La ley exige que las horas extra sean autorizadas por escrito antes de realizarse.'),
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

                    Forms\Components\Placeholder::make('info_paso_salario')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-salario-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('¿Cómo paga el salario?')
                        ->description('El RIT debe especificar la forma y frecuencia de pago. Si diferentes grupos de empleados reciben el pago de forma distinta, puede indicar ambas.')
                        ->schema([
                            Forms\Components\Select::make('forma_pago')
                                ->label('¿Cómo paga el salario?')
                                ->options([
                                    'transferencia' => 'Transferencia bancaria',
                                    'cheque'        => 'Cheque',
                                    'efectivo'      => 'Efectivo',
                                    'mixto'         => 'Mixto (transferencia y efectivo)',
                                ])
                                ->required()
                                ->native(false),

                            Forms\Components\CheckboxList::make('periodicidad_pago')
                                ->label('¿Cada cuánto paga el salario?')
                                ->helperText('Puede seleccionar varias si distintos cargos o grupos tienen diferente frecuencia de pago.')
                                ->options([
                                    'mensual'   => 'Mensual (último día hábil del mes)',
                                    'quincenal' => 'Quincenal (días 15 y último)',
                                    'semanal'   => 'Semanal',
                                    'diario'    => 'Diario / jornaleros',
                                    'destajo'   => 'Por obra o destajo (según producción)',
                                ])
                                ->default(['mensual'])
                                ->columns(3)
                                ->columnSpanFull()
                                ->required(),

                            Forms\Components\Textarea::make('periodicidad_detalle')
                                ->label('Si hay varios grupos con diferente frecuencia, ¿a quiénes paga diferente?')
                                ->rows(2)
                                ->placeholder('Ej: Conductores y operativos: semanal (viernes) / Personal administrativo: quincenal (15 y último día hábil)')
                                ->helperText('Solo si marcó más de una opción arriba.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('¿Da comisiones o bonos?')
                        ->description('Los beneficios que da de forma habitual deben quedar en el RIT para no convertirse en "salario" a efectos legales.')
                        ->schema([
                            Forms\Components\Radio::make('maneja_comisiones')
                                ->label('¿Paga comisiones o bonos a algún empleado?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live(),

                            Forms\Components\Select::make('tipo_comisiones')
                                ->label('¿Qué tipo de comisiones o bonos?')
                                ->options([
                                    'comisiones_ventas' => 'Comisiones de ventas',
                                    'bonos_desempeno'   => 'Bonos por desempeño / cumplimiento de metas',
                                    'ambos'             => 'Ambos',
                                ])
                                ->native(false)
                                ->visible(fn(Get $get) => $get('maneja_comisiones') === 'si'),

                            Forms\Components\Radio::make('tiene_beneficios_extralegales')
                                ->label('¿Da algún beneficio adicional al salario? (auxilio de alimentación, transporte adicional, subsidios...)')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('beneficios_extralegales')
                                ->label('Beneficios adicionales al salario')
                                ->schema([
                                    Forms\Components\TextInput::make('descripcion')
                                        ->label('¿Qué beneficio da?')
                                        ->required()
                                        ->placeholder('Ej: Auxilio de alimentación $150.000/mes, Subsidio de transporte adicional $80.000/mes'),
                                ])
                                ->addActionLabel('Agregar otro beneficio')
                                ->visible(fn(Get $get) => $get('tiene_beneficios_extralegales') === 'si')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Permisos y licencias')
                        ->description('El RIT debe establecer cómo solicitar un permiso y qué permisos especiales otorga la empresa.')
                        ->schema([
                            Forms\Components\Textarea::make('politica_permisos')
                                ->label('¿Cómo solicita un empleado un permiso personal?')
                                ->placeholder('Ej: El trabajador solicita el permiso por escrito con 24 horas de anticipación al jefe inmediato. Los permisos de más de un día requieren aprobación de gerencia...')
                                ->rows(2)
                                ->helperText('Puede escribirlo en sus propias palabras — el sistema lo redactará formalmente.')
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('tiene_licencias_especiales')
                                ->label('¿Da permisos especiales adicionales a los que exige la ley?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull()
                                ->helperText('La ley ya incluye licencia de maternidad, luto, etc. Solo marque "Sí" si da días adicionales.'),

                            Forms\Components\Textarea::make('descripcion_licencias')
                                ->label('¿Cuáles permisos adicionales da?')
                                ->rows(2)
                                ->placeholder('Ej: Licencia de matrimonio 1 día remunerado, Calamidad doméstica 3 días remunerados')
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

                    Forms\Components\Placeholder::make('info_paso_disciplina')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-disciplina-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('¿Qué conductas quiere regular?')
                        ->description('Haga clic en el campo y verá sugerencias comunes — puede agregarlas con un clic o escribir las propias. Presione Enter para añadir cada falta.')
                        ->schema([
                            Forms\Components\TagsInput::make('faltas_leves')
                                ->label('Faltas leves — se sancionan con llamado de atención verbal o escrito')
                                ->suggestions([
                                    'Impuntualidad',
                                    'No registrar asistencia',
                                    'No usar el uniforme',
                                    'Uso de celular en horario laboral',
                                    'Desorden en el puesto de trabajo',
                                ])
                                ->placeholder('Escriba una falta y presione Enter para agregar')
                                ->helperText('Son comportamientos que afectan el trabajo pero no son graves. Ej: llegar tarde, no registrar la entrada, desorden.')
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('faltas_graves')
                                ->label('Faltas graves — pueden llevar a suspensión sin sueldo')
                                ->suggestions([
                                    'Agresión verbal a compañero',
                                    'Ausentismo sin justificación',
                                    'Incumplir normas de seguridad',
                                    'Desobedecer órdenes del superior',
                                    'Daño a bienes de la empresa',
                                ])
                                ->placeholder('Escriba una falta y presione Enter para agregar')
                                ->helperText('Conductas que afectan seriamente el trabajo o el ambiente laboral.')
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('faltas_muy_graves')
                                ->label('Faltas muy graves — pueden llevar a despido con justa causa')
                                ->suggestions([
                                    'Hurto',
                                    'Agresión física',
                                    'Acoso sexual',
                                    'Divulgación de secretos empresariales',
                                    'Presentarse en estado de embriaguez',
                                    'Falsificación de documentos',
                                ])
                                ->placeholder('Escriba una falta y presione Enter para agregar')
                                ->helperText('Las conductas más serias: hurto, violencia física, acoso, presentarse bajo efectos de sustancias.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('¿Qué medidas disciplinarias puede aplicar la empresa?')
                        ->description('Seleccione las sanciones que está dispuesto a usar. El sistema las ordenará de menor a mayor en el texto del RIT. Recuerde: las sanciones deben ser proporcionales a la gravedad de la falta.')
                        ->schema([
                            Forms\Components\CheckboxList::make('sanciones_contempladas')
                                ->label('Medidas disciplinarias contempladas')
                                ->options([
                                    'llamado_verbal'  => 'Llamado de atención verbal',
                                    'llamado_escrito' => 'Llamado de atención escrito',
                                    'suspension_1_8'  => 'Suspensión 1 a 8 días sin sueldo',
                                    'suspension_1_15'  => 'Suspensión 1 a 15 días sin sueldo',
                                    'suspension_1_30' => 'Suspensión 1 a 30 días sin sueldo',
                                    'suspension_1_40' => 'Suspensión 1 a 40 días sin sueldo',
                                    'suspension_1_60' => 'Suspensión 1 a 60 días sin sueldo',
                                    'terminacion'     => 'Terminación del contrato con justa causa',
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

                    Forms\Components\Placeholder::make('info_paso_sst')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-sst-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Seguridad y Salud en el Trabajo (SG-SST)')
                        ->description('El Ministerio de Trabajo verifica que el RIT incluya el SG-SST. No importa si está en proceso — lo importante es que quede documentado.')
                        ->schema([
                            Forms\Components\Select::make('tiene_sg_sst')
                                ->label('¿Su empresa tiene el Sistema de Gestión de Seguridad y Salud en el Trabajo (SG-SST)?')
                                ->options([
                                    'implementado' => 'Sí, está implementado y en funcionamiento',
                                    'en_proceso'   => 'Estamos en proceso de implementarlo',
                                    'no'           => 'No, aún no lo tenemos',
                                ])
                                ->default('en_proceso')
                                ->native(false)
                                ->columnSpanFull(),

                            Forms\Components\CheckboxList::make('riesgos_principales')
                                ->label('¿Cuáles son los principales riesgos en su empresa? (seleccione todos los que aplican)')
                                ->options([
                                    'ergonomico'  => 'Ergonómico (computadores, posturas, levantamiento de cargas)',
                                    'psicosocial' => 'Psicosocial (estrés laboral, turnos nocturnos, atención al público)',
                                    'mecanico'    => 'Mecánico (maquinaria, herramientas, vehículos)',
                                    'electrico'   => 'Eléctrico (instalaciones, equipos de alta tensión)',
                                    'publico'     => 'Público (robo, violencia en atención al cliente)',
                                    'otro'        => 'Otro riesgo específico de mi empresa',
                                ])
                                ->default(['ergonomico'])
                                ->live()
                                ->columns(2),

                            Forms\Components\TextInput::make('riesgos_otros')
                                ->label('¿Cuál es ese otro riesgo?')
                                ->placeholder('Ej: Riesgo químico por manejo de solventes, riesgo de alturas en construcción')
                                ->visible(fn(Get $get) => in_array('otro', (array) $get('riesgos_principales'))),

                            Forms\Components\Radio::make('tiene_epp')
                                ->label('¿Sus trabajadores necesitan elementos de protección personal? (casco, guantes, gafas, botas...)')
                                ->options([
                                    'no' => 'No aplica — trabajo de oficina o bajo riesgo',
                                    'si' => 'Sí, se requieren elementos de protección',
                                ])
                                ->default('no')
                                ->inline()
                                ->live(),

                            Forms\Components\TextInput::make('epp_descripcion')
                                ->label('¿Qué elementos de protección usan?')
                                ->placeholder('Ej: Casco, guantes de trabajo, botas de seguridad punta de acero, gafas industriales')
                                ->visible(fn(Get $get) => $get('tiene_epp') === 'si'),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Normas de convivencia y uso de recursos')
                        ->description('Estas reglas previenen conflictos cotidianos y establecen expectativas claras desde el primer día de trabajo.')
                        ->schema([
                            Forms\Components\Select::make('politica_celular')
                                ->label('¿Pueden los empleados usar el celular personal durante el trabajo?')
                                ->options([
                                    'libre'     => 'Sí, libre uso',
                                    'descansos' => 'Solo en descansos y pausas',
                                    'prohibido' => 'No — prohibido salvo emergencias',
                                ])
                                ->default('descansos')
                                ->native(false),

                            Forms\Components\Radio::make('usa_uniforme')
                                ->label('¿La empresa entrega uniforme o dotación?')
                                ->options([
                                    'no'              => 'No',
                                    'uniforme'        => 'Sí — uniforme completo',
                                    'dotacion_basica' => 'Sí — dotación básica (zapatos, ropa de trabajo)',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\Radio::make('tiene_codigo_etica')
                                ->label('¿Tiene algún manual de ética, código de conducta o valores de empresa?')
                                ->options([
                                    'si'              => 'Sí',
                                    'no'              => 'No',
                                    'en_construccion' => 'Lo estamos construyendo',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\Select::make('politica_confidencialidad')
                                ->label('¿Exige confidencialidad o reserva de información a sus empleados?')
                                ->options([
                                    'por_contrato' => 'Sí — está en el contrato de trabajo',
                                    'solo_verbal'  => 'Solo lo mencionamos verbalmente',
                                    'no'           => 'No aplica a nuestra empresa',
                                ])
                                ->default('no')
                                ->native(false),

                            Forms\Components\Textarea::make('que_quiere_prevenir')
                                ->label('¿Qué situaciones problemáticas quiere evitar principalmente? (opcional)')
                                ->rows(2)
                                ->placeholder('Ej: Impuntualidad crónica, conflictos entre compañeros, uso indebido de información de clientes, manejo inapropiado de efectivo')
                                ->helperText('Escríbalo en sus propias palabras. Esto ayuda a personalizar el capítulo de conductas prohibidas.')
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

                    Forms\Components\Placeholder::make('info_paso_generar')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.rit-step-generar-info')->render()
                        ))
                        ->columnSpanFull(),

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
