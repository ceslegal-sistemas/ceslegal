<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;
use App\Models\DocumentoLegal;
use App\Services\BibliotecaLegalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BibliotecaLegalResource extends Resource
{
    protected static ?string $model = DocumentoLegal::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Biblioteca Legal';

    protected static ?string $modelLabel = 'Documento Legal';

    protected static ?string $pluralModelLabel = 'Biblioteca Legal';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 15;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del documento')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Sentencia T-239/2021 — Corte Constitucional')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('tipo')
                            ->label('Tipo de documento')
                            ->required()
                            ->native(false)
                            ->options(DocumentoLegal::$tiposLabels)
                            ->searchable(),

                        Forms\Components\TextInput::make('referencia')
                            ->label('Referencia / Radicado')
                            ->placeholder('Ej: T-239/2021, Art. 115 CST')
                            ->maxLength(100)
                            ->helperText('Número de sentencia, artículo o referencia oficial'),

                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción breve')
                            ->rows(2)
                            ->placeholder('Breve descripción del contenido o relevancia del documento')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Archivo')
                    ->icon('heroicon-o-paper-clip')
                    ->schema([
                        Forms\Components\FileUpload::make('archivo_path')
                            ->label('Archivo (PDF, DOCX o TXT)')
                            ->required()
                            ->disk('public')
                            ->directory('biblioteca-legal')
                            ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'])
                            ->maxSize(20480) // 20 MB
                            ->storeFileNamesIn('archivo_nombre_original')
                            ->helperText('Máximo 20 MB. Se aceptan PDF, DOCX y TXT.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Estado')
                    ->icon('heroicon-o-signal')
                    ->schema([
                        Forms\Components\Toggle::make('activo')
                            ->label('Documento activo')
                            ->default(true)
                            ->helperText('Solo los documentos activos son usados por la IA')
                            ->inline(false),
                    ])
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->wrap()
                    ->limit(60),

                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state) => DocumentoLegal::$tiposLabels[$state] ?? $state)
                    ->colors([
                        'primary'   => fn($state) => in_array($state, ['sentencia_cc', 'sentencia_csj', 'sentencia_ce']),
                        'warning'   => fn($state) => $state === 'cst',
                        'success'   => fn($state) => $state === 'ley',
                        'info'      => fn($state) => in_array($state, ['concepto_ministerio', 'doctrina']),
                        'secondary' => fn($state) => in_array($state, ['rit_referencia', 'otro']),
                    ]),

                Tables\Columns\TextColumn::make('referencia')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pendiente',
                        'info'    => 'procesando',
                        'success' => 'procesado',
                        'danger'  => 'error',
                    ])
                    ->icons([
                        'heroicon-o-clock'            => 'pendiente',
                        'heroicon-o-arrow-path'        => 'procesando',
                        'heroicon-o-check-circle'      => 'procesado',
                        'heroicon-o-exclamation-circle' => 'error',
                    ]),

                Tables\Columns\TextColumn::make('total_fragmentos')
                    ->label('Fragmentos')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_palabras')
                    ->label('Palabras')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ? number_format($state) : '—')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(DocumentoLegal::$tiposLabels)
                    ->multiple()
                    ->native(false),

                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente'  => 'Pendiente',
                        'procesando' => 'Procesando',
                        'procesado'  => 'Procesado',
                        'error'      => 'Con error',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\Action::make('procesar')
                    ->label('Procesar')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Procesar documento')
                    ->modalDescription('Se extraerá el texto, se fragmentará y se generarán los embeddings. Esto puede tomar unos segundos por MB de archivo.')
                    ->modalSubmitActionLabel('Sí, procesar')
                    ->action(function (DocumentoLegal $record) {
                        try {
                            app(BibliotecaLegalService::class)->procesarDocumento($record);
                            $record->refresh();
                            Notification::make()
                                ->success()
                                ->title('Documento procesado')
                                ->body("{$record->total_fragmentos} fragmentos · {$record->total_palabras} palabras")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al procesar')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('ver_error')
                    ->label('Ver error')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->visible(fn(DocumentoLegal $record) => $record->estado === 'error')
                    ->modalHeading('Error de procesamiento')
                    ->modalContent(fn(DocumentoLegal $record) => view(
                        'filament.components.modal-error-texto',
                        ['texto' => $record->error_mensaje]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('procesar_seleccionados')
                        ->label('Procesar seleccionados')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $servicio = app(BibliotecaLegalService::class);
                            $exitosos = 0;
                            $fallidos = 0;

                            foreach ($records as $doc) {
                                try {
                                    $servicio->procesarDocumento($doc);
                                    $exitosos++;
                                } catch (\Throwable $e) {
                                    $fallidos++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("Procesamiento completado")
                                ->body("{$exitosos} exitoso(s), {$fallidos} fallido(s).")
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('activar')
                        ->label('Activar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['activo' => true])),

                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn($records) => $records->each->update(['activo' => false])),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->emptyStateHeading('Biblioteca vacía')
            ->emptyStateDescription('Suba sentencias, artículos del CST o doctrina para que la IA las use como fuente de verdad.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Subir primer documento')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBibliotecaLegals::route('/'),
            'create' => Pages\CreateBibliotecaLegal::route('/create'),
            'edit'   => Pages\EditBibliotecaLegal::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $total = static::getModel()::activos()->procesados()->count();
        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
