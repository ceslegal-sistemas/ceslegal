<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;
use App\Models\DocumentoLegal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
                    ->label(fn(DocumentoLegal $record) => $record->estado === 'procesando' ? 'Procesando...' : 'Encolar')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('primary')
                    ->disabled(fn(DocumentoLegal $record) => $record->estado === 'procesando')
                    ->requiresConfirmation()
                    ->modalHeading('Encolar documento para procesar')
                    ->modalDescription('El documento se marcará como pendiente y el sistema lo procesará en el próximo ciclo del cron (máx. 5 minutos). No cierre esta ventana mientras espera — recargue la página pasados unos minutos.')
                    ->modalSubmitActionLabel('Encolar')
                    ->action(function (DocumentoLegal $record) {
                        $record->update(['estado' => 'pendiente', 'error_mensaje' => null]);
                        Notification::make()
                            ->success()
                            ->title('Documento encolado')
                            ->body('Se procesará en el próximo ciclo. Recargue la página en 1-2 minutos para ver el resultado.')
                            ->send();
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

                Tables\Actions\Action::make('previsualizar')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn(DocumentoLegal $record) => !empty($record->archivo_path))
                    ->modalHeading(fn(DocumentoLegal $record) => $record->titulo)
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::SevenExtraLarge)
                    ->modalContent(fn(DocumentoLegal $record) => self::buildPreviewContent($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn(DocumentoLegal $record) => !empty($record->archivo_path))
                    ->url(fn(DocumentoLegal $record) => route('biblioteca.descargar', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('procesar_seleccionados')
                        ->label('Encolar seleccionados')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $total = 0;
                            foreach ($records as $doc) {
                                $doc->update(['estado' => 'pendiente', 'error_mensaje' => null]);
                                $total++;
                            }
                            Notification::make()
                                ->success()
                                ->title("{$total} documento(s) encolados")
                                ->body('Se procesarán en el próximo ciclo del cron. Recargue en 1-2 minutos.')
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

    /** Genera el contenido del modal de previsualización */
    public static function buildPreviewContent(DocumentoLegal $record): HtmlString
    {
        $url  = Storage::disk('public')->url($record->archivo_path);
        $ext  = strtolower(pathinfo($record->archivo_nombre_original ?? $record->archivo_path, PATHINFO_EXTENSION));
        $desc = e($record->descripcion ?? '');
        $ref  = e($record->referencia ?? '');

        $infoBar = '';
        if ($ref || $desc) {
            $infoBar = '<div style="padding:.625rem 1rem;background:rgba(99,102,241,.07);border-radius:.5rem;margin-bottom:.75rem;font-size:.8125rem;color:#64748b">'
                . ($ref  ? '<span style="font-weight:600;color:#4338ca">' . $ref . '</span>' : '')
                . ($ref && $desc ? ' — ' : '')
                . $desc
                . '</div>';
        }

        if ($ext === 'pdf') {
            return new HtmlString(
                $infoBar .
                '<iframe src="' . $url . '#toolbar=1&view=FitH" '
                . 'style="width:100%;height:78vh;border:none;border-radius:.5rem;background:#f1f5f9">'
                . '<p style="padding:2rem;text-align:center">Su navegador no soporta previsualización de PDF. '
                . '<a href="' . $url . '" target="_blank">Abrir en nueva pestaña</a></p>'
                . '</iframe>'
            );
        }

        $iconSvg = '<svg style="width:48px;height:48px;color:#94a3b8;margin:0 auto 1rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>'
            . '</svg>';

        $downloadBtn = '<a href="' . route('biblioteca.descargar', $record) . '" '
            . 'style="display:inline-flex;align-items:center;gap:.5rem;margin-top:1.25rem;padding:.6rem 1.25rem;'
            . 'background:#4f46e5;color:#fff;border-radius:.5rem;font-size:.875rem;font-weight:600;text-decoration:none">'
            . '<svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>'
            . '</svg>Descargar ' . strtoupper($ext) . '</a>';

        return new HtmlString(
            $infoBar .
            '<div style="padding:3rem 2rem;text-align:center">'
            . $iconSvg
            . '<p style="font-size:1rem;font-weight:600;color:#374151;margin:0">Vista previa no disponible para archivos '
            . strtoupper($ext) . '</p>'
            . '<p style="font-size:.875rem;color:#6b7280;margin:.5rem 0 0">Solo los archivos PDF se pueden previsualizar en el navegador.</p>'
            . $downloadBtn
            . '</div>'
        );
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
