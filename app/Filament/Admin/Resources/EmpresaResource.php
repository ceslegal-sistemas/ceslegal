<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmpresaResource\Pages;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('reglamentoInterno');
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Empresa')
                    ->description('Datos básicos de identificación')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Forms\Components\TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: EMPRESA ABC S.A.S')
                            ->helperText('Nombre legal completo de la empresa')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nit')
                            ->label('NIT')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ej: 900123456-7')
                            ->mask('999999999-9')
                            ->helperText('Número de Identificación Tributaria')
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

                        Forms\Components\Select::make('dias_laborales')
                            ->label('Días Laborales')
                            ->options([
                                'lunes_viernes' => 'Lunes a Viernes',
                                'lunes_sabado' => 'Lunes a Sábado',
                            ])
                            ->default('lunes_viernes')
                            ->required()
                            ->native(false)
                            ->helperText('Seleccione los días que la empresa opera normalmente'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->description('Datos para comunicación')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            // ->required()
                            ->maxLength(50)
                            ->placeholder('Ej: +57 300 123 4567')
                            ->helperText('Número de contacto principal')
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
                            ->options([
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
                            ])
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
                            ->getOptionLabelFromRecordUsing(fn (ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
                            ->searchable(['codigo', 'nombre'])
                            ->preload(false)
                            ->nullable()
                            ->placeholder('Buscar por código o nombre...')
                            ->helperText('Actividad principal según el RUT de la empresa')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('actividadesSecundarias')
                            ->label('Actividades Secundarias')
                            ->relationship('actividadesSecundarias', 'nombre')
                            ->getOptionLabelFromRecordUsing(fn (ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
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
                        Forms\Components\FileUpload::make('rit_docx_adjunto')
                            ->label('Documento generado')
                            ->disk('public')
                            ->disabled()
                            ->dehydrated(false)
                            ->downloadable()
                            ->afterStateHydrated(function ($component, $record) {
                                $ruta = $record?->reglamentoInterno?->ruta_docx;
                                $component->state($ruta ? [$ruta] : []);
                            })
                            ->visible(fn ($record) => (bool) $record?->reglamentoInterno?->ruta_docx)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('rit_sin_docx')
                            ->label('Reglamento Interno')
                            ->content('Sin reglamento generado. Use el wizard "Construir RIT" para generarlo con IA.')
                            ->visible(fn ($record) => $record && !$record?->reglamentoInterno?->ruta_docx)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('reglamento_docx_temp')
                            ->label('Subir / Actualizar Reglamento Interno (.docx)')
                            ->helperText('Si no sube un reglamento, el sistema usará el Código Sustantivo del Trabajo como referencia para la validación de hechos.')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->disk('local')
                            ->directory('reglamentos-temp')
                            ->visibility('private')
                            ->maxSize(10240)
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),
            ]);
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

                Tables\Columns\TextColumn::make('dias_laborales')
                    ->label('Días Laborales')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'lunes_sabado' => 'Lun - Sáb',
                        default => 'Lun - Vie',
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'lunes_sabado' ? 'warning' : 'success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('actividadEconomica.codigo')
                    ->label('CIIU Principal')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->tooltip(fn (Empresa $record): ?string => $record->actividadEconomica?->nombre)
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
                    ->getStateUsing(fn(Empresa $record): string =>
                        match($record->reglamentoInterno?->fuente) {
                            'construido_ia' => 'IA generado',
                            default         => $record->reglamentoInterno ? 'Manual' : 'Sin RIT',
                        }
                    )
                    ->color(fn(string $state): string => match($state) {
                        'IA generado' => 'success',
                        'Manual'      => 'info',
                        default       => 'gray',
                    })
                    ->icon(fn(string $state): string => match($state) {
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
                    ->options(fn () => self::getDepartamentos())
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
                        return $query->whereHas('actividadEconomica', fn (Builder $q) => $q->whereIn('seccion', $values));
                    })
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('descargar_rit')
                    ->label('Descargar RIT')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn(Empresa $record): string => route('rit.descargar.admin', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Empresa $record): bool => $record->reglamentoInterno !== null),
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn(Empresa $record): bool => !auth()->user()->hasRole('cliente') || auth()->user()->empresa_id === $record->id),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (Tables\Actions\DeleteAction $action, \App\Models\Empresa $record) {
                        // Verificar si tiene procesos disciplinarios
                        if ($record->procesosDisciplinarios()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar la empresa')
                                ->body("La empresa '{$record->razon_social}' tiene {$record->procesosDisciplinarios()->count()} procesos disciplinarios asociados. Debe eliminar o reasignar esos procesos primero.")
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }

                        // Verificar si tiene trabajadores
                        if ($record->trabajadores()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar la empresa')
                                ->body("La empresa '{$record->razon_social}' tiene {$record->trabajadores()->count()} trabajadores asociados. Debe eliminar o reasignar esos trabajadores primero.")
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->action(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Support\Collection $records) {
                            $bloqueadas = [];
                            $eliminadas = 0;

                            foreach ($records as $record) {
                                // Verificar si tiene relaciones
                                if ($record->procesosDisciplinarios()->count() > 0 || $record->trabajadores()->count() > 0) {
                                    $bloqueadas[] = $record->razon_social;
                                } else {
                                    $record->delete();
                                    $eliminadas++;
                                }
                            }

                            if (count($bloqueadas) > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Algunas empresas no se pudieron eliminar')
                                    ->body('Las siguientes empresas tienen procesos o trabajadores asociados: ' . implode(', ', $bloqueadas))
                                    ->persistent()
                                    ->send();
                            }

                            if ($eliminadas > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Empresas eliminadas')
                                    ->body("{$eliminadas} empresa(s) eliminada(s) correctamente.")
                                    ->send();
                            }
                        }),
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
