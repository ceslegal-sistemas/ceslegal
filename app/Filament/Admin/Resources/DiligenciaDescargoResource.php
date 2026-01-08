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
                Forms\Components\Section::make('Información del Proceso')
                    ->schema([
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
                            ),

                        Forms\Components\DateTimePicker::make('fecha_diligencia')
                            ->label('Fecha de la Diligencia')
                            ->required()
                            ->native(false),

                        // Forms\Components\TextInput::make('lugar_diligencia')
                        //     ->label('Lugar de la Diligencia')
                        //     ->maxLength(255)
                        //     ->placeholder('Ej: Sala de audiencias, Virtual, etc.'),

                        Forms\Components\Select::make('lugar_diligencia')
                            ->label('¿Como se realizará la diligencia de descargos?')
                            ->options([
                                'presencial' => 'Presencial',
                                'virtual' => 'Virtual',
                                'telefonico' => 'Telefónico',
                            ])
                            ->searchable()
                            ->preload()
                            // ->native(false)
                            ->placeholder('Seleccione la modalidad'),
                    ])->columns(2),

                Forms\Components\Section::make('Acceso Web del Trabajador')
                    ->description('Configuración de acceso temporal para descargos en línea')
                    ->schema([
                        Forms\Components\TextInput::make('token_acceso')
                            ->label('Token de Acceso')
                            ->disabled()
                            ->helperText('Se genera automáticamente'),

                        Forms\Components\DateTimePicker::make('token_expira_en')
                            ->label('Token Expira En')
                            ->disabled()
                            ->native(false),

                        Forms\Components\Toggle::make('acceso_habilitado')
                            ->label('Acceso Habilitado')
                            ->helperText('Activar/desactivar el acceso del trabajador al formulario'),

                        Forms\Components\DatePicker::make('fecha_acceso_permitida')
                            ->label('Fecha de Acceso Permitida')
                            ->native(false)
                            ->helperText('El trabajador solo podrá acceder este día'),

                        Forms\Components\DateTimePicker::make('trabajador_accedio_en')
                            ->label('Trabajador Accedió En')
                            ->disabled()
                            ->native(false),

                        Forms\Components\TextInput::make('ip_acceso')
                            ->label('IP de Acceso')
                            ->disabled(),
                    ])->columns(3)->collapsible(),

                Forms\Components\Section::make('Información de la Diligencia')
                    ->schema([
                        Forms\Components\Toggle::make('trabajador_asistio')
                            ->label('¿El Trabajador Asistió?')
                            ->live(),

                        Forms\Components\Toggle::make('pruebas_aportadas')
                            ->label('¿Aportó Pruebas?')
                            ->live(),

                        // Forms\Components\Textarea::make('motivo_inasistencia')
                        //     ->label('Motivo de Inasistencia')
                        //     ->visible(fn(Forms\Get $get) => !$get('trabajador_asistio'))
                        //     ->columnSpanFull(),

                        Forms\Components\TextInput::make('acompanante_nombre')
                            ->label('Nombre del Acompañante')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('acompanante_cargo')
                            ->label('Cargo/Relación del Acompañante')
                            ->maxLength(255),


                        Forms\Components\Textarea::make('descripcion_pruebas')
                            ->label('Descripción de las Pruebas')
                            ->visible(fn(Forms\Get $get) => $get('pruebas_aportadas'))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->columnSpanFull(),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('Acta de Descargos')
                    ->schema([
                        Forms\Components\Toggle::make('acta_generada')
                            ->label('¿Acta Generada?'),

                        Forms\Components\TextInput::make('ruta_acta')
                            ->label('Ruta del Acta')
                            ->maxLength(255)
                            ->disabled(),
                    ])->columns(2)->collapsible(),
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
                    ->modalDescription('Se generarán 5 preguntas iniciales basadas en los hechos del proceso disciplinario.')
                    ->modalSubmitActionLabel('Generar')
                    ->action(function (DiligenciaDescargo $record) {
                        $iaService = new IADescargoService();

                        try {
                            $preguntas = $iaService->generarPreguntasCompletas($record, 5);

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
                    ->action(function (DiligenciaDescargo $record) {
                        if (!$record->ruta_acta || !file_exists($record->ruta_acta)) {
                            Notification::make()
                                ->warning()
                                ->title('Archivo no encontrado')
                                ->body('El archivo del acta no existe. Por favor, genérelo nuevamente.')
                                ->send();
                            return null;
                        }

                        return response()->download($record->ruta_acta);
                    })
                    ->visible(fn(DiligenciaDescargo $record) => $record->acta_generada && !empty($record->ruta_acta)),

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

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
