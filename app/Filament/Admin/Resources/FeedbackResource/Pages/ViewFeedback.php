<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Pages;

use App\Filament\Admin\Resources\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Calificación')
                    ->schema([
                        TextEntry::make('calificacion')
                            ->label('Puntuación')
                            ->formatStateUsing(function ($state) {
                                $stars = str_repeat('★', $state) . str_repeat('☆', 5 - $state);
                                return $stars . " ({$state}/5)";
                            })
                            ->size(TextEntry\TextEntrySize::Large)
                            ->color(fn ($state) => match (true) {
                                $state >= 4 => 'success',
                                $state >= 3 => 'warning',
                                default => 'danger',
                            }),
                        TextEntry::make('calificacion_texto')
                            ->label('Valoración')
                            ->getStateUsing(fn ($record) => $record->calificacion_text),
                    ])
                    ->columns(2),

                Section::make('Sugerencia')
                    ->schema([
                        TextEntry::make('sugerencia')
                            ->label('')
                            ->default('Sin sugerencia')
                            ->prose()
                            ->markdown(),
                    ])
                    ->collapsible(),

                Section::make('Información del Registro')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('tipo_text')
                                    ->label('Tipo de Feedback')
                                    ->getStateUsing(fn ($record) => $record->tipo_text)
                                    ->badge(),
                                TextEntry::make('procesoDisciplinario.codigo')
                                    ->label('Proceso')
                                    ->default('—'),
                                TextEntry::make('diligenciaDescargo.id')
                                    ->label('Diligencia')
                                    ->default('—'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Usuario')
                                    ->default('Anónimo'),
                                TextEntry::make('ip_address')
                                    ->label('Dirección IP')
                                    ->default('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
