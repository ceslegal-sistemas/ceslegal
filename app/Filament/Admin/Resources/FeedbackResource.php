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
                    ->sortable(),
                Tables\Columns\TextColumn::make('calificacion')
                    ->label('Calificación')
                    ->formatStateUsing(function ($state) {
                        $stars = str_repeat('★', $state) . str_repeat('☆', 5 - $state);
                        return $stars;
                    })
                    ->color(fn ($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'descargo_trabajador' => 'Trabajador',
                        'descargo_registro' => 'Admin',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'descargo_trabajador' => 'info',
                        'descargo_registro' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('procesoDisciplinario.codigo')
                    ->label('Proceso')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sugerencia')
                    ->label('Sugerencia')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('Sin sugerencia'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Anónimo')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('calificacion')
                    ->label('Calificación')
                    ->options([
                        '5' => '★★★★★ Excelente',
                        '4' => '★★★★☆ Bueno',
                        '3' => '★★★☆☆ Regular',
                        '2' => '★★☆☆☆ Malo',
                        '1' => '★☆☆☆☆ Muy malo',
                    ]),
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'descargo_trabajador' => 'Trabajador',
                        'descargo_registro' => 'Admin',
                    ]),
                Tables\Filters\Filter::make('con_sugerencia')
                    ->label('Con sugerencia')
                    ->query(fn (Builder $query) => $query->whereNotNull('sugerencia')),
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
            'view' => Pages\ViewFeedback::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() > 0 ? static::getModel()::count() : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $avgRating = static::getModel()::avg('calificacion');

        return match (true) {
            $avgRating >= 4 => 'success',
            $avgRating >= 3 => 'warning',
            default => 'danger',
        };
    }
}
