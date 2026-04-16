<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActaInspeccionResource\Pages;
use App\Models\ActaInspeccion;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActaInspeccionResource extends Resource
{
    protected static ?string $model                  = ActaInspeccion::class;
    protected static ?string $navigationIcon         = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel        = 'Actas de Inspección';
    protected static ?string $modelLabel             = 'Acta de Inspección';
    protected static ?string $pluralModelLabel       = 'Actas de Inspección';
    protected static ?string $navigationGroup        = 'Gestión Laboral';
    protected static ?int    $navigationSort         = 10;

    public static function shouldRegisterNavigation(): bool
    {
        $role = auth()->user()?->role;
        return in_array($role, ['super_admin', 'abogado']);
    }

    // ─── Formulario ──────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Información del Acta')
                ->columns(3)
                ->schema([

                    Forms\Components\TextInput::make('numero_acta')
                        ->label('N° Acta')
                        ->disabled()
                        ->dehydrated()
                        ->placeholder('Auto-generado al guardar')
                        ->columnSpan(1),

                    Forms\Components\Select::make('empresa_id')
                        ->label('Empresa')
                        ->options(fn () => Empresa::orderBy('razon_social')->pluck('razon_social', 'id'))
                        ->required()
                        ->searchable()
                        ->columnSpan(1),

                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options([
                            'borrador'   => 'Borrador',
                            'finalizada' => 'Finalizada',
                        ])
                        ->default('borrador')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\DatePicker::make('fecha')
                        ->label('Fecha')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('hora_inicio')
                        ->label('Hora de Inicio')
                        ->type('time')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('hora_cierre')
                        ->label('Hora de Cierre')
                        ->type('time')
                        ->columnSpan(1),

                ]),

            Forms\Components\Section::make('Contenido')
                ->schema([

                    Forms\Components\Textarea::make('objetivo')
                        ->label('Objetivo de la Inspección')
                        ->required()
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('tema')
                        ->label('Tema de la Inspección')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),

                ]),

            Forms\Components\Section::make('Asistentes')
                ->description('Personas presentes durante la inspección')
                ->schema([
                    Forms\Components\Repeater::make('asistentes')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre completo')
                                ->required()
                                ->maxLength(200),

                            Forms\Components\TextInput::make('cargo')
                                ->label('Cargo')
                                ->required()
                                ->maxLength(200),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Agregar asistente')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Compromisos')
                ->description('Compromisos adquiridos durante la inspección')
                ->schema([
                    Forms\Components\Repeater::make('compromisos')
                        ->label('')
                        ->schema([
                            Forms\Components\Textarea::make('compromiso')
                                ->label('Compromiso')
                                ->required()
                                ->rows(2)
                                ->maxLength(500)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('responsable')
                                ->label('Responsable')
                                ->required()
                                ->maxLength(200)
                                ->columnSpan(1),
                        ])
                        ->columns(3)
                        ->addActionLabel('+ Agregar compromiso')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Hallazgos y Observaciones')
                ->columns(1)
                ->schema([

                    Forms\Components\Textarea::make('hallazgos')
                        ->label('Hallazgos')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),

                ]),

        ]);
    }

    // ─── Tabla ───────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_acta')
                    ->label('N° Acta')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora_inicio')
                    ->label('Hora inicio'),

                Tables\Columns\TextColumn::make('objetivo')
                    ->label('Objetivo')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->objetivo),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'borrador',
                        'success' => 'finalizada',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'borrador'   => 'Borrador',
                        'finalizada' => 'Finalizada',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'borrador'   => 'Borrador',
                        'finalizada' => 'Finalizada',
                    ]),

                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
                            ->when($data['hasta'], fn ($q, $v) => $q->whereDate('fecha', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (ActaInspeccion $record) {
                        $service = app(\App\Services\ActaInspeccionDocService::class);
                        $ruta    = $service->generarDocx($record);
                        $nombre  = $record->numero_acta . '.docx';
                        return response()->download($ruta, $nombre)->deleteFileAfterSend(true);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActaInspeccions::route('/'),
            'create' => Pages\CreateActaInspeccion::route('/create'),
            'edit'   => Pages\EditActaInspeccion::route('/{record}/edit'),
        ];
    }

}
