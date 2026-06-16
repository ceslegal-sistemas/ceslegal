<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\JurisprudenciaResource\Pages;
use App\Models\Jurisprudencia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JurisprudenciaResource extends Resource
{
    protected static ?string $model = Jurisprudencia::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Jurisprudencia';

    protected static ?string $modelLabel = 'Sentencia';

    protected static ?string $pluralModelLabel = 'Jurisprudencia';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 16;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('referencia')
                        ->label('Referencia')
                        ->required()
                        ->placeholder('T-1040/2006'),
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'sentencia_cc' => 'Sentencia — Corte Constitucional',
                            'auto_cc'      => 'Auto — Corte Constitucional',
                            'sentencia_csj' => 'Sentencia — Corte Suprema',
                            'sentencia_ce' => 'Sentencia — Consejo de Estado',
                        ])
                        ->default('sentencia_cc'),
                    Forms\Components\DatePicker::make('fecha')->label('Fecha')->native(false),
                    Forms\Components\TextInput::make('magistrado')->label('Magistrado ponente'),
                ]),

            Forms\Components\Section::make('Extracto curado (lo que usa la IA)')
                ->description('Tema y tesis son lo que se indexa y la IA cita. Revíselos y ajústelos antes de activar.')
                ->schema([
                    Forms\Components\TextInput::make('tema')
                        ->label('Tema / descriptor')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('tesis')
                        ->label('Tesis / sub-regla')
                        ->rows(7)
                        ->columnSpanFull()
                        ->helperText('Si edita la tesis o el tema, vuelva a generar el embedding con la acción "Reprocesar".'),
                    Forms\Components\Toggle::make('activo')
                        ->label('Activa (la IA la usará)')
                        ->helperText('Solo las sentencias activas y procesadas entran al análisis.'),
                ]),

            Forms\Components\Section::make('Texto completo (fuente)')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('texto_completo')
                        ->label('Texto completo de la sentencia')
                        ->rows(12)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('referencia')->label('Referencia')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('tema')->label('Tema')->limit(60)->tooltip(fn($state) => $state)->searchable(),
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->date('Y-m-d')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('estado')->label('Estado')->badge()->colors([
                    'success' => 'procesado', 'warning' => 'procesando', 'danger' => 'error', 'gray' => 'pendiente',
                ]),
                Tables\Columns\ToggleColumn::make('activo')->label('Activa'),
                Tables\Columns\TextColumn::make('total_palabras')->label('Palabras')->numeric()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Actualizado')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll('10s')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')->options([
                    'pendiente' => 'Pendiente', 'procesando' => 'Procesando', 'procesado' => 'Procesado', 'error' => 'Error',
                ]),
                Tables\Filters\TernaryFilter::make('activo')->label('Activa'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importar_corte')
                    ->label('Importar de la Corte Constitucional')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('info')
                    ->modalHeading('Importar jurisprudencia por número de sentencia')
                    ->modalDescription('Se descarga el texto oficial y la IA redacta un extracto (tema + tesis). Queda INACTIVA hasta que usted la revise y active.')
                    ->modalSubmitActionLabel('Importar')
                    ->form([
                        Forms\Components\Textarea::make('referencias')
                            ->label('Sentencias (una por línea)')
                            ->rows(5)
                            ->required()
                            ->placeholder("T-1040/2006\nC-200/2019\nSU-049/2017")
                            ->helperText('Formatos: T-1040/2006, C-200/2019, SU-049/2017. Corre en segundo plano; recibirá una notificación por cada una.'),
                    ])
                    ->action(function (array $data): void {
                        $refs = collect(preg_split('/[\r\n,;]+/', $data['referencias']))
                            ->map(fn($r) => trim($r))->filter()->unique()->values();
                        if ($refs->isEmpty()) {
                            return;
                        }
                        $uid = auth()->id();
                        foreach ($refs as $ref) {
                            \App\Jobs\ImportarJurisprudenciaJob::dispatch($ref, $uid);
                        }
                        Notification::make()->title('Importación en curso')
                            ->body($refs->count() . ' sentencia(s) en cola.')->info()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reprocesar')
                    ->label('Reprocesar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Vuelve a generar el extracto (tema/tesis) y el embedding desde el texto completo.')
                    ->visible(fn(Jurisprudencia $r) => filled($r->texto_completo))
                    ->action(function (Jurisprudencia $r) {
                        try {
                            app(\App\Services\JurisprudenciaScraperService::class)->procesar($r);
                            Notification::make()->success()->title('Reprocesada')->body($r->referencia . ' actualizada.')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Error')->body($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('ver_error')
                    ->label('Ver error')->icon('heroicon-o-exclamation-triangle')->color('danger')
                    ->visible(fn(Jurisprudencia $r) => $r->estado === 'error')
                    ->modalContent(fn(Jurisprudencia $r) => new \Illuminate\Support\HtmlString('<p style="font-size:13px">' . e($r->error_mensaje) . '</p>'))
                    ->modalSubmitAction(false),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activar')->label('Activar')->icon('heroicon-o-check')->color('success')
                        ->action(fn($records) => $records->each->update(['activo' => true]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('desactivar')->label('Desactivar')->icon('heroicon-o-x-mark')->color('gray')
                        ->action(fn($records) => $records->each->update(['activo' => false]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin jurisprudencia')
            ->emptyStateDescription('Use "Importar de la Corte Constitucional" para traer sentencias por número.')
            ->emptyStateIcon('heroicon-o-scale');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListJurisprudencias::route('/'),
            'create' => Pages\CreateJurisprudencia::route('/create'),
            'edit'   => Pages\EditJurisprudencia::route('/{record}/edit'),
        ];
    }
}
