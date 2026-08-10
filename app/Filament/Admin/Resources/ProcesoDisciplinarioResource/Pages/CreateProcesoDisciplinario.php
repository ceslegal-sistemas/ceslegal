<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Concerns\HasVerificacionFotografica;
use App\Models\DiaNoHabil;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Services\EvaluacionHechosService;
use App\Services\TerminoLegalService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use HusamTariq\FilamentTimePicker\Forms\Components\TimePickerField;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class CreateProcesoDisciplinario extends CreateRecord
{
    use HasWizard;
    use HasVerificacionFotografica;

    protected static string $resource = ProcesoDisciplinarioResource::class;

    public function getTitle(): string
    {
        return 'Crear Citación de Descargos';
    }

    // Vista custom: oculta el stepper nativo de Filament (el wizard usa su propio
    // encabezado de paso con barra de progreso), igual que en el wizard de RIT.
    protected static string $view = 'filament.admin.resources.proceso-disciplinarios.pages.create-proceso-disciplinario';

    // ──────────────────────────────────────────────────────────────────────────
    // Estado del formulario de hechos
    // ──────────────────────────────────────────────────────────────────────────

    public bool   $chatListo              = false;
    public bool   $generandoHechos        = false;
    public bool   $mejorando              = false;
    public string $feedbackVoz            = '';
    /** @var array{hechos: string, fecha_ocurrencia: string|null, resumen: string}|array */
    public array  $datosExtraidos         = [];
    /** @var array<int, array{ok: bool, texto: string}> */
    public array  $analisisDescripcion    = [];
    public string $feedbackQuienReporta   = '';
    public bool   $feedbackQuienReportaOk = false;
    public bool   $verificandoDiscriminacion   = false;
    public string $discriminacionIACategoria   = '';
    public string $discriminacionIATermino     = '';
    public string $discriminacionIASugerencia  = '';
    public bool   $discriminacionIAOk          = true;
    public int    $tsUltimaVerifDiscriminacion = 0;
    /** @var array<int, array{marker: string, label: string, opciones: string[]}> */
    public array  $sugerenciasCompletado  = [];
    /** Hash del último texto de hechos ya enviado a clasificarIncidente() -
     *  evita reclasificar con la IA si el texto no cambió desde la última vez. */
    public ?string $ultimoTextoAutoClasificado = null;

    // ──────────────────────────────────────────────────────────────────────────
    // Wizard steps
    // ──────────────────────────────────────────────────────────────────────────

    protected function getSteps(): array
    {
        return [
            // ── Paso 0: Bienvenida (pantalla propia, no cuenta en "Paso X de 3") ──
            Step::make('bienvenida')
                ->label('Bienvenida')
                ->description('Lea antes de empezar')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\View::make('filament.components.bienvenida-proceso')
                        ->key('proc_bienvenida_contenido')
                        ->columnSpanFull(),
                ]),

            // ── Paso 1: Quién y cuándo (trabajador + cuando fusionados) ──
            Step::make('trabajador')
                ->label('Quién y cuándo')
                ->description('Trabajador involucrado, fecha y hora del hecho')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\View::make('filament.components.step-header')
                        ->key('proc_step_header_trabajador')
                        ->viewData([
                            'step' => 1,
                            'total' => 3,
                            'title' => 'Quién y cuándo',
                            'accent' => '#e11d48',
                            'lord' => 'https://cdn.lordicon.com/bushiqea.json',
                            'subtitle' => 'Empresa, trabajador involucrado, y fecha/hora del hecho.',
                        ])
                        ->columnSpanFull(),

                    // Los 2 cards informativos de este paso (antes uno dentro de cada
                    // Section) se fusionan en una sola tarjeta aquí arriba; las Sections
                    // de abajo conservan su encabezado y sus campos sin cambios.
                    Forms\Components\Placeholder::make('info_paso_trabajador_cuando')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.paso-trabajador-cuando-info', [
                                'esCliente' => auth()->user()?->isCliente() ?? false,
                            ])->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Trabajador')
                        ->schema([
                            Forms\Components\Select::make('empresa_id')
                                ->label('¿A qué empresa pertenece el trabajador?')
                                ->relationship(
                                    name: 'empresa',
                                    titleAttribute: 'razon_social',
                                    modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->paraAsignar(),
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->default(function () {
                                    $user = auth()->user();
                                    if ($user?->isCliente()) {
                                        return $user->empresa_id;
                                    }
                                    // Bufete: usa la empresa seleccionada en el topbar.
                                    if ($user?->esAbogadoDeBufete()) {
                                        return \App\Support\EmpresaActiva::id();
                                    }
                                    return null;
                                })
                                ->hidden(function () {
                                    $user = auth()->user();
                                    if ($user?->isCliente()) {
                                        return true;
                                    }
                                    // Bufete con empresa activa: se oculta y se toma la del topbar.
                                    return ($user?->esAbogadoDeBufete() ?? false)
                                        && \App\Support\EmpresaActiva::id() !== null;
                                })
                                // ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                                ->dehydrated()
                                ->afterStateUpdated(fn(Forms\Set $set) => $set('trabajador_id', null))
                                ->helperText('Seleccione la empresa primero - esto cargará la lista de trabajadores disponibles.')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('trabajador_id')
                                ->label('¿Cuál es el trabajador involucrado?')
                                ->placeholder('Buscar trabajador...')
                                ->required()
                                ->live()
                                ->searchable()
                                ->options(
                                    fn(Get $get): array =>
                                    Trabajador::query()
                                        ->where('empresa_id', $get('empresa_id'))
                                        ->where('active', true)
                                        ->get()
                                        ->mapWithKeys(fn($t) => [
                                            $t->id => $t->nombre_completo . ' · ' . $t->numero_documento,
                                        ])
                                        ->toArray()
                                )
                                ->disabled(fn(Get $get) => !$get('empresa_id'))
                                ->helperText(
                                    fn(Get $get) => $get('empresa_id')
                                        ? 'Si el trabajador no aparece en la lista, use el botón \'+\' para registrarlo.'
                                        : 'Primero seleccione la empresa para ver los trabajadores disponibles.'
                                )
                                ->suffixIcon('heroicon-o-user-group')
                                ->createOptionForm(function (Get $get) {
                                    return [
                                        Forms\Components\Placeholder::make('info_nuevo_trabajador')
                                            ->label('')
                                            ->content(new HtmlString(
                                                '<div style="background:rgba(225,29,72,.06);border-left:3px solid #e11d48;border-radius:.5rem;padding:.75rem 1rem;font-size:.8125rem;color:var(--fi-color-gray-600,#4b5563);line-height:1.6;">'
                                                    . 'Complete los datos básicos del trabajador. El <strong>correo electrónico</strong> es indispensable para enviarle la citación.'
                                                    . '</div>'
                                            )),

                                        Forms\Components\Hidden::make('empresa_id')
                                            ->default(fn() => $get('../../empresa_id')),

                                        Forms\Components\Select::make('tipo_documento')
                                            ->label('Tipo de Documento')
                                            ->options([
                                                'CC'   => 'Cédula de Ciudadanía',
                                                'CE'   => 'Cédula de Extranjería',
                                                'TI'   => 'Tarjeta de Identidad',
                                                'PASS' => 'Pasaporte',
                                            ])
                                            ->required()
                                            ->default('CC')
                                            ->native(false)
                                            ->live(),

                                        Forms\Components\TextInput::make('numero_documento')
                                            ->label('Número de Documento')
                                            ->required()
                                            // Mismo comportamiento que TrabajadorResource: sin esta mascara
                                            // el campo aceptaba hasta 50 digitos (con ->numeric()->integer()
                                            // solamente) y ademas rechazaba pasaportes alfanumericos.
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
                                            }),

                                        Forms\Components\Select::make('genero')
                                            ->label('Género')
                                            ->options([
                                                'masculino' => 'Masculino',
                                                'femenino'  => 'Femenino',
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
                                                'Gerente General'           => 'Gerente General',
                                                'Gerente Administrativo'    => 'Gerente Administrativo',
                                                'Coordinador'               => 'Coordinador',
                                                'Supervisor'                => 'Supervisor',
                                                'Jefe de Área'              => 'Jefe de Área',
                                                'Asistente Administrativo'  => 'Asistente Administrativo',
                                                'Auxiliar Administrativo'   => 'Auxiliar Administrativo',
                                                'Secretaria'                => 'Secretaria',
                                                'Recepcionista'             => 'Recepcionista',
                                                'Contador'                  => 'Contador',
                                                'Auxiliar Contable'         => 'Auxiliar Contable',
                                                'Conductor'                 => 'Conductor',
                                                'Mensajero'                 => 'Mensajero',
                                                'Operario'                  => 'Operario',
                                                'Técnico'                   => 'Técnico',
                                                'Vendedor'                  => 'Vendedor',
                                                '__otro__'                  => '--- Otro (personalizado) ---',
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
                                            ->helperText('Se usará para enviar el enlace del formulario virtual de descargos.')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ej: juan.perez@empresa.com'),

                                        Forms\Components\TextInput::make('telefono')
                                            ->label('Teléfono / Celular (WhatsApp)')
                                            ->tel()
                                            ->mask('9999999999')
                                            ->maxLength(10)
                                            ->extraInputAttributes(['inputmode' => 'numeric'])
                                            ->rules(['nullable', 'regex:/^[0-9]{10}$/'])
                                            ->validationMessages([
                                                'regex' => 'El teléfono debe tener exactamente 10 dígitos numéricos (sin +57, espacios, guiones ni letras).',
                                            ])
                                            ->placeholder('3001234567')
                                            ->helperText('10 dígitos, solo números. Para notificaciones por WhatsApp.')
                                            ->suffixIcon('heroicon-o-phone'),
                                    ];
                                })
                                ->createOptionUsing(function (array $data, Get $get) {
                                    if (isset($data['cargo_select'])) {
                                        $data['cargo'] = $data['cargo_select'] === '__otro__'
                                            ? $data['cargo_otro']
                                            : $data['cargo_select'];
                                    }
                                    unset($data['cargo_select'], $data['cargo_otro']);

                                    $empresaId = $get('empresa_id');
                                    $data['empresa_id'] = $empresaId;
                                    $data['active'] = true;

                                    $existente = Trabajador::where('tipo_documento', $data['tipo_documento'])
                                        ->where('numero_documento', $data['numero_documento'])
                                        ->where('empresa_id', $empresaId)
                                        ->first();

                                    if ($existente) {
                                        $existente->update([
                                            'nombres'   => $data['nombres'],
                                            'apellidos' => $data['apellidos'],
                                            'email'     => $data['email'],
                                            'telefono'  => $data['telefono'] ?? $existente->telefono,
                                            'cargo'     => $data['cargo'],
                                            'genero'    => $data['genero'],
                                            'active'    => true,
                                        ]);
                                        Notification::make()
                                            ->info()
                                            ->title('Trabajador actualizado')
                                            ->body("La información de {$existente->nombre_completo} ha sido actualizada.")
                                            ->send();
                                        return $existente->id;
                                    }

                                    $trabajador = Trabajador::create($data);
                                    Notification::make()
                                        ->success()
                                        ->title('Trabajador creado')
                                        ->body("Se registró a {$trabajador->nombre_completo} exitosamente.")
                                        ->send();
                                    return $trabajador->id;
                                })
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('trabajador_confirmado')
                                ->label('')
                                ->visible(fn(Get $get) => (bool) $get('trabajador_id'))
                                ->content(function (Get $get) {
                                    $t = Trabajador::find($get('trabajador_id'));
                                    if (!$t) return '';
                                    return new HtmlString(
                                        view('filament.components.paso-trabajador-confirmado', ['trabajador' => $t])->render()
                                    );
                                })
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Cuándo ocurrió')
                        ->schema([
                            Forms\Components\DatePicker::make('fecha_hecho')
                                ->label('¿Cuándo ocurrió?')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->disabledDates(fn(Get $get) => self::fechasInhabilesHecho($get('empresa_id')))
                                ->helperText(fn(Get $get) => self::textoDiasHabilesHecho($get('empresa_id'))),

                            TimePickerField::make('hora_aproximada_hecho')
                                ->label('Hora aproximada (opcional)')
                                ->helperText('Horario Colombia (UTC-5)'),

                            // Varias fechas: el hecho pudo ocurrir en más de un día laboral.
                            Forms\Components\Repeater::make('fechas_ocurrencia_adicionales')
                                ->label('¿Ocurrió en más de un día? Agregue las fechas adicionales')
                                ->schema([
                                    Forms\Components\DatePicker::make('fecha')
                                        ->label('Fecha adicional')
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->maxDate(now())
                                        ->disabledDates(fn(Get $get) => self::fechasInhabilesHecho($get('../../empresa_id'))),
                                ])
                                ->addActionLabel('Agregar otra fecha')
                                ->defaultItems(0)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),

            // ── Paso 2: Qué pasó (hechos + pruebas fusionados) ───────────────
            Step::make('hechos')
                ->label('Qué pasó')
                ->description('Hechos, evidencias y testigos')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\View::make('filament.components.step-header')
                        ->key('proc_step_header_hechos')
                        ->viewData([
                            'step' => 2,
                            'total' => 3,
                            'title' => 'Qué pasó',
                            'accent' => '#f97316',
                            'lord' => 'https://cdn.lordicon.com/bpptgtfr.json',
                            'subtitle' => 'Descripción de los hechos, evidencias y testigos.',
                        ])
                        ->columnSpanFull(),

                    // Los 2 cards informativos de este paso se fusionan en una sola
                    // tarjeta aquí arriba; las Sections de abajo conservan su
                    // encabezado y sus campos sin cambios.
                    Forms\Components\Placeholder::make('info_paso_hechos_pruebas')
                        ->label('')
                        ->content(fn() => new HtmlString(
                            view('filament.components.paso-hechos-pruebas-info')->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Hechos')
                        ->schema([
                            Forms\Components\TextInput::make('quien_reporta')
                                ->label('¿Quién reporta el incidente?')
                                ->helperText('Indique el cargo y/o nombre de quien notificó el hecho. Ejemplos: "Supervisor de turno Carlos Ruiz", "El propio empleador", "Jefa de Recursos Humanos María García", "Compañero del área de logística".')
                                ->placeholder('Ej: Supervisor de turno / Jefe de área / El empleador directamente')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn($livewire) => $livewire->analizarQuienReporta())
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('feedback_quien_reporta')
                                ->label('')
                                ->content(fn($livewire) => new HtmlString(
                                    view('filament.components.feedback-quien-reporta', [
                                        'feedback' => $livewire->feedbackQuienReporta,
                                        'ok'       => $livewire->feedbackQuienReportaOk,
                                    ])->render()
                                ))
                                ->columnSpanFull()
                                ->visible(fn($livewire) => $livewire->feedbackQuienReporta !== ''),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\Textarea::make('descripcion_hecho')
                                        ->label('¿Qué ocurrió?')
                                        ->helperText('Escriba una descripción breve y use "Generar redacción con IA" para obtener la versión profesional. La IA corregirá el lenguaje automáticamente.')
                                        ->required()
                                        ->minLength(40)
                                        ->validationMessages([
                                            'min' => 'Necesitamos al menos una descripción básica de lo ocurrido.',
                                        ])
                                        ->rules([
                                            fn() => function (string $attribute, $value, \Closure $fail) {
                                                $tn = mb_strtolower(strtr($value, [
                                                    'á' => 'a',
                                                    'é' => 'e',
                                                    'í' => 'i',
                                                    'ó' => 'o',
                                                    'ú' => 'u',
                                                    'Á' => 'a',
                                                    'É' => 'e',
                                                    'Í' => 'i',
                                                    'Ó' => 'o',
                                                    'Ú' => 'u',
                                                    'ñ' => 'n',
                                                    'Ñ' => 'n',
                                                    'ä' => 'a',
                                                    'ë' => 'e',
                                                    'ï' => 'i',
                                                    'ö' => 'o',
                                                    'ü' => 'u',
                                                ]));
                                                // Bloquear groserías e insultos
                                                // Pasada 1: raíces siempre vulgares, sin \b (detecta compuestos)
                                                $raicesV = ['hijueputa', 'hijueputo', 'hijuemadre', 'hijueperra', 'malparido', 'malparida', 'gonorrea', 'pirobo', 'piroba', 'mierda', 'verga', 'mamahuevo', 'mamaguevo', 'coño', 'pajuo', 'pajua', 'berriondo', 'berrionda', 'monda', 'hp'];
                                                // 'cono' va en pasada 2 con \b para evitar falso positivo en "conocimiento"
                                                $hayGroseria = false;
                                                foreach ($raicesV as $r) {
                                                    if (str_contains($tn, $r)) {
                                                        $hayGroseria = true;
                                                        break;
                                                    }
                                                }
                                                // Pasada 2: insultos con límite de palabra
                                                if (!$hayGroseria) {
                                                    $hayGroseria = (bool) preg_match('/\b(cono|hp|sopenco|lambon|lambona|arrecho|arrecha|marico|marica|maricon|puta|puto|pendejo|pendeja|imbecil|idiota|estupido|estupida|culo|culero|cabron|cabrona|jodido|jodida|bastardo|bastarda|cretino|cretina|guevon|huevon|huebon|carajo|degenerado|degenerada|pervertido|pervertida|mamarracho|mamarracha|baboso|babosa|desgraciado|desgraciada|maldito|maldita|miserable|tonto|tonta|burro|burra|bruto|bruta|vago|vaga|flojo|floja|holgazan|holgazana|inepto|inepta|incapaz|inservible|torpe|zoquete|necio|necia|bobo|boba|bestia|animal)\b/u', $tn);
                                                }
                                                if ($hayGroseria) {
                                                    $fail('Retire el lenguaje inapropiado antes de continuar. Use "Generar redacción con IA" para corregirlo automáticamente.');
                                                    return;
                                                }
                                                // Bloquear acusaciones declarativas sin "presuntamente"
                                                $verbosGraves =
                                                    'acoso|hostigo|intimido|robo|hurto|sustrajo|'
                                                    . 'golpeo|agredio|pego|ataco|amenazo|insulto|'
                                                    . 'ofendio|maltrato|abuso|violo|discrimino';
                                                $tienePresuntivo = (bool) preg_match(
                                                    '/\b(presuntamente|presunta\s+conducta|al\s+parecer|supuestamente|segun\s+(lo\s+)?report[ao]|aparentemente|se\s+alega|se\s+presume|habria|se\s+le\s+atribuye|se\s+le\s+imputa)\b/u',
                                                    $tn
                                                );
                                                $tieneVerbosGraves = (bool) preg_match("/\b({$verbosGraves})\b/u", $tn);
                                                if ($tieneVerbosGraves && !$tienePresuntivo) {
                                                    $fail('Use lenguaje presuntivo antes de continuar. Ejemplo: "presuntamente acosó", "presuntamente agredió". Use "Generar redacción con IA" para corregirlo automáticamente.');
                                                    return;
                                                }
                                                // Bloquear lenguaje discriminatorio
                                                $terminosProtegidos = [
                                                    'raza o etnia'                         => ['negro', 'negra', 'negroto', 'negrota', 'negrito', 'negrita', 'indio', 'india', 'indigena', 'zambo', 'zamba', 'mulato', 'mulata', 'afro', 'afrocolombiano', 'afrodescendiente', 'mestizo', 'mestiza', 'moreno', 'morena', 'trigueño', 'trigueña', 'gringo', 'gringa', 'cholo', 'chola', 'montañero', 'montañera'],
                                                    'orientación sexual o identidad de género' => ['gay', 'lesbiana', 'bisexual', 'travesti', 'travestido', 'travestida', 'transexual', 'transgenero', 'transgenerista', 'homosexual', 'queer', 'intersexual'],
                                                    'discapacidad física'                  => ['invalido', 'invalida', 'lisiado', 'lisiada', 'tullido', 'tullida', 'cojo', 'coja', 'manco', 'manca', 'ciego', 'ciega', 'sordo', 'sorda', 'mudo', 'muda', 'jorobado', 'jorobada', 'paralitico', 'paralitica', 'minusvalido', 'minusvalida', 'discapacitado', 'discapacitada', 'postrado', 'postrada', 'tetraplejico', 'paraplejico'],
                                                    'discapacidad cognitiva o mental'      => ['mongolito', 'mongolita', 'mogolico', 'mogolica', 'mongol', 'tarado', 'tarada', 'demente', 'loco', 'loca', 'chiflado', 'chiflada', 'perturbado', 'perturbada', 'autista', 'esquizofrenico', 'esquizofrenica'],
                                                    'religión'                             => ['judio', 'judia', 'musulman', 'musulmana', 'evangelico', 'evangelica'],
                                                    'origen nacional'                      => ['venezolano', 'venezolana', 'extranjero', 'extranjera', 'clandestino', 'clandestina', 'mojado', 'mojada'],
                                                ];
                                                $catDisc = null;
                                                foreach ($terminosProtegidos as $cat => $terminos) {
                                                    foreach ($terminos as $t) {
                                                        if (preg_match('/\b' . preg_quote($t, '/') . '\b/u', $tn)) {
                                                            $catDisc = $cat;
                                                            break 2;
                                                        }
                                                    }
                                                }
                                                if ($catDisc) {
                                                    $fail("La descripción contiene una referencia a {$catDisc} del trabajador. Esto viola jurisprudencia antidiscriminatoria. Use \"Generar redacción con IA\" para corregirlo.");
                                                }
                                            },
                                        ])
                                        ->rows(7)
                                        ->placeholder('Ej: El trabajador llegó dos horas tarde sin avisar y no respondió los mensajes del supervisor...')
                                        // debounce en 2.5s (antes 800ms): el análisis de
                                        // lenguaje es barato y puede seguir siendo casi
                                        // inmediato, pero ahora este mismo evento también
                                        // dispara autoClasificarGravedad() (llamada real a la
                                        // IA) - un debounce más largo evita lanzarla en cada
                                        // pausa breve mientras el usuario sigue escribiendo.
                                        ->live(debounce: 2500)
                                        ->afterStateUpdated(function ($livewire) {
                                            $livewire->analizarDescripcion();
                                            $livewire->autoClasificarGravedad();
                                        })
                                        ->hintActions([
                                            Forms\Components\Actions\Action::make('generar_redaccion')
                                                ->label(fn($livewire) => $livewire->mejorando ? 'Generando...' : 'Generar redacción con IA')
                                                ->icon('heroicon-m-sparkles')
                                                ->color('primary')
                                                ->tooltip('La IA generará una redacción profesional completa usando todos los datos del caso')
                                                ->disabled(fn(Get $get, $livewire) => $livewire->mejorando || mb_strlen($get('descripcion_hecho') ?? '') < 10)
                                                ->action(fn($livewire) => $livewire->generarRedaccion()),

                                            // ── CLASIFICADOR DE INCIDENTE ──────────────────
                                            // Mismo clasificador que ProcesoDisciplinarioResource.php
                                            // (formulario de editar) - se agrega también aquí porque
                                            // esta página (el wizard de "Crear Citación de Descargos")
                                            // tiene su PROPIO formulario duplicado con nombres de campo
                                            // distintos (descripcion_hecho en vez de hechos, fecha_hecho
                                            // en vez de fecha_ocurrencia). No hay selector de motivos del
                                            // RIT en este wizard, así que motivos_rit siempre va vacío
                                            // (el prompt lo maneja sin problema).
                                            Forms\Components\Actions\Action::make('clasificarGravedadIA')
                                                ->label('Clasificar gravedad con IA')
                                                ->icon('heroicon-o-scale')
                                                ->color('info')
                                                ->tooltip('La IA revisará el RIT, el CST y el historial del trabajador para estimar la gravedad de la falta')
                                                ->requiresConfirmation()
                                                ->modalHeading('Clasificar Gravedad con IA')
                                                ->modalDescription('La IA revisará el RIT, el Código Sustantivo del Trabajo y el historial disciplinario del trabajador para estimar la gravedad de la posible falta y el nivel de rigor que debería tener la diligencia de descargos. Antes de clasificar, verifica que los hechos descritos sean suficientes para sostener ese rigor.')
                                                ->modalSubmitActionLabel('Clasificar')
                                                ->action(function (Forms\Set $set, Forms\Get $get, $livewire) {
                                                    $trabajadorId = $get('trabajador_id');
                                                    $empresaId = $get('empresa_id');
                                                    $hechos = $get('descripcion_hecho');

                                                    // Registrar el hash aquí también, para que el
                                                    // auto-clasificador (debounced en el campo de
                                                    // hechos) no vuelva a llamar a la IA con el mismo
                                                    // texto justo después de que el usuario ya lo pidió
                                                    // manualmente.
                                                    if ($hechos) {
                                                        $livewire->ultimoTextoAutoClasificado = md5(trim(strip_tags($hechos)));
                                                    }

                                                    if (!$trabajadorId || !$empresaId) {
                                                        Notification::make()
                                                            ->warning()
                                                            ->title('Datos incompletos')
                                                            ->body('Seleccione primero la empresa y el trabajador.')
                                                            ->send();
                                                        return;
                                                    }

                                                    if (!$hechos) {
                                                        Notification::make()
                                                            ->warning()
                                                            ->title('Datos incompletos')
                                                            ->body('Describa los hechos antes de clasificar la gravedad.')
                                                            ->send();
                                                        return;
                                                    }

                                                    $trabajador = Trabajador::find($trabajadorId);

                                                    $fechas = [];
                                                    if ($get('fecha_hecho')) {
                                                        $fechas[] = Carbon::parse($get('fecha_hecho'))->format('Y-m-d');
                                                    }
                                                    foreach ($get('fechas_ocurrencia_adicionales') ?? [] as $item) {
                                                        if (!empty($item['fecha'])) {
                                                            $fechas[] = Carbon::parse($item['fecha'])->format('Y-m-d');
                                                        }
                                                    }

                                                    try {
                                                        $iaService = app(\App\Services\IADescargoService::class);
                                                        $resultado = $iaService->clasificarIncidente([
                                                            'empresa_id'        => (int) $empresaId,
                                                            'trabajador_id'     => (int) $trabajadorId,
                                                            'cargo'             => $trabajador?->cargo ?? 'No especificado',
                                                            'hechos'            => $hechos,
                                                            'fechas_ocurrencia' => $fechas,
                                                            'motivos_rit'       => [],
                                                        ]);
                                                    } catch (\Exception $e) {
                                                        Log::error('Error al clasificar gravedad del incidente con IA', [
                                                            'error' => $e->getMessage(),
                                                        ]);
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('No se pudo clasificar')
                                                            ->body('Ocurrió un error inesperado: ' . $e->getMessage())
                                                            ->persistent()
                                                            ->send();
                                                        return;
                                                    }

                                                    $set('clasificacion_incidente_ia', json_encode($resultado));

                                                    if ($resultado['informacion_suficiente'] === false) {
                                                        Notification::make()
                                                            ->warning()
                                                            ->title('Información insuficiente para clasificar')
                                                            ->body('Complete la descripción de los hechos con lo indicado abajo y vuelva a clasificar.')
                                                            ->duration(10000)
                                                            ->send();
                                                    } elseif ($resultado['informacion_suficiente'] === null) {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('No se pudo clasificar')
                                                            ->body($resultado['error'] ?? 'El análisis automático no estuvo disponible. Intente de nuevo.')
                                                            ->persistent()
                                                            ->send();
                                                    } else {
                                                        Notification::make()
                                                            ->success()
                                                            ->title('Gravedad clasificada: ' . strtoupper($resultado['gravedad_estimada'] ?? ''))
                                                            ->body('Esta clasificación se usará como piso mínimo de rigor durante la diligencia de descargos. Revísela antes de continuar.')
                                                            ->duration(8000)
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->columnSpan(2),

                                    Forms\Components\Placeholder::make('panel_analisis_ia')
                                        ->label('')
                                        ->content(fn($livewire) => new HtmlString(
                                            view('filament.components.paso-hechos-analisis', [
                                                'items'       => $livewire->analisisDescripcion,
                                                'feedbackVoz' => '',
                                            ])->render()
                                        ))
                                        ->columnSpan(1),
                                ]),

                            Forms\Components\Placeholder::make('clasificacion_incidente_ia_display')
                                ->hiddenLabel()
                                ->content(function (Forms\Get $get) {
                                    $raw = $get('clasificacion_incidente_ia');
                                    $c = $raw ? json_decode($raw, true) : null;
                                    if (!is_array($c)) {
                                        return new HtmlString('');
                                    }

                                    // Capítulo del RIT ya disponible en el sistema, para
                                    // mostrarlo como referencia en vez de que el usuario
                                    // tenga que transcribirlo cuando el clasificador dice
                                    // "información insuficiente" y pide ese capítulo.
                                    $capituloRit = null;
                                    $hechosResaltado = null;
                                    if (($c['informacion_suficiente'] ?? null) === false) {
                                        if ($get('empresa_id')) {
                                            $capituloRit = app(\App\Services\ReglamentoInternoService::class)
                                                ->obtenerCapituloDisciplinarioTexto((int) $get('empresa_id'));
                                        }
                                        $fragmentos = $c['fragmentos_a_revisar'] ?? [];
                                        if (!empty($fragmentos)) {
                                            $hechosResaltado = app(\App\Services\IADescargoService::class)
                                                ->resaltarFragmentos($get('descripcion_hecho') ?? '', $fragmentos);
                                        }
                                    }

                                    return new HtmlString(
                                        view('filament.components.clasificacion-gravedad-resultado', [
                                            'clasificacion' => $c,
                                            'capituloRit' => $capituloRit,
                                            'hechosResaltado' => $hechosResaltado,
                                        ])->render()
                                    );
                                })
                                ->visible(fn(Forms\Get $get) => filled($get('clasificacion_incidente_ia')))
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('clasificacion_incidente_ia')
                                ->dehydrated(true),

                            // Aviso junto a "Siguiente" cuando la clasificación quedó
                            // incompleta - no bloquea el avance (un caso puede ser
                            // genuinamente leve; el sistema ya usa CONVERSACIONAL como
                            // piso seguro cuando no logra clasificar), solo recuerda
                            // completar los datos pedidos arriba para un expediente más
                            // sólido. El botón "Siguiente" del Wizard no es reactivo al
                            // estado del formulario, así que el aviso va aquí, al final
                            // del paso, en vez de en el botón mismo.
                            Forms\Components\Placeholder::make('clasificacion_incidente_ia_aviso_siguiente')
                                ->hiddenLabel()
                                ->content(function (Forms\Get $get) {
                                    $raw = $get('clasificacion_incidente_ia');
                                    $c = $raw ? json_decode($raw, true) : null;
                                    if (!is_array($c) || ($c['informacion_suficiente'] ?? null) !== false) {
                                        return new HtmlString('');
                                    }

                                    return new HtmlString(
                                        '<div class="flex items-center gap-2 justify-end text-sm text-amber-600 dark:text-amber-400">'
                                        . '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                                        . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>'
                                        . '</svg>'
                                        . '<span>Puede continuar, pero la clasificación de gravedad quedó incompleta - revise los datos pedidos arriba.</span>'
                                        . '</div>'
                                    );
                                })
                                ->visible(fn(Forms\Get $get) => filled($get('clasificacion_incidente_ia')))
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Evidencias y testigos')
                        ->schema([
                            Forms\Components\Radio::make('tiene_evidencias')
                                ->label('¿Existe evidencia del hecho?')
                                ->options(['si' => 'Sí', 'no' => 'No'])
                                ->required()
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('evidencias_empleador')
                                ->label('Adjuntar archivos (opcional)')
                                ->helperText('Máx. 5 archivos · 10 MB c/u · PDF, imágenes, Word.')
                                ->multiple()
                                ->maxFiles(5)
                                ->maxSize(10240)
                                ->disk('public')
                                ->directory('evidencias')
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                ])
                                ->visible(fn(Get $get) => $get('tiene_evidencias') === 'si')
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('hubo_testigos')
                                ->label('¿Hubo personas que presenciaron el hecho?')
                                ->options(['si' => 'Sí', 'no' => 'No'])
                                ->required()
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('testigos')
                                ->label('Testigos')
                                ->schema([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Nombre completo')
                                        ->required()
                                        ->placeholder('Ej: Carlos Pérez'),
                                    Forms\Components\TextInput::make('cargo')
                                        ->label('Cargo')
                                        ->placeholder('Ej: Supervisor de planta'),
                                ])
                                ->columns(2)
                                ->addActionLabel('Agregar testigo')
                                ->minItems(1)
                                ->visible(fn(Get $get) => $get('hubo_testigos') === 'si')
                                ->columnSpanFull(),
                        ]),
                ]),

            // ── Paso 3: Revisión y envío (revision + verificacion fusionados) ──
            Step::make('revision')
                ->label('Revisión y envío')
                ->description('Confirme, autorice y programe la audiencia')
                ->icon('heroicon-o-check-circle')
                ->schema([

                    Forms\Components\View::make('filament.components.step-header')
                        ->key('proc_step_header_revision')
                        ->viewData([
                            'step' => 3,
                            'total' => 3,
                            'title' => 'Revisión y envío',
                            'accent' => '#22c55e',
                            'lord' => 'https://cdn.lordicon.com/fikcyfpp.json',
                            'subtitle' => 'Revise, autorice con su verificación de identidad, y programe la audiencia.',
                        ])
                        ->columnSpanFull(),

                    // ── Resumen del expediente ────────────────────────────────
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('resumen_completo')
                                ->label('')
                                ->content(fn(Get $get, $livewire) => new HtmlString(
                                    view('filament.components.paso-revision-resumen', [
                                        'quien_reporta'    => $get('quien_reporta'),
                                        'descripcion'      => $get('descripcion_hecho'),
                                        'fecha'            => $get('fecha_hecho'),
                                        'hora'             => $get('hora_aproximada_hecho'),
                                        'en_horario'       => $get('en_horario_laboral'),
                                        'tiene_evidencias' => $get('tiene_evidencias'),
                                        'hubo_testigos'    => $get('hubo_testigos'),
                                        'testigos'         => $get('testigos') ?? [],
                                        'trabajador_id'    => $get('trabajador_id'),
                                        'chat_listo'       => $livewire->chatListo,
                                    ])->render()
                                ))
                                ->columnSpanFull(),
                        ]),

                    // ── Descripción jurídica ──────────────────────────────────
                    // Se genera SOLA al llegar a este paso (mismo patrón que el
                    // clasificador de gravedad del Paso 2: se dispara automático, pero
                    // la acción manual se deja siempre visible por si el cliente quiere
                    // volver a ejecutarla). El disparador va en un componente Blade
                    // propio (auto-generar-hechos), no en ->extraAttributes() de la
                    // Section: un primer intento con extraAttributes() en la Section no
                    // disparaba (la Section envuelve su contenido en marcado propio que
                    // aparentemente rompe el anidamiento que necesita el x-init) - un
                    // <div> propio, igual que el de la cámara de "Verificación", sí
                    // funciona (ver memoria filament-wizard-x-init-eager).
                    Forms\Components\View::make('filament.components.auto-generar-hechos')
                        ->key('proc_auto_generar_hechos')
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Descripción jurídica')
                        ->description('La IA redacta los hechos en lenguaje formal para el expediente disciplinario.')
                        ->icon('heroicon-o-sparkles')
                        // Acción en el encabezado de la sección, como texto (mismo
                        // estilo que "Generar redacción con IA"/"Clasificar gravedad
                        // con IA" del Paso 2, que van como hintActions del campo) - no
                        // un botón de ancho completo.
                        ->headerActions([
                            Forms\Components\Actions\Action::make('generar_hechos')
                                ->label(fn(Get $get, $livewire) => $livewire->generandoHechos
                                    ? 'Generando...'
                                    : (filled($get('hechos_ia')) ? 'Volver a generar con IA' : 'Generar con IA'))
                                ->icon('heroicon-m-sparkles')
                                ->color('gray')
                                ->link()
                                ->disabled(fn($livewire) => $livewire->generandoHechos)
                                ->action(fn($livewire) => $livewire->generarHechos()),
                        ])
                        ->schema([
                            Forms\Components\Placeholder::make('generando_hechos_aviso')
                                ->hiddenLabel()
                                ->content(new HtmlString(
                                    '<div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">'
                                    . '<svg class="w-4 h-4 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">'
                                    . '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
                                    . '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>'
                                    . '</svg>'
                                    . '<span>Redactando la descripción jurídica con IA...</span>'
                                    . '</div>'
                                ))
                                ->visible(fn($livewire) => $livewire->generandoHechos)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('hechos_ia')
                                ->label('Descripción generada (editable)')
                                ->helperText('Revise y edite si es necesario antes de crear el proceso.')
                                ->rows(8)
                                ->hidden(fn(Get $get) => empty($get('hechos_ia')))
                                ->columnSpanFull(),
                        ]),

                    // ── Audiencia de descargos ────────────────────────────────
                    Forms\Components\Section::make('Audiencia de descargos')
                        ->description('Programe cuándo se realizará la audiencia virtual con el trabajador.')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Forms\Components\DatePicker::make('fecha_descargos_programada')
                                ->label('Fecha de la audiencia')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->minDate(function (Get $get) {
                                    // Super admin puede seleccionar desde hoy
                                    if (auth()->user()?->hasRole('super_admin')) {
                                        return now()->startOfDay();
                                    }

                                    $festivos       = self::getFestivosDatepicker();
                                    $empresaId      = $get('empresa_id');
                                    $empresa        = $empresaId ? Empresa::find($empresaId) : null;
                                    $diasHabiles    = $empresa?->diasHabilesSet() ?? \App\Support\DiasHabiles::DEFECTO;

                                    // Contar 6 días hábiles según la jornada real de la empresa
                                    $fecha    = now()->copy();
                                    $contados = 0;

                                    while ($contados < 6) {
                                        $fecha->addDay();
                                        $esFestivo = in_array($fecha->format('Y-m-d'), $festivos);
                                        $esHabil   = in_array($fecha->dayOfWeekIso, $diasHabiles, true);

                                        if ($esHabil && !$esFestivo) {
                                            $contados++;
                                        }
                                    }

                                    return $fecha->startOfDay();
                                })
                                ->maxDate(fn() => now()->addMonths(3)->endOfDay())
                                ->disabledDates(function (Get $get) {
                                    $empresaId   = $get('empresa_id');
                                    $empresa     = $empresaId ? Empresa::find($empresaId) : null;
                                    $diasHabiles = $empresa?->diasHabilesSet() ?? \App\Support\DiasHabiles::DEFECTO;

                                    $festivos = self::getFestivosDatepicker();

                                    // Solo iterar el rango visible (3 meses) para mantener el payload pequeño
                                    $deshabilitadas = [];
                                    $inicio         = now()->startOfDay();
                                    $fin            = now()->addMonths(3);

                                    for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
                                        if (! in_array($d->dayOfWeekIso, $diasHabiles, true)) {
                                            $deshabilitadas[] = $d->format('Y-m-d');
                                        }
                                    }

                                    return array_unique(array_merge($deshabilitadas, $festivos));
                                })
                                ->helperText(function (Get $get) {
                                    $empresaId = $get('empresa_id');
                                    $empresa   = $empresaId ? Empresa::find($empresaId) : null;
                                    $texto     = $empresa
                                        ? \App\Support\DiasHabiles::texto($empresa->diasHabilesSet())
                                        : 'Lunes a Viernes';

                                    return "Días hábiles: {$texto}. Festivos no disponibles (mínimo 6 días hábiles).";
                                }),

                            TimePickerField::make('hora_descargos_programada')
                                ->label('Hora de la audiencia')
                                ->required()
                                ->helperText('Horario Colombia (UTC-5)'),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Verificación de identidad')
                        ->description('Equivalencia funcional de su firma: declaración + verificación fotográfica.')
                        ->icon('heroicon-o-finger-print')
                        ->schema([
                            Forms\Components\Grid::make(1)
                                ->schema([
                                    Forms\Components\TextInput::make('citante_nombre')
                                        ->label('Nombre completo')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('citante_cargo')
                                        ->label('Cargo')
                                        ->required()
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Placeholder::make('declaracion_citacion_texto')
                                ->hiddenLabel()
                                ->content(fn() => new HtmlString(
                                    '<style>:root{--decl-bg:rgba(0,0,0,0.03);--decl-border:rgba(0,0,0,0.10);--decl-left:rgba(0,0,0,0.15);--decl-label:rgba(0,0,0,0.45);--decl-text:rgba(17,24,39,0.78);}' .
                                        'html.dark{--decl-bg:rgba(255,255,255,0.04);--decl-border:rgba(255,255,255,0.10);--decl-left:rgba(255,255,255,0.25);--decl-label:rgba(255,255,255,0.38);--decl-text:rgba(255,255,255,0.72);}</style>' .
                                        '<div style="padding:14px 16px;background:var(--decl-bg);border-radius:12px;border:1px solid var(--decl-border);border-left:3px solid var(--decl-left);">' .
                                        '<p style="font-size:10px;font-weight:700;color:var(--decl-label);text-transform:uppercase;letter-spacing:0.1em;margin:0 0 6px;">Declaración de quien cita</p>' .
                                        '<p style="font-size:13px;color:var(--decl-text);line-height:1.6;margin:0;">Al marcar la casilla a continuación, declaro que tengo la potestad para iniciar este proceso disciplinario, que los hechos descritos son ciertos según mi conocimiento, y que autorizo expresamente la citación a descargos del trabajador. Entiendo que esta acción queda registrada con fecha, hora e imagen de verificación como parte del expediente del proceso.</p>' .
                                        '</div>'
                                )),

                            Forms\Components\Checkbox::make('citacion_declaracion_aceptada')
                                ->label('Acepto y confirmo la declaración anterior como persona autorizada para citar a descargos')
                                ->required()
                                ->accepted(),

                            Forms\Components\Placeholder::make('webcam_citante')
                                ->label('Foto de verificación')
                                ->content(fn() => view('filament.components.webcam-autorizador', [
                                    'wireTargetPath' => 'data.foto_citante_base64',
                                    // El paso ahora se llama 'revision' (fusionado con
                                    // verificación) - el componente de la cámara necesita
                                    // este slug para arrancar sola al llegar aquí (ver
                                    // memoria filament-wizard-x-init-eager).
                                    'wizardStepId'   => 'revision',
                                ])),

                            // La obligatoriedad se valida en mutateFormDataBeforeCreate() con
                            // una notificación visible: al ser un campo oculto (base64), el
                            // error de "requerido" no se mostraría al usuario.
                            Forms\Components\Hidden::make('foto_citante_base64'),
                        ]),
                ]),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generación de hechos con IA (formulario → llamada única)
    // ──────────────────────────────────────────────────────────────────────────

    public function generarHechos(): void
    {
        $empresaId    = $this->data['empresa_id'] ?? null;
        $trabajadorId = $this->data['trabajador_id'] ?? null;

        if (!$empresaId || !$trabajadorId) {
            Notification::make()->warning()
                ->title('Complete el Paso 1 primero')
                ->body('Debe seleccionar empresa y trabajador.')
                ->send();
            return;
        }

        if (empty($this->data['descripcion_hecho'])) {
            Notification::make()->warning()
                ->title('Campos incompletos')
                ->body('Debe completar la descripción del hecho en el Paso 2.')
                ->send();
            return;
        }

        if (empty($this->data['fecha_hecho'])) {
            Notification::make()->warning()
                ->title('Campos incompletos')
                ->body('Debe indicar la fecha del hecho en el Paso 3.')
                ->send();
            return;
        }

        $this->generandoHechos = true;

        try {
            $trabajador = Trabajador::find($trabajadorId);

            // Construir contexto adicional
            $partes = [];

            $testigos = $this->data['testigos'] ?? [];
            if (!empty($testigos)) {
                $textoTestigos = collect($testigos)
                    ->map(fn($t) => ($t['nombre'] ?? '') . (isset($t['cargo']) ? " ({$t['cargo']})" : ''))
                    ->filter()->join(', ');
                $partes[] = 'Testigos: ' . $textoTestigos;
            }

            $enHorario = $this->data['en_horario_laboral'] ?? null;
            if ($enHorario === 'si')      $partes[] = 'El hecho ocurrió en horario laboral';
            elseif ($enHorario === 'no')  $partes[] = 'El hecho ocurrió fuera del horario laboral';

            // Fecha(s) del hecho: la principal + las adicionales del Paso 2 (el hecho
            // pudo repetirse en más de un día). Se envían todas a la IA para que la
            // redacción las mencione, en vez de solo la primera fecha.
            $fechaTexto = Carbon::parse($this->data['fecha_hecho'])
                ->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

            $fechasAdicionales = collect($this->data['fechas_ocurrencia_adicionales'] ?? [])
                ->pluck('fecha')
                ->filter()
                ->map(fn($f) => Carbon::parse($f)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
                ->values();

            if ($fechasAdicionales->isNotEmpty()) {
                $fechaTexto = "{$fechaTexto}, y también el " . $fechasAdicionales->join(', el ');
            }

            $resultado = app(EvaluacionHechosService::class)->generarHechosDesdeFormulario(
                datosFormulario: [
                    'descripcion_hecho'      => $this->data['descripcion_hecho'] ?? '',
                    'fecha_hecho'            => $fechaTexto,
                    'trabajador_notifico'    => false,
                    'detalle_notificacion'   => null,
                    'evidencias_disponibles' => !empty($partes) ? implode('. ', $partes) : null,
                ],
                empresaId: (int) $empresaId,
                nombreTrabajador: $trabajador?->nombre_completo ?? 'el trabajador',
                cargo: $trabajador?->cargo ?? 'No especificado',
                trabajadorId: (int) $trabajadorId,
            );

            $this->data['hechos_ia'] = $resultado['hechos'];
            $this->datosExtraidos    = $resultado;
            $this->chatListo         = true;
        } catch (\Exception $e) {
            Log::error('Error al generar hechos desde formulario', ['error' => $e->getMessage()]);
            Notification::make()->danger()
                ->title('Error al conectar con la IA')
                ->body('No se pudo generar la descripción. Intente nuevamente.')
                ->send();
        } finally {
            $this->generandoHechos = false;
        }
    }

    private function buildContextoFormulario(): array
    {
        $contexto    = [];
        $empresaId = $this->data['empresa_id'] ?? null;
        if ($empresaId) {
            $e = Empresa::find($empresaId);
            if ($e) $contexto['empresa_nombre'] = $e->razon_social;
        }

        $quienReporta = $this->data['quien_reporta'] ?? null;
        if ($quienReporta) {
            $contexto['quien_reporta'] = $quienReporta;
        }

        $trabajadorId = $this->data['trabajador_id'] ?? null;
        if ($trabajadorId) {
            $t = Trabajador::find($trabajadorId);
            if ($t) {
                $contexto['trabajador_nombre'] = $t->nombre_completo;
                $contexto['trabajador_cargo']  = $t->cargo ?? '';
                $procesos = $t->procesosDisciplinarios()->count();
                $contexto['reincidente'] = $procesos > 0
                    ? "Sí - tiene {$procesos} proceso(s) disciplinario(s) previo(s)"
                    : 'No - primer proceso disciplinario';
            }
        }

        if (!empty($this->data['fecha_hecho'])) {
            $contexto['fecha_hecho'] = Carbon::parse($this->data['fecha_hecho'])
                ->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

            // Fechas adicionales (el hecho pudo repetirse en más de un día laboral):
            // se anexan para que la IA (redacción y feedback de dictado) las tenga en
            // cuenta y no asuma que ocurrió una sola vez.
            $fechasAdicionales = collect($this->data['fechas_ocurrencia_adicionales'] ?? [])
                ->pluck('fecha')
                ->filter()
                ->map(fn($f) => Carbon::parse($f)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
                ->values();

            if ($fechasAdicionales->isNotEmpty()) {
                $contexto['fecha_hecho'] .= ', y también el ' . $fechasAdicionales->join(', el ');
            }
        }
        if (!empty($this->data['hora_aproximada_hecho'])) {
            // Carbon::parse() acepta 'H:i' y 'H:i:s' sin fallar
            $contexto['hora_hecho'] = \Carbon\Carbon::parse($this->data['hora_aproximada_hecho'])
                ->format('g:i A');
        }
        $enHorario = $this->data['en_horario_laboral'] ?? null;
        if ($enHorario) {
            $contexto['en_horario'] = match ($enHorario) {
                'si'      => 'Sí',
                'no'      => 'No',
                'parcial' => 'Parcialmente',
                default   => $enHorario,
            };
        }

        return $contexto;
    }

    public function obtenerFeedbackVoz(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');
        if (mb_strlen($texto) < 30) {
            $this->feedbackVoz = '';
            return;
        }
        $empresaId = (int) ($this->data['empresa_id'] ?? 0);
        $contexto  = $this->buildContextoFormulario();
        $this->feedbackVoz = app(EvaluacionHechosService::class)->darFeedbackDictado($texto, $empresaId, $contexto);
        if ($this->feedbackVoz) {
            // Para TTS solo hablar la primera oración (evitar leer el análisis completo)
            $primeraOracion = preg_split('/(?<=[.!?])\s+/', trim($this->feedbackVoz))[0] ?? $this->feedbackVoz;
            if (mb_strlen($primeraOracion) > 180) {
                $primeraOracion = mb_substr($primeraOracion, 0, 180) . '…';
            }
            $this->dispatch('hablar-feedback', texto: $primeraOracion);
        }
    }

    public function generarRedaccion(): void
    {
        $borrador = trim($this->data['descripcion_hecho'] ?? '');

        if (mb_strlen($borrador) < 10) {
            Notification::make()->warning()
                ->title('Escriba una idea primero')
                ->body('Describa brevemente lo que ocurrió y la IA generará la redacción completa.')
                ->send();
            return;
        }

        $this->mejorando = true;
        $empresaId = (int) ($this->data['empresa_id'] ?? 0);
        $contexto  = $this->buildContextoFormulario();

        try {
            $redaccion = app(EvaluacionHechosService::class)
                ->generarRedaccionCompleta($borrador, $empresaId, $contexto);

            $this->data['descripcion_hecho'] = $redaccion;
            $this->sugerenciasCompletado     = [];
            $this->analizarDescripcion();

            Notification::make()->success()
                ->title('Redacción generada')
                ->body('Revise el texto y ajuste los detalles específicos si es necesario.')
                ->send();
        } catch (\Exception $e) {
            Log::error('generarRedaccion', ['error' => $e->getMessage()]);
            Notification::make()->danger()
                ->title('Error al generar redacción')
                ->body($e->getMessage())
                ->send();
        } finally {
            $this->mejorando = false;
        }
    }

    public function aplicarSugerencia(string $marker, string $valor): void
    {
        $texto = $this->data['descripcion_hecho'] ?? '';
        $this->data['descripcion_hecho'] = str_replace($marker, $valor, $texto);

        // Quitar esta sugerencia de la lista
        $this->sugerenciasCompletado = array_values(
            array_filter($this->sugerenciasCompletado, fn($s) => $s['marker'] !== $marker)
        );

        // Cuando la lista de sugerencias queda vacía, comprobar si aún hay marcadores en el texto
        if (empty($this->sugerenciasCompletado)) {
            $t = $this->data['descripcion_hecho'];

            if (preg_match('/\[COMPLETAR:/i', $t)) {
                // Quedan marcadores sin sugerencias - regenerar sin exclusiones de contexto
                // para garantizar que todos sean presentados al usuario
                $nuevas = app(EvaluacionHechosService::class)->generarSugerenciasCompletado($t, []);
                $this->sugerenciasCompletado = $nuevas;

                // Si la IA aún no devuelve nada (fallo de servicio), limpiar igual
                if (empty($this->sugerenciasCompletado)) {
                    $t = preg_replace('/\[COMPLETAR:\s*(?:[^\[\]]+|\[[^\]]*\])+\]/iu', '', $t);
                    $t = preg_replace('/\[[^\[\]]*\]/u', '', $t);
                    $t = str_replace(']]', '', $t);
                    $t = trim(preg_replace('/[ \t]{2,}/', ' ', $t));
                    $this->data['descripcion_hecho'] = $t;
                }
            } else {
                // No quedan marcadores - limpiar residuos menores (]] sueltos, etc.)
                $t = preg_replace('/\[[^\[\]]*\]/u', '', $t);
                $t = str_replace(']]', '', $t);
                $t = trim(preg_replace('/[ \t]{2,}/', ' ', $t));
                $this->data['descripcion_hecho'] = $t;
            }
        }

        $this->analizarDescripcion();
    }

    /**
     * Versión automática de la acción "clasificarGravedadIA": se dispara sola
     * cuando el usuario deja de escribir un momento (ver ->afterStateUpdated()
     * de descripcion_hecho), en vez de esperar a que la persona haga clic en
     * el botón. Misma llamada a IADescargoService::clasificarIncidente() que
     * el botón manual, pero silenciosa (sin notificaciones) para no
     * interrumpir cada vez que el texto se estabiliza, y con dos guardas para
     * no gastar la IA de más: longitud mínima más alta que la del botón
     * manual, y un hash del último texto ya clasificado para no repetir la
     * llamada si el texto no cambió.
     */
    public function autoClasificarGravedad(): void
    {
        $hechos = trim(strip_tags($this->data['descripcion_hecho'] ?? ''));
        $trabajadorId = $this->data['trabajador_id'] ?? null;
        $empresaId = $this->data['empresa_id'] ?? null;

        if (!$trabajadorId || !$empresaId || mb_strlen($hechos) < 60) {
            return;
        }

        $hash = md5($hechos);
        if ($this->ultimoTextoAutoClasificado === $hash) {
            return;
        }
        $this->ultimoTextoAutoClasificado = $hash;

        $trabajador = Trabajador::find($trabajadorId);

        $fechas = [];
        if (!empty($this->data['fecha_hecho'])) {
            $fechas[] = Carbon::parse($this->data['fecha_hecho'])->format('Y-m-d');
        }
        foreach ($this->data['fechas_ocurrencia_adicionales'] ?? [] as $item) {
            if (!empty($item['fecha'])) {
                $fechas[] = Carbon::parse($item['fecha'])->format('Y-m-d');
            }
        }

        try {
            $resultado = app(\App\Services\IADescargoService::class)->clasificarIncidente([
                'empresa_id'        => (int) $empresaId,
                'trabajador_id'     => (int) $trabajadorId,
                'cargo'             => $trabajador?->cargo ?? 'No especificado',
                'hechos'            => $this->data['descripcion_hecho'] ?? '',
                'fechas_ocurrencia' => $fechas,
                'motivos_rit'       => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al auto-clasificar gravedad del incidente con IA', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $this->data['clasificacion_incidente_ia'] = json_encode($resultado);
    }

    public function analizarDescripcion(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');

        if (mb_strlen($texto) < 15) {
            $this->analisisDescripcion = [];
            return;
        }

        // Texto normalizado: sin tildes ni mayúsculas - tolerante a mala ortografía
        $tn = mb_strtolower(strtr($texto, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'ä' => 'a',
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'à' => 'a',
            'è' => 'e',
            'ì' => 'i',
            'ò' => 'o',
            'ù' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
            'ç' => 'c',
            'Ç' => 'c',
        ]));

        // ── 1. Detectar categoría preliminar de la falta ──────────────────────
        $categoria = null;
        if (preg_match('/\b(llego\s+tarde|tardanza|impuntual|no\s+asistio|falto\s+(al\s+)?trabajo|ausencia\s+injustificada|abandono\s+el?\s+puesto|no\s+se\s+presento|retraso)\b/u', $tn)) {
            $categoria = 'Ausentismo o impuntualidad';
        } elseif (preg_match('/\b(golpeo|agredio|agresion|amenazo|insulto|maltrato|ofendio|violencia|pelea|empujo)\b/u', $tn)) {
            $categoria = 'Violencia o maltrato laboral';
        } elseif (preg_match('/\b(robo|hurto|sustrajo|se\s+llevo|apropio|dinero|efectivo)\b/u', $tn)) {
            $categoria = 'Hurto o apropiación indebida';
        } elseif (preg_match('/\b(se\s+nego|no\s+acato|desobedecio|ignoro\s+(la\s+)?orden|insubordinacion|descato)\b/u', $tn)) {
            $categoria = 'Insubordinación';
        } elseif (preg_match('/\b(acoso|hostigo|intimido|discrimino)\b/u', $tn)) {
            $categoria = 'Acoso o discriminación';
        } elseif (preg_match('/\b(incumplio|omitio|violo\s+(el\s+)?reglamento|no\s+siguio\s+(el\s+)?protocolo|incumplimiento\s+(de\s+)?normas)\b/u', $tn)) {
            $categoria = 'Incumplimiento normativo';
        }

        // ── 2. Nivel de detalle ───────────────────────────────────────────────
        $longitud = mb_strlen($texto);
        if ($longitud >= 150) {
            $detalleOk  = true;
            $detalleTxt = 'Descripción detallada - la IA tendrá buen contexto';
        } elseif ($longitud >= 70) {
            $detalleOk  = false;
            $detalleTxt = 'Agregue más contexto: consecuencias, antecedentes o reincidencia';
        } else {
            $detalleOk  = false;
            $detalleTxt = 'Descripción muy breve - amplíe con más detalles';
        }

        // ── 3. Acción concreta del trabajador ─────────────────────────────────
        $tieneAccion = $detalleOk || (bool) preg_match(
            '/\b(llego|falto|no\s+asistio|golpeo|agredio|robo|hurto|sustrajo|tomo|omitio|incumplio|abandono|se\s+nego|irrespeto|insulto|amenazo|tardo|descato|acoso|hostigo|intimido|realizo|efectuo|cometio|ejecuto|envio|procedio|manifesto|exhibio|pego|ataco|maltrato|ofendio|abuso|violo|discrimino)\b/u',
            $tn
        );

        // ── 4. Groserías, insultos y calificativos despectivos ────────────────
        // Pasada 1 - raíces que NUNCA son aceptables, incluso dentro de
        // palabras compuestas (ej: "setentahijueputa", "dobleverga").
        // Sin \b para capturar compuestos numéricos o prefijados.
        $raicesVulgares =
            'hijueputa|hijueputo|hijuemadre|hijueperra|'  // variantes de "hijo de puta"
            . 'malparido|malparida|'
            . 'gonorrea|'
            . 'pirobo|piroba|'
            . 'mierda|'
            . 'verga|'
            . 'mamahuevo|mamaguevo|'
            . 'coño|'          // "cono" va con \b (evita falso en "conocimiento")
            . 'pajuo|pajua|'
            . 'berriondo|berrionda|'
            . 'monda|'                                     // vulgar colombiano
            . 'hp|';                                       // abreviatura siempre vulgar

        $tieneGroserias  = false;
        $palabraGroseria = null;

        foreach (explode('|', rtrim($raicesVulgares, '|')) as $raiz) {
            if (preg_match('/(' . preg_quote($raiz, '/') . ')/u', $tn, $m)) {
                $tieneGroserias  = true;
                $palabraGroseria = $m[1];
                break;
            }
        }

        // Pasada 2 - insultos que requieren límite de palabra
        if (!$tieneGroserias) {
            $pat2 = '/\b(cono|hp|sopenco|sopencos|lambon|lambona|'
                . 'arrecho|arrecha|marico|marica|maricon|'
                . 'puta|puto|pendejo|pendeja|imbecil|idiota|estupido|estupida|'
                . 'culo|culero|culera|cabron|cabrona|jodido|jodida|'
                . 'bastardo|bastarda|cretino|cretina|guevon|huevon|huebon|'
                . 'carajo|degenerado|degenerada|pervertido|pervertida|'
                . 'mamarracho|mamarracha|baboso|babosa|'
                . 'desgraciado|desgraciada|maldito|maldita|miserable|'
                . 'tonto|tonta|burro|burra|bruto|bruta|'
                . 'vago|vaga|flojo|floja|holgazan|holgazana|'
                . 'inepto|inepta|inservible|torpe|zoquete|'
                . 'necio|necia|bobo|boba|bestia|animal'
                . ')\b/u';
            if (preg_match($pat2, $tn, $m)) {
                $tieneGroserias  = true;
                $palabraGroseria = $m[1];
            }
        }

        // ── 5. Lenguaje presuntivo vs declarativo ─────────────────────────────
        $verbosGraves =
            'acoso|hostigo|hostigaron|intimido|robo|hurto|sustrajo|'
            . 'golpeo|agredio|pego|ataco|amenazo|insulto|ofendio|maltrato|'
            . 'abuso|violo|discrimino|acoso\s+sexualmente|agredio\s+fisicamente';

        $tienePresuntivo = (bool) preg_match(
            '/\b(presuntamente|presunta\s+conducta|al\s+parecer|supuestamente|'
                . 'segun\s+(lo\s+)?report[ao]|aparentemente|se\s+alega|se\s+presume|habria|'
                . 'habria\s+cometido|se\s+le\s+atribuye|se\s+le\s+imputa)\b/u',
            $tn
        );

        $verboGraveEncontrado = null;
        if (preg_match("/\b({$verbosGraves})\b/u", $tn, $m)) {
            $verboGraveEncontrado = $m[1];
        }
        $tieneVerbosGraves  = $verboGraveEncontrado !== null;
        $necesitaPresuntivo = $tieneVerbosGraves && !$tienePresuntivo;

        // ── 6. Lenguaje discriminatorio ───────────────────────────────────────
        // Detecta referencias a características protegidas usadas como descriptor
        // del trabajador. Se organiza por categoría para dar un mensaje preciso.
        $categoriasDiscriminacion = [

            'raza o etnia' => [
                'negro',
                'negra',
                'negroto',
                'negrota',
                'negrito',
                'negrita',
                'indio',
                'india',
                'indigena',
                'indigeno',
                'zambo',
                'zamba',
                'mulato',
                'mulata',
                'mulatillo',
                'mulatilla',
                'afro',
                'afrocolombiano',
                'afrodescendiente',
                'afrovenezolano',
                'mestizo',
                'mestiza',
                'moreno',
                'morena',               // descriptor racial peyorativo
                'trigueño',
                'trigueña',
                'gringo',
                'gringa',               // extranjero/racial
                'cholo',
                'chola',                 // peyorativo andino
                'montañero',
                'montañera',         // peyorativo regional colombiano
            ],

            'orientación sexual o identidad de género' => [
                'gay',
                'lesbiana',
                'bisexual',
                'travesti',
                'travestido',
                'travestida',
                'transexual',
                'transgenero',
                'transgenerista',
                'transgenerismo',
                'homosexual',
                'heterosexual',
                'queer',
                'intersexual',
            ],

            'discapacidad física' => [
                'invalido',
                'invalida',
                'inválido',
                'inválida',
                'lisiado',
                'lisiada',
                'tullido',
                'tullida',
                'cojo',
                'coja',
                'cojito',
                'cojita',
                'manco',
                'manca',
                'ciego',
                'ciega',
                'ceguito',
                'ceguita',
                'sordo',
                'sorda',
                'sordito',
                'sordita',
                'mudo',
                'muda',
                'jorobado',
                'jorobada',
                'paralitico',
                'paralitica',
                'minusvalido',
                'minusvalida',
                'discapacitado',
                'discapacitada',
                'postrado',
                'postrada',
                'tetraplejico',
                'paraplejico',
            ],

            'discapacidad cognitiva o mental' => [
                'retrasadito',
                'mongolito',
                'mongolita',
                'mogolico',
                'mogolica',
                'mongol',
                'tarado',
                'tarada',
                'demente',
                'dementado',
                'loco',
                'loca',
                'locazo',
                'locaza',     // cuando se usa como etiqueta
                'chiflado',
                'chiflada',
                'perturbado',
                'perturbada',
                'autista',                           // usado peyorativamente
                'esquizofrenico',
                'esquizofrenica',
            ],

            'religión o creencia' => [
                'judio',
                'judia',
                'judío',
                'judía',
                'islamico',
                'islamica',
                'musulman',
                'musulmana',
                'evangelico',
                'evangelica',           // a veces usado pejorativamen
                'ateo',
                'atea',
                'pagano',
                'pagana',
            ],

            'origen nacional' => [
                'venezolano',
                'venezolana',           // usado peyorativamente
                'extranjero',
                'extranjera',
                'clandestino',
                'clandestina',         // migrante indocumentado peyorativo
                'mojado',
                'mojada',                   // migrante peyorativo
                'veneco',
                'veneca',
                'venecos',
                'venecas',           // peyorativo muy común para migrantes venezolanos en Colombia
                'beneco',
                'beneca',
                'benecos',
                'benecas',           // variante ortográfica de veneco
                'chamo',
                'chama',
                'chamos',
                'chamas',               // jerga venezolana usada peyorativamente en Colombia
                'veneco invasor',
                'veneca invasora',              // frase xenofóbica
            ],

            'apariencia física' => [
                'gordo',
                'gorda',                     // cuando se usa como etiqueta insultante
                'enano',
                'enana',                     // peyorativo para estatura baja
                'narigon',
                'narigona',                // peyorativo facial
                'carecuadrado',                      // insulto colombiano de apariencia
            ],
        ];

        $categoriaDiscriminadaEncontrada = null;
        $terminoDiscriminatorioEncontrado = null;
        foreach ($categoriasDiscriminacion as $nombreCategoria => $terminos) {
            foreach ($terminos as $termino) {
                if (preg_match('/\b' . preg_quote($termino, '/') . '\b/u', $tn)) {
                    $categoriaDiscriminadaEncontrada  = $nombreCategoria;
                    $terminoDiscriminatorioEncontrado = $termino;
                    break 2;
                }
            }
        }
        $tieneDiscriminacion = $categoriaDiscriminadaEncontrada !== null;

        // ── Check IA automático (throttle: máximo 1 llamada cada 5 segundos) ──
        // Solo corre si los patrones no detectaron nada (para no hacer la llamada
        // cuando ya tenemos certeza), el texto es suficientemente largo, y no hay
        // una llamada en curso.
        $ahora = time();
        $debeVerificarIA = !$tieneDiscriminacion
            && !$tieneGroserias
            && mb_strlen($texto) >= 30
            && !$this->verificandoDiscriminacion
            && ($ahora - $this->tsUltimaVerifDiscriminacion) >= 5;

        if ($debeVerificarIA) {
            $this->tsUltimaVerifDiscriminacion = $ahora; // bloquea llamadas concurrentes
            $this->verificandoDiscriminacion   = true;
            try {
                $resultado = app(\App\Services\EvaluacionHechosService::class)
                    ->verificarDiscriminacion($texto);
                $this->discriminacionIAOk         = $resultado['ok'];
                $this->discriminacionIACategoria  = $resultado['categoria'] ?? '';
                $this->discriminacionIATermino    = $resultado['termino'] ?? '';
                $this->discriminacionIASugerencia = $resultado['sugerencia'] ?? '';
            } catch (\Exception $e) {
                // fail-safe: mantiene el resultado anterior
            } finally {
                $this->verificandoDiscriminacion = false;
            }
        }

        // Resetear resultado de IA si los patrones ya detectaron algo
        // (innecesario seguir esperando confirmación de la IA)
        if ($tieneDiscriminacion || $tieneGroserias) {
            $this->discriminacionIAOk = true;
        }

        $this->analisisDescripcion = [
            [
                'tipo'  => 'discriminacion',
                'ok'    => !$tieneDiscriminacion && $this->discriminacionIAOk,
                'texto' => (function () use ($tieneDiscriminacion, $categoriaDiscriminadaEncontrada, $terminoDiscriminatorioEncontrado): string {
                    if ($tieneDiscriminacion) {
                        return "Lenguaje discriminatorio: la palabra \"{$terminoDiscriminatorioEncontrado}\" hace referencia a {$categoriaDiscriminadaEncontrada} - esto no debe incluirse en la descripción del hecho. Viola jurisprudencia antidiscriminatoria y puede invalidar el proceso.";
                    }
                    if (!$this->discriminacionIAOk && $this->discriminacionIACategoria) {
                        $msg = "La IA detectó lenguaje discriminatorio";
                        if ($this->discriminacionIATermino) $msg .= ": \"{$this->discriminacionIATermino}\"";
                        $msg .= " - referencia a {$this->discriminacionIACategoria}. Esto puede invalidar el proceso disciplinario.";
                        if ($this->discriminacionIASugerencia) $msg .= " Sugerencia: {$this->discriminacionIASugerencia}.";
                        return $msg;
                    }
                    if ($this->verificandoDiscriminacion) {
                        return 'Verificando lenguaje con IA...';
                    }
                    return 'Sin referencias a características protegidas';
                })(),
            ],
            [
                'tipo'  => 'groserias',
                'ok'    => !$tieneGroserias,
                'texto' => $tieneGroserias
                    ? "Lenguaje inapropiado: la palabra \"{$palabraGroseria}\" no puede aparecer en un documento jurídico. Corrija o use \"Generar redacción con IA\"."
                    : 'Lenguaje apropiado para un documento jurídico',
            ],
            [
                'tipo'  => 'presuntivo',
                'ok'    => !$necesitaPresuntivo,
                'texto' => $necesitaPresuntivo
                    ? "El verbo \"{$verboGraveEncontrado}\" es una acusación directa - use \"presuntamente {$verboGraveEncontrado}\" para no afirmar como hecho probado algo que aún está en investigación."
                    : ($tieneVerbosGraves
                        ? 'Lenguaje presuntivo correcto - la acusación está bien calificada'
                        : 'Sin acusaciones graves que requieran calificación presuntiva'),
            ],
            [
                'tipo'  => 'categoria',
                'ok'    => $categoria !== null,
                'texto' => $categoria ?? 'Siga escribiendo para identificar la conducta',
                'badge' => $categoria,
            ],
            [
                'tipo'  => 'detalle',
                'ok'    => $detalleOk,
                'texto' => $detalleTxt,
            ],
            [
                'tipo'  => 'accion',
                'ok'    => $tieneAccion,
                'texto' => $tieneAccion
                    ? 'Acción del trabajador claramente descrita'
                    : 'Incluya el verbo que describe la acción del trabajador',
            ],
        ];
    }

    // Fuerza un check de IA inmediato (usado al generar redacción o al intentar avanzar)
    public function verificarDiscriminacionConIA(): void
    {
        $this->tsUltimaVerifDiscriminacion = 0; // resetea throttle para forzar ejecución
        $this->analizarDescripcion();            // el check IA corre dentro de analizarDescripcion
    }

    public function analizarQuienReporta(): void
    {
        $texto = trim($this->data['quien_reporta'] ?? '');

        if (mb_strlen($texto) < 2) {
            $this->feedbackQuienReporta   = '';
            $this->feedbackQuienReportaOk = false;
            return;
        }

        // Detectar respuestas vagas, subjetivas o incoherentes
        $esVago = (bool) preg_match(
            '/^\s*(no\s+s[eé]|no\s+recuerdo|no\s+se\s+sabe|nadie|alguien|cualquiera|una\s+persona|la\s+persona|quien\s+sea|no\s+aplica|n\/?a|desconozco|sin\s+informaci[oó]n|no\s+tengo\s+idea|no\s+hay|ninguno|ninguna|no\s+hay\s+quien|no\s+existe)\s*$/iu',
            $texto
        );

        if ($esVago) {
            $this->feedbackQuienReportaOk = false;
            $this->feedbackQuienReporta   =
                'Respuesta insuficiente. Debe indicar quién reportó concretamente. '
                . 'Especifique el cargo (ej: "Supervisor de producción"), el nombre si lo conoce '
                . '(ej: "Ana Torres, jefa de turno"), o la relación con el trabajador '
                . '(ej: "El empleador directamente", "Un compañero del área de logística").';
            return;
        }

        // Detectar si es demasiado corto sin rol ni nombre reconocible
        $tieneContexto = mb_strlen($texto) >= 10 || (bool) preg_match(
            '/\b(jefe|jefa|supervisor|supervisora|gerente|director|directora|empleador|empresa|recursos\s+humanos|rrhh|rr\.?\s*hh\.?|compañero|compañera|colega|cliente|proveedor|coordinador|coordinadora|encargado|encargada|responsable|vigilante|seguridad|área|cargo|departamento|sección|sr\.|sra\.|don|doña)\b/iu',
            $texto
        );

        if (!$tieneContexto) {
            $this->feedbackQuienReportaOk = false;
            $this->feedbackQuienReporta   =
                'Agregue más detalle: indique el cargo o rol de quien reporta '
                . '(ej: "Jefe de logística Carlos Ruiz") o su relación con el trabajador '
                . '(ej: "Compañero del mismo turno").';
            return;
        }

        $this->feedbackQuienReportaOk = true;
        $this->feedbackQuienReporta   = 'Información clara - la IA podrá identificar correctamente al reportante.';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Acciones y cabecera
    // ──────────────────────────────────────────────────────────────────────────

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\Action::make('tutorial')
    //             ->label('¿Necesitas ayuda?')
    //             ->icon('heroicon-o-question-mark-circle')
    //             ->color('gray')
    //             ->extraAttributes([
    //                 'data-tour' => 'help-button',
    //                 'onclick'   => 'window.iniciarTour(); return false;',
    //             ]),
    //     ];
    // }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Crear proceso y enviar citación')
            ->requiresConfirmation()
            ->modalHeading('Confirmar creación del proceso disciplinario')
            ->modalDescription(fn() => $this->getMensajeConfirmacion())
            ->modalSubmitActionLabel('Confirmar y crear')
            ->modalIcon('heroicon-o-paper-airplane')
            ->color('success');
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    private function getMensajeConfirmacion(): string
    {
        $trabajadorId = $this->data['trabajador_id'] ?? null;
        $trabajador   = $trabajadorId ? Trabajador::find($trabajadorId) : null;

        if ($trabajador && !empty($trabajador->email)) {
            return "Se creará el proceso disciplinario para {$trabajador->nombre_completo} y se enviará automáticamente la citación para la audiencia virtual al correo: {$trabajador->email}";
        }

        return '¿Está seguro que desea crear este proceso disciplinario?';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Draft persistence (session)
    // ──────────────────────────────────────────────────────────────────────────

    private function getDraftKey(): string
    {
        return 'proceso_draft_' . auth()->id();
    }

    public function mount(): void
    {
        parent::mount();

        // Bloqueo temprano para cliente/bufete (su empresa se conoce de entrada): si el
        // RIT es obligatorio y falta, se redirige a configurarlo antes de continuar.
        $user = auth()->user();
        if (in_array($user?->role, ['cliente', 'bufete'], true)) {
            $empresa = app(\App\Services\GuiaProcesoService::class)->empresaObjetivo($user);
            if ($empresa && $empresa->requiereRit() === true && ! $this->empresaTieneRit($empresa)) {
                Notification::make()
                    ->warning()
                    ->title('Primero configure el Reglamento Interno')
                    ->body('Su empresa está obligada a tener RIT (Art. 105 CST) y aún no lo ha cargado ni construido. No puede crear citaciones de descargos hasta configurarlo.')
                    ->persistent()
                    ->send();

                $this->redirect(\App\Filament\Admin\Pages\MiReglamentoInterno::getUrl());

                return;
            }
        }

        try {
            $draft = session($this->getDraftKey());
            if ($draft && is_array($draft) && !empty($draft)) {
                // Exclude file upload fields - they can't be restored from session
                unset($draft['evidencias_empleador']);
                $this->form->fill($draft);
            }
        } catch (\Throwable) {
            // If restore fails for any reason, just start fresh
        }
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data')) {
            $toSave = $this->data;
            unset($toSave['evidencias_empleador']);
            session([$this->getDraftKey() => $toSave]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Lifecycle hooks
    // ──────────────────────────────────────────────────────────────────────────

    /** ¿La empresa tiene un RIT cargado o construido? (misma noción que la guía). */
    protected function empresaTieneRit(\App\Models\Empresa $empresa): bool
    {
        // RIT vigente: activo y con contenido (coincide con la guía y Mi Reglamento).
        // Incluye fuente=mejora_ia a propósito: un RIT mejorado por la IA y ya
        // adoptado por el cliente ES su reglamento vigente, no algo a ignorar -
        // antes se excluía por error y bloqueaba la creación de descargos como
        // si la empresa no tuviera RIT (caso real: RENBEL 2.0).
        return \App\Models\ReglamentoInterno::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->whereNotNull('texto_completo')
            ->where('texto_completo', '!=', '')
            ->exists();
    }

    protected function beforeCreate(): void
    {
        // Bloqueo legal: si la empresa está obligada a tener RIT (Art. 105 CST) y aún
        // no lo ha cargado ni construido, no se puede crear la citación de descargos.
        $empresaId = $this->data['empresa_id'] ?? auth()->user()?->empresa_id;
        $empresa   = $empresaId ? \App\Models\Empresa::find($empresaId) : null;
        if ($empresa && $empresa->requiereRit() === true && ! $this->empresaTieneRit($empresa)) {
            Notification::make()
                ->warning()
                ->title('Reglamento Interno de Trabajo obligatorio')
                ->body('Esta empresa está obligada a tener RIT (Art. 105 CST) y aún no lo ha cargado ni construido. Configúrelo antes de crear citaciones de descargos.')
                ->persistent()
                ->send();

            $this->halt();
        }

        if (!$this->chatListo || empty($this->datosExtraidos['hechos'])) {
            Notification::make()
                ->warning()
                ->title('Descripción jurídica requerida')
                ->body('Debe generar la descripción jurídica en el paso de Revisión antes de crear el proceso.')
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verificación fotográfica de quien cita a descargos (equivalencia funcional de
        // firma, Ley 527/1999 Art. 7-8 y Decreto 2364/2012): es un campo oculto (base64),
        // así que el error de "requerido" no se mostraría al usuario. Se valida aquí con
        // un aviso claro ANTES de crear el registro.
        if (empty($data['foto_citante_base64'])) {
            Notification::make()
                ->danger()
                ->title('Falta la verificación fotográfica')
                ->body('Debe tomar la foto de verificación antes de crear la citación.')
                ->persistent()
                ->send();

            throw new Halt();
        }

        // Clientes tienen empresa_id oculto - asignarlo desde su perfil si no llegó
        if (empty($data['empresa_id'])) {
            $data['empresa_id'] = auth()->user()?->empresa_id;
        }

        $data['modalidad_descargos'] = 'virtual';
        $data['hechos'] = $data['hechos_ia'] ?? $this->datosExtraidos['hechos'] ?? '';

        // sanciones_laborales_ids: este wizard nunca tuvo el selector manual
        // "Motivo de los descargos" que sí existe al editar (ver
        // ProcesoDisciplinarioResource::formSchema()) - sin ese dato, la tabla
        // de sanciones del documento generado cae siempre al catálogo COMPLETO
        // del RIT en vez de mostrar solo la conducta del incidente. Se toma
        // automáticamente de clasificacion_incidente_ia.conducta_rit_aplicable
        // (ya validado en IADescargoService::clasificarIncidente() como copia
        // literal de una entrada real del catálogo - nunca inventado).
        $clasificacion = json_decode($data['clasificacion_incidente_ia'] ?? '', true);
        $conductaRit   = is_array($clasificacion) ? ($clasificacion['conducta_rit_aplicable'] ?? '') : '';
        if (is_string($conductaRit) && $conductaRit !== '') {
            $data['sanciones_laborales_ids'] = [$conductaRit];
        }

        // fecha_ocurrencia: preferir la fecha explícita del formulario
        if (!empty($data['fecha_hecho'])) {
            $data['fecha_ocurrencia'] = $data['fecha_hecho'];
        } elseif (!empty($this->datosExtraidos['fecha_ocurrencia'])) {
            $data['fecha_ocurrencia'] = $this->datosExtraidos['fecha_ocurrencia'];
        }

        // pruebas_iniciales: compilar desde testigos
        $pruebas = [];

        $testigos = $data['testigos'] ?? [];
        if (!empty($testigos)) {
            $textoTestigos = collect($testigos)
                ->map(fn($t) => ($t['nombre'] ?? '') . (isset($t['cargo']) ? " ({$t['cargo']})" : ''))
                ->filter()->join(', ');
            $pruebas[] = 'Testigos: ' . $textoTestigos;
        }

        if (!empty($pruebas)) {
            $data['pruebas_iniciales'] = implode("\n", $pruebas);
        }

        // Eliminar campos temporales del wizard
        unset(
            $data['descripcion_hecho'],
            $data['quien_reporta'],
            $data['fecha_hecho'],
            $data['hora_aproximada_hecho'],
            $data['en_horario_laboral'],
            $data['tiene_evidencias'],
            $data['hubo_testigos'],
            $data['testigos'],
            $data['hechos_ia'],
            $data['fecha_temp_descargos'],
            $data['hora_temp_descargos'],
            $data['foto_citante_base64'],
            $data['citacion_declaracion_aceptada']
        );

        return $data;
    }

    /**
     * Fechas (Y-m-d) que NO son día laboral de la empresa, para deshabilitar en los
     * date pickers de "cuándo ocurrió". Los días laborales salen del RIT (o su
     * respaldo). Así el hecho solo puede fecharse en un día laboral y se omite la
     * pregunta de "¿ocurrió en horario laboral?".
     */
    public static function fechasInhabilesHecho($empresaId, int $mesesAtras = 18): array
    {
        $empresa = $empresaId ? \App\Models\Empresa::find($empresaId) : null;
        $set     = $empresa?->diasHabilesSet() ?? \App\Support\DiasHabiles::DEFECTO;

        $disabled = [];
        $fin      = now()->endOfDay();
        for ($d = now()->copy()->subMonths($mesesAtras)->startOfDay(); $d->lte($fin); $d->addDay()) {
            if (! in_array($d->dayOfWeekIso, $set, true)) {
                $disabled[] = $d->format('Y-m-d');
            }
        }

        return $disabled;
    }

    /** Texto de ayuda con los días laborales de la empresa para el picker de "cuándo ocurrió". */
    public static function textoDiasHabilesHecho($empresaId): string
    {
        $empresa = $empresaId ? \App\Models\Empresa::find($empresaId) : null;
        $texto   = $empresa ? \App\Support\DiasHabiles::texto($empresa->diasHabilesSet()) : 'Lunes a Viernes';

        return "Solo días laborales de la empresa ({$texto}); el hecho debió ocurrir en un día laboral.";
    }

    protected function afterCreate(): void
    {
        session()->forget($this->getDraftKey());

        $proceso = $this->record;

        // Persistir la foto de verificación de quien cita a descargos. Se hace aquí (no
        // en mutateFormDataBeforeCreate) porque el directorio necesita el ID real del
        // registro, que solo existe después de crearlo. citante_nombre/citante_cargo ya
        // se guardaron con el resto del registro (son campos normales del formulario).
        // $this->data conserva el estado crudo aunque mutateFormDataBeforeCreate ya haya
        // limpiado $data.
        $fotoPath = $this->guardarFotoVerificacion(
            $this->data['foto_citante_base64'] ?? null,
            "fotos-verificacion/citacion/{$proceso->id}",
        );
        if ($fotoPath) {
            $proceso->update(['foto_citante_path' => $fotoPath, 'foto_citante_en' => now()]);
        }

        if (
            !empty($proceso->fecha_descargos_programada) &&
            !empty($proceso->trabajador?->email) &&
            $proceso->modalidad_descargos === 'virtual'
        ) {
            // EN COLA (no síncrono): generar el PDF, crear la diligencia, generar
            // las preguntas iniciales con IA y enviar el correo puede tardar
            // 15-45+ segundos - suficiente para que el proxy/PHP-FPM de
            // producción corte la conexión antes de responder (mismo límite ya
            // diagnosticado para "RIT mejorado" y "Emitir Sanción"). Sin
            // confirmación clara, el usuario reintentaba el mismo formulario
            // creyendo que no había funcionado, creando procesos duplicados
            // para el mismo trabajador/incidente (caso real: PD-2026-0057/
            // 0058/0059). Ver App\Jobs\GenerarYEnviarCitacionJob.
            \App\Jobs\GenerarYEnviarCitacionJob::dispatch($proceso, auth()->id());

            Notification::make()
                ->success()
                ->title('Proceso creado')
                ->body('Estamos generando el PDF y enviando la citación en segundo plano. Le avisaremos aquí mismo cuando esté lista.')
                ->duration(6000)
                ->send();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers para DatePicker de días hábiles
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve todos los festivos del año en curso y del siguiente,
     * combinando festivos fijos, móviles (Ley Emiliani) y los de la BD.
     */
    protected static function getFestivosDatepicker(): array
    {
        $festivos = array_merge(
            self::calcularFestivosAnio(now()->year),
            self::calcularFestivosAnio(now()->year + 1)
        );

        // Agregar festivos adicionales registrados en la BD
        try {
            if (Schema::hasTable('dias_no_habiles')) {
                $bd = DiaNoHabil::pluck('fecha')
                    ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
                    ->toArray();
                $festivos = array_merge($festivos, $bd);
            }
        } catch (\Exception) {
        }

        return array_unique($festivos);
    }

    protected static function calcularFestivosAnio(int $year): array
    {
        // Festivos fijos
        $fijos = [
            "{$year}-01-01", // Año Nuevo
            "{$year}-05-01", // Día del Trabajo
            "{$year}-07-20", // Día de la Independencia
            "{$year}-08-07", // Batalla de Boyacá
            "{$year}-12-08", // Inmaculada Concepción
            "{$year}-12-25", // Navidad
        ];

        // Semana Santa (basados en Pascua)
        $pascua  = Carbon::createFromTimestamp(easter_date($year));
        $moviles = [
            $pascua->copy()->subDays(3)->format('Y-m-d'), // Jueves Santo
            $pascua->copy()->subDays(2)->format('Y-m-d'), // Viernes Santo
            $pascua->copy()->addDays(43)->format('Y-m-d'), // Ascensión
            $pascua->copy()->addDays(64)->format('Y-m-d'), // Corpus Christi
            $pascua->copy()->addDays(71)->format('Y-m-d'), // Sagrado Corazón
        ];

        // Festivos Ley Emiliani (se trasladan al lunes siguiente)
        $emiliani = [
            "{$year}-01-06", // Reyes Magos
            "{$year}-03-19", // San José
            "{$year}-06-29", // San Pedro y San Pablo
            "{$year}-08-15", // Asunción de la Virgen
            "{$year}-10-12", // Día de la Raza
            "{$year}-11-01", // Todos los Santos
            "{$year}-11-11", // Independencia de Cartagena
        ];

        $emilianiAjustados = [];
        foreach ($emiliani as $fecha) {
            $d = Carbon::parse($fecha);
            if (!$d->isMonday()) {
                $d = $d->next(Carbon::MONDAY);
            }
            $emilianiAjustados[] = $d->format('Y-m-d');
        }

        return array_merge($fijos, $moviles, $emilianiAjustados);
    }
}
