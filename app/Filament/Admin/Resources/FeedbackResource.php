<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FeedbackResource\Pages;
use App\Models\Feedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $modelLabel = 'Feedback';

    protected static ?string $pluralModelLabel = 'Feedbacks';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Feedback')
                    ->schema([
                        Forms\Components\TextInput::make('calificacion')
                            ->label('Calificación')
                            ->disabled(),
                        Forms\Components\TextInput::make('tipo')
                            ->label('Tipo')
                            ->disabled(),
                        Forms\Components\Textarea::make('sugerencia')
                            ->label('Sugerencia')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'descargo_trabajador' => 'Trabajador',
                        'descargo_registro'   => 'Cliente',
                        'plataforma_general'  => 'Plataforma',
                        default               => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'descargo_trabajador' => 'info',
                        'descargo_registro'   => 'primary',
                        'plataforma_general'  => 'gray',
                        default               => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'descargo_trabajador' => 'heroicon-m-user',
                        'descargo_registro'   => 'heroicon-m-briefcase',
                        default               => 'heroicon-m-globe-alt',
                    }),

                Tables\Columns\TextColumn::make('respondente')
                    ->label('Respondió')
                    ->getStateUsing(function (Feedback $record): string {
                        if ($record->tipo === Feedback::TIPO_DESCARGO_TRABAJADOR) {
                            return $record->diligenciaDescargo?->proceso?->trabajador?->nombre_completo
                                ?? 'Trabajador anónimo';
                        }
                        return $record->user?->name ?? 'Usuario anónimo';
                    })
                    ->searchable(false)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('procesoDisciplinario.codigo')
                    ->label('Proceso')
                    ->searchable()
                    ->placeholder('—')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('calificacion')
                    ->label('Calificación')
                    ->formatStateUsing(fn ($state) => $state
                        ? str_repeat('★', (int) $state) . str_repeat('☆', 5 - (int) $state)
                        : '—'
                    )
                    ->color(fn ($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        $state > 0  => 'danger',
                        default     => 'gray',
                    })
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nps_score')
                    ->label('NPS')
                    ->formatStateUsing(function ($state, Feedback $record): string {
                        if ($state === null) return '—';
                        $categoria = $record->getNpsCategoria();
                        return $state . ' · ' . $categoria;
                    })
                    ->badge()
                    ->color(function (Feedback $record): string {
                        return match ($record->getNpsCategoria()) {
                            'Promotor'   => 'success',
                            'Neutro'     => 'warning',
                            'Detractor'  => 'danger',
                            default      => 'gray',
                        };
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sugerencia')
                    ->label('Sugerencia / Comentario')
                    ->limit(60)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('Sin comentario')
                    ->wrap(),

                Tables\Columns\IconColumn::make('tiene_respuestas')
                    ->label('Resp. adicionales')
                    ->getStateUsing(fn (Feedback $record) => !empty($record->respuestas_adicionales))
                    ->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-left-ellipsis')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('trigger')
                    ->label('Contexto')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'primer_proceso'  => 'Primer proceso',
                        'post_diligencia' => 'Post diligencia',
                        'periodico'       => 'Periódico',
                        'hito'            => 'Hito de uso',
                        default           => $state ?? '—',
                    })
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario admin')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de respondente')
                    ->options([
                        'descargo_trabajador' => 'Trabajador (descargos)',
                        'descargo_registro'   => 'Cliente (registro proceso)',
                        'plataforma_general'  => 'Plataforma general',
                    ]),

                Tables\Filters\SelectFilter::make('calificacion')
                    ->label('Calificación')
                    ->options([
                        '5' => '★★★★★ Excelente',
                        '4' => '★★★★☆ Bueno',
                        '3' => '★★★☆☆ Regular',
                        '2' => '★★☆☆☆ Malo',
                        '1' => '★☆☆☆☆ Muy malo',
                    ]),

                Tables\Filters\Filter::make('con_nps')
                    ->label('Con puntuación NPS')
                    ->query(fn (Builder $query) => $query->whereNotNull('nps_score')),

                Tables\Filters\Filter::make('con_sugerencia')
                    ->label('Con comentario / sugerencia')
                    ->query(fn (Builder $query) => $query->whereNotNull('sugerencia')),

                Tables\Filters\Filter::make('negativos')
                    ->label('Solo negativos (≤ 2 ★)')
                    ->query(fn (Builder $query) => $query->where('calificacion', '<=', 2)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'view'  => Pages\ViewFeedback::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $total = static::getModel()::count();
        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $avg = static::getModel()::avg('calificacion');

        return match (true) {
            $avg >= 4   => 'success',
            $avg >= 3   => 'warning',
            default     => 'danger',
        };
    }
}
