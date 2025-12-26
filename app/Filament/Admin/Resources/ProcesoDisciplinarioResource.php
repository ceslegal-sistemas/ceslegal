<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;
use App\Models\DisponibilidadAbogado;
use App\Models\ProcesoDisciplinario;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use HusamTariq\FilamentTimePicker\Forms\Components\TimePickerField;

class ProcesoDisciplinarioResource extends Resource
{
    protected static ?string $model = ProcesoDisciplinario::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Procesos Disciplinarios';

    protected static ?string $modelLabel = 'Proceso Disciplinario';

    protected static ?string $pluralModelLabel = 'Procesos Disciplinarios';

    protected static ?string $navigationGroup = 'Gestión Laboral';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se generará automáticamente')
                            ->helperText('El código se asigna automáticamente al crear el proceso'),

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
                                return 'Busque y seleccione la empresa';
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('trabajador_id', null);
                                // $set('abogado_id', null);
                            }),

                        Forms\Components\Select::make('trabajador_id')
                            ->label('Trabajador')
                            ->options(
                                fn(Get $get): array =>
                                Trabajador::query()
                                    ->where('empresa_id', $get('empresa_id'))
                                    ->where('active', true)
                                    ->get()
                                    ->pluck('nombre_completo', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->required()
                            ->disabled(fn(Get $get) => !$get('empresa_id'))
                            ->helperText('Seleccione primero la empresa')
                            ->suffixIcon('heroicon-o-user-group')
                            ->createOptionModalHeading('Crear Nuevo Trabajador'),

                        Forms\Components\Select::make('abogado_id')
                            ->label('Abogado Asignado')
                            ->relationship('abogado', 'name', fn($query) => $query->role('abogado'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(function () {
                                // Asignar el primer abogado disponible por defecto
                                return User::role('abogado')->first()?->id;
                            })
                            ->helperText('Seleccione el abogado que llevará el proceso')
                            ->suffixIcon('heroicon-o-user')
                            ->live(),

                        Forms\Components\Select::make('modalidad_descargos')
                            ->label('¿Cómo se realizará la diligencia de descargos?')
                            ->options([
                                'presencial' => 'Presencial',
                                'virtual' => 'Virtual',
                                'telefonico' => 'Telefónico',
                            ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('fecha_temp_descargos', null);
                                $set('hora_temp_descargos', null);
                                $set('fecha_descargos_programada', null);
                            })
                            ->placeholder('Seleccione la modalidad'),
                    ])->columns(2),

                Forms\Components\Section::make('Programación de Descargos')
                    ->schema([
                        // PARA PRESENCIAL Y TELEFÓNICO: Selector de fecha + hora dinámica
                        Forms\Components\DatePicker::make('fecha_temp_descargos')
                            ->label('Seleccione la Fecha')
                            ->required()
                            ->minDate(now()->addDays(5)->startOfDay())
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Set $set) {
                                $set('hora_temp_descargos', null);
                                $set('fecha_descargos_programada', null);
                            })
                            ->visible(fn(Get $get) => in_array($get('modalidad_descargos'), ['presencial', 'telefonico']))
                            ->helperText('Seleccione el día para ver las horas disponibles'),

                        Forms\Components\Radio::make('hora_temp_descargos')
                            ->label('Seleccione la Hora Disponible')
                            ->options(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                $modalidad = $get('modalidad_descargos');
                                $abogadoId = $get('abogado_id');

                                if (!$fecha || !$abogadoId || !in_array($modalidad, ['presencial', 'telefonico'])) {
                                    return [];
                                }

                                $slots = \App\Services\DisponibilidadHelper::obtenerSlotsDisponibles(
                                    $abogadoId,
                                    $fecha,
                                    $modalidad
                                );

                                return \App\Services\DisponibilidadHelper::formatearSlotsParaSelector($slots);
                            })
                            ->required()
                            ->live()
                            ->visible(fn(Get $get) => in_array($get('modalidad_descargos'), ['presencial', 'telefonico']) && $get('fecha_temp_descargos'))
                            ->helperText('Disponibilidad de 45 minutos - Horario de oficina: 8:00 AM - 5:00 PM')
                            ->descriptions(function (Get $get) {
                                $fecha = $get('fecha_temp_descargos');
                                $modalidad = $get('modalidad_descargos');
                                $abogadoId = $get('abogado_id');

                                if (!$fecha || !$abogadoId || !in_array($modalidad, ['presencial', 'telefonico'])) {
                                    return [];
                                }

                                $slots = \App\Services\DisponibilidadHelper::obtenerSlotsDisponibles(
                                    $abogadoId,
                                    $fecha,
                                    $modalidad
                                );

                                // Generar descripciones para cada slot
                                $descriptions = [];
                                foreach ($slots as $slot) {
                                    $key = $slot['datetime_inicio'];
                                    $descriptions[$key] = '45 minutos';
                                }
                                return $descriptions;
                            })
                            ->columns(3)
                            ->inline()
                            ->columnSpanFull(),

                        // PARA VIRTUAL: Selector manual de fecha y hora
                        Forms\Components\DatePicker::make('fecha_descargos_programada')
                            ->label('Fecha Programada de Descargos')
                            ->required()
                            ->minDate(now()->addDays(5)->startOfDay())
                            ->native(false)
                            ->live()
                            ->visible(fn(Get $get) => $get('modalidad_descargos') === 'virtual')
                            ->helperText('Seleccione la fecha para la audiencia virtual'),


                        TimePickerField::make('hora_descargos_programada')
                            ->okLabel("Confirmar")
                            ->cancelLabel("Cancelar")
                            // Forms\Components\TimePicker::make('hora_descargos_programada')
                            ->label('Hora Programada de Descargos')
                            ->required()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $fecha = $get('fecha_descargos_programada');
                                $hora = $state;

                                if ($fecha && $hora) {
                                    // Combinar fecha y hora en un datetime
                                    $datetime = \Carbon\Carbon::parse($fecha)->setTimeFromTimeString($hora);
                                    $set('fecha_descargos_programada', $datetime->format('Y-m-d H:i:s'));
                                }
                            })
                            // ->minTime('08:00')
                            // ->maxTime('17:00')
                            // ->seconds(false)
                            // ->native(false)
                            ->visible(fn(Get $get) => $get('modalidad_descargos') === 'virtual')
                            ->helperText('Seleccione la hora para la audiencia virtual'),

                        // Forms\Components\Select::make('estado')
                        //     ->label('Estado')
                        //     ->options([
                        //         'apertura' => 'Apertura',
                        //         'descargos_pendientes' => 'Descargos Pendientes',
                        //         'descargos_realizados' => 'Descargos Realizados',
                        //         'sancion_emitida' => 'Sanción Emitida',
                        //         'impugnacion_realizada' => 'Impugnación Realizada',
                        //         'cerrado' => 'Cerrado',
                        //     ])
                        //     ->default('apertura')
                        //     ->required()
                        //     ->live(),

                        Forms\Components\Select::make('articulos_legales_ids')
                            ->label('Artículos Legales Incumplidos')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\ArticuloLegal::activos()
                                    ->ordenado()
                                    ->get()
                                    ->mapWithKeys(fn($articulo) => [
                                        $articulo->id => $articulo->texto_completo
                                    ]);
                            })
                            ->placeholder('Seleccione uno o más artículos...')
                            ->helperText('Seleccione los artículos del Código Sustantivo del Trabajo que presuntamente incumplió el trabajador')
                            ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'abogado']))
                            ->columnSpanFull(),

                    ])->columns(2),

                Forms\Components\Section::make('Detalles del Proceso')
                    ->schema([
                        Forms\Components\DateTimePicker::make('fecha_solicitud')
                            ->label('Fecha de Solicitud')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y H:i')
                            ->required()
                            ->native(false),

                        Forms\Components\RichEditor::make('hechos')
                            ->label('Motivo de la citacion a diligencia de descargos')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'italic',
                                'orderedList',
                                'redo',
                                'undo',
                            ])
                            ->helperText('Describa detalladamente los hechos que motivan el proceso disciplinario')
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('fecha_ocurrencia')
                            ->label('Fecha de Ocurrencia de los Hechos')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required()
                            ->helperText('Fecha en que ocurrieron los hechos descritos arriba'),
                    ]),

                Forms\Components\Section::make('Decisión y Sanción')
                    ->schema([
                        Forms\Components\Toggle::make('decision_sancion')
                            ->label('¿Procede Sanción?')
                            ->live(),

                        Forms\Components\Toggle::make('impugnado')
                            ->label('¿Procede Impugnación?')
                            ->live(),

                        Forms\Components\Select::make('tipo_sancion')
                            ->label('Tipo de Sanción')
                            ->options([
                                'llamado_atencion' => 'Llamado de Atención',
                                'suspension' => 'Suspensión',
                                'terminacion' => 'Terminación de Contrato',
                            ])
                            ->visible(fn(Get $get) => $get('decision_sancion') === true),

                        // Forms\Components\Textarea::make('motivo_archivo')
                        //     ->label('Motivo de Archivo')
                        //     ->rows(3)
                        //     ->visible(fn(Get $get) => $get('decision_sancion') === false)
                        //     ->columnSpanFull(),

                        // Forms\Components\DateTimePicker::make('fecha_notificacion')
                        //     ->label('Fecha de Notificación')
                        //     ->displayFormat('d/m/Y H:i')
                        //     ->native(false),

                        Forms\Components\DateTimePicker::make('fecha_limite_impugnacion')
                            ->label('Fecha Límite para Impugnación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->minDate(now()->addDays(3)->startOfDay())
                            ->helperText('3 días hábiles desde la notificación')
                            ->visible(fn(Get $get) => $get('impugnado') === true),

                        // Forms\Components\DateTimePicker::make('fecha_impugnacion')
                        //     ->label('Fecha de Impugnación')
                        //     ->displayFormat('d/m/Y H:i')
                        //     ->native(false)
                        //     ->visible(fn(Get $get) => $get('impugnado') === true),<

                        Forms\Components\DateTimePicker::make('fecha_cierre')
                            ->label('Fecha de Cierre')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->visible(fn(Get $get) => in_array($get('estado'), ['cerrado', 'archivado'])),
                    ])->columns(2)
                    ->visible(fn() => Auth::user()->hasRole(['super_admin']))
                    ->disabled(fn() => !Auth::user()->hasRole(['super_admin'])),
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
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(['nombres', 'apellidos'])
                    ->sortable()
                    ->description(
                        fn(ProcesoDisciplinario $record): string =>
                        $record->trabajador->cargo ?? ''
                    ),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->colors([
                        'gray' => 'apertura',
                        'warning' => ['descargos_pendientes'],
                        'info' => ['descargos_realizados'],
                        'primary' => ['sancion_emitida'],
                        'success' => 'cerrado',
                        'danger' => ['impugnacion_realizada'],
                        'secondary' => 'archivado',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'apertura' => 'Apertura',
                        'descargos_pendientes' => 'Descargos Pendientes',
                        'descargos_realizados' => 'Descargos Realizados',
                        'sancion_emitida' => 'Sanción Emitida',
                        'impugnacion_realizada' => 'Impugnación Realizada',
                        'cerrado' => 'Cerrado',
                        'archivado' => 'Archivado',
                        default => $state,
                    }),

                // Tables\Columns\TextColumn::make('tipo_sancion')
                //     ->label('Tipo Sanción')
                //     ->formatStateUsing(fn(?string $state): string => match ($state) {
                //         'llamado_atencion' => 'Llamado de Atención',
                //         'suspension' => 'Suspensión',
                //         'terminacion' => 'Terminación',
                //         default => 'N/A',
                //     })
                //     ->toggleable(),

                Tables\Columns\IconColumn::make('impugnado')
                    ->label('Impugnado')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fecha_descargos_programada')
                    ->label('Descargos Programados')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('modalidad_descargos')
                    ->label('Modalidad Descargos')
                    ->sortable()
                    ->toggleable(),


                Tables\Columns\BadgeColumn::make('modalidad_descargos')
                    ->label('Modalidad Descargos')
                    ->sortable()
                    ->searchable()
                    ->colors([
                        'primary' => 'presencial',
                        'success' => 'telefonico',
                        'gray' => 'virtual',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'presencial' => 'Presencial',
                        'telefonico' => 'Telefónico',
                        'virtual' => 'Virtual',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('fecha_solicitud')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'apertura' => 'Apertura',
                        'descargos_pendientes' => 'Descargos Pendientes',
                        'descargos_realizados' => 'Descargos Realizados',
                        'sancion_emitida' => 'Sanción Emitida',
                        'impugnacion_realizada' => 'Impugnación Realizada',
                        'cerrado' => 'Cerrado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('modalidad_descargos')
                    ->label('Modalidad de Descargos')
                    ->options([
                        'presencial' => 'Presencial',
                        'telefonico' => 'Telefónico',
                        'virtual' => 'Virtual',
                    ]),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('impugnado')
                    ->label('Impugnados')
                    ->query(fn(Builder $query): Builder => $query->where('impugnado', true)),

                Tables\Filters\TrashedFilter::make()
                    ->label('Eliminados'),
            ])
            ->actions([
                // Botón 1: Generar Documento (solo para revisar)
                Tables\Actions\Action::make('generar_documento')
                    ->label('Generar Documento')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('¿Generar documento de citación?')
                    ->modalDescription('Se generará el documento de citación para que pueda revisarlo. No se enviará por correo.')
                    ->modalSubmitActionLabel('Generar Documento')
                    ->modalCancelActionLabel('Cancelar')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        !empty($record->fecha_descargos_programada) &&
                            auth()->user()?->hasAnyRole(['super_admin', 'abogado'])
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $service = new \App\Services\DocumentGeneratorService();

                        try {
                            $pdfPath = $service->generarCitacionDescargos($record);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Documento generado!')
                                ->body('El documento de citación se generó exitosamente. Puede revisarlo en: ' . basename($pdfPath))
                                ->duration(8000)
                                ->send();

                            // Opcional: descargar el archivo
                            return response()->download($pdfPath);
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al generar documento')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // Botón 2: Enviar Citación (generar y enviar)
                Tables\Actions\Action::make('enviar_citacion')
                    ->label('Enviar Citación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Generar y enviar citación?')
                    ->modalDescription(
                        fn(ProcesoDisciplinario $record) =>
                        "Se generará la citación a descargos y se enviará por correo electrónico a: " .
                            ($record->trabajador->email ?? 'No tiene email registrado')
                    )
                    ->modalSubmitActionLabel('Sí, Generar y Enviar')
                    ->modalCancelActionLabel('Cancelar')
                    ->visible(
                        fn(ProcesoDisciplinario $record) =>
                        !empty($record->trabajador->email) &&
                            !empty($record->fecha_descargos_programada) &&
                            auth()->user()?->hasAnyRole(['super_admin', 'abogado'])
                    )
                    ->action(function (ProcesoDisciplinario $record) {
                        $service = new \App\Services\DocumentGeneratorService();
                        $result = $service->generarYEnviarCitacion($record);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('¡Citación enviada!')
                                ->body('La citación se generó y envió exitosamente al correo del trabajador.')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al enviar citación')
                                ->body($result['message'])
                                ->send();
                        }
                    }),

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
                        ->label('Eliminar seleccionados'),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Restaurar seleccionados'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListProcesoDisciplinarios::route('/'),
            'create' => Pages\CreateProcesoDisciplinario::route('/create'),
            'edit' => Pages\EditProcesoDisciplinario::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Si es cliente, solo mostrar procesos de su empresa
        $user = Auth::user();
        if ($user && $user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $query;
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

    protected static function getAreas(): array
    {
        return [
            'Administración',
            'Recursos Humanos',
            'Contabilidad y Finanzas',
            'Tesorería',
            'Ventas',
            'Mercadeo',
            'Comercial',
            'Operaciones',
            'Producción',
            'Logística',
            'Almacén',
            'Tecnología',
            'Sistemas',
            'TI',
            'Compras',
            'Servicio al Cliente',
            'Calidad',
            'Mantenimiento',
            'Seguridad',
            'Jurídica',
            'Legal',
            'Auditoría',
            'Gerencia',
            'Dirección',
        ];
    }

    protected static function getDepartamentos(): array
    {
        return [
            'Amazonas' => 'Amazonas',
            'Antioquia' => 'Antioquia',
            'Arauca' => 'Arauca',
            'Atlántico' => 'Atlántico',
            'Bolívar' => 'Bolívar',
            'Boyacá' => 'Boyacá',
            'Caldas' => 'Caldas',
            'Caquetá' => 'Caquetá',
            'Casanare' => 'Casanare',
            'Cauca' => 'Cauca',
            'Cesar' => 'Cesar',
            'Chocó' => 'Chocó',
            'Córdoba' => 'Córdoba',
            'Cundinamarca' => 'Cundinamarca',
            'Guainía' => 'Guainía',
            'Guaviare' => 'Guaviare',
            'Huila' => 'Huila',
            'La Guajira' => 'La Guajira',
            'Magdalena' => 'Magdalena',
            'Meta' => 'Meta',
            'Nariño' => 'Nariño',
            'Norte de Santander' => 'Norte de Santander',
            'Putumayo' => 'Putumayo',
            'Quindío' => 'Quindío',
            'Risaralda' => 'Risaralda',
            'San Andrés y Providencia' => 'San Andrés y Providencia',
            'Santander' => 'Santander',
            'Sucre' => 'Sucre',
            'Tolima' => 'Tolima',
            'Valle del Cauca' => 'Valle del Cauca',
            'Vaupés' => 'Vaupés',
            'Vichada' => 'Vichada',
        ];
    }

    protected static function getCiudades(?string $departamento): array
    {
        if (!$departamento) {
            return [];
        }

        $ciudades = [
            'Amazonas' => ['Leticia', 'Puerto Nariño', 'El Encanto', 'La Chorrera'],
            'Antioquia' => ['Medellín', 'Bello', 'Itagüí', 'Envigado', 'Rionegro', 'Sabaneta', 'Apartadó', 'Turbo', 'Caucasia', 'Yarumal'],
            'Arauca' => ['Arauca', 'Arauquita', 'Saravena', 'Tame', 'Fortul'],
            'Atlántico' => ['Barranquilla', 'Soledad', 'Malambo', 'Sabanalarga', 'Puerto Colombia', 'Galapa'],
            'Bolívar' => ['Cartagena', 'Magangué', 'Turbaco', 'El Carmen de Bolívar', 'Arjona', 'Mompós'],
            'Boyacá' => ['Tunja', 'Duitama', 'Sogamoso', 'Chiquinquirá', 'Puerto Boyacá', 'Paipa', 'Villa de Leyva', 'Moniquirá'],
            'Caldas' => ['Manizales', 'Villamaría', 'Chinchiná', 'La Dorada', 'Riosucio', 'Anserma'],
            'Caquetá' => ['Florencia', 'San Vicente del Caguán', 'Puerto Rico', 'El Doncello', 'Belén de los Andaquíes'],
            'Casanare' => ['Yopal', 'Aguazul', 'Villanueva', 'Monterrey', 'Paz de Ariporo'],
            'Cauca' => ['Popayán', 'Santander de Quilichao', 'Puerto Tejada', 'Guapi', 'Patía'],
            'Cesar' => ['Valledupar', 'Aguachica', 'Codazzi', 'Bosconia', 'Chiriguaná', 'La Jagua de Ibirico'],
            'Chocó' => ['Quibdó', 'Istmina', 'Condoto', 'Tadó', 'Acandí', 'Bahía Solano'],
            'Córdoba' => ['Montería', 'Cereté', 'Lorica', 'Sahagún', 'Planeta Rica', 'Montelíbano'],
            'Cundinamarca' => ['Bogotá D.C.', 'Soacha', 'Facatativá', 'Chía', 'Zipaquirá', 'Fusagasugá', 'Madrid', 'Girardot', 'Cajicá', 'La Calera'],
            'Guainía' => ['Inírida', 'Barranco Minas', 'Mapiripana', 'San Felipe'],
            'Guaviare' => ['San José del Guaviare', 'Calamar', 'El Retorno', 'Miraflores'],
            'Huila' => ['Neiva', 'Pitalito', 'Garzón', 'La Plata', 'Campoalegre', 'Gigante'],
            'La Guajira' => ['Riohacha', 'Maicao', 'Uribia', 'Manaure', 'Villanueva', 'Fonseca'],
            'Magdalena' => ['Santa Marta', 'Ciénaga', 'Fundación', 'Zona Bananera', 'Plato', 'El Banco'],
            'Meta' => ['Villavicencio', 'Acacías', 'Granada', 'Puerto López', 'San Martín', 'Cumaral'],
            'Nariño' => ['Pasto', 'Tumaco', 'Ipiales', 'Túquerres', 'La Unión', 'Sandoná'],
            'Norte de Santander' => ['Cúcuta', 'Ocaña', 'Pamplona', 'Villa del Rosario', 'Los Patios', 'Tibú'],
            'Putumayo' => ['Mocoa', 'Puerto Asís', 'Orito', 'Valle del Guamuez', 'Villagarzón'],
            'Quindío' => ['Armenia', 'Calarcá', 'La Tebaida', 'Montenegro', 'Circasia', 'Quimbaya'],
            'Risaralda' => ['Pereira', 'Dosquebradas', 'La Virginia', 'Santa Rosa de Cabal', 'Marsella'],
            'San Andrés y Providencia' => ['San Andrés', 'Providencia'],
            'Santander' => ['Bucaramanga', 'Floridablanca', 'Girón', 'Piedecuesta', 'Barrancabermeja', 'San Gil', 'Socorro'],
            'Sucre' => ['Sincelejo', 'Corozal', 'San Marcos', 'Tolú', 'Sampués'],
            'Tolima' => ['Ibagué', 'Espinal', 'Melgar', 'Honda', 'Chaparral', 'Líbano'],
            'Valle del Cauca' => ['Cali', 'Palmira', 'Buenaventura', 'Tuluá', 'Cartago', 'Buga', 'Jamundí', 'Yumbo'],
            'Vaupés' => ['Mitú', 'Carurú', 'Taraira'],
            'Vichada' => ['Puerto Carreño', 'La Primavera', 'Cumaribo'],
        ];

        return array_combine(
            $ciudades[$departamento] ?? [],
            $ciudades[$departamento] ?? []
        );
    }
}
