<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Pages;

use App\Filament\Admin\Resources\FeedbackResource;
use App\Models\Feedback;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

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
                // ── Quién respondió ──────────────────────────────────────────
                Section::make('¿Quién respondió?')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('tipo')
                                    ->label('Tipo de respondente')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'descargo_trabajador' => 'Trabajador',
                                        'descargo_registro'   => 'Cliente',
                                        'plataforma_general'  => 'Plataforma general',
                                        default               => $state,
                                    })
                                    ->color(fn ($state) => match ($state) {
                                        'descargo_trabajador' => 'info',
                                        'descargo_registro'   => 'primary',
                                        default               => 'gray',
                                    }),

                                TextEntry::make('respondente_nombre')
                                    ->label('Nombre')
                                    ->getStateUsing(function (Feedback $record): string {
                                        if ($record->tipo === Feedback::TIPO_DESCARGO_TRABAJADOR) {
                                            return $record->diligenciaDescargo?->proceso?->trabajador?->nombre_completo
                                                ?? 'Trabajador anónimo';
                                        }
                                        return $record->user?->name ?? 'Usuario anónimo';
                                    }),

                                TextEntry::make('trigger')
                                    ->label('Contexto del feedback')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'primer_proceso'  => 'Primer proceso',
                                        'post_diligencia' => 'Post diligencia',
                                        'periodico'       => 'Periódico',
                                        'hito'            => 'Hito de uso',
                                        default           => $state ?? '—',
                                    })
                                    ->default('—'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('procesoDisciplinario.codigo')
                                    ->label('Proceso disciplinario')
                                    ->default('—')
                                    ->icon('heroicon-o-document-text'),

                                TextEntry::make('created_at')
                                    ->label('Fecha de respuesta')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-o-clock'),
                            ]),
                    ]),

                // ── Calificación y NPS ───────────────────────────────────────
                Section::make('Calificación')
                    ->icon('heroicon-o-star')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('calificacion')
                                    ->label('Puntuación (1–5 estrellas)')
                                    ->formatStateUsing(function ($state): string {
                                        if (!$state) return 'Sin calificar';
                                        $stars = str_repeat('★', (int) $state) . str_repeat('☆', 5 - (int) $state);
                                        return $stars . '  (' . $state . '/5)';
                                    })
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color(fn ($state) => match (true) {
                                        $state >= 4 => 'success',
                                        $state >= 3 => 'warning',
                                        $state > 0  => 'danger',
                                        default     => 'gray',
                                    }),

                                TextEntry::make('calificacion_text_label')
                                    ->label('Valoración')
                                    ->getStateUsing(fn (Feedback $record) => $record->calificacion_text),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('nps_score')
                                    ->label('NPS (0–10)')
                                    ->formatStateUsing(fn ($state) => $state !== null ? (string) $state . ' / 10' : 'No respondido')
                                    ->badge()
                                    ->color(function (Feedback $record): string {
                                        return match ($record->getNpsCategoria()) {
                                            'Promotor'  => 'success',
                                            'Neutro'    => 'warning',
                                            'Detractor' => 'danger',
                                            default     => 'gray',
                                        };
                                    }),

                                TextEntry::make('nps_categoria')
                                    ->label('Categoría NPS')
                                    ->getStateUsing(fn (Feedback $record) => $record->getNpsCategoria() ?? 'No aplica')
                                    ->badge()
                                    ->color(function (Feedback $record): string {
                                        return match ($record->getNpsCategoria()) {
                                            'Promotor'  => 'success',
                                            'Neutro'    => 'warning',
                                            'Detractor' => 'danger',
                                            default     => 'gray',
                                        };
                                    }),
                            ]),
                    ])
                    ->columns(1),

                // ── Comentario / Sugerencia ───────────────────────────────────
                Section::make('Comentario o sugerencia')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        TextEntry::make('sugerencia')
                            ->label('')
                            ->default('El respondente no dejó ningún comentario.')
                            ->prose()
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // ── Respuestas adicionales ────────────────────────────────────
                Section::make('Respuestas adicionales')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        TextEntry::make('respuestas_adicionales_formateadas')
                            ->label('')
                            ->getStateUsing(function (Feedback $record): HtmlString|string {
                                $data = $record->respuestas_adicionales;

                                if (empty($data)) {
                                    return 'No hay respuestas adicionales registradas.';
                                }

                                $html = '<dl class="space-y-3">';
                                foreach ($data as $item) {
                                    if (is_array($item)) {
                                        $pregunta  = $item['pregunta'] ?? $item['question'] ?? $item['label'] ?? '';
                                        $respuesta = $item['respuesta'] ?? $item['answer'] ?? $item['value'] ?? '';
                                    } else {
                                        continue;
                                    }

                                    if (!$pregunta && !$respuesta) continue;

                                    $html .= '<div>';
                                    $html .= '<dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">'
                                           . e($pregunta) . '</dt>';
                                    $html .= '<dd class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">'
                                           . e($respuesta) . '</dd>';
                                    $html .= '</div>';
                                }
                                $html .= '</dl>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->hidden(fn (Feedback $record) => empty($record->respuestas_adicionales)),

                // ── Metadatos técnicos ────────────────────────────────────────
                Section::make('Metadatos técnicos')
                    ->icon('heroicon-o-server')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Usuario autenticado')
                                    ->default('Anónimo'),

                                TextEntry::make('ip_address')
                                    ->label('Dirección IP')
                                    ->default('—'),

                                TextEntry::make('diligenciaDescargo.id')
                                    ->label('ID Diligencia')
                                    ->default('—'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
