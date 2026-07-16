<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmpresaResource\Pages;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('reglamentoInterno');
        $user  = auth()->user();

        if ($user && $user->hasRole('cliente')) {
            $query->where('id', $user->empresa_id);
        }

        return $query;
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?string $navigationGroup = 'Administración';

    /** El cliente solo tiene una empresa: en el menú aparece como "Mi empresa". */
    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('cliente') ? 'Mi empresa' : 'Empresas';
    }

    protected static ?int $navigationSort = 2;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Datos de la empresa')
                ->icon('heroicon-o-building-office-2')
                ->iconColor('primary')
                ->schema([
                    Infolists\Components\TextEntry::make('razon_social')
                        ->label('Razón social')->weight('bold')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('nit')->label('NIT')
                        ->icon('heroicon-o-identification'),
                    Infolists\Components\TextEntry::make('tipo_societario')
                        ->label('Tipo societario')->placeholder('—'),
                    Infolists\Components\TextEntry::make('representante_legal')
                        ->label('Representante legal')->placeholder('—')->icon('heroicon-o-user'),
                    Infolists\Components\TextEntry::make('actividadEconomica.nombre')
                        ->label('Actividad económica')->placeholder('—'),
                    Infolists\Components\TextEntry::make('numero_empleados')
                        ->label('Número de empleados')->placeholder('—')->icon('heroicon-o-user-group'),
                    Infolists\Components\IconEntry::make('active')
                        ->label('Activa')->boolean(),
                ])->columns(2),

            Infolists\Components\Section::make('Contacto y ubicación')
                ->icon('heroicon-o-phone')
                ->iconColor('primary')
                ->schema([
                    Infolists\Components\TextEntry::make('telefono')
                        ->label('Teléfono')->placeholder('—')->icon('heroicon-o-phone'),
                    Infolists\Components\TextEntry::make('email_contacto')
                        ->label('Correo')->placeholder('—')->icon('heroicon-o-envelope'),
                    Infolists\Components\TextEntry::make('direccion')
                        ->label('Dirección')->placeholder('—')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('departamento')
                        ->label('Departamento')->placeholder('—'),
                    Infolists\Components\TextEntry::make('ciudad')
                        ->label('Ciudad / Municipio')->placeholder('—'),
                    Infolists\Components\TextEntry::make('dias_laborales_texto')
                        ->label('Días laborales')
                        ->state(fn(Empresa $record) => $record->dias_laborales_texto)
                        ->badge()->color('gray'),
                ])->columns(2),

            Infolists\Components\Section::make('Reglamento Interno de Trabajo')
                ->icon('heroicon-o-document-text')
                ->iconColor('primary')
                ->schema([
                    // ViewEntry renderiza el blade tal cual (sin sanitizar el HTML/estilos),
                    // a diferencia de TextEntry->html(). Muestra la obligación + la tarjeta.
                    Infolists\Components\ViewEntry::make('rit_status')
                        ->hiddenLabel()
                        ->view('filament.components.empresa-rit-status'),
                ]),
        ]);
    }

    /** ¿La empresa que se está creando está obligada a tener RIT? (Art. 105 CST) */
    public static function esObligadaRit(Get $get): bool
    {
        $seccion = $get('actividad_economica_id')
            ? ActividadEconomica::find($get('actividad_economica_id'))?->seccion
            : null;
        $n = $get('numero_empleados');
        $n = is_numeric($n) ? (int) $n : null;

        return \App\Support\ObligacionRit::requiere($n, $seccion) === true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    /**
     * Esquema del formulario de empresa, expuesto para reutilizarlo en el wizard
     * de creación (CreateEmpresa) sin duplicar campos.
     */
    public static function formSchema(): array
    {
        return [
            Forms\Components\Section::make('Información de la Empresa')
                ->description('Datos básicos de identificación')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Forms\Components\TextInput::make('razon_social')
                        ->label('Razón Social')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ej: EMPRESA ABC')
                        ->helperText('Nombre legal sin tipo societario')
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->columnSpan(2),

                    Forms\Components\Select::make('tipo_societario')
                        ->label('Tipo Societario')
                        ->options(\App\Models\Empresa::TIPOS_SOCIETARIOS)
                        ->searchable()
                        ->placeholder('Seleccione...')
                        ->helperText('Forma jurídica')
                        ->live(),

                    Forms\Components\TextInput::make('nit')
                        ->label('NIT')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->mask(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                            ? '999999999-9'
                            : null)
                        ->placeholder(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                            ? 'Ej: 900123456-7'
                            : 'Ej: 1023456789')
                        ->helperText(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                            ? 'Incluya el dígito de verificación separado por guion'
                            : 'Número de cédula de ciudadanía')
                        ->rules(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                            ? ['regex:/^\d{6,12}-\d$/']
                            : [])
                        ->validationMessages(['regex' => 'El NIT debe incluir el dígito de verificación (ej: 900123456-7).'])
                        ->suffixIcon('heroicon-o-identification'),

                    Forms\Components\TextInput::make('representante_legal')
                        ->label('Representante Legal')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ej: Juan Pérez García')
                        ->helperText('Nombre del representante legal')
                        ->suffixIcon('heroicon-o-user'),

                    Forms\Components\Toggle::make('active')
                        ->label('Empresa Activa')
                        ->default(true)
                        ->helperText('Desactive si la empresa ya no está en servicio')
                        ->inline(false),

                    // Los días laborales se definen en el Reglamento Interno (no aquí).
                    // Se conserva el campo legado oculto como respaldo por defecto.
                    Forms\Components\Hidden::make('dias_laborales')
                        ->default('lunes_viernes'),
                ])->columns(2),

            Forms\Components\Section::make('Información de Contacto')
                ->description('Datos para comunicación')
                ->icon('heroicon-o-phone')
                ->schema([
                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono / Celular')
                        ->tel()
                        // ->required()
                        ->mask('9999999999')
                        ->maxLength(10)
                        ->rules(['nullable', 'regex:/^[0-9]{10}$/'])
                        ->validationMessages([
                            'regex' => 'El teléfono debe tener exactamente 10 dígitos numéricos (sin +57, espacios, guiones ni letras).',
                        ])
                        ->placeholder('3001234567')
                        ->helperText('10 dígitos, solo números. Se usa para las notificaciones por WhatsApp.')
                        ->suffixIcon('heroicon-o-phone'),

                    Forms\Components\TextInput::make('email_contacto')
                        ->label('Email de Contacto')
                        ->email()
                        // ->required()
                        ->maxLength(255)
                        ->placeholder('contacto@empresa.com')
                        ->helperText('Correo electrónico principal')
                        ->suffixIcon('heroicon-o-envelope'),

                    Forms\Components\Textarea::make('direccion')
                        ->label('Dirección')
                        // ->required()
                        ->rows(2)
                        ->placeholder('Ej: Calle 123 # 45-67, Edificio ABC, Piso 3')
                        ->helperText('Dirección completa de la empresa')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Ubicación')
                ->description('Ciudad y departamento')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Forms\Components\Select::make('departamento')
                        ->label('Departamento')
                        ->required()
                        ->searchable()
                        // Misma fuente que el registro (tabla departamentos, incluye Bogotá D.C.).
                        ->options(self::getDepartamentos())
                        ->live()
                        ->afterStateUpdated(fn(Set $set) => $set('ciudad', null))
                        ->helperText('Seleccione el departamento'),

                    Forms\Components\Select::make('ciudad')
                        ->label('Ciudad')
                        ->required()
                        ->searchable()
                        ->options(function (Get $get) {
                            $departamento = $get('departamento');
                            return self::getCiudadesPorDepartamento($departamento);
                        })
                        ->disabled(fn(Get $get) => empty($get('departamento')))
                        ->helperText('Seleccione primero el departamento')
                        ->placeholder('Seleccione una ciudad...'),
                ])->columns(2),

            Forms\Components\Section::make('Actividad Económica (CIIU)')
                ->description('Clasificación Industrial Internacional Uniforme Rev. 4 A.C. Colombia')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    Forms\Components\Select::make('actividad_economica_id')
                        ->label('Actividad Económica Principal')
                        ->relationship('actividadEconomica', 'nombre')
                        ->getOptionLabelFromRecordUsing(fn(ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
                        ->getOptionLabelUsing(function ($value): ?string {
                            $a = ActividadEconomica::find($value);
                            return $a ? "{$a->codigo} - {$a->nombre}" : null;
                        })
                        ->searchable(['codigo', 'nombre'])
                        ->preload(false)
                        ->nullable()
                        ->live()
                        ->placeholder('Buscar por código o nombre...')
                        ->helperText('Actividad principal según el RUT de la empresa')
                        ->columnSpanFull(),

                    // Nº de empleados → determina la obligación de RIT (Art. 105 CST).
                    Forms\Components\TextInput::make('numero_empleados')
                        ->label('Número de empleados')
                        ->numeric()
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->placeholder('Ej: 12')
                        ->helperText('Empleados permanentes. Determina si la empresa está obligada a tener RIT.')
                        ->suffixIcon('heroicon-o-user-group'),

                    Forms\Components\Placeholder::make('obligacion_rit')
                        ->hiddenLabel()
                        ->content(function (Get $get) {
                            $seccion = $get('actividad_economica_id')
                                ? ActividadEconomica::find($get('actividad_economica_id'))?->seccion
                                : null;
                            $n = $get('numero_empleados');
                            $n = is_numeric($n) ? (int) $n : null;

                            return new \Illuminate\Support\HtmlString(
                                \App\Support\ObligacionRit::avisoHtml($n, $seccion)
                            );
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('actividadesSecundarias')
                        ->label('Actividades Secundarias')
                        ->relationship('actividadesSecundarias', 'nombre')
                        ->getOptionLabelFromRecordUsing(fn(ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
                        ->getOptionLabelUsing(function ($value): ?string {
                            $a = ActividadEconomica::find($value);
                            return $a ? "{$a->codigo} - {$a->nombre}" : null;
                        })
                        ->searchable(['codigo', 'nombre'])
                        ->preload(false)
                        ->multiple()
                        ->nullable()
                        ->placeholder('Buscar por código o nombre...')
                        ->helperText('Actividades complementarias que también ejerce la empresa')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Reglamento Interno')
                ->description('Documento normativo interno de la empresa')
                ->icon('heroicon-o-document-text')
                ->schema([

                    // ── Al CREAR: rama según obligación (¿tiene RIT?) ────────────
                    Forms\Components\Placeholder::make('rit_obligacion_crear')
                        ->hiddenLabel()
                        ->visibleOn('create')
                        ->content(function (Get $get) {
                            $seccion = $get('actividad_economica_id')
                                ? ActividadEconomica::find($get('actividad_economica_id'))?->seccion
                                : null;
                            $n = $get('numero_empleados');
                            $n = is_numeric($n) ? (int) $n : null;

                            return new HtmlString(\App\Support\ObligacionRit::avisoHtml($n, $seccion));
                        })
                        ->columnSpanFull(),

                    // Campo oculto que guarda la elección; las cards lo escriben.
                    Forms\Components\Hidden::make('rit_opcion')
                        ->live()
                        ->default(fn(Get $get) => static::esObligadaRit($get) ? 'construir' : 'despues'),

                    // Selector en cards con Lordicon (loop), estilo "tipo de cuenta".
                    Forms\Components\View::make('filament.components.rit-opcion-cards')
                        ->visibleOn('create')
                        ->viewData(fn(Get $get) => ['esObligada' => static::esObligadaRit($get)])
                        ->columnSpanFull(),

                    // ── Visor / descarga cuando existe RIT ───────────────────────
                    // Estado del RIT (tarjeta branded) — al editar. La obligación ya se
                    // muestra arriba junto al Nº de empleados, por eso aquí va sin ella.
                    Forms\Components\Placeholder::make('rit_estado')
                        ->hiddenLabel()
                        ->visibleOn('edit')
                        ->content(fn($record) => new HtmlString(
                            view('filament.components.empresa-rit-status', [
                                'empresa'           => $record,
                                'mostrarObligacion' => false,
                            ])->render()
                        ))
                        ->columnSpanFull(),

                    // ── Upload para agregar / reemplazar RIT ─────────────────────
                    //   El toggle de visibilidad va en el Group (contenedor), no sobre
                    //   el FileUpload directo (no re-renderiza confiable dentro del form).
                    //   Al editar: siempre visible. Al crear: solo si eligió "tiene".
                    Forms\Components\Group::make([
                        Forms\Components\FileUpload::make('reglamento_docx_temp')
                            ->label('Subir Reglamento Interno (.docx o .pdf)')
                            ->helperText('Formatos aceptados: .docx y .pdf — máx. 10 MB.')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/pdf',
                            ])
                            ->disk('local')
                            ->directory('reglamentos-temp')
                            ->visibility('private')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ])
                        // Al editar: siempre visible. Al crear: solo si eligió la card
                        // "Sí, ya lo tengo" (control con Alpine, reacciona al instante).
                        ->extraAttributes(fn(string $operation): array => $operation === 'create'
                            ? ['x-show' => "\$wire.data?.rit_opcion === 'tiene'", 'x-cloak' => 'true']
                            : [])
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->description(fn(Empresa $record): ?string => $record->departamento),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email_contacto')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('representante_legal')
                    ->label('Representante')
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('dias_laborales_texto')
                    ->label('Días Laborales')
                    ->state(fn(Empresa $record) => $record->dias_laborales_texto)
                    ->badge()
                    ->color(fn(Empresa $record) => count($record->diasHabilesSet()) >= 6 ? 'warning' : 'success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('actividadEconomica.codigo')
                    ->label('CIIU Principal')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->tooltip(fn(Empresa $record): ?string => $record->actividadEconomica?->nombre)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trabajadores_count')
                    ->label('Trabajadores')
                    ->counts('trabajadores')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rit_estado')
                    ->label('RIT')
                    ->badge()
                    ->getStateUsing(
                        fn(Empresa $record): string =>
                        match ($record->reglamentoInterno?->fuente) {
                            'construido_ia' => 'IA generado',
                            default         => $record->reglamentoInterno ? 'Manual' : 'Sin RIT',
                        }
                    )
                    ->color(fn(string $state): string => match ($state) {
                        'IA generado' => 'success',
                        'Manual'      => 'info',
                        default       => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'IA generado' => 'heroicon-o-cpu-chip',
                        'Manual'      => 'heroicon-o-document-arrow-up',
                        default       => 'heroicon-o-document-minus',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('departamento')
                    ->label('Departamento')
                    ->options(fn() => self::getDepartamentos())
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todas las empresas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),

                Tables\Filters\SelectFilter::make('ciiu_seccion')
                    ->label('Sección CIIU')
                    ->options(\App\Filament\Admin\Resources\ActividadEconomicaResource::getSecciones())
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }
                        return $query->whereHas('actividadEconomica', fn(Builder $q) => $q->whereIn('seccion', $values));
                    })
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('descargar_rit')
                    ->label('Descargar RIT')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn(Empresa $record): string => (
                        auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('abogado')
                        ? route('rit.descargar.admin', $record)
                        : route('rit.descargar')
                    ))
                    ->openUrlInNewTab()
                    ->visible(fn(Empresa $record): bool => $record->reglamentoInterno !== null),
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Empresa $record): bool => !auth()->user()->hasRole('cliente') || auth()->user()->empresa_id === $record->id),

                Tables\Actions\Action::make('desactivar')
                    ->label('Desactivar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar Empresa')
                    ->modalDescription(fn(Empresa $record) => "¿Está seguro que desea desactivar a la empresa '{$record->nombre_completo}'? La empresa no será eliminada, solo quedará marcada como inactiva.")
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->visible(fn(Empresa $record) => $record->active)
                    ->action(function (Empresa $record) {
                        $record->update(['active' => false]);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Empresa desactivada')
                            ->body("La empresa '{$record->nombre_completo}' ha sido desactivada.")
                            ->send();
                    }),
                Tables\Actions\Action::make('activar')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activar Empresa')
                    ->modalDescription(fn(Empresa $record) => "¿Está seguro que desea activar a la empresa '{$record->nombre_completo}'?")
                    ->modalSubmitActionLabel('Sí, activar')
                    ->visible(fn(Empresa $record) => !$record->active)
                    ->action(function (Empresa $record) {
                        $record->update(['active' => true]);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Empresa activada')
                            ->body("La empresa '{$record->nombre_completo}' ha sido activada.")
                            ->send();
                    }),


                // Tables\Actions\DeleteAction::make()
                //     ->label('Eliminar')
                //     ->before(function (Tables\Actions\DeleteAction $action, \App\Models\Empresa $record) {
                //         // Verificar si tiene procesos disciplinarios
                //         if ($record->procesosDisciplinarios()->count() > 0) {
                //             \Filament\Notifications\Notification::make()
                //                 ->danger()
                //                 ->title('No se puede eliminar la empresa')
                //                 ->body("La empresa '{$record->razon_social}' tiene {$record->procesosDisciplinarios()->count()} procesos disciplinarios asociados. Debe eliminar o reasignar esos procesos primero.")
                //                 ->persistent()
                //                 ->send();

                //             $action->cancel();
                //         }

                //         // Verificar si tiene trabajadores
                //         if ($record->trabajadores()->count() > 0) {
                //             \Filament\Notifications\Notification::make()
                //                 ->danger()
                //                 ->title('No se puede eliminar la empresa')
                //                 ->body("La empresa '{$record->razon_social}' tiene {$record->trabajadores()->count()} trabajadores asociados. Debe eliminar o reasignar esos trabajadores primero.")
                //                 ->persistent()
                //                 ->send();

                //             $action->cancel();
                //         }
                //     }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('desactivar_seleccionados')
                        ->label('Desactivar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Desactivar Empresas')
                        ->modalDescription('¿Está seguro que desea desactivar las empresas seleccionadas?')
                        ->modalSubmitActionLabel('Sí, desactivar')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $desactivados = 0;
                            foreach ($records as $record) {
                                if ($record->active) {
                                    $record->update(['active' => false]);
                                    $desactivados++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Empresas desactivadas')
                                ->body("{$desactivados} empresa(s) desactivada(s) correctamente.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('activar_seleccionados')
                        ->label('Activar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activar Empresas')
                        ->modalDescription('¿Está seguro que desea activar las empresas seleccionadas?')
                        ->modalSubmitActionLabel('Sí, activar')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $activados = 0;
                            foreach ($records as $record) {
                                if (!$record->active) {
                                    $record->update(['active' => true]);
                                    $activados++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Empresas activadas')
                                ->body("{$activados} empresa(s) activada(s) correctamente.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'view' => Pages\ViewEmpresa::route('/{record}'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if ($user->hasRole('cliente')) {
            return static::getModel()::where('active', true)
                ->where('id', $user->empresa_id)
                ->count();
        }

        return static::getModel()::where('active', true)->count();
    }

    public static function getDepartamentos(): array
    {
        return \DB::table('departamentos')
            ->orderBy('nombre')
            ->pluck('nombre', 'nombre')
            ->toArray();
    }

    public static function getCiudadesPorDepartamento(?string $departamento): array
    {
        if (empty($departamento)) {
            return [];
        }

        $municipios = \DB::table('municipios')
            ->join('departamentos', 'municipios.departamento_id', '=', 'departamentos.id')
            ->where('departamentos.nombre', $departamento)
            ->orderBy('municipios.nombre')
            ->pluck('municipios.nombre')
            ->toArray();

        if (empty($municipios)) {
            return [$departamento => $departamento];
        }

        return array_combine($municipios, $municipios);
    }
}
