<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Trabajador;
use App\Services\DocumentGeneratorService;
use App\Services\EvaluacionHechosService;
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
                                        ->pluck('nombre_completo', 'id')
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

                            Forms\Components\Select::make('lugar_tipo')
                                ->label('¿Dónde ocurrió?')
                                ->options([
                                    'planta'         => 'Planta de producción',
                                    'oficina'        => 'Oficina',
                                    'sede_principal' => 'Sede principal',
                                    'bodega'         => 'Bodega / Almacén',
                                    'externo'        => 'Lugar externo a la empresa',
                                    'virtual'        => 'Entorno virtual / remoto',
                                    'otro'           => 'Otro (especificar)',
                                ])
                                ->required()
                                ->native(false)
                                ->placeholder('Seleccione el lugar...')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('lugar_libre')
                                ->label('Especifique el lugar')
                                ->placeholder('Ej: bodega norte, área de carga...')
                                ->visible(fn(Get $get) => $get('lugar_tipo') === 'otro')
                                ->required(fn(Get $get) => $get('lugar_tipo') === 'otro')
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('en_horario_laboral')
                                ->label('¿Ocurrió en horario laboral?')
                                ->options(['si' => 'Sí', 'no' => 'No', 'parcial' => 'Parcialmente'])
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

                            Forms\Components\Select::make('quien_reporta')
                                ->label('¿Quién reporta el incidente?')
                                ->options([
                                    'empleador'  => 'El empleador directamente',
                                    'supervisor' => 'Un supervisor o jefe inmediato',
                                    'rrhh'       => 'El área de Recursos Humanos',
                                    'compañero'  => 'Un compañero de trabajo',
                                    'cliente'    => 'Un cliente o proveedor',
                                    'otro'       => 'Otro',
                                ])
                                ->required()
                                ->native(false)
                                ->placeholder('Seleccione quién reporta...')
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\Textarea::make('descripcion_hecho')
                                        ->label('¿Qué ocurrió?')
                                        ->helperText('Escriba una idea general — use el micrófono o "Mejorar con IA" si prefiere no redactar.')
                                        ->required()
                                        ->minLength(40)
                                        ->validationMessages([
                                            'min' => 'Necesitamos al menos una idea de lo que ocurrió. Use el micrófono o el botón "Mejorar con IA".',
                                        ])
                                        ->rows(7)
                                        ->placeholder('Ej: El trabajador llegó dos horas tarde sin avisar...')
                                        ->live(debounce: 800)
                                        ->afterStateUpdated(fn($livewire) => $livewire->analizarDescripcion())
                                        ->hintActions([
                                            Forms\Components\Actions\Action::make('mejorar_ia')
                                                ->label(fn($livewire) => $livewire->mejorando ? 'Mejorando...' : 'Mejorar con IA')
                                                ->icon('heroicon-m-sparkles')
                                                ->color('gray')
                                                ->tooltip('La IA expandirá y mejorará lo que escribió')
                                                ->disabled(fn(Get $get, $livewire) => $livewire->mejorando || mb_strlen($get('descripcion_hecho') ?? '') < 10)
                                                ->action(fn($livewire) => $livewire->mejorarDescripcion()),
                                        ])
                                        ->columnSpan(2),

                                    Forms\Components\Placeholder::make('panel_analisis_ia')
                                        ->label('')
                                        ->content(fn($livewire) => new HtmlString(
                                            view('filament.components.paso-hechos-analisis', [
                                                'items'       => $livewire->analisisDescripcion,
                                                'feedbackVoz' => $livewire->feedbackVoz,
                                            ])->render()
                                        ))
                                        ->columnSpan(1),

                                    Forms\Components\Placeholder::make('mic_helper')
                                        ->label('')
                                        ->content(fn() => new HtmlString(
                                            view('filament.components.hechos-asistente')->render()
                                        ))
                                        ->columnSpan(2),

                                    Forms\Components\Placeholder::make('sugerencias_completado')
                                        ->label('')
                                        ->content(fn($livewire) => !empty($livewire->sugerenciasCompletado)
                                            ? new HtmlString(view('filament.components.paso-hechos-completar', [
                                                'sugerencias' => $livewire->sugerenciasCompletado,
                                            ])->render())
                                            : new HtmlString('')
                                        )
                                        ->columnSpan(2),
                                ]),
                        ]),
                ]),

            // ── Paso 4: Evidencias ───────────────────────────────────────────
            Step::make('evidencias')
                ->label('Evidencias')
                ->description('Pruebas disponibles')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_evidencias')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-evidencias-info')->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('tiene_evidencias')
                                ->label('¿Existe evidencia del hecho?')
                                ->options(['si' => 'Sí', 'no' => 'No'])
                                ->required()
                                ->inline()
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\CheckboxList::make('tipos_evidencias')
                                ->label('¿Qué tipo de evidencia tiene?')
                                ->options([
                                    'correo'             => 'Correo electrónico',
                                    'asistencia'         => 'Registro de asistencia',
                                    'camaras'            => 'Cámaras de seguridad',
                                    'documento'          => 'Documento interno',
                                    'reporte_supervisor' => 'Reporte del supervisor',
                                    'testigos'           => 'Testigos presenciales',
                                    'otro'               => 'Otro',
                                ])
                                ->columns(2)
                                ->visible(fn(Get $get) => $get('tiene_evidencias') === 'si')
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
                        ]),
                ]),

            // ── Paso 5: Testigos ─────────────────────────────────────────────
            Step::make('testigos')
                ->label('Testigos')
                ->description('¿Hubo personas que presenciaron?')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Placeholder::make('info_paso_testigos')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.paso-testigos-info')->render()
                                ))
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

            // ── Paso 6: Revisión y envío ─────────────────────────────────────
            Step::make('revision')
                ->label('Revisión')
                ->description('Confirme y genere la citación')
                ->icon('heroicon-o-check-circle')
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
                                'tipos_evidencias' => $get('tipos_evidencias') ?? [],
                                'hubo_testigos'    => $get('hubo_testigos'),
                                'testigos'         => $get('testigos') ?? [],
                                'trabajador_id'    => $get('trabajador_id'),
                                'chat_listo'       => $livewire->chatListo,
                            ])->render()
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generar_hechos')
                            ->label(fn($livewire) => $livewire->generandoHechos ? 'Generando...' : 'Generar descripción jurídica')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->disabled(fn($livewire) => $livewire->generandoHechos)
                            ->action(fn($livewire) => $livewire->generarHechos()),
                    ])->fullWidth(),

                    Forms\Components\Placeholder::make('hechos_generados_info')
                        ->label('')
                        ->content(fn($livewire) => $livewire->chatListo
                            ? new HtmlString('<div class="flex items-center gap-2 text-sm text-success-600 dark:text-success-400 py-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Descripción jurídica generada. Puede editarla antes de crear el proceso.</div>')
                            : new HtmlString('')
                        )
                        ->columnSpanFull()
                        ->hidden(fn($livewire) => !$livewire->chatListo),

                    Forms\Components\Textarea::make('hechos_ia')
                        ->label('Descripción jurídica generada (editable)')
                        ->helperText('Revise y edite si es necesario.')
                        ->rows(8)
                        ->hidden(fn(Get $get) => empty($get('hechos_ia')))
                        ->columnSpanFull(),

                    Forms\Components\DatePicker::make('fecha_descargos_programada')
                        ->label('Fecha de la audiencia de descargos')
                        ->required()
                        ->native(false)
                        ->minDate(now())
                        ->displayFormat('d/m/Y')
                        ->helperText('Fecha en que se realizará la audiencia virtual'),

                    TimePickerField::make('hora_descargos_programada')
                        ->label('Hora de la audiencia')
                        ->required()
                        ->helperText('Horario Colombia (UTC-5)'),
                ])->columns(2),
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
            $evidenciasLabels = [
                'correo'             => 'correos electrónicos',
                'asistencia'         => 'registros de asistencia',
                'camaras'            => 'cámaras de seguridad',
                'documento'          => 'documentos internos',
                'reporte_supervisor' => 'reporte del supervisor',
                'testigos'           => 'testigos presenciales',
                'otro'               => 'otras evidencias',
            ];
            $tiposEvidencias = $this->data['tipos_evidencias'] ?? [];
            $partes = [];

            if (!empty($tiposEvidencias)) {
                $labels   = array_map(fn($k) => $evidenciasLabels[$k] ?? $k, $tiposEvidencias);
                $partes[] = 'Se cuenta con: ' . implode(', ', $labels);
            }

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

    public function obtenerFeedbackVoz(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');
        if (mb_strlen($texto) < 30) {
            $this->feedbackVoz = '';
            return;
        }
        $empresaId = (int) ($this->data['empresa_id'] ?? 0);
        $this->feedbackVoz = app(EvaluacionHechosService::class)->darFeedbackDictado($texto, $empresaId);
        if ($this->feedbackVoz) {
            // Para TTS solo hablar la primera oración (evitar leer el análisis completo)
            $primeraOracion = preg_split('/(?<=[.!?])\s+/', trim($this->feedbackVoz))[0] ?? $this->feedbackVoz;
            if (mb_strlen($primeraOracion) > 180) {
                $primeraOracion = mb_substr($primeraOracion, 0, 180) . '…';
            }
            $this->dispatch('hablar-feedback', texto: $primeraOracion);
        }
    }

    public function mejorarDescripcion(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');

        if (mb_strlen($texto) < 10) {
            Notification::make()->warning()
                ->title('Escriba algo primero')
                ->body('Escriba una idea básica y la IA la expandirá.')
                ->send();
            return;
        }

        $this->mejorando = true;
        $empresaId = (int) ($this->data['empresa_id'] ?? 0);

        // Build context from already-filled wizard steps so the AI doesn't show [COMPLETAR] for known data
        $contexto = [];

        $trabajadorId = $this->data['trabajador_id'] ?? null;
        if ($trabajadorId) {
            $t = Trabajador::find($trabajadorId);
            if ($t) {
                $contexto['trabajador_nombre'] = $t->nombre_completo;
                $contexto['trabajador_cargo']  = $t->cargo ?? '';
            }
        }

        if (!empty($this->data['fecha_hecho'])) {
            $contexto['fecha_hecho'] = Carbon::parse($this->data['fecha_hecho'])
                ->locale('es')
                ->isoFormat('D [de] MMMM [de] YYYY');
        }

        if (!empty($this->data['hora_aproximada_hecho'])) {
            $contexto['hora_hecho'] = $this->data['hora_aproximada_hecho'];
        }

        $lugarLabels = [
            'planta'         => 'Planta de producción',
            'oficina'        => 'Oficina',
            'sede_principal' => 'Sede principal',
            'bodega'         => 'Bodega / Almacén',
            'externo'        => 'Lugar externo a la empresa',
            'virtual'        => 'Entorno virtual / remoto',
        ];
        $lugarTipo = $this->data['lugar_tipo'] ?? null;
        if ($lugarTipo) {
            $contexto['lugar'] = $lugarTipo === 'otro'
                ? ($this->data['lugar_libre'] ?? '')
                : ($lugarLabels[$lugarTipo] ?? $lugarTipo);
        }

        try {
            $service  = app(EvaluacionHechosService::class);
            $mejorado = $service->mejorarRedaccion($texto, $empresaId, $contexto);
            $this->data['descripcion_hecho'] = $mejorado;
            $this->analizarDescripcion();

            // Generar sugerencias IA para los campos [COMPLETAR] que quedaron
            $this->sugerenciasCompletado = $service->generarSugerenciasCompletado($mejorado);

            Notification::make()->success()
                ->title('Texto mejorado')
                ->body('Revise y complete los campos marcados con las sugerencias de abajo.')
                ->send();
        } catch (\Exception $e) {
            Log::error('mejorarDescripcion', ['error' => $e->getMessage()]);
            Notification::make()->danger()
                ->title('Error al mejorar texto')
                ->body('No se pudo conectar con la IA. Intente de nuevo.')
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

        $this->analizarDescripcion();
    }

    public function analizarDescripcion(): void
    {
        $texto = trim($this->data['descripcion_hecho'] ?? '');

        if (mb_strlen($texto) < 15) {
            $this->analisisDescripcion = [];
            return;
        }

        // ── 1. Detectar categoría preliminar de la falta ──────────────────────
        $categoria = null;
        if (preg_match('/\b(lleg[oó]\s+tarde|tardanza|impuntual|no\s+asisti[oó]|falt[oó]\s+(al\s+)?trabajo|ausencia\s+injustificada|abandon[oó]\s+el?\s+puesto|no\s+se\s+present[oó]|retraso)\b/ui', $texto)) {
            $categoria = 'Ausentismo o impuntualidad';
        } elseif (preg_match('/\b(golpe[oó]|agredi[oó]|amenaz[oó]|insult[oó]|maltrat[oó]|ofendi[oó]|agresión|violencia|pelea|empuj[oó])\b/ui', $texto)) {
            $categoria = 'Violencia o maltrato laboral';
        } elseif (preg_match('/\b(rob[oó]|hurt[oó]|sustrajo|se\s+llev[oó]|apropi[oó]|hurto|dinero|efectivo)\b/ui', $texto)) {
            $categoria = 'Hurto o apropiación indebida';
        } elseif (preg_match('/\b(se\s+neg[oó]|no\s+acató|desobedeció|ignor[oó]\s+(la\s+)?orden|insubordinaci[oó]n|descat[oó])\b/ui', $texto)) {
            $categoria = 'Insubordinación';
        } elseif (preg_match('/\b(acoso|hostig[oó]|intimid[oó]|discrimin[oó]|acosó)\b/ui', $texto)) {
            $categoria = 'Acoso o discriminación';
        } elseif (preg_match('/\b(incumpli[oó]|omiti[oó]|violó\s+(el\s+)?reglamento|no\s+sigui[oó]\s+(el\s+)?protocolo|incumplimiento\s+(de\s+)?normas)\b/ui', $texto)) {
            $categoria = 'Incumplimiento normativo';
        }

        // ── 2. Nivel de detalle ───────────────────────────────────────────────
        $longitud = mb_strlen($texto);
        if ($longitud >= 150) {
            $detalleOk   = true;
            $detalleTxt  = 'Descripción detallada — la IA tendrá buen contexto';
        } elseif ($longitud >= 70) {
            $detalleOk   = false;
            $detalleTxt  = 'Agregue más contexto: consecuencias, antecedentes o reincidencia';
        } else {
            $detalleOk   = false;
            $detalleTxt  = 'Descripción muy breve — amplíe con más detalles';
        }

        // ── 3. Acción concreta del trabajador ─────────────────────────────────
        $tieneAccion = (bool) preg_match(
            '/\b(lleg[oó]|falt[oó]|no\s+asisti[oó]|golpe[oó]|agredi[oó]|rob[oó]|hurtó|sustrajo|tom[oó]|omiti[oó]|incumpli[oó]|abandon[oó]|se\s+neg[oó]|irrespet[oó]|insult[oó]|amena[zz][oó]|tard[oó]|descat[oó]|acosó|hostig[oó]|intimid[oó])\b/ui',
            $texto
        );

        $this->analisisDescripcion = [
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
                    : 'Describa qué hizo exactamente el trabajador',
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Acciones y cabecera
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tutorial')
                ->label('¿Necesitas ayuda?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'data-tour' => 'help-button',
                    'onclick'   => 'window.iniciarTour(); return false;',
                ]),
        ];
    }

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

        // pruebas_iniciales: compilar desde evidencias + testigos
        $pruebas = [];
        $evidenciasLabels = [
            'correo'             => 'correos electrónicos',
            'asistencia'         => 'registros de asistencia',
            'camaras'            => 'cámaras de seguridad',
            'documento'          => 'documentos internos',
            'reporte_supervisor' => 'reporte del supervisor',
            'testigos'           => 'testigos presenciales',
            'otro'               => 'otras evidencias',
        ];

        $tiposEvidencias = $data['tipos_evidencias'] ?? [];
        if (!empty($tiposEvidencias)) {
            $labels    = array_map(fn($k) => $evidenciasLabels[$k] ?? $k, $tiposEvidencias);
            $pruebas[] = 'Tipos de evidencia disponibles: ' . implode(', ', $labels);
        }

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
            $data['tipos_evidencias'],
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
}
