<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use App\Models\AreaPractica;
use App\Models\Empresa;
use App\Models\InformeJuridico;
use App\Models\SubtipoGestion;
use App\Models\TipoGestion;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateInformeJuridico extends CreateRecord
{
    use HasWizard;

    protected static string $resource = InformeJuridicoResource::class;

    protected function getSteps(): array
    {
        return [

            // ── Step 1: Cliente y periodo ──────────────────────────────────────
            Step::make('cliente_periodo')
                ->label('Cliente y Periodo')
                ->description('Empresa y mes de la gestión')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Forms\Components\Select::make('empresa_id')
                        ->label('Empresa')
                        ->relationship('empresa', 'razon_social')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Seleccione la empresa...')
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('anio')
                                ->label('Año')
                                ->options(function () {
                                    $currentYear = now()->year;
                                    $years = [];
                                    for ($i = $currentYear - 2; $i <= $currentYear; $i++) {
                                        $years[$i] = $i;
                                    }
                                    return $years;
                                })
                                ->default(now()->year)
                                ->required()
                                ->native(false),

                            Forms\Components\Select::make('mes')
                                ->label('Mes')
                                ->options([
                                    'enero'      => 'Enero',
                                    'febrero'    => 'Febrero',
                                    'marzo'      => 'Marzo',
                                    'abril'      => 'Abril',
                                    'mayo'       => 'Mayo',
                                    'junio'      => 'Junio',
                                    'julio'      => 'Julio',
                                    'agosto'     => 'Agosto',
                                    'septiembre' => 'Septiembre',
                                    'octubre'    => 'Octubre',
                                    'noviembre'  => 'Noviembre',
                                    'diciembre'  => 'Diciembre',
                                ])
                                ->default(strtolower(now()->locale('es')->translatedFormat('F')))
                                ->required()
                                ->native(false),

                            Forms\Components\DatePicker::make('fecha_gestion')
                                ->label('Fecha de la gestión')
                                ->default(now())
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->helperText('Día exacto en que se realizó el trabajo'),
                        ]),
                ]),

            // ── Step 2: Clasificación ──────────────────────────────────────────
            Step::make('clasificacion')
                ->label('Clasificación')
                ->description('Área, tipo y subtipo de gestión')
                ->icon('heroicon-o-tag')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('area_practica_id')
                                ->label('Área de Práctica')
                                ->options(fn () => AreaPractica::activos()->ordenado()->pluck('nombre', 'id'))
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\Select::make('color')
                                        ->label('Color')
                                        ->options([
                                            'gray'    => 'Gris',
                                            'primary' => 'Azul',
                                            'success' => 'Verde',
                                            'warning' => 'Amarillo',
                                            'danger'  => 'Rojo',
                                            'info'    => 'Celeste',
                                        ])
                                        ->default('gray'),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $data['orden'] = AreaPractica::max('orden') + 1;
                                    return AreaPractica::create($data)->getKey();
                                }),

                            Forms\Components\Select::make('tipo_gestion_id')
                                ->label('Tipo de Gestión')
                                ->options(fn () => TipoGestion::activos()->ordenado()->pluck('nombre', 'id'))
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('subtipo_id', null))
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(100),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $data['orden'] = TipoGestion::max('orden') + 1;
                                    return TipoGestion::create($data)->getKey();
                                }),

                            Forms\Components\Select::make('subtipo_id')
                                ->label('Subtipo')
                                ->options(function (Get $get) {
                                    $tipoId = $get('tipo_gestion_id');
                                    return SubtipoGestion::activos()
                                        ->ordenado()
                                        ->where(function ($query) use ($tipoId) {
                                            $query->whereNull('tipo_gestion_id')
                                                ->orWhere('tipo_gestion_id', $tipoId);
                                        })
                                        ->pluck('nombre', 'id');
                                })
                                ->native(false)
                                ->searchable()
                                ->placeholder('Opcional...')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('nombre')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(100),
                                ])
                                ->createOptionUsing(function (array $data, Get $get): int {
                                    $data['orden'] = SubtipoGestion::max('orden') + 1;
                                    $data['tipo_gestion_id'] = $get('tipo_gestion_id');
                                    return SubtipoGestion::create($data)->getKey();
                                }),

                            Forms\Components\Select::make('estado')
                                ->label('Estado')
                                ->options([
                                    'en_proceso' => 'En Proceso',
                                    'realizado'  => 'Realizado',
                                    'entregado'  => 'Entregado',
                                    'pendiente'  => 'Pendiente',
                                ])
                                ->default('en_proceso')
                                ->required()
                                ->native(false)
                                ->helperText('Cambie a "Realizado" al completar la gestión.'),
                        ]),
                ]),

            // ── Step 3: Descripción y cierre ──────────────────────────────────
            Step::make('descripcion_cierre')
                ->label('Descripción')
                ->description('Detalle, tiempo dedicado y adjuntos')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\RichEditor::make('descripcion')
                        ->label('Descripción de la Gestión')
                        ->required()
                        ->placeholder('Describa la gestión realizada o use el botón de IA...')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                        ])
                        ->hintColor('primary')
                        ->hintAction(
                            Forms\Components\Actions\Action::make('generarDescripcion')
                                ->icon('heroicon-o-sparkles')
                                ->label('Generar con IA')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Generar Descripción con IA')
                                ->modalDescription('La IA generará una descripción profesional basada en los campos ya seleccionados.')
                                ->modalSubmitActionLabel('Generar')
                                ->action(function (Set $set, Get $get) {
                                    try {
                                        $empresaId      = $get('empresa_id');
                                        $areaPracticaId = $get('area_practica_id');
                                        $tipoGestionId  = $get('tipo_gestion_id');
                                        $subtipoId      = $get('subtipo_id');
                                        $mes            = $get('mes');
                                        $anio           = $get('anio');

                                        if (!$empresaId || !$areaPracticaId || !$tipoGestionId) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('Datos incompletos')
                                                ->body('Vuelva al Paso 1 y 2 y seleccione empresa, área y tipo de gestión.')
                                                ->send();
                                            return;
                                        }

                                        $empresa      = Empresa::find($empresaId);
                                        $areaPractica = AreaPractica::find($areaPracticaId);
                                        $tipoGestion  = TipoGestion::find($tipoGestionId);
                                        $subtipo      = $subtipoId ? SubtipoGestion::find($subtipoId) : null;

                                        $mesTexto     = ucfirst($mes ?? '');
                                        $subtipoTexto = $subtipo ? " — {$subtipo->nombre}" : '';

                                        $provider = config('services.ia.provider', 'gemini');
                                        $config   = config("services.ia.{$provider}", []);
                                        $apiKey   = $config['api_key'];
                                        $model    = $config['model'];

                                        $prompt = "Escribe una descripción breve (1-2 oraciones) para un informe de gestión jurídica:\n\n"
                                            . "Empresa: {$empresa->razon_social}\n"
                                            . "Periodo: {$mesTexto} {$anio}\n"
                                            . "Área: {$areaPractica->nombre}\n"
                                            . "Tipo de gestión: {$tipoGestion->nombre}{$subtipoTexto}\n\n"
                                            . "Ejemplo: \"Se elaboró contrato de prestación de servicios. Documento revisado conforme a los requerimientos.\"\n\n"
                                            . "Responde solo con el texto, sin comillas ni explicaciones:";

                                        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                                        $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                            ->timeout(30)
                                            ->post($url, [
                                                'contents'         => [['parts' => [['text' => $prompt]]]],
                                                'generationConfig' => [
                                                    'temperature'     => 0.7,
                                                    'maxOutputTokens' => $config['max_tokens'] ?? 300,
                                                    'topP'            => 0.95,
                                                ],
                                            ]);

                                        if (!$response->successful()) {
                                            throw new \Exception('Error en API: ' . $response->body());
                                        }

                                        $data = $response->json();

                                        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                                            throw new \Exception('Respuesta sin contenido válido');
                                        }

                                        $texto = trim($data['candidates'][0]['content']['parts'][0]['text']);
                                        $set('descripcion', "<p>{$texto}</p>");

                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Descripción generada')
                                            ->body('Revise y ajuste si es necesario.')
                                            ->duration(5000)
                                            ->send();

                                    } catch (\Exception $e) {
                                        \Filament\Notifications\Notification::make()
                                            ->danger()
                                            ->title('Error al generar')
                                            ->body($e->getMessage())
                                            ->persistent()
                                            ->send();

                                        Log::error('Error IA informe jurídico', ['error' => $e->getMessage()]);
                                    }
                                })
                        )
                        ->columnSpanFull(),

                    // Tiempo: horas + minutos separados
                    Forms\Components\Fieldset::make('Tiempo dedicado')
                        ->schema([
                            Forms\Components\TextInput::make('tiempo_horas')
                                ->label('Horas')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(23)
                                ->default(0)
                                ->suffix('h')
                                ->placeholder('0'),

                            Forms\Components\TextInput::make('tiempo_mins')
                                ->label('Minutos')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(59)
                                ->default(0)
                                ->suffix('min')
                                ->placeholder('0'),
                        ])
                        ->columns(2),

                    Forms\Components\FileUpload::make('adjuntos')
                        ->label('Documentos adjuntos')
                        ->multiple()
                        ->directory('informes-juridicos')
                        ->disk('public')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                        ])
                        ->maxSize(10240)
                        ->maxFiles(10)
                        ->helperText('PDF, Word o imágenes. Máximo 10 archivos de 10 MB c/u.')
                        ->downloadable()
                        ->reorderable()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('observacion')
                        ->label('Observaciones')
                        ->rows(2)
                        ->placeholder('Notas adicionales (opcional)...')
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Combinar horas y minutos en tiempo_minutos
        $horas   = (int) ($data['tiempo_horas'] ?? 0);
        $minutos = (int) ($data['tiempo_mins']  ?? 0);
        $data['tiempo_minutos'] = ($horas * 60) + $minutos;
        unset($data['tiempo_horas'], $data['tiempo_mins']);

        // Generar código único IGJ-YYYY-NNN
        $anio  = $data['anio'] ?? now()->year;
        $count = InformeJuridico::where('anio', $anio)->count();
        $data['codigo'] = sprintf('IGJ-%d-%03d', $anio, $count + 1);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
