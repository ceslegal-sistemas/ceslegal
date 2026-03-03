<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InformeJuridicoResource\Pages;
use App\Models\AreaPractica;
use App\Models\Empresa;
use App\Models\InformeJuridico;
use App\Models\SubtipoGestion;
use App\Models\TipoGestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InformeJuridicoResource extends Resource
{
    protected static ?string $model = InformeJuridico::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Historial de Informes';

    protected static ?string $modelLabel = 'Informe de Gestión Jurídica';

    protected static ?string $pluralModelLabel = 'Informes de Gestión Jurídica';

    protected static ?string $navigationGroup = 'Gestión Jurídica';

    protected static ?int $navigationSort = 10;

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Crear Informe Jurídico')
                ->icon('heroicon-o-plus-circle')
                ->url(static::getUrl('create'))
                ->group(static::getNavigationGroup())
                ->sort(10),

            NavigationItem::make('Historial de Informes')
                ->icon(static::getNavigationIcon())
                ->url(static::getUrl('index'))
                ->group(static::getNavigationGroup())
                ->sort(11)
                ->isActiveWhen(fn() => request()->routeIs(static::getRouteBaseName() . '.*') && !request()->routeIs(static::getRouteBaseName() . '.create')),
        ];
    }

    // ── Formulario (usado en la página de EDICIÓN) ─────────────────────────
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificación')
                    ->description('Empresa, periodo y fecha exacta')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Generado automáticamente'),

                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'razon_social')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Seleccione...')
                            ->columnSpan(2),

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
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->helperText('Día exacto en que se realizó el trabajo'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Clasificación')
                    ->description('Área, tipo y subtipo de gestión')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Forms\Components\Grid::make(4)
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
                                    })
                                    ->columnSpan(1),

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
                                    })
                                    ->columnSpan(1),

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
                                    })
                                    ->columnSpan(1),

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
                                    ->columnSpan(1),
                            ]),
                    ]),

                Forms\Components\Section::make('Descripción')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\RichEditor::make('descripcion')
                            ->label('Descripción de la Gestión')
                            ->required()
                            ->placeholder('Describa brevemente la gestión realizada...')
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
                                    ->modalDescription('La IA generará una descripción profesional basada en los campos seleccionados.')
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
                                                    ->body('Por favor, seleccione la empresa, área de práctica y tipo de gestión.')
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
                                                ->body('Revise y ajuste según sea necesario.')
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

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    // ── Tabla ──────────────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('fecha_gestion')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('anio')
                    ->label('Año')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('mes')
                    ->label('Mes')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('areaPractica.nombre')
                    ->label('Área')
                    ->badge()
                    ->color(fn ($record) => $record->areaPractica?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('tipoGestion.nombre')
                    ->label('Tipo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subtipo.nombre')
                    ->label('Subtipo')
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->html()
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'en_proceso' => 'info',
                        'realizado'  => 'primary',
                        'entregado'  => 'success',
                        'pendiente'  => 'warning',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'en_proceso' => 'En Proceso',
                        'realizado'  => 'Realizado',
                        'entregado'  => 'Entregado',
                        'pendiente'  => 'Pendiente',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('tiempo_minutos')
                    ->label('Tiempo')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $horas   = intdiv($state, 60);
                        $minutos = $state % 60;
                        return $horas > 0 ? "{$horas}h {$minutos}m" : "{$minutos} min";
                    })
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->formatStateUsing(function ($state) {
                            $horas   = intdiv($state, 60);
                            $minutos = $state % 60;
                            return "{$horas}h {$minutos}m";
                        })
                        ->label('Total'))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('adjuntos')
                    ->label('Adjuntos')
                    ->icon(fn ($state) => !empty($state) ? 'heroicon-o-paper-clip' : null)
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('creador.name')
                    ->label('Registrado por')
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('anio')
                    ->label('Año')
                    ->options(function () {
                        $currentYear = now()->year;
                        $years = [];
                        for ($i = $currentYear - 5; $i <= $currentYear; $i++) {
                            $years[$i] = $i;
                        }
                        return $years;
                    }),

                Tables\Filters\SelectFilter::make('mes')
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
                    ]),

                Tables\Filters\SelectFilter::make('area_practica_id')
                    ->label('Área de Práctica')
                    ->relationship('areaPractica', 'nombre'),

                Tables\Filters\SelectFilter::make('tipo_gestion_id')
                    ->label('Tipo de Gestión')
                    ->relationship('tipoGestion', 'nombre'),

                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'en_proceso' => 'En Proceso',
                        'realizado'  => 'Realizado',
                        'entregado'  => 'Entregado',
                        'pendiente'  => 'Pendiente',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver'),
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('empresa.razon_social')->label('Empresa')->collapsible(),
                Tables\Grouping\Group::make('anio')->label('Año')->collapsible(),
                Tables\Grouping\Group::make('mes')->label('Mes')->collapsible(),
                Tables\Grouping\Group::make('areaPractica.nombre')->label('Área de Práctica')->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInformesJuridicos::route('/'),
            'create' => Pages\CreateInformeJuridico::route('/create'),
            'view'   => Pages\ViewInformeJuridico::route('/{record}'),
            'edit'   => Pages\EditInformeJuridico::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['empresa', 'creador', 'areaPractica', 'tipoGestion', 'subtipo']);
    }
}
