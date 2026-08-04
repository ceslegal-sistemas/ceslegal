<?php

namespace App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;

use App\Filament\Admin\Resources\ReglamentoInternoResource;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SancionLaboral;
use App\Jobs\GenerarTextoRITJob;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;
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

    // Custom view adds novalidate to the <form> element at server-render time,
    // preventing Mac browsers from triggering native HTML5 validation.
    protected static string $view = 'filament.admin.resources.reglamento-internos.pages.create-reglamento-interno';

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

            // Garantizar siempre los datos de la empresa (Step 1 - campos disabled)
            $saved['razon_social']    = $empresa->razon_social ?? '';
            $saved['tipo_societario'] = $empresa->tipo_societario ?? '';
            $saved['nit']             = $empresa->nit ?? '';
            $saved['domicilio']    = trim(
                ($empresa->direccion ?? '') . ' ' .
                    ($empresa->ciudad ?? '') . ', ' .
                    ($empresa->departamento ?? '')
            );

            // Si no hay IDs guardados en el cuestionario, tomarlos de la empresa
            if (empty($saved['actividad_economica_id']) && $empresa->actividad_economica_id) {
                $saved['actividad_economica_id'] = $empresa->actividad_economica_id;
            }
            // Igual para el número de empleados: si el cuestionario aún no lo tiene
            // guardado, usar el que ya se capturó en el registro/edición de la empresa
            // - evita pedir el mismo dato dos veces. NOTA: el ->default() del campo en
            // getSteps() NO alcanza a aplicar aquí porque este fillForm() personalizado
            // siempre llena el formulario explícitamente (ver docblock del método), así
            // que el fallback debe vivir en este array $saved, igual que arriba.
            if (empty($saved['num_trabajadores']) && $empresa->numero_empleados) {
                $saved['num_trabajadores'] = $empresa->numero_empleados;
            }
            if (empty($saved['actividades_secundarias_ids'])) {
                $ids = $empresa->actividadesSecundarias()->pluck('actividades_economicas.id')->toArray();
                if (!empty($ids)) {
                    $saved['actividades_secundarias_ids'] = $ids;
                }
            }

            // Garantizar al menos 1 ítem en el repeater de cargos
            if (empty($saved['cargos'])) {
                $saved['cargos'] = [['nombre_cargo' => '', 'instancia_sancionatoria' => 'ninguna']];
            }

            // Las conductas ya NO se precargan del catálogo estático: se generan con IA
            // desde el botón "Generar conductas con IA" del régimen disciplinario.
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
        // Normalizar cargos del Repeater
        if (isset($data['cargos']) && is_array($data['cargos'])) {
            $data['cargos'] = array_map(function ($item) {
                // Migración: campo antiguo puede_sancionar (Toggle) → instancia_sancionatoria (Select)
                if (isset($item['puede_sancionar']) && !isset($item['instancia_sancionatoria'])) {
                    $item['instancia_sancionatoria'] = $item['puede_sancionar'] ? 'primera_instancia' : 'ninguna';
                    unset($item['puede_sancionar']);
                }
                return $item;
            }, $data['cargos']);
        }

        return $data;
    }

    /**
     * Compone el texto legible 'periodicidad_detalle' (el que consume la generación
     * del RIT) a partir de las filas cargo+periodicidad elegidas en el wizard.
     */
    public static function componerPeriodicidadDetalle($filas): string
    {
        return collect($filas ?? [])
            ->filter(fn($f) => ! empty($f['cargo']) && ! empty($f['periodicidad']))
            ->map(fn($f) => $f['cargo'] . ': ' . ucfirst((string) $f['periodicidad']))
            ->implode(' / ');
    }

    /**
     * A partir de la jornada PERSONALIZADA (turnos con días + horario), deriva los
     * días hábiles y sintetiza los campos que consume la generación del RIT (horario,
     * turnos, nocturnos, sábados/dominicales), para no romper el prompt al haber
     * reemplazado las antiguas preguntas de horario/turnos/sábados por el repeater.
     */
    public static function sintetizarJornada(array $data): array
    {
        $rows = is_array($data['jornada_personalizada'] ?? null) ? $data['jornada_personalizada'] : [];

        // Días hábiles = unión de los días de todos los turnos.
        $dias = [];
        foreach ($rows as $r) {
            foreach ((array) ($r['dias'] ?? []) as $d) {
                if (is_numeric($d)) {
                    $dias[(int) $d] = true;
                }
            }
        }
        $diasSet = array_keys($dias);
        sort($diasSet);

        // Respaldo si no hay turnos: 24/7 si la modalidad lo indica; si no, Lunes a Viernes.
        if (empty($diasSet)) {
            $mod = (array) ($data['modalidades_jornada'] ?? []);
            $diasSet = in_array('operacion_continua_247', $mod, true)
                ? [1, 2, 3, 4, 5, 6, 7]
                : \App\Support\DiasHabiles::DEFECTO;
        }

        $out = ['dias_habiles' => $diasSet];

        if (!empty($rows)) {
            $primero = $rows[0];
            $out['horario_entrada'] = $primero['hora_inicio'] ?? '';
            $out['horario_salida']  = $primero['hora_fin'] ?? '';
            $out['opera_en_turnos'] = count($rows) > 1 ? 'Sí (' . count($rows) . ' turnos)' : 'No';

            // Cargos con trabajo nocturno (turnos que inician >=21h o terminan hasta las 6h).
            $nocturnos = [];
            foreach ($rows as $r) {
                $ini = (int) substr((string) ($r['hora_inicio'] ?? ''), 0, 2);
                $fin = (int) substr((string) ($r['hora_fin'] ?? ''), 0, 2);
                if (($ini >= 21 || $ini < 6 || ($fin > 0 && $fin <= 6)) && !empty($r['cargos'])) {
                    $nocturnos[] = $r['cargos'];
                }
            }
            $out['cargos_nocturnos'] = implode(', ', array_filter($nocturnos));

            // Resumen textual de los turnos (para prompt/documento).
            $out['descripcion_turnos'] = collect($rows)->map(function ($r) {
                $nom = trim((string) ($r['nombre'] ?? '')) ?: 'Turno';
                $d   = collect((array) ($r['dias'] ?? []))
                    ->map(fn($x) => \App\Support\DiasHabiles::LABELS[(int) $x] ?? '')
                    ->filter()->join(', ');
                return "{$nom}: {$d} de " . ($r['hora_inicio'] ?? '?') . ' a ' . ($r['hora_fin'] ?? '?')
                    . (!empty($r['cargos']) ? " ({$r['cargos']})" : '');
            })->join(' | ');
        }

        // Sábados / domingos según los días marcados (para los recargos del RIT).
        $out['trabaja_sabados']     = in_array(6, $diasSet, true) ? 'dia_completo' : 'no';
        $out['jornada_sabado']      = $out['trabaja_sabados'];
        $out['trabaja_dominicales'] = in_array(7, $diasSet, true) ? 'regularmente' : 'no';

        return $out;
    }

    /**
     * Infiere los riesgos SST probables a partir de la(s) actividad(es)
     * económica(s) seleccionada(s): primero por sección CIIU (A–U) y luego
     * con un refuerzo por palabras clave del nombre de la actividad.
     * No incluye 'otro'. Devuelve claves del CheckboxList de riesgos.
     */
    protected function riesgosSugeridos(Get $get): array
    {
        $ids = array_filter(array_merge(
            [$get('actividad_economica_id')],
            (array) $get('actividades_secundarias_ids'),
        ));

        if (empty($ids)) {
            return [];
        }

        // Riesgos típicos por sección CIIU Rev. 4 (DANE).
        $porSeccion = [
            'A' => ['ergonomico', 'mecanico', 'quimico', 'biologico', 'fisico', 'vial'],
            'B' => ['mecanico', 'quimico', 'fisico', 'alturas', 'locativo', 'ergonomico'],
            'C' => ['mecanico', 'ergonomico', 'quimico', 'fisico', 'electrico', 'locativo'],
            'D' => ['electrico', 'mecanico', 'alturas', 'fisico'],
            'E' => ['biologico', 'quimico', 'mecanico', 'ergonomico'],
            'F' => ['alturas', 'mecanico', 'locativo', 'ergonomico', 'fisico', 'electrico'],
            'G' => ['ergonomico', 'publico', 'locativo', 'vial'],
            'H' => ['vial', 'ergonomico', 'mecanico', 'psicosocial'],
            'I' => ['biologico', 'ergonomico', 'locativo', 'publico', 'fisico'],
            'J' => ['psicosocial', 'ergonomico'],
            'K' => ['psicosocial', 'ergonomico', 'publico'],
            'L' => ['psicosocial', 'ergonomico'],
            'M' => ['psicosocial', 'ergonomico'],
            'N' => ['ergonomico', 'psicosocial', 'publico', 'biologico'],
            'O' => ['psicosocial', 'ergonomico', 'publico'],
            'P' => ['psicosocial', 'ergonomico', 'publico'],
            'Q' => ['biologico', 'psicosocial', 'ergonomico', 'quimico'],
            'R' => ['psicosocial', 'publico', 'ergonomico', 'fisico'],
            'S' => ['ergonomico', 'publico', 'quimico'],
            'T' => ['ergonomico', 'biologico', 'locativo'],
            'U' => ['psicosocial', 'ergonomico'],
        ];

        $rows = ActividadEconomica::whereIn('id', $ids)->get(['seccion', 'nombre']);
        $riesgos = [];

        foreach ($rows as $row) {
            $sec = strtoupper(trim((string) $row->seccion));
            $riesgos = array_merge($riesgos, $porSeccion[$sec] ?? []);
            $riesgos = array_merge($riesgos, $this->riesgosPorPalabras($row->nombre));
        }

        // Si no se reconoció nada, asumir perfil de oficina / bajo riesgo.
        if (empty($riesgos)) {
            $riesgos = ['ergonomico', 'psicosocial'];
        }

        return array_values(array_unique($riesgos));
    }

    /**
     * Refuerzo por palabras clave del nombre de la actividad (sin tildes).
     */
    protected function riesgosPorPalabras(?string $nombre): array
    {
        $n = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $nombre));

        $reglas = [
            ['kw' => ['constru', 'obra', 'andamio', 'altura', 'techo'],                          'r' => ['alturas', 'mecanico', 'locativo']],
            ['kw' => ['transport', 'conduc', 'vehicul', 'logistic', 'mensajer', 'domicil', 'taxi', 'carga'], 'r' => ['vial']],
            ['kw' => ['aliment', 'restaurant', 'comida', 'carnic', 'panad', 'agro', 'agricol', 'pecuar', 'ganad', 'pesca', 'cultivo'], 'r' => ['biologico']],
            ['kw' => ['quimic', 'solvent', 'pintura', 'plagui', 'fumig', 'plastic'],             'r' => ['quimico']],
            ['kw' => ['salud', 'hospital', 'clinic', 'medic', 'odontolog', 'veterinar', 'laboratorio'], 'r' => ['biologico', 'quimico']],
            ['kw' => ['electric', 'energia', 'subestac'],                                         'r' => ['electrico']],
            ['kw' => ['mina', 'mineria', 'cantera', 'extracci', 'carbon'],                        'r' => ['mecanico', 'quimico', 'fisico']],
            ['kw' => ['segurid', 'vigilanc', 'custodia'],                                         'r' => ['publico']],
            ['kw' => ['limpieza', 'aseo', 'residuo', 'reciclaj', 'desech'],                       'r' => ['biologico', 'quimico']],
        ];

        $out = [];
        foreach ($reglas as $regla) {
            foreach ($regla['kw'] as $kw) {
                if (str_contains($n, $kw)) {
                    $out = array_merge($out, $regla['r']);
                    break;
                }
            }
        }

        return $out;
    }

    protected function getSteps(): array
    {
        $empresa = $this->getEmpresa();

        return [

            // ─────────────────────────────────────────────────────────────────
            // STEP 0: Bienvenida
            // ─────────────────────────────────────────────────────────────────
            Step::make('bienvenida')
                ->label('Bienvenida')
                ->description('Lea antes de empezar')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\View::make('filament.components.bienvenida-rit')
                        ->key('rit_bienvenida_contenido')
                        ->columnSpanFull(),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 1: Empresa y Actividad Económica
            // ─────────────────────────────────────────────────────────────────
            Step::make('empresa')
                ->label('Empresa')
                ->description('Datos generales')
                ->icon('heroicon-o-building-office-2')
                ->schema([

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_empresa')
                        ->viewData([
                            'step' => 1,
                            'total' => 7,
                            'title' => 'Empresa',
                            'accent' => '#f97316',
                            'lord' => 'https://cdn.lordicon.com/moedrfvp.json',
                            'subtitle' => 'Datos generales de su empresa y su actividad económica.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_empresa')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-empresa-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('Datos de su empresa')
                        ->description('Estos datos vienen de su registro y aparecerán en el encabezado oficial del Reglamento.')
                        ->schema([
                            Forms\Components\TextInput::make('razon_social')
                                ->label('Razón social')
                                ->default($empresa?->razon_social ?? '')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('tipo_societario')
                                ->label('Tipo societario')
                                ->default($empresa?->tipo_societario ?? '')
                                ->disabled()
                                ->dehydrated(false),

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
                                ->dehydrated(false)
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Section::make('¿A qué se dedica su empresa?')
                        ->description('La actividad económica define los riesgos laborales específicos que el RIT debe cubrir. Si no sabe el código, busque por nombre (ej: "servicios", "construcción").')
                        ->schema([
                            Forms\Components\Select::make('actividad_economica_id')
                                ->label('Actividad económica principal')
                                ->searchable()
                                ->getSearchResultsUsing(
                                    fn(string $search) =>
                                    ActividadEconomica::query()
                                        ->where('activo', true)
                                        ->where(
                                            fn($q) => $q
                                                ->where('codigo', 'like', "%{$search}%")
                                                ->orWhere('nombre', 'like', "%{$search}%")
                                        )
                                        ->orderBy('codigo')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} - {$a->nombre}"])
                                        ->all()
                                )
                                ->getOptionLabelUsing(
                                    fn($value) => ($a = ActividadEconomica::find($value))
                                        ? "{$a->codigo} - {$a->nombre}"
                                        : $value
                                )
                                ->default($empresa?->actividad_economica_id)
                                ->nullable()
                                ->placeholder('Buscar por código CIIU o nombre...')
                                ->helperText('Actividad principal según el RUT. Ej: 4711 - Comercio al por menor en establecimientos no especializados')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('actividades_secundarias_ids')
                                ->label('¿Tiene otras actividades secundarias?')
                                ->searchable()
                                ->multiple()
                                ->getSearchResultsUsing(
                                    fn(string $search) =>
                                    ActividadEconomica::query()
                                        ->where('activo', true)
                                        ->where(
                                            fn($q) => $q
                                                ->where('codigo', 'like', "%{$search}%")
                                                ->orWhere('nombre', 'like', "%{$search}%")
                                        )
                                        ->orderBy('codigo')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} - {$a->nombre}"])
                                        ->all()
                                )
                                ->getOptionLabelUsing(
                                    fn($value) => ($a = ActividadEconomica::find($value))
                                        ? "{$a->codigo} - {$a->nombre}"
                                        : $value
                                )
                                ->getOptionLabelsUsing(
                                    fn(array $values) =>
                                    ActividadEconomica::whereIn('id', $values)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} - {$a->nombre}"])
                                        ->all()
                                )
                                ->default($empresa?->actividadesSecundarias?->pluck('id')->toArray() ?? [])
                                ->nullable()
                                ->placeholder('Buscar actividades adicionales')
                                ->helperText('Solo si su empresa realiza actividades muy diferentes entre sí.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Tamaño y sedes')
                        ->schema([
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('¿Cuántos empleados tiene actualmente?')
                                ->numeric()
                                ->integer()
                                ->extraInputAttributes(['min' => 1, 'onkeydown' => "return event.key !== '-'"])
                                ->minValue(1)
                                ->default($empresa?->numero_empleados)
                                ->placeholder('Ej: 15')
                                ->hint('¿Es obligatorio el RIT?')
                                ->hintColor('info')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'El RIT es obligatorio para empresas con más de 5 trabajadores en lo comercial, más de 10 en lo industrial y más de 20 en lo agrícola, ganadero o forestal (Art. 105 CST).')
                                ->helperText('Cuente todos los trabajadores, incluyendo los de tiempo parcial.'),

                            Forms\Components\ToggleButtons::make('tiene_sucursales')
                                ->label('¿Tiene sucursales o sedes en otras ciudades?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                // ->colors(['no' => 'primary', 'si' => 'success'])
                                ->live(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Repeater::make('sucursales')
                        ->label('Sucursales / Sedes adicionales')
                        ->schema([
                            Forms\Components\TextInput::make('ciudad')
                                ->label('Ciudad')
                                ->placeholder('Ej: Medellín'),
                            Forms\Components\TextInput::make('direccion')
                                ->label('Dirección')
                                ->placeholder('Ej: Calle 50 # 40-20'),
                            Forms\Components\TextInput::make('num_trabajadores')
                                ->label('N.° trabajadores en esa sede')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->extraInputAttributes(['min' => 0, 'onkeydown' => "return event.key !== '-'"])
                                ->placeholder('Ej: 5'),
                        ])
                        ->columns(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->addActionLabel('Agregar otra sede')
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

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_estructura')
                        ->viewData([
                            'step' => 2,
                            'total' => 7,
                            'title' => 'Estructura',
                            'accent' => '#34d399',
                            'lord' => 'https://cdn.lordicon.com/jdgfsfzr.json',
                            'subtitle' => 'Cargos, tipos de contrato y relaciones colectivas.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_estructura')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-estructura-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('¿Qué cargos existen en su empresa?')
                        ->description('Liste cada cargo que existe. Puede ser Gerente, Operario, Vendedor, Auxiliar... el nombre que use internamente. Solo marque "puede sancionar" en los que realmente toman decisiones disciplinarias.')
                        ->schema([
                            Forms\Components\Repeater::make('cargos')
                                ->label('')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre_cargo')
                                        ->label('Nombre del cargo')
                                        ->placeholder('Ej: Gerente General, Operario planta, Vendedor externo'),
                                    Forms\Components\Select::make('instancia_sancionatoria')
                                        ->label('Rol disciplinario')
                                        ->options([
                                            'ninguna'           => 'Sin facultad disciplinaria',
                                            'primera_instancia' => 'Primera instancia (impone la sanción)',
                                            'segunda_instancia' => 'Segunda instancia (confirma o revoca apelaciones)',
                                        ])
                                        ->searchable()
                                        ->reactive()
                                        ->default('ninguna')
                                        ->helperText('Solo los cargos con autoridad real deben tener esta facultad.'),
                                ])
                                ->columns(['default' => 1, 'sm' => 2])
                                ->addActionLabel('Agregar otro cargo')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Contratos y documentación')
                        ->description('Esto determina qué cláusulas aplican en el Reglamento para cada tipo de empleado.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('tiene_manual_funciones')
                                ->label('¿Tiene escrito qué hace cada cargo? (manual de funciones)')
                                ->options([
                                    'si'              => 'Sí, tenemos manual de funciones',
                                    'no'              => 'No',
                                    'en_construccion' => 'Lo estamos construyendo',
                                ])
                                ->colors([
                                    'si' => 'success',
                                    'no' => 'danger',
                                    'en_construccion' => 'warning',
                                ])
                                ->default('no')
                                ->live()
                                ->inline()
                                // ->native(false)
                                ->helperText('No es obligatorio para el RIT, pero es buena práctica tenerlo.'),

                            // Forms\Components\CheckboxList::make('tipos_contrato')
                            //     ->label('¿Qué tipos de contrato usa con sus empleados?')
                            //     ->options([
                            //         'indefinido'  => 'Término indefinido (sin fecha de fin)',
                            //         'fijo'        => 'Término fijo (con fecha de vencimiento)',
                            //         'obra_labor'  => 'Obra o labor (hasta terminar el proyecto)',
                            //         'aprendizaje' => 'Aprendizaje SENA',
                            //     ])
                            //     ->default(['indefinido'])
                            //     ->columns(['default' => 1, 'sm' => 2])
                            //     ->helperText('Seleccione todos los que usa actualmente.'),

                            Forms\Components\ToggleButtons::make('tipos_contrato')
                                ->label('¿Qué tipos de contrato usa con sus empleados?')
                                ->options([
                                    'indefinido'  => 'Término indefinido (sin fecha de fin)',
                                    'fijo'        => 'Término fijo (con fecha de vencimiento)',
                                    'obra_labor'  => 'Obra o labor (hasta terminar el proyecto)',
                                    'aprendizaje' => 'Aprendizaje SENA',
                                ])
                                ->icons([
                                    'indefinido'  => 'heroicon-o-briefcase',
                                    'fijo'        => 'heroicon-o-calendar',
                                    'obra_labor'  => 'heroicon-o-cube-transparent',
                                    'aprendizaje' => 'heroicon-o-academic-cap',
                                ])
                                ->colors([
                                    'indefinido'  => 'success',
                                    'fijo'        => 'primary',
                                    'obra_labor'  => 'warning',
                                    'aprendizaje' => 'info',
                                ])
                                ->multiple()
                                ->default([])
                                ->live()
                                ->columns(['default' => 1, 'sm' => 2])
                                ->inline()
                                ->helperText('Seleccione todos los que usa actualmente en la empresa.'),

                            Forms\Components\TextInput::make('num_aprendices_sena')
                                ->label('¿Cuántos aprendices SENA tiene actualmente?')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->extraInputAttributes(['min' => 0, 'onkeydown' => "return event.key !== '-'"])
                                ->default(0)
                                ->placeholder('0')
                                ->helperText('Si tiene contrato de aprendizaje, indique cuántos. Si no tiene, deje en 0.')
                                ->visible(fn(Get $get) => in_array('aprendizaje', (array) $get('tipos_contrato'))),

                            Forms\Components\ToggleButtons::make('tiene_trabajadores_mision')
                                ->label('¿Tiene temporales o trabajadores de una empresa de servicios?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->helperText('Ej: Personal enviado por una temporal o empresa de outsourcing.'),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('Relaciones colectivas de trabajo')
                        ->description('Esta información define si el RIT debe incluir cláusulas sobre convención o pacto colectivo.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('tiene_sindicato')
                                ->label('¿Existe sindicato en la empresa?')
                                ->options([
                                    'no'           => 'No',
                                    'si'           => 'Sí',
                                    'en_formacion' => 'En proceso de formación',
                                ])
                                ->colors([
                                    'no' => 'primary',
                                    'si' => 'success',
                                    'en_formacion' => 'warning',
                                ])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nombre_sindicato')
                                ->label('Nombre del sindicato')
                                ->placeholder('Ej: SINTRAINDUCON, Sindicato de Trabajadores de la Construcción')
                                ->visible(fn(Get $get) => $get('tiene_sindicato') === 'si')
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('tiene_convencion_colectiva')
                                ->label('¿Tiene convención colectiva vigente?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->helperText('Acuerdo negociado con el sindicato que mejora condiciones de trabajo.'),

                            Forms\Components\ToggleButtons::make('tiene_pacto_colectivo')
                                ->label('¿Tiene pacto colectivo con trabajadores no sindicalizados?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->helperText('Acuerdo directo con trabajadores no afiliados al sindicato.'),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 3: Jornada Laboral
            // ─────────────────────────────────────────────────────────────────
            Step::make('jornada')
                ->label('Jornada')
                ->description('Horarios y turnos')
                ->icon('heroicon-o-clock')
                ->schema([

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_jornada')
                        ->viewData([
                            'step' => 3,
                            'total' => 7,
                            'title' => 'Jornada',
                            'accent' => '#c9a84c',
                            'lord' => 'https://cdn.lordicon.com/uphbloed.json',
                            'subtitle' => 'Horarios, turnos, dominicales y control de asistencia.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_jornada')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-jornada-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('¿Cómo trabajan sus empleados?')
                        ->description('Seleccione todo lo que aplique a su empresa. Si es oficina pura, solo marque "Jornada fija diurna".')
                        ->schema([
                            Forms\Components\ToggleButtons::make('modalidades_jornada')
                                ->label('Tipos de jornada en su empresa')
                                ->options([
                                    'jornada_fija_diurna'     => 'Jornada fija diurna (oficina, lunes a viernes)',
                                    'turnos_rotativos'        => 'Turnos rotativos (el empleado cambia entre día/noche)',
                                    'turno_nocturno_regular'  => 'Turno nocturno fijo (siempre de noche)',
                                    'operacion_continua_247'  => 'Operación continua 24/7 (nunca para)',
                                    'jornada_flexible'        => 'Horario flexible o variable',
                                    'teletrabajo'             => 'Teletrabajo / trabajo desde casa',
                                    'vigilancia_guardias'     => 'Vigilancia / guardias de seguridad',
                                    'personalizado'           => 'Personalizado (defina días y horarios)',
                                ])
                                ->colors([
                                    'jornada_fija_diurna'     => 'primary',
                                    'turnos_rotativos'        => 'warning',
                                    'turno_nocturno_regular'  => 'gray',
                                    'operacion_continua_247'  => 'danger',
                                    'jornada_flexible'        => 'success',
                                    'teletrabajo'             => 'info',
                                    'vigilancia_guardias'     => 'gray',
                                    'personalizado'           => 'primary',
                                ])
                                ->icons([
                                    'jornada_fija_diurna'     => 'heroicon-o-building-office',
                                    'turnos_rotativos'        => 'heroicon-o-arrows-right-left',
                                    'turno_nocturno_regular'  => 'heroicon-o-moon',
                                    'operacion_continua_247'  => 'heroicon-o-bolt',
                                    'jornada_flexible'        => 'heroicon-o-adjustments-horizontal',
                                    'teletrabajo'             => 'heroicon-o-computer-desktop',
                                    'vigilancia_guardias'     => 'heroicon-o-shield-check',
                                    'personalizado'           => 'heroicon-o-clock',
                                ])
                                ->live()
                                ->multiple()
                                ->default([])
                                ->hint('Límite legal de jornada')
                                ->hintColor('info')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'La jornada máxima ordinaria se reduce gradualmente a 42 horas semanales, sin disminuir el salario ni los derechos del trabajador (Ley 2101 de 2021).')
                                ->columns(['default' => 1, 'sm' => 2])
                                ->columnSpanFull(),

                            // Jornada PERSONALIZADA: define día(s) + horario por turno. Reemplaza
                            // las antiguas preguntas de horario, turnos y sábados/domingos. Los días
                            // marcados aquí determinan los días hábiles para los plazos.
                            Forms\Components\Repeater::make('jornada_personalizada')
                                ->label('Turnos y horarios de la empresa')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Nombre del turno (opcional)')
                                        ->placeholder('Ej: Administrativo, Turno noche')
                                        ->columnSpanFull(),

                                    Forms\Components\CheckboxList::make('dias')
                                        ->label('Días')
                                        ->options(\App\Support\DiasHabiles::opciones())
                                        ->columns(['default' => 2, 'sm' => 4])
                                        ->required()
                                        ->columnSpanFull(),

                                    TimePickerField::make('hora_inicio')
                                        ->label('Hora de inicio')
                                        ->okLabel('Aceptar')
                                        ->cancelLabel('Cancelar'),

                                    TimePickerField::make('hora_fin')
                                        ->label('Hora de fin')
                                        ->okLabel('Aceptar')
                                        ->cancelLabel('Cancelar'),

                                    Forms\Components\TextInput::make('cargos')
                                        ->label('Cargos en este turno (opcional)')
                                        ->placeholder('Ej: Operarios, Vigilantes')
                                        ->columnSpanFull(),
                                ])
                                ->columns(['default' => 1, 'sm' => 2])
                                ->addActionLabel('Agregar turno')
                                ->defaultItems(1)
                                ->minItems(1)
                                ->reorderable(false)
                                ->required(fn(Get $get) => in_array('personalizado', (array) $get('modalidades_jornada'), true))
                                ->visible(fn(Get $get) => in_array('personalizado', (array) $get('modalidades_jornada'), true))
                                ->validationMessages(['required' => 'Agregue al menos un turno con sus días y horario.', 'min' => 'Agregue al menos un turno.'])
                                ->helperText('Agregue un turno por cada horario distinto. Los días marcados determinan los días hábiles de la empresa (para descargos y términos legales).')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('cargos_exentos_jornada')
                                ->label('¿Tiene cargos de confianza sin horario fijo? (Gerentes, directores, jefes)')
                                ->placeholder('Ej: Gerente General, Director Financiero, Jefe de Operaciones')
                                ->helperText('Estos cargos están exentos del límite de 8 horas diarias (Art. 162 CST). Déjelo en blanco si no aplica.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Control de asistencia y horas extras')
                        ->description('¿Cómo sabe quién llegó y a qué hora? ¿Qué pasa si alguien trabaja tiempo adicional?')
                        ->schema([
                            Forms\Components\ToggleButtons::make('control_asistencia')
                                ->label('¿Cómo controla la asistencia?')
                                ->options([
                                    'biometrico'         => 'Reloj biométrico',
                                    'planilla'           => 'Planilla / manual',
                                    'app'                => 'App o sistema digital',
                                    'supervision_rondas' => 'Supervisión / rondas',
                                    'sin_control'        => 'Sin sistema formal aún',
                                ])
                                ->icons([
                                    'biometrico'         => 'heroicon-o-finger-print',
                                    'planilla'           => 'heroicon-o-clipboard-document-list',
                                    'app'                => 'heroicon-o-device-phone-mobile',
                                    'supervision_rondas' => 'heroicon-o-eye',
                                    'sin_control'        => 'heroicon-o-exclamation-triangle',
                                ])
                                ->colors([
                                    'biometrico'         => 'success',
                                    'planilla'           => 'gray',
                                    'app'                => 'info',
                                    'supervision_rondas' => 'warning',
                                    'sin_control'        => 'danger',
                                ])
                                ->inline()
                                ->default('planilla')
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('politica_horas_extras')
                                ->label('¿Qué hace con las horas extras?')
                                ->options([
                                    'recargo_legal'  => 'Se pagan con recargo legal',
                                    'no_autorizadas' => 'No se autorizan',
                                    'compensatorio'  => 'Se compensan con tiempo libre',
                                ])
                                ->icons([
                                    'recargo_legal'  => 'heroicon-o-banknotes',
                                    'no_autorizadas' => 'heroicon-o-no-symbol',
                                    'compensatorio'  => 'heroicon-o-clock',
                                ])
                                ->colors([
                                    'recargo_legal'  => 'success',
                                    'no_autorizadas' => 'danger',
                                    'compensatorio'  => 'info',
                                ])
                                ->inline()
                                ->default('recargo_legal')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'Máximo 2 horas extra al día y 12 a la semana. El recargo es del 25% sobre la hora ordinaria si son diurnas y del 75% si son nocturnas (Arts. 22 Ley 50 de 1990 y 168 CST).')
                                ->helperText('La ley exige que las horas extra sean autorizadas por escrito antes de realizarse.')
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 4: Salario y Beneficios
            // ─────────────────────────────────────────────────────────────────
            Step::make('salario')
                ->label('Salario')
                ->description('Pago y beneficios')
                ->icon('heroicon-o-banknotes')
                ->schema([

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_salario')
                        ->viewData([
                            'step' => 4,
                            'total' => 7,
                            'title' => 'Salario',
                            'accent' => '#e11d48',
                            'lord' => 'https://cdn.lordicon.com/hmpomorl.json',
                            'subtitle' => 'Forma de pago, beneficios, permisos e incapacidades.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_salario')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-salario-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('¿Cómo paga el salario?')
                        ->description('El RIT debe especificar la forma y frecuencia de pago. Si diferentes grupos de empleados reciben el pago de forma distinta, puede indicar ambas.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('forma_pago')
                                ->label('¿Cómo paga el salario?')
                                ->options([
                                    'transferencia' => 'Transferencia bancaria',
                                    'cheque'        => 'Cheque',
                                    'efectivo'      => 'Efectivo',
                                    'mixto'         => 'Mixto (transferencia y efectivo)',
                                ])
                                ->icons([
                                    'transferencia' => 'heroicon-o-building-library',
                                    'cheque'        => 'heroicon-o-document-text',
                                    'efectivo'      => 'heroicon-o-banknotes',
                                    'mixto'         => 'heroicon-o-arrows-right-left',
                                ])
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('periodicidad_pago')
                                ->label('¿Cada cuánto paga el salario?')
                                ->helperText('Puede seleccionar varias si distintos cargos o grupos tienen diferente frecuencia de pago.')
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
                                ->live()
                                ->default([])
                                ->multiple()
                                ->columns(['default' => 1, 'sm' => 2, 'md' => 3])
                                ->columnSpanFull()
                                ->live(),

                            // Muestra los cargos ya registrados en el wizard para asignarles
                            // su periodicidad (antes era texto libre). Se compone el texto
                            // 'periodicidad_detalle' que consume la generación del RIT.
                            Forms\Components\Repeater::make('periodicidad_diferenciada')
                                ->label('¿A quiénes paga diferente? Elija el cargo y su periodicidad')
                                ->schema([
                                    Forms\Components\Select::make('cargo')
                                        ->label('Cargo')
                                        ->options(fn(Get $get) => collect($get('../../cargos') ?? [])
                                            ->pluck('nombre_cargo')
                                            ->filter()
                                            ->mapWithKeys(fn($c) => [$c => $c])
                                            ->toArray())
                                        ->searchable()
                                        ->required()
                                        ->native(false)
                                        ->placeholder('Seleccione un cargo registrado'),
                                    Forms\Components\Select::make('periodicidad')
                                        ->label('Periodicidad')
                                        ->options([
                                            'mensual'   => 'Mensual',
                                            'quincenal' => 'Quincenal',
                                            'semanal'   => 'Semanal',
                                            'diario'    => 'Diario',
                                            'destajo'   => 'Por destajo',
                                        ])
                                        ->required()
                                        ->native(false),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->addActionLabel('Agregar cargo con pago diferente')
                                ->visible(fn(Get $get) => count((array) $get('periodicidad_pago')) > 1)
                                ->live()
                                ->afterStateUpdated(fn(Get $get, Set $set) => $set('periodicidad_detalle', self::componerPeriodicidadDetalle($get('periodicidad_diferenciada'))))
                                ->helperText('Los cargos provienen de los que registró antes en el asistente.')
                                ->columnSpanFull(),

                            // Texto compuesto (respaldo para la generación del RIT).
                            Forms\Components\Hidden::make('periodicidad_detalle'),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Section::make('¿Da comisiones o bonos?')
                        ->description('Los beneficios que da de forma habitual deben quedar en el RIT para no convertirse en "salario" a efectos legales.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('maneja_comisiones')
                                ->label('¿Paga comisiones o bonos a algún empleado?')
                                ->options(['no' => 'No', 'si' => 'Sí'])
                                ->default('no')
                                ->inline()
                                ->live(),

                            Forms\Components\ToggleButtons::make('tipo_comisiones')
                                ->label('¿Qué tipo de comisiones o bonos?')
                                ->options([
                                    'comisiones_ventas' => 'Comisiones de ventas',
                                    'bonos_desempeno'   => 'Bonos por desempeño / cumplimiento de metas',
                                    'ambos'             => 'Ambos',
                                ])
                                ->icons([
                                    'comisiones_ventas' => 'heroicon-o-currency-dollar',
                                    'bonos_desempeno'   => 'heroicon-o-trophy',
                                    'ambos'             => 'heroicon-o-squares-plus',
                                ])
                                ->inline()
                                ->columnSpanFull()
                                ->visible(fn(Get $get) => $get('maneja_comisiones') === 'si'),

                            Forms\Components\ToggleButtons::make('tiene_beneficios_extralegales')
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
                                        ->placeholder('Ej: Auxilio de alimentación $150.000/mes, Subsidio de transporte adicional $80.000/mes'),
                                ])
                                ->addActionLabel('Agregar otro beneficio')
                                ->visible(fn(Get $get) => $get('tiene_beneficios_extralegales') === 'si')
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Section::make('Permisos y licencias')
                        ->description('El RIT debe establecer cómo solicitar un permiso y qué permisos especiales otorga la empresa.')
                        ->schema([
                            Forms\Components\Textarea::make('politica_permisos')
                                ->label('¿Cómo solicita un empleado un permiso personal?')
                                ->placeholder('Ej: El trabajador solicita el permiso por escrito con 24 horas de anticipación al jefe inmediato. Los permisos de más de un día requieren aprobación de gerencia...')
                                ->rows(2)
                                ->helperText('Puede escribirlo en sus propias palabras - el sistema lo redactará formalmente.')
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('tiene_licencias_especiales')
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

                            Forms\Components\Textarea::make('politica_incapacidades')
                                ->label('¿Cómo maneja las incapacidades médicas? (opcional)')
                                ->rows(2)
                                ->placeholder('Ej: El trabajador debe reportar la incapacidad el mismo día a su jefe. Debe entregar el original en los 3 días siguientes. La empresa cubre el primer día de incapacidad...')
                                ->helperText('El sistema ya incluye las reglas legales base (EPS, ARL). Escriba aquí lo específico de su empresa.')
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

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_disciplina')
                        ->viewData([
                            'step' => 5,
                            'total' => 7,
                            'title' => 'Disciplina',
                            'accent' => '#fb923c',
                            'lord' => 'https://cdn.lordicon.com/xjsqfzte.json',
                            'subtitle' => 'Conductas sancionables y medidas disciplinarias.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_disciplina')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-disciplina-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('Conductas sancionables y medidas disciplinarias')
                        ->description('Genere el régimen disciplinario con IA según la actividad y los cargos de su empresa (conforme al CST). Luego puede eliminar las que no apliquen, cambiar el tipo de falta, ajustar la sanción o agregar conductas propias de su sector.')
                        ->schema([
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('generar_conductas_ia')
                                    ->label('Generar conductas con IA')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('primary')
                                    ->requiresConfirmation()
                                    ->modalHeading('Generar conductas con IA')
                                    ->modalDescription('La IA generará el régimen disciplinario según la actividad y los cargos de su empresa, conforme al CST. Reemplazará las conductas actuales del listado.')
                                    ->modalSubmitActionLabel('Generar')
                                    ->action(function (Get $get, Set $set) {
                                        $actividadId = $get('actividad_economica_id');
                                        $actividad   = $actividadId ? optional(ActividadEconomica::find($actividadId))->nombre : '';
                                        $cargos      = collect($get('cargos') ?? [])->pluck('nombre_cargo')->filter()->join(', ');

                                        $rows = app(\App\Services\ReglamentoInternoService::class)
                                            ->generarConductasParaWizard(['actividad' => $actividad, 'cargos' => $cargos]);

                                        if (empty($rows)) {
                                            Notification::make()->warning()
                                                ->title('No se pudieron generar las conductas')
                                                ->body('La IA no devolvió un listado válido. Intente de nuevo.')
                                                ->send();
                                            return;
                                        }

                                        $set('sanciones_configuradas', $rows);
                                        Notification::make()->success()
                                            ->title('Conductas generadas')
                                            ->body(count($rows) . ' conductas generadas con IA. Revíselas y ajústelas según su empresa.')
                                            ->send();
                                    }),
                            ])->columnSpanFull(),

                            Forms\Components\Repeater::make('sanciones_configuradas')
                                ->label('Régimen disciplinario')
                                ->helperText('Genere las conductas con IA (botón de arriba) y luego edítelas. Haga clic en una conducta para expandirla; elimine con el ícono de basura; agregue conductas al final.')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Conducta sancionable')
                                        ->required()
                                        ->columnSpanFull(),
                                    Forms\Components\ToggleButtons::make('tipo_falta')
                                        ->label('Tipo de falta')
                                        ->options([
                                            'leve'      => 'Leve',
                                            'grave'     => 'Grave',
                                            'muy_grave' => 'Muy grave',
                                        ])
                                        ->colors([
                                            'leve'      => 'gray',
                                            'grave'     => 'warning',
                                            'muy_grave' => 'danger',
                                        ])
                                        ->icons([
                                            'leve'      => 'heroicon-o-information-circle',
                                            'grave'     => 'heroicon-o-exclamation-triangle',
                                            'muy_grave' => 'heroicon-o-fire',
                                        ])
                                        ->inline()
                                        ->required()
                                        ->hintIcon('heroicon-m-information-circle', tooltip: 'La sanción debe ser proporcional a la gravedad de la falta. Clasificar bien (leve, grave o muy grave) es clave para que la medida resista una eventual demanda.')
                                        ->columnSpan(['default' => 1, 'sm' => 6]),
                                    Forms\Components\ToggleButtons::make('tipo_sancion')
                                        ->label('Sanción aplicable')
                                        ->options([
                                            'llamado_atencion' => 'Llamado',
                                            'suspension'       => 'Suspensión',
                                            'terminacion'      => 'Terminación',
                                        ])
                                        ->colors([
                                            'llamado_atencion' => 'info',
                                            'suspension'       => 'warning',
                                            'terminacion'      => 'danger',
                                        ])
                                        ->inline()
                                        ->required()
                                        ->live()
                                        ->columnSpan(['default' => 1, 'sm' => 6]),
                                    Forms\Components\ToggleButtons::make('escenario_suspension')
                                        ->label('Escenario')
                                        ->options([
                                            'primera_vez'  => 'Primera vez',
                                            'reincidencia' => 'Reincidencia',
                                        ])
                                        ->colors([
                                            'primera_vez'  => 'success',
                                            'reincidencia' => 'danger',
                                        ])
                                        ->icons([
                                            'primera_vez'  => 'heroicon-o-flag',
                                            'reincidencia' => 'heroicon-o-arrow-path',
                                        ])
                                        ->inline()
                                        ->live()
                                        ->hidden(fn(Get $get): bool => $get('tipo_sancion') !== 'suspension')
                                        ->columnSpan(['default' => 1, 'sm' => 8])
                                        // Los radios nativos no se deseleccionan: al hacer clic en la
                                        // opción ya activa, la limpiamos manualmente (deja el escenario en blanco).
                                        ->extraAttributes([
                                            'x-data' => '{ pre: null }',
                                            'x-on:mousedown.capture' => "pre = \$event.target.closest('div')?.querySelector('input[type=radio]')?.checked ? \$event.target.closest('div').querySelector('input[type=radio]').value : null",
                                            'x-on:click.capture' => "(() => { const inp = \$event.target.closest('div')?.querySelector('input[type=radio]'); if (!inp || pre === null || inp.value !== pre) return; \$event.preventDefault(); inp.checked = false; const a = [...inp.attributes].find(x => x.name.startsWith('wire:model')); if (a) \$wire.set(a.value, null); })()",
                                        ]),
                                    Forms\Components\TextInput::make('dias_suspension')
                                        ->label('Días de suspensión')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(1)
                                        ->maxValue(fn(Get $get): int => $get('escenario_suspension') === 'primera_vez' ? 8 : 60)
                                        ->live(onBlur: true)
                                        // Clamp en tiempo real al límite del CST según el escenario.
                                        ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                            if ($state === null || $state === '') {
                                                return;
                                            }
                                            $max = $get('escenario_suspension') === 'primera_vez' ? 8 : 60;
                                            $v = (int) $state;
                                            if ($v > $max) {
                                                $set('dias_suspension', $max);
                                            } elseif ($v < 1) {
                                                $set('dias_suspension', 1);
                                            }
                                        })
                                        ->extraInputAttributes(fn(Get $get): array => [
                                            'min'       => 1,
                                            'max'       => $get('escenario_suspension') === 'primera_vez' ? 8 : 60,
                                            'onkeydown' => "return event.key !== '-'",
                                        ])
                                        ->placeholder('máx.')
                                        ->hintIcon('heroicon-m-information-circle', tooltip: 'La suspensión no puede superar 8 días por la primera vez ni 2 meses (60 días) en caso de reincidencia (Art. 112 CST).')
                                        ->hidden(fn(Get $get): bool => $get('tipo_sancion') !== 'suspension')
                                        ->columnSpan(['default' => 1, 'sm' => 4]),
                                ])
                                ->columns(['default' => 1, 'sm' => 12])
                                ->defaultItems(0)
                                ->reorderable(false)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(
                                    fn(array $state): string => ($state['nombre'] ?? 'Nueva conducta') .
                                        ' - ' . match ($state['tipo_falta'] ?? 'leve') {
                                            'muy_grave' => 'Muy grave',
                                            'grave'     => 'Grave',
                                            default     => 'Leve',
                                        } .
                                        ' → ' . match ($state['tipo_sancion'] ?? '') {
                                            'llamado_atencion' => 'Llamado de atención',
                                            'suspension'       => 'Suspensión' . (!empty($state['dias_suspension']) ? ' ' . $state['dias_suspension'] . ' días' : ''),
                                            'terminacion'      => 'Terminación',
                                            default            => '-',
                                        }
                                )
                                ->addActionLabel('+ Agregar conducta')
                                ->columnSpanFull(),
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

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_sst')
                        ->viewData([
                            'step' => 6,
                            'total' => 7,
                            'title' => 'SST y Conducta',
                            'accent' => '#f472b6',
                            'lord' => 'https://cdn.lordicon.com/edcgvlnw.json',
                            'subtitle' => 'Seguridad y salud en el trabajo y normas de convivencia.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_sst')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-sst-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\Section::make('Seguridad y Salud en el Trabajo (SG-SST)')
                        ->description('El Ministerio de Trabajo verifica que el RIT incluya el SG-SST. No importa si está en proceso - lo importante es que quede documentado.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('tiene_sg_sst')
                                ->label('¿Su empresa tiene el Sistema de Gestión de Seguridad y Salud en el Trabajo (SG-SST)?')
                                ->options([
                                    'implementado' => 'Sí, está implementado y en funcionamiento',
                                    'en_proceso'   => 'Estamos en proceso de implementarlo',
                                    'no'           => 'No, aún no lo tenemos',
                                ])
                                ->colors([
                                    'implementado' => 'success',
                                    'en_proceso'   => 'warning',
                                    'no'           => 'danger',
                                ])
                                ->icons([
                                    'implementado' => 'heroicon-o-shield-check',
                                    'en_proceso'   => 'heroicon-o-clock',
                                    'no'           => 'heroicon-o-shield-exclamation',
                                ])
                                ->default('en_proceso')
                                ->inline()
                                ->hint('Obligatorio para toda empresa')
                                ->hintColor('info')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'El SG-SST es obligatorio para todas las empresas sin importar su tamaño o sector (Decreto 1072 de 2015 y Resolución 0312 de 2019).')
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('riesgos_principales')
                                ->label('¿Cuáles son los principales riesgos en su empresa? (seleccione todos los que aplican)')
                                ->multiple()
                                ->hintAction(
                                    \Filament\Forms\Components\Actions\Action::make('sugerir_riesgos')
                                        ->label('Sugerir según mi actividad')
                                        ->icon('heroicon-m-sparkles')
                                        ->link()
                                        ->action(function (Get $get, Set $set): void {
                                            $sugeridos = $this->riesgosSugeridos($get);

                                            if (empty($sugeridos)) {
                                                Notification::make()
                                                    ->warning()
                                                    ->title('Primero seleccione su actividad económica')
                                                    ->body('Vuelva al paso "Empresa" y elija su actividad para poder sugerir riesgos.')
                                                    ->send();
                                                return;
                                            }

                                            $actuales = (array) $get('riesgos_principales');
                                            $union = array_values(array_unique(array_merge($actuales, $sugeridos)));
                                            $set('riesgos_principales', $union);

                                            Notification::make()
                                                ->success()
                                                ->title('Riesgos sugeridos agregados')
                                                ->body('Se marcaron los riesgos típicos de su actividad. Revise y desmarque los que no apliquen.')
                                                ->send();
                                        }),
                                )
                                ->options([
                                    'ergonomico'  => 'Ergonómico - posturas, levantamiento de cargas, trabajo de pie',
                                    'psicosocial' => 'Psicosocial - estrés, turnos nocturnos, atención al público',
                                    'mecanico'    => 'Mecánico - maquinaria, herramientas, vehículos',
                                    'electrico'   => 'Eléctrico - instalaciones eléctricas, equipos de alta tensión',
                                    'publico'     => 'Público - riesgo de robo, violencia en atención al cliente',
                                    'alturas'     => 'Alturas - trabajo en andamios, techos, superficies elevadas',
                                    'quimico'     => 'Químico - exposición a solventes, pinturas, gases o sustancias tóxicas',
                                    'vial'        => 'Vial - conducción de vehículos, motos o maquinaria en vías',
                                    'fisico'      => 'Físico - ruido excesivo, vibraciones, temperatura extrema',
                                    'biologico'   => 'Biológico - manipulación de alimentos, residuos o agentes biológicos',
                                    'locativo'    => 'Locativo - pisos húmedos, escaleras, superficies irregulares',
                                    'otro'        => 'Otro riesgo específico de mi empresa',
                                ])
                                ->icons([
                                    'ergonomico'  => 'heroicon-o-user',
                                    'psicosocial' => 'heroicon-o-face-frown',
                                    'mecanico'    => 'heroicon-o-cog-6-tooth',
                                    'electrico'   => 'heroicon-o-bolt',
                                    'publico'     => 'heroicon-o-shield-exclamation',
                                    'alturas'     => 'heroicon-o-arrow-trending-up',
                                    'quimico'     => 'heroicon-o-beaker',
                                    'vial'        => 'heroicon-o-truck',
                                    'fisico'      => 'heroicon-o-speaker-wave',
                                    'biologico'   => 'heroicon-o-bug-ant',
                                    'locativo'    => 'heroicon-o-building-office-2',
                                    'otro'        => 'heroicon-o-ellipsis-horizontal-circle',
                                ])
                                ->default(['ergonomico'])
                                ->live()
                                ->columns(['default' => 1, 'sm' => 2])
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('riesgos_otros')
                                ->label('¿Cuál es ese otro riesgo?')
                                ->placeholder('Ej: Riesgo químico por manejo de solventes, riesgo de alturas en construcción')
                                ->visible(fn(Get $get) => in_array('otro', (array) $get('riesgos_principales')))
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('tiene_epp')
                                ->label('¿Sus trabajadores necesitan elementos de protección personal? (casco, guantes, gafas, botas...)')
                                ->options([
                                    'no' => 'No aplica - trabajo de oficina o bajo riesgo',
                                    'si' => 'Sí, se requieren elementos de protección',
                                ])
                                ->default('no')
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('epp_descripcion')
                                ->label('¿Qué elementos de protección usan?')
                                ->placeholder('Ej: Casco, guantes de trabajo, botas de seguridad punta de acero, gafas industriales')
                                ->visible(fn(Get $get) => $get('tiene_epp') === 'si')
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),

                    Forms\Components\Section::make('Normas de convivencia y uso de recursos')
                        ->description('Estas reglas previenen conflictos cotidianos y establecen expectativas claras desde el primer día de trabajo.')
                        ->schema([
                            Forms\Components\ToggleButtons::make('politica_celular')
                                ->label('¿Pueden los empleados usar el celular personal durante el trabajo?')
                                ->options([
                                    'libre'     => 'Sí, libre uso',
                                    'descansos' => 'Solo en descansos y pausas',
                                    'prohibido' => 'No - prohibido salvo emergencias',
                                ])
                                ->colors([
                                    'libre'     => 'success',
                                    'descansos' => 'warning',
                                    'prohibido' => 'danger',
                                ])
                                ->icons([
                                    'libre'     => 'heroicon-o-device-phone-mobile',
                                    'descansos' => 'heroicon-o-pause-circle',
                                    'prohibido' => 'heroicon-o-no-symbol',
                                ])
                                ->default('descansos')
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\ToggleButtons::make('usa_uniforme')
                                ->label('¿La empresa entrega uniforme o dotación?')
                                ->options([
                                    'no'              => 'No',
                                    'uniforme'        => 'Sí - uniforme completo',
                                    'dotacion_basica' => 'Sí - dotación básica (zapatos, ropa de trabajo)',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\ToggleButtons::make('tiene_codigo_etica')
                                ->label('¿Tiene algún manual de ética, código de conducta o valores de empresa?')
                                ->options([
                                    'si'              => 'Sí',
                                    'no'              => 'No',
                                    'en_construccion' => 'Lo estamos construyendo',
                                ])
                                ->default('no')
                                ->inline(),

                            Forms\Components\ToggleButtons::make('politica_confidencialidad')
                                ->label('¿Exige confidencialidad o reserva de información a sus empleados?')
                                ->options([
                                    'por_contrato' => 'Sí - está en el contrato de trabajo',
                                    'solo_verbal'  => 'Solo lo mencionamos verbalmente',
                                    'no'           => 'No aplica a nuestra empresa',
                                ])
                                ->colors([
                                    'por_contrato' => 'success',
                                    'solo_verbal'  => 'warning',
                                    'no'           => 'gray',
                                ])
                                ->icons([
                                    'por_contrato' => 'heroicon-o-lock-closed',
                                    'solo_verbal'  => 'heroicon-o-chat-bubble-left-right',
                                    'no'           => 'heroicon-o-lock-open',
                                ])
                                ->default('no')
                                ->inline()
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('que_quiere_prevenir')
                                ->label('¿Qué situaciones problemáticas quiere evitar principalmente? (opcional)')
                                ->rows(2)
                                ->placeholder('Ej: Impuntualidad crónica, conflictos entre compañeros, uso indebido de información de clientes, manejo inapropiado de efectivo')
                                ->helperText('Escríbalo en sus propias palabras. Esto ayuda a personalizar el capítulo de conductas prohibidas.')
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'sm' => 2]),
                ]),

            // ─────────────────────────────────────────────────────────────────
            // STEP 7: Revisión y Generar
            // ─────────────────────────────────────────────────────────────────
            Step::make('revision')
                ->label('Generar')
                ->description('Revisar y construir')
                ->icon('heroicon-o-cpu-chip')
                ->schema([

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('rit_step_header_generar')
                        ->viewData([
                            'step' => 7,
                            'total' => 7,
                            'title' => 'Revisión y generación',
                            'accent' => '#fb7185',
                            'lord' => 'https://cdn.lordicon.com/wpsdctqb.json',
                            'subtitle' => 'Revise el resumen; la IA redactará su Reglamento.',
                        ])
                        ->columnSpanFull(),

                    // Forms\Components\Placeholder::make('info_paso_generar')
                    //     ->label('')
                    //     ->content(fn() => new HtmlString(
                    //         view('filament.components.rit-step-generar-info')->render()
                    //     ))
                    //     ->columnSpanFull(),

                    Forms\Components\View::make('filament.components.rit-revision-resumen')
                        ->key('rit_revision_resumen')
                        ->viewData(fn(Get $get) => [
                                'empresa'                  => $this->getEmpresa(),
                                'num_trabajadores'         => $get('num_trabajadores'),
                                'actividad_economica'      => $get('actividad_economica'),
                                'tiene_sucursales'         => $get('tiene_sucursales'),
                                'sucursales'               => $get('sucursales') ?? [],
                                'cargos'                   => $get('cargos') ?? [],
                                'tipos_contrato'           => $get('tipos_contrato') ?? [],
                                'num_aprendices_sena'      => $get('num_aprendices_sena'),
                                'tiene_sindicato'          => $get('tiene_sindicato'),
                                'tiene_convencion_colectiva' => $get('tiene_convencion_colectiva'),
                                'tiene_pacto_colectivo'    => $get('tiene_pacto_colectivo'),
                                'modalidades_jornada'      => $get('modalidades_jornada') ?? [],
                                'jornada_personalizada'    => $get('jornada_personalizada') ?? [],
                                'horario_entrada'          => $get('horario_entrada'),
                                'horario_salida'           => $get('horario_salida'),
                                'opera_en_turnos'          => $get('opera_en_turnos'),
                                'turnos_definidos'         => $get('turnos_definidos') ?? [],
                                'descripcion_turnos'       => $get('descripcion_turnos'),
                                'cargos_nocturnos'         => $get('cargos_nocturnos'),
                                'jornada_sabado'           => $get('jornada_sabado'),
                                'trabaja_dominicales'      => $get('trabaja_dominicales'),
                                'cargos_exentos_jornada'   => $get('cargos_exentos_jornada'),
                                'control_asistencia'       => $get('control_asistencia'),
                                'forma_pago'               => $get('forma_pago'),
                                'periodicidad_pago'        => $get('periodicidad_pago') ?? [],
                                'periodicidad_detalle'     => $get('periodicidad_detalle'),
                                'politica_incapacidades'   => $get('politica_incapacidades'),
                                'faltas_leves'             => $get('faltas_leves') ?? [],
                                'faltas_graves'            => $get('faltas_graves') ?? [],
                                'sanciones'                => $get('sanciones_contempladas') ?? [],
                                'tiene_sg_sst'             => $get('tiene_sg_sst'),
                                'riesgos_principales'      => $get('riesgos_principales') ?? [],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Creación del registro
    // ─────────────────────────────────────────────────────────────────────────

    protected function handleRecordCreation(array $data): Model
    {
        // Componer el texto de periodicidad a partir de las filas cargo+periodicidad
        // elegidas (garantiza el valor que consume la generación del RIT).
        if (! empty($data['periodicidad_diferenciada'])) {
            $data['periodicidad_detalle'] = self::componerPeriodicidadDetalle($data['periodicidad_diferenciada']);
        }

        // Derivar días hábiles y sintetizar los campos de jornada desde el repeater
        // personalizado (reemplaza las antiguas preguntas de horario/turnos/sábados).
        $data = array_merge($data, self::sintetizarJornada($data));

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
                ? "{$actividad->codigo} - {$actividad->nombre}"
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
                ->map(fn($a) => "{$a->codigo} - {$a->nombre}")
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

        // 4. Guardar cuestionario PRIMERO en estado 'generando' - si la UI se cierra o
        //    el navegador falla, las respuestas no se pierden y el job puede completarse.
        $record = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'nombre'                  => 'Reglamento Interno - ' . now()->format('d/m/Y'),
                'texto_completo'          => '',
                'activo'                  => false,
                'respuestas_cuestionario' => $data,
                'fuente'                  => 'construido_ia',
                'dias_habiles'            => \App\Support\DiasHabiles::normalizar($data['dias_habiles'] ?? []),
                'dias_laborales'          => \App\Support\DiasHabiles::aLegado($data['dias_habiles'] ?? []),
                'estado_generacion'       => 'generando',
                'mensaje_error_ia'        => null,
            ]
        );

        // 5. Despachar el job al queue 'gemini' - la IA corre fuera del ciclo HTTP,
        //    sin límites de timeout del servidor web.
        GenerarTextoRITJob::dispatch($record, Auth::id());

        Notification::make()
            ->info()
            ->title('Generando su Reglamento Interno...')
            ->body('La IA está redactando su RIT en segundo plano. Recibirá una notificación cuando esté listo (1-2 minutos).')
            ->persistent()
            ->send();

        return $record;
    }

    public function getBreadcrumbs(): array
    {
        return [
            \App\Filament\Admin\Pages\Dashboard::getUrl() => 'Panel',
            'Construir Reglamento Interno de Trabajo',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl();
    }

    private function getEmpresa(): ?Empresa
    {
        $user = Auth::user();
        if (!$user) return null;
        // Bufete: construye el RIT de la empresa seleccionada en el topbar.
        if ($user->esAbogadoDeBufete()) {
            $id = \App\Support\EmpresaActiva::id();
            return $id ? Empresa::find($id) : null;
        }
        if ($user->hasRole('super_admin') || $user->hasRole('abogado')) {
            // Respeta la empresa activa (p. ej. la recién creada) si está seleccionada.
            $id = \App\Support\EmpresaActiva::id();
            return $id ? Empresa::find($id) : Empresa::first();
        }
        return $user->empresa ?? null;
    }
}
