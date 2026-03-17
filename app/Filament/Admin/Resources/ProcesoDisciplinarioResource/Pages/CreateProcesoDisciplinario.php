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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class CreateProcesoDisciplinario extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ProcesoDisciplinarioResource::class;

    // ──────────────────────────────────────────────────────────────────────────
    // Estado del chatbot
    // ──────────────────────────────────────────────────────────────────────────

    /** @var array<int, array{rol: string, texto: string}> */
    public array  $conversacionChat     = [];
    public string $mensajeUsuarioActual = '';
    public bool   $chatListo            = false;
    public bool   $chatIniciado         = false;
    public bool   $enviandoMensaje      = false;
    /** @var array{hechos: string, fecha_ocurrencia: string|null, resumen: string}|array */
    public array  $datosExtraidos       = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Wizard steps
    // ──────────────────────────────────────────────────────────────────────────

    protected function getSteps(): array
    {
        return [
            // ── Paso 1: Empresa y Trabajador ─────────────────────────────────
            Step::make('trabajador')
                ->label('Empresa y Trabajador')
                ->description('¿Para qué empresa y con qué trabajador?')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Select::make('empresa_id')
                                ->label('Empresa')
                                ->relationship('empresa', 'razon_social')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->default(function () {
                                    $user = auth()->user();
                                    return $user && $user->isCliente() ? $user->empresa_id : null;
                                })
                                ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                                ->dehydrated()
                                ->afterStateUpdated(fn(Forms\Set $set) => $set('trabajador_id', null))
                                ->helperText('Seleccione la empresa donde ocurrió la situación')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('trabajador_id')
                                ->label('Trabajador involucrado')
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
                                ->helperText('Seleccione primero la empresa para ver los trabajadores')
                                ->suffixIcon('heroicon-o-user-group')
                                ->createOptionForm(function (Get $get) {
                                    return [
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
                                            ->helperText('Se usará para enviar la citación de descargos')
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
                        ]),
                ]),

            // ── Paso 2: Chatbot IA ────────────────────────────────────────────
            Step::make('situacion')
                ->label('Descripción de la situación')
                ->description('El asistente IA le guiará para documentar los hechos')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Forms\Components\Placeholder::make('chatbot_ia')
                        ->label('')
                        ->content(fn($livewire) => new HtmlString(
                            view('filament.components.conversacion-hechos', [
                                'conversacion'   => $livewire->conversacionChat,
                                'chatIniciado'   => $livewire->chatIniciado,
                                'chatListo'      => $livewire->chatListo,
                                'enviando'       => $livewire->enviandoMensaje,
                                'datosExtraidos' => $livewire->datosExtraidos,
                            ])->render()
                        ))
                        ->columnSpanFull(),
                ]),

            // ── Paso 3: Programar diligencia ──────────────────────────────────
            Step::make('programar')
                ->label('Programar audiencia')
                ->description('Seleccione la fecha y hora de la audiencia virtual')
                ->icon('heroicon-o-calendar')
                ->schema([
                    Forms\Components\Placeholder::make('resumen_situacion')
                        ->label('Situación documentada')
                        ->content(fn($livewire) => new HtmlString(
                            !empty($livewire->datosExtraidos['resumen'])
                                ? "<div class='p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'>" .
                                  "<div class='flex items-center gap-2 mb-1'>" .
                                  "<svg class='w-4 h-4 text-green-600 dark:text-green-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>" .
                                  "<span class='text-sm font-semibold text-green-800 dark:text-green-300'>Información recopilada</span>" .
                                  "</div>" .
                                  "<p class='text-sm text-gray-700 dark:text-gray-300'>" . e($livewire->datosExtraidos['resumen'] ?? '') . "</p>" .
                                  "</div>"
                                : "<span class='text-sm text-amber-600 dark:text-amber-400'>⚠️ No completó la conversación con el asistente. Regrese al paso anterior.</span>"
                        ))
                        ->columnSpanFull(),

                    Forms\Components\DatePicker::make('fecha_descargos_programada')
                        ->label('Fecha de la audiencia')
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
    // Métodos del chatbot
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Inicia la conversación: pide a la IA su mensaje de apertura.
     */
    public function iniciarChatbot(): void
    {
        $trabajadorId = $this->data['trabajador_id'] ?? null;
        $empresaId    = $this->data['empresa_id'] ?? null;

        if (!$trabajadorId || !$empresaId) {
            Notification::make()
                ->warning()
                ->title('Seleccione primero el trabajador')
                ->body('Debe completar el Paso 1 antes de iniciar la conversación.')
                ->send();
            return;
        }

        $trabajador = Trabajador::find($trabajadorId);

        if (!$trabajador) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se encontró el trabajador seleccionado.')
                ->send();
            return;
        }

        try {
            $resultado = app(EvaluacionHechosService::class)->obtenerMensajeInicial(
                (int) $empresaId,
                $trabajador->nombre_completo,
                $trabajador->cargo ?? 'No especificado',
                (int) $trabajadorId
            );

            $this->conversacionChat[] = ['rol' => 'ia', 'texto' => $resultado['mensaje']];
            $this->chatIniciado = true;

        } catch (\Exception $e) {
            Log::error('Error al iniciar chatbot de hechos', ['error' => $e->getMessage()]);

            $this->conversacionChat[] = [
                'rol'   => 'ia',
                'texto' => "Hola. Para documentar el proceso disciplinario de {$trabajador->nombre_completo}, cuénteme: ¿qué situación ocurrió?",
            ];
            $this->chatIniciado = true;
        }

        $this->dispatch('chatbot-actualizado');
    }

    /**
     * Procesa el mensaje del empleador y obtiene la siguiente respuesta de la IA.
     */
    public function enviarMensajeChatbot(): void
    {
        $mensaje = trim($this->mensajeUsuarioActual);

        if (empty($mensaje) || $this->enviandoMensaje || $this->chatListo) {
            return;
        }

        $this->mensajeUsuarioActual = '';
        $this->conversacionChat[]   = ['rol' => 'usuario', 'texto' => $mensaje];
        $this->enviandoMensaje      = true;

        $this->dispatch('chatbot-actualizado');

        try {
            $trabajadorId = $this->data['trabajador_id'] ?? null;
            $empresaId    = $this->data['empresa_id'] ?? null;
            $trabajador   = $trabajadorId ? Trabajador::find($trabajadorId) : null;

            $resultado = app(EvaluacionHechosService::class)->procesarMensaje(
                $mensaje,
                $this->conversacionChat,
                (int) $empresaId,
                $trabajador?->nombre_completo ?? 'el trabajador',
                $trabajador?->cargo ?? 'No especificado',
                (int) $trabajadorId
            );

            $this->conversacionChat[] = ['rol' => 'ia', 'texto' => $resultado['mensaje']];

            if ($resultado['listo'] && !empty($resultado['datos'])) {
                $this->chatListo      = true;
                $this->datosExtraidos = $resultado['datos'];
            }
        } catch (\Exception $e) {
            Log::error('Error al procesar mensaje chatbot de hechos', ['error' => $e->getMessage()]);

            $this->conversacionChat[] = [
                'rol'   => 'ia',
                'texto' => 'Lo siento, tuve un problema de conexión. Por favor, intente de nuevo.',
            ];
        } finally {
            $this->enviandoMensaje = false;
        }

        $this->dispatch('chatbot-actualizado');
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
                ->title('Conversación incompleta')
                ->body('Debe completar la conversación con el asistente de IA en el Paso 2 antes de crear el proceso.')
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Modalidad siempre virtual
        $data['modalidad_descargos'] = 'virtual';

        // Hechos y fecha extraídos por la IA
        $data['hechos'] = $this->datosExtraidos['hechos'] ?? '';

        if (!empty($this->datosExtraidos['fecha_ocurrencia'])) {
            $data['fecha_ocurrencia'] = $this->datosExtraidos['fecha_ocurrencia'];
        }

        // Limpiar campos temporales que no van a BD
        unset($data['fecha_temp_descargos'], $data['hora_temp_descargos']);

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
