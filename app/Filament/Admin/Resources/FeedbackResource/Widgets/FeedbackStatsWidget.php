<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Widgets;

use App\Models\Feedback;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeedbackStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFeedback = Feedback::count();
        $avgRating = Feedback::avg('calificacion') ?? 0;
        $conSugerencias = Feedback::whereNotNull('sugerencia')->count();
        $ultimaSemana = Feedback::where('created_at', '>=', now()->subWeek())->count();

        // Distribución de calificaciones
        $distribucion = Feedback::selectRaw('calificacion, count(*) as total')
            ->groupBy('calificacion')
            ->pluck('total', 'calificacion')
            ->toArray();

        $chart = [];
        for ($i = 1; $i <= 5; $i++) {
            $chart[] = $distribucion[$i] ?? 0;
        }

        return [
            Stat::make('Total Feedback', $totalFeedback)
                ->description('Respuestas recibidas')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Calificación Promedio', number_format($avgRating, 1) . ' ★')
                ->description(match (true) {
                    $avgRating >= 4.5 => 'Excelente',
                    $avgRating >= 4 => 'Muy bueno',
                    $avgRating >= 3 => 'Bueno',
                    $avgRating >= 2 => 'Regular',
                    default => 'Necesita mejora',
                })
                ->descriptionIcon('heroicon-o-star')
                ->color(match (true) {
                    $avgRating >= 4 => 'success',
                    $avgRating >= 3 => 'warning',
                    default => 'danger',
                })
                ->chart($chart),

            Stat::make('Con Sugerencias', $conSugerencias)
                ->description(($totalFeedback > 0 ? round(($conSugerencias / $totalFeedback) * 100) : 0) . '% del total')
                ->descriptionIcon('heroicon-o-light-bulb')
                ->color('info'),

            Stat::make('Última Semana', $ultimaSemana)
                ->description('Nuevos feedbacks')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('gray'),
        ];
    }
}
