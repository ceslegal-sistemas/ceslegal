<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;
use App\Filament\Admin\Resources\DiligenciaDescargoResource\RelationManagers;
use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use App\Services\IADescargoService;
use App\Services\ActaDescargosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DiligenciaDescargoResource extends Resource
{
    protected static ?string $model = DiligenciaDescargo::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Descargos';

    protected static ?string $modelLabel = 'Diligencia de Descargo';

    protected static ?string $pluralModelLabel = 'Diligencias de Descargos';

    protected static ?string $navigationGroup = 'Gestión Laboral';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Proceso')
                    ->description('Seleccione el proceso y programe la diligencia')
                    ->schema([
                        // Forms\Components\Select::make('proceso_id')
                        //     ->label('Seleccione el Trabajador y Proceso')
                        //     ->relationship(
                        //         name: 'proceso',
                        //         titleAttribute: 'codigo',
                        //         modifyQueryUsing: fn (Builder $query) => $query
                        //             ->with(['trabajador', 'empresa'])
                        //             ->whereIn('estado', ['notificado', 'descargos_citados'])
                        //     )
                        //     ->getOptionLabelFromRecordUsing(fn ($record) =>
                        //         "{$record->trabajador->nombre_completo} - {$record->trabajador->cargo} (Proceso {$record->codigo})"
                        //     )
                        //     ->searchable(['codigo'])
                        //     ->preload()
                        //     ->required()
                        //     ->placeholder('Seleccione un proceso')
                        //     ->helperText('Haga clic para ver todos los procesos disponibles')
                        //     ->columnSpanFull(),

                        Forms\Components\Select::make('proceso_id')
                            ->label('Proceso Disciplinario')
                            ->relationship(
                                'proceso',
                                'codigo',
                                fn(Builder $query) => $query->with(['trabajador', 'empresa'])
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                "{$record->codigo} - {$record->trabajador->nombre_completo}"
                            )
                            ->columnSpanFull(),

                        Forms\Components\Select::make('lugar_diligencia')
                            ->label('Modalidad')
                            ->options([
                                'presencial' => 'Presencial - El trabajador viene a la oficina',
                                'virtual' => 'Virtual - El trabajador responde por internet desde su casa',
                                'telefonico' => 'Telefónico - Se hará por llamada telefónica',
                            ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === 'virtual') {
                                    $set('acceso_habilitado', true);
                                } else {
                                    $set('acceso_habilitado', false);
                                }
                            })
                            ->afterStateHydrated(function ($state, Forms\Set $set) {
                                if ($state === 'virtual') {
                                    $set('acceso_habilitado', true);
                                } else {
                                    $set('acceso_habilitado', false);
                                }
                            })
                            ->helperText('Seleccione cómo se realizará la diligencia')
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('fecha_diligencia')
                            ->label('Fecha y Hora de la Reunión')
                            ->visible(fn(Forms\Get $get) => in_array($get('lugar_diligencia'), ['presencial', 'telefonico']))
                            ->required(fn(Forms\Get $get) => in_array($get('lugar_diligencia'), ['presencial', 'telefonico']))
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->timezone('America/Bogota')
                            ->helperText('Seleccione el día y la hora exacta de la reunión')
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('fecha_acceso_permitida')
                            ->label('Fecha de Acceso')
                            ->visible(fn(Forms\Get $get) => $get('lugar_diligencia') === 'virtual')
                            ->required(fn(Forms\Get $get) => $get('lugar_diligencia') === 'virtual')
                            ->native(false)
                            ->minDate(now()->startOfDay())
                            ->helperText('Seleccione el día en que el trabajador podrá entrar al sistema a responder')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('lugar_especifico')
                            ->label('Lugar Específico')
                            ->visible(fn(Forms\Get $get) => $get('lugar_diligencia') === 'presencial')
                            ->maxLength(255)
                            ->placeholder('Ejemplo: Sala de juntas, Oficina 301, Calle 50 # 20-30')
                            ->helperText('Indique dónde se realizará la diligencia')
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('acceso_habilitado')
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make('Notas y Observaciones')
                    ->description('Registre información adicional relevante')
                    ->schema([
                        Forms\Components\TextInput::make('acompanante_nombre')
                            ->label('Nombre del Acompañante')
                            ->maxLength(255)
                            ->placeholder('Ejemplo: María López')
                            ->helperText('Si el trabajador viene con alguien, indique el nombre'),

                        Forms\Components\TextInput::make('acompanante_cargo')
                            ->label('Relación del Acompañante')
                            ->maxLength(255)
                            ->placeholder('Ejemplo: Abogado, Familiar, Compañero')
                            ->helperText('Indique quién es la persona que acompaña'),

                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones Generales')
                            ->rows(3)
                            ->placeholder('Escriba aquí cualquier información importante sobre la diligencia...')
                            ->columnSpanFull(),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('Archivos de Evidencia')
                    ->description('Archivos que el trabajador ha subido por internet')
                    ->schema([
                        Forms\Components\Placeholder::make('archivos_evidencia_info')
                            ->label('Archivos Adjuntos')
                            ->content(function ($record) {
                                if (!$record || !$record->archivos_evidencia || empty($record->archivos_evidencia)) {
                                    return 'El trabajador no ha subido archivos';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($record->archivos_evidencia as $archivo) {
                                    $nombre = $archivo['nombre'] ?? 'Archivo';
                                    $path = $archivo['path'] ?? '';
                                    $url = $path ? asset('storage/' . $path) : '#';

                                    $html .= "<div class='p-3 bg-gray-50 rounded flex justify-between items-center border'>";
                                    $html .= "<span class='font-medium'>{$nombre}</span>";
                                    $html .= "<a href='{$url}' target='_blank' class='px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700'>Descargar</a>";
                                    $html .= "</div>";
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => !$record || !$record->archivos_evidencia || empty($record->archivos_evidencia)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('proceso.codigo')
                    ->label('Proceso')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proceso.trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_diligencia')
                    ->label('Fecha Diligencia')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Tables\Columns\TextColumn::make('lugar_diligencia')
                //     ->label('Lugar')
                //     ->searchable()
                //     ->limit(30),

                Tables\Columns\IconColumn::make('trabajador_asistio')
                    ->label('Asistió')
                    ->boolean()
                    ->sortable(),

                // Tables\Columns\IconColumn::make('acceso_habilitado')
                //     ->label('Acceso Web')
                //     ->boolean()
                //     ->sortable(),

                Tables\Columns\TextColumn::make('preguntas_count')
                    ->label('Preguntas')
                    ->counts('preguntas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('respuestas_count')
                    ->label('Respondidas')
                    ->getStateUsing(function (DiligenciaDescargo $record) {
                        return $record->preguntas()
                            ->whereHas('respuesta')
                            ->count();
                    })
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('archivos_evidencia')
                    ->label('Archivos')
                    ->boolean()
                    ->getStateUsing(function (DiligenciaDescargo $record) {
                        return $record->archivos_evidencia && count($record->archivos_evidencia) > 0;
                    })
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(function (DiligenciaDescargo $record) {
                        if ($record->archivos_evidencia && count($record->archivos_evidencia) > 0) {
                            $cantidad = count($record->archivos_evidencia);
                            return "{$cantidad} archivo(s) adjunto(s)";
                        }
                        return 'Sin archivos';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('JSON_LENGTH(COALESCE(archivos_evidencia, "[]")) ' . $direction);
                    }),

                Tables\Columns\TextColumn::make('trabajador_accedio_en')
                    ->label('Accedió En')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('acceso_habilitado')
                    ->label('Acceso Web Habilitado'),

                Tables\Filters\TernaryFilter::make('trabajador_asistio')
                    ->label('Trabajador Asistió'),

                Tables\Filters\Filter::make('con_acceso')
                    ->label('Con Acceso del Trabajador')
                    ->query(fn(Builder $query) => $query->whereNotNull('trabajador_accedio_en')),
            ])
            ->actions([
                Tables\Actions\Action::make('generar_preguntas_ia')
                    ->label('Generar Preguntas IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Generar preguntas con IA?')
                    ->modalDescription('Se generarán 2 preguntas iniciales basadas en los hechos del proceso disciplinario.')
                    ->modalSubmitActionLabel('Generar')
                    ->action(function (DiligenciaDescargo $record) {
                        $iaService = new IADescargoService();

                        try {
                            $preguntas = $iaService->generarPreguntasCompletas($record, 2);

                            Notification::make()
                                ->success()
                                ->title('Preguntas generadas')
                                ->body(count($preguntas) . ' preguntas generadas (estándar + IA + cierre).')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Error al generar preguntas: ' . $e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn(DiligenciaDescargo $record) => $record->preguntas()->count() === 13),

                Tables\Actions\Action::make('ver_link_acceso')
                    ->label('Ver Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->modalHeading('Link de Acceso a Descargos')
                    ->modalContent(function (DiligenciaDescargo $record) {
                        $url = route('descargos.acceso', ['token' => $record->token_acceso]);
                        return view('filament.modals.link-descargos', [
                            'url' => $url,
                            'diligencia' => $record
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(fn(DiligenciaDescargo $record) => !empty($record->token_acceso)),


                Tables\Actions\Action::make('generar_acta')
                    ->label('Generar Acta')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Generar acta de descargos?')
                    ->modalDescription('Se generará el acta de descargos en formato DOCX con todas las preguntas y respuestas.')
                    ->modalSubmitActionLabel('Generar Acta')
                    ->action(function (DiligenciaDescargo $record) {
                        $actaService = new ActaDescargosService();
                        $resultado = $actaService->generarActaDescargos($record);

                        if ($resultado['success']) {
                            $record->update([
                                'acta_generada' => true,
                                'ruta_acta' => $resultado['path'],
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Acta generada')
                                ->body('El acta de descargos se generó exitosamente.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('Error al generar el acta: ' . ($resultado['error'] ?? 'Error desconocido'))
                                ->send();
                        }
                    })
                    ->visible(
                        fn(DiligenciaDescargo $record) =>
                        $record->preguntas()->has('respuesta')->count() > 0 && !$record->acta_generada
                    ),

                Tables\Actions\Action::make('descargar_acta')
                    ->label('Descargar Acta')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn(DiligenciaDescargo $record) => route('descargar.acta', $record->id))
                    ->openUrlInNewTab()
                    ->visible(fn(DiligenciaDescargo $record) => $record->acta_generada && !empty($record->ruta_acta) && file_exists($record->ruta_acta)),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('regenerar_token')
                        ->label('Regenerar Token')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('¿Regenerar token de acceso?')
                        ->modalDescription('Se generará un nuevo token. El anterior dejará de funcionar.')
                        ->action(function (DiligenciaDescargo $record) {
                            $record->generarTokenAcceso();

                            Notification::make()
                                ->success()
                                ->title('Token regenerado')
                                ->body('Nuevo token generado exitosamente.')
                                ->send();
                        })
                        ->visible(fn(DiligenciaDescargo $record) => !empty($record->token_acceso)),

                    Tables\Actions\Action::make('regenerar_acta')
                        ->label('Regenerar Acta')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('¿Regenerar acta de descargos?')
                        ->modalDescription('Se eliminará el acta actual y se generará una nueva con la información actualizada.')
                        ->modalSubmitActionLabel('Regenerar')
                        ->action(function (DiligenciaDescargo $record) {
                            // Eliminar archivo anterior si existe
                            if ($record->ruta_acta && file_exists($record->ruta_acta)) {
                                unlink($record->ruta_acta);
                            }

                            // Generar nueva acta
                            $actaService = new ActaDescargosService();
                            $resultado = $actaService->generarActaDescargos($record);

                            if ($resultado['success']) {
                                $record->update([
                                    'acta_generada' => true,
                                    'ruta_acta' => $resultado['path'],
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Acta regenerada')
                                    ->body('El acta de descargos se regeneró exitosamente.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('Error al regenerar el acta: ' . ($resultado['error'] ?? 'Error desconocido'))
                                    ->send();
                            }
                        })
                        ->visible(fn(DiligenciaDescargo $record) => $record->acta_generada),

                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDiligenciaDescargos::route('/'),
            'create' => Pages\CreateDiligenciaDescargo::route('/create'),
            'view' => Pages\ViewDiligenciaDescargo::route('/{record}'),
            'edit' => Pages\EditDiligenciaDescargo::route('/{record}/edit'),
        ];
    }
}
