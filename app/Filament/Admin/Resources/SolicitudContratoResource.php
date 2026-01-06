<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages;
use App\Models\SolicitudContrato;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SolicitudContratoResource extends Resource
{
    protected static ?string $model = SolicitudContrato::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Solicitudes de Contrato';

    protected static ?string $modelLabel = 'Solicitud de Contrato';

    protected static ?string $pluralModelLabel = 'Solicitudes de Contrato';

    protected static ?string $navigationGroup = 'Gestión de Contratos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Información Básica')
                        ->description('Datos generales de la solicitud')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Select::make('empresa_id')
                                ->label('Empresa')
                                ->relationship('empresa', 'razon_social')
                                ->searchable()
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
                                    return 'Seleccione la empresa para la cual se solicita el contrato';
                                })
                                ->placeholder('Busque y seleccione la empresa...')
                                ->suffixIcon('heroicon-o-building-office')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('tipo_contrato')
                                ->label('Tipo de Contrato')
                                ->required()
                                ->options([
                                    'Contrato a Término Fijo' => 'Contrato a Término Fijo - Duración determinada',
                                    'Contrato a Término Indefinido' => 'Contrato a Término Indefinido - Sin fecha de terminación',
                                    'Contrato de Obra o Labor' => 'Contrato de Obra o Labor - Por proyecto específico',
                                    'Contrato de Prestación de Servicios' => 'Contrato de Prestación de Servicios - Independiente',
                                    'Contrato de Aprendizaje' => 'Contrato de Aprendizaje - Estudiante/Aprendiz',
                                    'Contrato Ocasional o Transitorio' => 'Contrato Ocasional o Transitorio - Máximo 30 días',
                                ])
                                ->native(false)
                                ->searchable()
                                ->helperText('Tipo de contrato laboral a generar')
                                ->placeholder('Seleccione el tipo de contrato...')
                                ->suffixIcon('heroicon-o-document-duplicate'),

                            Forms\Components\DateTimePicker::make('fecha_solicitud')
                                ->label('Fecha de Solicitud')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->helperText('Fecha y hora en que se realiza la solicitud')
                                ->suffixIcon('heroicon-o-calendar'),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('Datos del Trabajador')
                        ->description('Información del trabajador')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Toggle::make('_usar_trabajador_existente')
                                ->label('¿Usar trabajador existente?')
                                ->helperText('Active si el trabajador ya está registrado en el sistema')
                                ->live()
                                ->default(false)
                                ->inline(false)
                                ->columnSpanFull(),

                            Forms\Components\Select::make('trabajador_id')
                                ->label('Seleccionar Trabajador Existente')
                                ->relationship('trabajador', 'nombres')
                                ->searchable(['nombres', 'apellidos', 'numero_documento'])
                                ->preload()
                                ->getOptionLabelFromRecordUsing(
                                    fn(Trabajador $record): string =>
                                    "{$record->nombres} {$record->apellidos} - {$record->tipo_documento}: {$record->numero_documento}"
                                )
                                ->visible(fn(Get $get) => $get('_usar_trabajador_existente'))
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?int $state) {
                                    if ($state) {
                                        $trabajador = Trabajador::find($state);
                                        if ($trabajador) {
                                            $set('trabajador_nombres', $trabajador->nombres);
                                            $set('trabajador_apellidos', $trabajador->apellidos);
                                            $set('trabajador_documento_tipo', $trabajador->tipo_documento);
                                            $set('trabajador_documento_numero', $trabajador->numero_documento);
                                            $set('trabajador_email', $trabajador->email);
                                            $set('trabajador_telefono', $trabajador->telefono);
                                            $set('trabajador_direccion', $trabajador->direccion);
                                        }
                                    }
                                })
                                ->helperText('Busque por nombre, apellidos o número de documento')
                                ->placeholder('Busque el trabajador...')
                                ->suffixIcon('heroicon-o-magnifying-glass')
                                ->columnSpanFull(),

                            Forms\Components\Section::make('Datos Personales del Trabajador')
                                ->visible(fn(Get $get) => !$get('_usar_trabajador_existente'))
                                ->schema([
                                    Forms\Components\TextInput::make('trabajador_nombres')
                                        ->label('Nombres')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Juan Carlos')
                                        ->helperText('Nombres completos del trabajador'),

                                    Forms\Components\TextInput::make('trabajador_apellidos')
                                        ->label('Apellidos')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: Pérez García')
                                        ->helperText('Apellidos completos del trabajador'),

                                    Forms\Components\Select::make('trabajador_documento_tipo')
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
                                        ->live()
                                        ->suffixIcon('heroicon-o-identification'),

                                    Forms\Components\TextInput::make('trabajador_documento_numero')
                                        ->label('Número de Documento')
                                        ->required()
                                        ->numeric()
                                        ->maxLength(50)
                                        ->placeholder(fn(Get $get) => match ($get('trabajador_documento_tipo')) {
                                            'CC' => 'Ej: 1234567890',
                                            'CE' => 'Ej: 9876543210',
                                            'TI' => 'Ej: 1234567890123',
                                            'PASS' => 'Ej: AB123456',
                                            default => 'Ingrese el número',
                                        })
                                        ->helperText('Número de identificación del trabajador'),

                                    Forms\Components\TextInput::make('trabajador_email')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('trabajador@empresa.com')
                                        ->helperText('Email de contacto del trabajador')
                                        ->suffixIcon('heroicon-o-envelope'),

                                    Forms\Components\TextInput::make('trabajador_telefono')
                                        ->label('Teléfono / Celular')
                                        ->tel()
                                        ->maxLength(50)
                                        ->placeholder('Ej: +57 300 123 4567')
                                        ->helperText('Número de contacto')
                                        ->suffixIcon('heroicon-o-phone'),

                                    Forms\Components\Textarea::make('trabajador_direccion')
                                        ->label('Dirección de Residencia')
                                        ->rows(2)
                                        ->placeholder('Ej: Calle 123 # 45-67')
                                        ->helperText('Dirección completa (opcional)')
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ]),

                    Forms\Components\Wizard\Step::make('Detalles del Cargo')
                        ->description('Información del puesto y responsabilidades')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            // Forms\Components\Select::make('cargo_contrato')
                            //     ->label('Cargo')
                            //     ->required()
                            //     ->searchable()
                            //     ->options(self::getCargos())
                            //     ->getSearchResultsUsing(
                            //         fn(string $search): array =>
                            //         collect(self::getCargos())
                            //             ->filter(fn($cargo) => Str::contains(Str::lower($cargo), Str::lower($search)))
                            //             ->take(10)
                            //             ->mapWithKeys(fn($cargo) => [$cargo => $cargo])
                            //             ->toArray()
                            //     )
                            //     ->createOptionUsing(fn(string $value) => $value)
                            //     ->helperText('Seleccione o escriba el cargo para el contrato')
                            //     ->placeholder('Busque o escriba el cargo...')
                            //     ->suffixIcon('heroicon-o-briefcase')
                            //     ->columnSpanFull(),

                            Forms\Components\Select::make('cargo_contrato')
                                ->label('Cargo')
                                ->searchable()
                                ->suffixIcon('heroicon-o-briefcase')
                                ->columnSpanFull()
                                ->options(function () {
                                    $cargos = [];
                                    foreach (self::getCargos() as $cargo) {
                                        $cargos[$cargo] = $cargo;
                                    }
                                    $cargos['__otro__'] = '--- Otro (personalizado) ---';
                                    return $cargos;
                                })
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                    if ($state !== '__otro__') {
                                        $set('cargo', $state);
                                        $set('cargo_otro', null);
                                    } else {
                                        $set('cargo', null);
                                    }
                                })
                                ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                    // Al cargar el registro (edición), establecer cargo_select basado en el valor de cargo
                                    $cargo = $get('cargo');
                                    if ($cargo && in_array($cargo, self::getCargos())) {
                                        $set('cargo_contrato', $cargo);
                                    } elseif ($cargo) {
                                        $set('cargo_contrato', '__otro__');
                                    }
                                })
                                ->dehydrated(false)
                                ->helperText('Seleccione un cargo de la lista o elija "Otro" para personalizar')
                                ->placeholder('Seleccione el cargo...')
                                ->suffixIcon('heroicon-o-briefcase')
                                ->required(fn(Get $get) => empty($get('cargo_otro'))),

                            Forms\Components\TextInput::make('cargo_otro')
                                ->label('Especifique el Cargo')
                                ->columnSpanFull()
                                ->visible(fn(Get $get) => $get('cargo_contrato') === '__otro__')
                                ->required(fn(Get $get) => $get('cargo_contrato') === '__otro__')
                                ->placeholder('Ej: Jefe de Proyectos Especiales')
                                ->helperText('Escriba el nombre del cargo personalizado')
                                ->afterStateHydrated(function (Set $set, Get $get, ?string $state) {
                                    // Al cargar el registro (edición), si el cargo no está en la lista, establecer cargo_otro
                                    $cargo = $get('cargo');
                                    if ($cargo && !in_array($cargo, self::getCargos())) {
                                        $set('cargo_otro', $cargo);
                                    }
                                }),

                            Forms\Components\Hidden::make('cargo')
                                ->required()
                                ->dehydrateStateUsing(function (Get $get) {
                                    return $get('cargo_contrato') === '__otro__'
                                        ? $get('cargo_otro')
                                        : $get('cargo_contrato');
                                }),

                            Forms\Components\RichEditor::make('responsabilidades')
                                ->label('Responsabilidades del Cargo')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'orderedList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Liste las responsabilidades principales del cargo...')
                                ->helperText('Describa las funciones y responsabilidades principales')
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('objeto_comercial')
                                ->label('Objeto Comercial')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Describa el objeto comercial del contrato...')
                                ->helperText('Objetivo comercial y alcance del contrato')
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('manual_funciones')
                                ->label('Manual de Funciones')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'orderedList',
                                    'italic',
                                    'undo',
                                    'redo',
                                ])
                                ->placeholder('Detalle el manual de funciones...')
                                ->helperText('Descripción detallada de funciones del puesto')
                                ->columnSpanFull(),

                            Forms\Components\DatePicker::make('fecha_inicio_propuesta')
                                ->label('Fecha de Inicio Propuesta')
                                ->native(false)
                                ->minDate(now())
                                ->displayFormat('d/m/Y')
                                ->helperText('Fecha propuesta para iniciar el contrato')
                                ->placeholder('Seleccione la fecha...')
                                ->suffixIcon('heroicon-o-calendar'),

                            Forms\Components\TextInput::make('salario_propuesto')
                                ->label('Salario Propuesto')
                                ->numeric()
                                ->prefix('$')
                                ->placeholder('Ej: 2500000')
                                ->helperText('Salario mensual propuesto para el cargo')
                                ->suffixIcon('heroicon-o-currency-dollar'),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('Documentos')
                        ->description('Archivos adjuntos')
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Forms\Components\FileUpload::make('ruta_orden_compra')
                                ->label('Orden de Compra')
                                ->directory('solicitudes-contratos/ordenes-compra')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->helperText('Adjunte la orden de compra o autorización (PDF, JPG, PNG - Máx. 5MB)')
                                ->downloadable()
                                ->openable()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('ruta_manual_funciones')
                                ->label('Manual de Funciones')
                                ->directory('solicitudes-contratos/manuales-funciones')
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->maxSize(10240)
                                ->helperText('Adjunte el manual de funciones (PDF, DOC, DOCX - Máx. 10MB)')
                                ->downloadable()
                                ->openable()
                                ->columnSpanFull(),
                        ]),
                ])
                    ->columnSpanFull()
                    ->persistStepInQueryString()
                    ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset dark:focus:ring-offset-0 min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Crear Solicitud</button>')),

                // Campos solo para edición - Estado del proceso
                Forms\Components\Section::make('Estado de la Solicitud')
                    ->description('Información sobre el estado actual')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'pendiente' => 'Pendiente de Asignación',
                                'en_analisis' => 'En Análisis Jurídico',
                                'contrato_generado' => 'Contrato Generado',
                                'enviado_rrhh' => 'Enviado a RRHH',
                                'finalizado' => 'Finalizado',
                                'rechazado' => 'Rechazado',
                            ])
                            ->required()
                            ->default('pendiente')
                            ->native(false)
                            ->helperText('Estado actual de la solicitud')
                            ->suffixIcon('heroicon-o-flag'),

                        Forms\Components\Select::make('abogado_id')
                            ->label('Abogado Asignado')
                            ->relationship('abogado', 'name', fn(Builder $query) => $query->where('role', 'abogado'))
                            ->searchable()
                            ->preload()
                            ->helperText('Abogado responsable del análisis')
                            ->placeholder('Seleccione un abogado...')
                            ->suffixIcon('heroicon-o-scale'),

                        Forms\Components\DateTimePicker::make('fecha_analisis')
                            ->label('Fecha de Análisis')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Fecha en que se realizó el análisis jurídico')
                            ->suffixIcon('heroicon-o-calendar'),

                        Forms\Components\DateTimePicker::make('fecha_generacion_contrato')
                            ->label('Fecha de Generación del Contrato')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Fecha en que se generó el contrato')
                            ->suffixIcon('heroicon-o-calendar'),

                        Forms\Components\DateTimePicker::make('fecha_envio_rrhh')
                            ->label('Fecha de Envío a RRHH')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Fecha en que se envió a Recursos Humanos')
                            ->suffixIcon('heroicon-o-calendar'),

                        Forms\Components\DateTimePicker::make('fecha_cierre')
                            ->label('Fecha de Cierre')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Fecha de finalización del proceso')
                            ->suffixIcon('heroicon-o-calendar'),
                    ])->columns(2)
                    ->hiddenOn('create'),

                Forms\Components\Section::make('Análisis Jurídico')
                    ->description('Observaciones y objeto jurídico')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\RichEditor::make('objeto_juridico_redactado')
                            ->label('Objeto Jurídico Redactado')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'orderedList',
                                'italic',
                                'undo',
                                'redo',
                            ])
                            ->placeholder('Redacte el objeto jurídico del contrato...')
                            ->helperText('Redacción jurídica del objeto del contrato')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('observaciones_juridicas')
                            ->label('Observaciones Jurídicas')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'orderedList',
                                'italic',
                                'undo',
                                'redo',
                            ])
                            ->placeholder('Ingrese observaciones jurídicas...')
                            ->helperText('Notas y observaciones del análisis jurídico')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('ruta_contrato')
                            ->label('Contrato Generado')
                            ->directory('solicitudes-contratos/contratos-generados')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Contrato final generado (PDF - Máx. 10MB)')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('create')
                    ->collapsed(),
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
                    ->weight('bold')
                    ->icon('heroicon-o-hashtag')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'pendiente',
                        'warning' => 'en_analisis',
                        'info' => 'contrato_generado',
                        'primary' => 'enviado_rrhh',
                        'success' => 'finalizado',
                        'danger' => 'rechazado',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pendiente',
                        'heroicon-o-document-magnifying-glass' => 'en_analisis',
                        'heroicon-o-document-check' => 'contrato_generado',
                        'heroicon-o-paper-airplane' => 'enviado_rrhh',
                        'heroicon-o-check-circle' => 'finalizado',
                        'heroicon-o-x-circle' => 'rechazado',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pendiente' => 'Pendiente',
                        'en_analisis' => 'En Análisis',
                        'contrato_generado' => 'Contrato Generado',
                        'enviado_rrhh' => 'Enviado a RRHH',
                        'finalizado' => 'Finalizado',
                        'rechazado' => 'Rechazado',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn(string $state): string => explode(' - ', $state)[0] ?? $state)
                    ->icon('heroicon-o-document-duplicate'),

                Tables\Columns\TextColumn::make('trabajador_nombres')
                    ->label('Trabajador')
                    ->searchable(['trabajador_nombres', 'trabajador_apellidos'])
                    ->sortable()
                    ->description(
                        fn(SolicitudContrato $record): string =>
                        "{$record->trabajador_documento_tipo}: {$record->trabajador_documento_numero}"
                    )
                    ->formatStateUsing(
                        fn(SolicitudContrato $record): string =>
                        "{$record->trabajador_nombres} {$record->trabajador_apellidos}"
                    )
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('cargo_contrato')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-briefcase'),

                Tables\Columns\TextColumn::make('abogado.name')
                    ->label('Abogado')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->default('Sin asignar')
                    ->icon('heroicon-o-scale'),

                Tables\Columns\TextColumn::make('salario_propuesto')
                    ->label('Salario')
                    ->numeric()
                    ->money('COP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fecha_solicitud')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->description(
                        fn(SolicitudContrato $record): string =>
                        $record->fecha_solicitud->diffForHumans()
                    )
                    ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'en_analisis' => 'En Análisis',
                        'contrato_generado' => 'Contrato Generado',
                        'enviado_rrhh' => 'Enviado a RRHH',
                        'finalizado' => 'Finalizado',
                        'rechazado' => 'Rechazado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('tipo_contrato')
                    ->label('Tipo de Contrato')
                    ->options([
                        'Contrato a Término Fijo' => 'Término Fijo',
                        'Contrato a Término Indefinido' => 'Término Indefinido',
                        'Contrato de Obra o Labor' => 'Obra o Labor',
                        'Contrato de Prestación de Servicios' => 'Prestación de Servicios',
                        'Contrato de Aprendizaje' => 'Aprendizaje',
                        'Contrato Ocasional o Transitorio' => 'Ocasional',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('abogado')
                    ->label('Abogado')
                    ->relationship('abogado', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ])
            ->defaultSort('fecha_solicitud', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudContratos::route('/'),
            'create' => Pages\CreateSolicitudContrato::route('/create'),
            'view' => Pages\ViewSolicitudContrato::route('/{record}'),
            'edit' => Pages\EditSolicitudContrato::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('estado', 'pendiente')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
}
