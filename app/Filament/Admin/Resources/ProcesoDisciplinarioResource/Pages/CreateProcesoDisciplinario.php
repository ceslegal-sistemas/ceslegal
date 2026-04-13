<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\DiaNoHabil;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Services\DocumentGeneratorService;
use App\Services\EvaluacionHechosService;
use App\Services\TerminoLegalService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Forms\Components\Wizard\Step;
use HusamTariq\FilamentTimePicker\Forms\Components\TimePickerField;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class CreateProcesoDisciplinario extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ProcesoDisciplinarioResource::class;

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

    // ──────────────────────────────────────────────────────────────────────────
    // Wizard steps
    // ──────────────────────────────────────────────────────────────────────────

    protected function getSteps(): array
    {
        return [
            // ── Paso 0: Bienvenida ────────────────────────────────────────────
            Step::make('bienvenida')
                ->label('Bienvenida')
                ->description('Lea antes de empezar')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\Placeholder::make('bienvenida_contenido')
                        ->label('')
                        ->content(fn () => new HtmlString(
                            view('filament.components.bienvenida-proceso')->render()
                        ))
                        ->columnSpanFull(),
                ]),

            // ── Paso 1: Empresa y Trabajador ─────────────────────────────────
            Step::make('trabajador')
                ->label('Trabajador')
                ->description('¿Con qué trabajador?')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_trabajador')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-trabajador-info', [
                                        'esCliente' => auth()->user()?->isCliente() ?? false,
                                    ])->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\Select::make('empresa_id')
                                ->label('¿A qué empresa pertenece el trabajador?')
                                ->relationship('empresa', 'razon_social')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->default(function () {
                                    $user = auth()->user();
                                    return $user && $user->isCliente() ? $user->empresa_id : null;
                                })
                                ->hidden(fn() => auth()->user()?->isCliente() ?? false)
                                // ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                                ->dehydrated()
                                ->afterStateUpdated(fn(Forms\Set $set) => $set('trabajador_id', null))
                                ->helperText('Seleccione la empresa primero — esto cargará la lista de trabajadores disponibles.')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('trabajador_id')
                                ->label('¿Cuál es el trabajador involucrado?')
                                ->placeholder('Buscar trabajador...')
                                ->required()
                                ->live()
                                ->searchable()
                                ->options(fn(Get $get): array =>
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
                                ->helperText(fn(Get $get) => $get('empresa_id')
                                    ? 'Si el trabajador no aparece en la lista, use el botón \'+\' para registrarlo.'
                                    : 'Primero seleccione la empresa para ver los trabajadores disponibles.'
                                )
                                ->suffixIcon('heroicon-o-user-group')
                                ->createOptionForm(function (Get $get) {
                                    return [
                                        Forms\Components\Placeholder::make('info_nuevo_trabajador')
                                            ->label('')
                                            ->content(new HtmlString(
                                                '<div style="background:rgba(99,102,241,.06);border-left:3px solid #6366f1;border-radius:.5rem;padding:.75rem 1rem;font-size:.8125rem;color:var(--fi-color-gray-600,#4b5563);line-height:1.6;">'
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
                                            ->numeric()
                                            ->maxLength(50)
                                            ->placeholder('Ej: 1234567890'),

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
                                ->content(function(Get $get) {
                                    $t = Trabajador::find($get('trabajador_id'));
                                    if (!$t) return '';
                                    return new HtmlString(
                                        view('filament.components.paso-trabajador-confirmado', ['trabajador' => $t])->render()
                                    );
                                })
                                ->columnSpanFull(),
                        ]),
                ]),

            // ── Paso 2: Cuándo y dónde ───────────────────────────────────────
            Step::make('cuando')
                ->label('Cuándo y dónde')
                ->description('Fecha, hora y lugar')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_cuando')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-cuando-info')->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\DatePicker::make('fecha_hecho')
                                ->label('¿Cuándo ocurrió?')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->helperText('Fecha en que ocurrió el hecho'),

                            TimePickerField::make('hora_aproximada_hecho')
                                ->label('Hora aproximada (opcional)')
                                ->helperText('Horario Colombia (UTC-5)'),

                            Forms\Components\TextInput::make('lugar_tipo')
                                ->label('¿Dónde ocurrió?')
                                ->placeholder('Ej: planta de producción, oficina de recursos humanos...')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('en_horario_laboral')
                                ->label('¿Ocurrió en horario laboral?')
                                ->options(['si' => 'Sí', 'no' => 'No'])
                                ->required()
                                ->inline()
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),

            // ── Paso 3: Hechos ───────────────────────────────────────────────
            Step::make('hechos')
                ->label('Hechos')
                ->description('¿Qué ocurrió?')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_hechos')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-hechos-info')->render()
                                ))
                                ->columnSpanFull(),

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
                                                    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
                                                    'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
                                                    'ñ'=>'n','Ñ'=>'n','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
                                                ]));
                                                // Bloquear groserías e insultos
                                                // Pasada 1: raíces siempre vulgares, sin \b (detecta compuestos)
                                                $raicesV = ['hijueputa','hijueputo','hijuemadre','hijueperra','malparido','malparida','gonorrea','pirobo','piroba','mierda','verga','mamahuevo','mamaguevo','coño','pajuo','pajua','berriondo','berrionda','monda','hp'];
                                                // 'cono' va en pasada 2 con \b para evitar falso positivo en "conocimiento"
                                                $hayGroseria = false;
                                                foreach ($raicesV as $r) { if (str_contains($tn, $r)) { $hayGroseria = true; break; } }
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
                                                    'raza o etnia'                         => ['negro','negra','negroto','negrota','negrito','negrita','indio','india','indigena','zambo','zamba','mulato','mulata','afro','afrocolombiano','afrodescendiente','mestizo','mestiza','moreno','morena','trigueño','trigueña','gringo','gringa','cholo','chola','montañero','montañera'],
                                                    'orientación sexual o identidad de género' => ['gay','lesbiana','bisexual','travesti','travestido','travestida','transexual','transgenero','transgenerista','homosexual','queer','intersexual'],
                                                    'discapacidad física'                  => ['invalido','invalida','impedido','impedida','lisiado','lisiada','tullido','tullida','cojo','coja','manco','manca','ciego','ciega','sordo','sorda','mudo','muda','jorobado','jorobada','paralitico','paralitica','minusvalido','minusvalida','discapacitado','discapacitada','postrado','postrada','tetraplejico','paraplejico'],
                                                    'discapacidad cognitiva o mental'      => ['retrasado','retrasada','mongolito','mongolita','mogolico','mogolica','mongol','tarado','tarada','demente','loco','loca','chiflado','chiflada','perturbado','perturbada','anormal','deficiente','autista','esquizofrenico','esquizofrenica'],
                                                    'religión'                             => ['judio','judia','musulman','musulmana','evangelico','evangelica'],
                                                    'origen nacional'                      => ['venezolano','venezolana','extranjero','extranjera','clandestino','clandestina','mojado','mojada'],
                                                ];
                                                $catDisc = null;
                                                foreach ($terminosProtegidos as $cat => $terminos) {
                                                    foreach ($terminos as $t) {
                                                        if (preg_match('/\b' . preg_quote($t, '/') . '\b/u', $tn)) { $catDisc = $cat; break 2; }
                                                    }
                                                }
                                                if ($catDisc) {
                                                    $fail("La descripción contiene una referencia a {$catDisc} del trabajador. Esto viola jurisprudencia antidiscriminatoria. Use \"Generar redacción con IA\" para corregirlo.");
                                                }
                                            },
                                        ])
                                        ->rows(7)
                                        ->placeholder('Ej: El trabajador llegó dos horas tarde sin avisar y no respondió los mensajes del supervisor...')
                                        ->live(debounce: 800)
                                        ->afterStateUpdated(fn($livewire) => $livewire->analizarDescripcion())
                                        ->hintActions([
                                            Forms\Components\Actions\Action::make('generar_redaccion')
                                                ->label(fn($livewire) => $livewire->mejorando ? 'Generando...' : 'Generar redacción con IA')
                                                ->icon('heroicon-m-sparkles')
                                                ->color('primary')
                                                ->tooltip('La IA generará una redacción profesional completa usando todos los datos del caso')
                                                ->disabled(fn(Get $get, $livewire) => $livewire->mejorando || mb_strlen($get('descripcion_hecho') ?? '') < 10)
                                                ->action(fn($livewire) => $livewire->generarRedaccion()),
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
                        ]),
                ]),

            // ── Paso 4: Pruebas (Evidencias + Testigos) ──────────────────────
            Step::make('pruebas')
                ->label('Pruebas')
                ->description('Evidencias y testigos del hecho')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_pruebas')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-pruebas-info')->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('tiene_evidencias')
                                ->label('¿Existe evidencia del hecho?')
                                ->options(['si' => 'Sí', 'no' => 'No'])
                                ->required()
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('evidencias_empleador')
                                ->label('Adjuntar archivos (opcional)')
                                ->helperText('Máx. 5 archivos · 5 MB c/u · PDF, imágenes, Word.')
                                ->multiple()
                                ->maxFiles(5)
                                ->maxSize(5120)
                                ->disk('public')
                                ->directory('evidencias')
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'image/jpeg', 'image/png', 'image/webp',
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

            // ── Paso 5: Revisión y envío ─────────────────────────────────────
            Step::make('revision')
                ->label('Revisión')
                ->description('Confirme y genere la citación')
                ->icon('heroicon-o-check-circle')
                ->schema([

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
                                        'lugar_tipo'       => $get('lugar_tipo'),
                                        'lugar_libre'      => $get('lugar_libre'),
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
                    Forms\Components\Section::make('Descripción jurídica')
                        ->description('La IA redacta los hechos en lenguaje formal para el expediente disciplinario.')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('generar_hechos')
                                    ->label(fn($livewire) => $livewire->generandoHechos ? 'Generando...' : 'Generar descripción jurídica')
                                    ->icon('heroicon-m-sparkles')
                                    ->color('primary')
                                    ->disabled(fn($livewire) => $livewire->generandoHechos)
                                    ->action(fn($livewire) => $livewire->generarHechos()),
                            ])->fullWidth()->columnSpanFull(),

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
                                ->live()
                                ->displayFormat('d/m/Y')
                                ->minDate(function (Get $get) {
                                    // Super admin puede seleccionar desde hoy
                                    if (auth()->user()?->hasRole('super_admin')) {
                                        return now()->startOfDay();
                                    }

                                    $empresaId     = $get('empresa_id');
                                    $empresa       = $empresaId ? Empresa::find($empresaId) : null;
                                    $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                    if ($trabajaSabados) {
                                        // Contar 6 días hábiles: lunes–sábado, excluir domingos y festivos
                                        $fecha    = now()->copy();
                                        $contados = 0;
                                        $festivos = self::getFestivosDatepicker();

                                        while ($contados < 6) {
                                            $fecha->addDay();
                                            if (!$fecha->isSunday() && !in_array($fecha->format('Y-m-d'), $festivos)) {
                                                $contados++;
                                            }
                                        }
                                        return $fecha->startOfDay();
                                    }

                                    // Lunes–Viernes: usa TerminoLegalService
                                    return app(TerminoLegalService::class)
                                        ->calcularFechaVencimiento(now(), 6)
                                        ->startOfDay();
                                })
                                ->maxDate(fn() => now()->addMonth()->endOfDay())
                                ->disabledDates(function (Get $get) {
                                    $deshabilitadas = [];
                                    $inicio         = now()->startOfDay();
                                    $fin            = now()->addYear();

                                    $empresaId     = $get('empresa_id');
                                    $empresa       = $empresaId ? Empresa::find($empresaId) : null;
                                    $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                    $festivos = self::getFestivosDatepicker();

                                    for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
                                        if ($trabajaSabados) {
                                            // Solo domingos bloqueados
                                            if ($d->isSunday()) {
                                                $deshabilitadas[] = $d->format('Y-m-d');
                                            }
                                        } else {
                                            // Sábados y domingos bloqueados
                                            if ($d->isWeekend()) {
                                                $deshabilitadas[] = $d->format('Y-m-d');
                                            }
                                        }
                                    }

                                    return array_unique(array_merge($deshabilitadas, $festivos));
                                })
                                ->helperText(function (Get $get) {
                                    $empresaId     = $get('empresa_id');
                                    $empresa       = $empresaId ? Empresa::find($empresaId) : null;
                                    $trabajaSabados = $empresa?->trabajaSabados() ?? false;

                                    return $trabajaSabados
                                        ? 'Domingos y festivos no disponibles (mínimo 6 días hábiles)'
                                        : 'Fines de semana y festivos no disponibles (mínimo 6 días hábiles)';
                                }),

                            TimePickerField::make('hora_descargos_programada')
                                ->label('Hora de la audiencia')
                                ->required()
                                ->helperText('Horario Colombia (UTC-5)'),
                        ])
                        ->columns(2),

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

            // Construir texto de lugar
            $lugarLabels = [
                'planta'         => 'Planta de producción',
                'oficina'        => 'Oficina',
                'sede_principal' => 'Sede principal',
                'bodega'         => 'Bodega / Almacén',
                'externo'        => 'Lugar externo a la empresa',
                'virtual'        => 'Entorno virtual / remoto',
            ];
            $lugarTipo  = $this->data['lugar_tipo'] ?? null;
            $lugarHecho = $lugarTipo === 'otro'
                ? ($this->data['lugar_libre'] ?? null)
                : ($lugarLabels[$lugarTipo] ?? null);

            // Construir texto de evidencias y contexto adicional
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

            $resultado = app(EvaluacionHechosService::class)->generarHechosDesdeFormulario(
                datosFormulario: [
                    'descripcion_hecho'      => $this->data['descripcion_hecho'] ?? '',
                    'fecha_hecho'            => $this->data['fecha_hecho'] ?? '',
                    'lugar_hecho'            => $lugarHecho,
                    'trabajador_notifico'    => false,
                    'detalle_notificacion'   => null,
                    'evidencias_disponibles' => !empty($partes) ? implode('. ', $partes) : null,
                ],
                empresaId:        (int) $empresaId,
                nombreTrabajador: $trabajador?->nombre_completo ?? 'el trabajador',
                cargo:            $trabajador?->cargo ?? 'No especificado',
                trabajadorId:     (int) $trabajadorId,
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
        $lugarLabels = [
            'planta'         => 'Planta de producción',
            'oficina'        => 'Oficina',
            'sede_principal' => 'Sede principal',
            'bodega'         => 'Bodega / Almacén',
            'externo'        => 'Lugar externo a la empresa',
            'virtual'        => 'Entorno virtual / remoto',
        ];

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
                    ? "Sí — tiene {$procesos} proceso(s) disciplinario(s) previo(s)"
                    : 'No — primer proceso disciplinario';
            }
        }

        if (!empty($this->data['fecha_hecho'])) {
            $contexto['fecha_hecho'] = Carbon::parse($this->data['fecha_hecho'])
                ->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        }
        if (!empty($this->data['hora_aproximada_hecho'])) {
            $contexto['hora_hecho'] = \Carbon\Carbon::createFromFormat('H:i:s', $this->data['hora_aproximada_hecho'])
                ->format('g:i A');
        }
        $lugarTipo = $this->data['lugar_tipo'] ?? null;
        if ($lugarTipo) {
            $contexto['lugar'] = $lugarTipo === 'otro'
                ? ($this->data['lugar_libre'] ?? '')
                : ($lugarLabels[$lugarTipo] ?? $lugarTipo);
        }
        $enHorario = $this->data['en_horario_laboral'] ?? null;
        if ($enHorario) {
            $contexto['en_horario'] = match($enHorario) {
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
                // Quedan marcadores sin sugerencias — regenerar sin exclusiones de contexto
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
                // No quedan marcadores — limpiar residuos menores (]] sueltos, etc.)
                $t = preg_replace('/\[[^\[\]]*\]/u', '', $t);
                $t = str_replace(']]', '', $t);
                $t = trim(preg_replace('/[ \t]{2,}/', ' ', $t));
                $this->data['descripcion_hecho'] = $t;
            }
        }

        $this->analizarDescripcion();
    }

    public function analizarDescripcion(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');

        if (mb_strlen($texto) < 15) {
            $this->analisisDescripcion = [];
            return;
        }

        // Texto normalizado: sin tildes ni mayúsculas — tolerante a mala ortografía
        $tn = mb_strtolower(strtr($texto, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
            'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
            'ñ'=>'n','Ñ'=>'n','ç'=>'c','Ç'=>'c',
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
            $detalleTxt = 'Descripción detallada — la IA tendrá buen contexto';
        } elseif ($longitud >= 70) {
            $detalleOk  = false;
            $detalleTxt = 'Agregue más contexto: consecuencias, antecedentes o reincidencia';
        } else {
            $detalleOk  = false;
            $detalleTxt = 'Descripción muy breve — amplíe con más detalles';
        }

        // ── 3. Acción concreta del trabajador ─────────────────────────────────
        $tieneAccion = $detalleOk || (bool) preg_match(
            '/\b(llego|falto|no\s+asistio|golpeo|agredio|robo|hurto|sustrajo|tomo|omitio|incumplio|abandono|se\s+nego|irrespeto|insulto|amenazo|tardo|descato|acoso|hostigo|intimido|realizo|efectuo|cometio|ejecuto|envio|procedio|manifesto|exhibio|pego|ataco|maltrato|ofendio|abuso|violo|discrimino)\b/u',
            $tn
        );

        // ── 4. Groserías, insultos y calificativos despectivos ────────────────
        // Pasada 1 — raíces que NUNCA son aceptables, incluso dentro de
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

        // Pasada 2 — insultos que requieren límite de palabra
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
                'negro','negra','negroto','negrota','negrito','negrita',
                'indio','india','indigena','indigeno',
                'zambo','zamba','mulato','mulata','mulatillo','mulatilla',
                'afro','afrocolombiano','afrodescendiente','afrovenezolano',
                'mestizo','mestiza',
                'moreno','morena',               // descriptor racial peyorativo
                'trigueño','trigueña',
                'gringo','gringa',               // extranjero/racial
                'cholo','chola',                 // peyorativo andino
                'montañero','montañera',         // peyorativo regional colombiano
            ],

            'orientación sexual o identidad de género' => [
                'gay','lesbiana','bisexual',
                'travesti','travestido','travestida',
                'transexual','transgenero','transgenerista','transgenerismo',
                'homosexual','heterosexual',
                'queer','intersexual',
            ],

            'discapacidad física' => [
                'invalido','invalida','inválido','inválida',
                'impedido','impedida',
                'lisiado','lisiada',
                'tullido','tullida',
                'cojo','coja','cojito','cojita',
                'manco','manca',
                'ciego','ciega','ceguito','ceguita',
                'sordo','sorda','sordito','sordita',
                'mudo','muda',
                'jorobado','jorobada',
                'paralitico','paralitica',
                'minusvalido','minusvalida',
                'discapacitado','discapacitada',
                'postrado','postrada',
                'tetraplejico','paraplejico',
            ],

            'discapacidad cognitiva o mental' => [
                'retrasado','retrasada','retraso','retrasadito',
                'mongolito','mongolita','mogolico','mogolica','mongol',
                'tarado','tarada',
                'demente','dementado',
                'loco','loca','locazo','locaza',     // cuando se usa como etiqueta
                'chiflado','chiflada',
                'perturbado','perturbada',
                'anormal',
                'deficiente',                        // deficiencia mental peyorativo
                'autista',                           // usado peyorativamente
                'esquizofrenico','esquizofrenica',
            ],

            'religión o creencia' => [
                'judio','judia','judío','judía',
                'islamico','islamica',
                'musulman','musulmana',
                'evangelico','evangelica',           // a veces usado pejorativamen
                'ateo','atea',
                'pagano','pagana',
            ],

            'origen nacional' => [
                'venezolano','venezolana',           // usado peyorativamente
                'extranjero','extranjera',
                'clandestino','clandestina',         // migrante indocumentado peyorativo
                'mojado','mojada',                   // migrante peyorativo
                'veneco','veneca','venecos','venecas',           // peyorativo muy común para migrantes venezolanos en Colombia
                'beneco','beneca','benecos','benecas',           // variante ortográfica de veneco
                'chamo','chama','chamos','chamas',               // jerga venezolana usada peyorativamente en Colombia
                'veneco invasor','veneca invasora',              // frase xenofóbica
            ],

            'apariencia física' => [
                'gordo','gorda',                     // cuando se usa como etiqueta insultante
                'enano','enana',                     // peyorativo para estatura baja
                'narigon','narigona',                // peyorativo facial
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
                'texto' => (function() use ($tieneDiscriminacion, $categoriaDiscriminadaEncontrada, $terminoDiscriminatorioEncontrado): string {
                    if ($tieneDiscriminacion) {
                        return "Lenguaje discriminatorio: la palabra \"{$terminoDiscriminatorioEncontrado}\" hace referencia a {$categoriaDiscriminadaEncontrada} — esto no debe incluirse en la descripción del hecho. Viola jurisprudencia antidiscriminatoria y puede invalidar el proceso.";
                    }
                    if (!$this->discriminacionIAOk && $this->discriminacionIACategoria) {
                        $msg = "La IA detectó lenguaje discriminatorio";
                        if ($this->discriminacionIATermino) $msg .= ": \"{$this->discriminacionIATermino}\"";
                        $msg .= " — referencia a {$this->discriminacionIACategoria}. Esto puede invalidar el proceso disciplinario.";
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
                    ? "El verbo \"{$verboGraveEncontrado}\" es una acusación directa — use \"presuntamente {$verboGraveEncontrado}\" para no afirmar como hecho probado algo que aún está en investigación."
                    : ($tieneVerbosGraves
                        ? 'Lenguaje presuntivo correcto — la acusación está bien calificada'
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
        $this->feedbackQuienReporta   = 'Información clara — la IA podrá identificar correctamente al reportante.';
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

        try {
            $draft = session($this->getDraftKey());
            if ($draft && is_array($draft) && !empty($draft)) {
                // Exclude file upload fields — they can't be restored from session
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

    protected function beforeCreate(): void
    {
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
        $data['modalidad_descargos'] = 'virtual';
        $data['hechos'] = $data['hechos_ia'] ?? $this->datosExtraidos['hechos'] ?? '';

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
            $data['lugar_tipo'],
            $data['lugar_libre'],
            $data['en_horario_laboral'],
            $data['tiene_evidencias'],
            $data['hubo_testigos'],
            $data['testigos'],
            $data['hechos_ia'],
            $data['fecha_temp_descargos'],
            $data['hora_temp_descargos']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        session()->forget($this->getDraftKey());

        $proceso = $this->record;

        if (
            !empty($proceso->fecha_descargos_programada) &&
            !empty($proceso->trabajador->email) &&
            $proceso->modalidad_descargos === 'virtual'
        ) {
            try {
                $documentService = new DocumentGeneratorService();
                $resultado       = $documentService->generarYEnviarCitacion($proceso);

                if ($resultado['success']) {
                    $preguntasConIA   = $resultado['preguntas_ia_generadas'] ?? false;
                    $formatoDocumento = $resultado['formato_documento'] ?? 'pdf';

                    $mensaje = $preguntasConIA
                        ? 'La citación fue enviada automáticamente con link de acceso web y preguntas generadas por IA.'
                        : 'La citación fue enviada exitosamente, pero no se pudieron generar preguntas con IA. Deberá generarlas manualmente.';

                    if ($formatoDocumento === 'docx') {
                        $mensaje .= ' ADVERTENCIA: El documento fue enviado en formato DOCX (LibreOffice no está instalado).';
                    }

                    Notification::make()
                        ->success()
                        ->title('Proceso creado y citación enviada')
                        ->body($mensaje)
                        ->duration($preguntasConIA ? 5000 : 8000)
                        ->send();
                } else {
                    Notification::make()
                        ->warning()
                        ->title('Proceso creado con advertencia')
                        ->body('El proceso fue creado pero hubo un error al enviar la citación: ' . $resultado['message'])
                        ->duration(8000)
                        ->send();
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Proceso creado con advertencia')
                    ->body('El proceso fue creado pero hubo un error al enviar la citación automáticamente.')
                    ->duration(8000)
                    ->send();

                Log::error('Excepción al enviar citación automática', [
                    'proceso_id' => $proceso->id,
                    'error'      => $e->getMessage(),
                ]);
            }
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
        } catch (\Exception) {}

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
