<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Widgets;

use App\Models\Feedback;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeedbackStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total          = Feedback::count();
        $avgRating      = Feedback::whereNotNull('calificacion')->avg('calificacion') ?? 0;
        $totalTrabajadores = Feedback::where('tipo', Feedback::TIPO_DESCARGO_TRABAJADOR)->count();
        $totalClientes  = Feedback::where('tipo', Feedback::TIPO_DESCARGO_REGISTRO)->count();
        $conSugerencias = Feedback::whereNotNull('sugerencia')->count();

        // NPS: promotores (9-10) - detractores (0-6), ignorando neutros (7-8)
        $promotores  = Feedback::whereNotNull('nps_score')->where('nps_score', '>=', 9)->count();
        $detractores = Feedback::whereNotNull('nps_score')->where('nps_score', '<=', 6)->count();
        $totalNps    = Feedback::whereNotNull('nps_score')->count();
        $npsScore    = $totalNps > 0
            ? round((($promotores - $detractores) / $totalNps) * 100)
            : null;

        // Distribución de calificaciones (para chart)
        $distribucion = Feedback::whereNotNull('calificacion')
            ->selectRaw('calificacion, count(*) as total')
            ->groupBy('calificacion')
            ->pluck('total', 'calificacion')
            ->toArray();

        $chart = [];
        for ($i = 1; $i <= 5; $i++) {
            $chart[] = $distribucion[$i] ?? 0;
        }

        return [
            Stat::make('Calificación promedio', $avgRating > 0 ? number_format($avgRating, 1) . ' ★' : '—')
                ->description(match (true) {
                    $avgRating >= 4.5 => 'Excelente',
                    $avgRating >= 4   => 'Muy bueno',
                    $avgRating >= 3   => 'Bueno',
                    $avgRating >= 2   => 'Regular',
                    $avgRating > 0    => 'Necesita mejora',
                    default           => 'Sin calificaciones',
                })
                ->descriptionIcon('heroicon-o-star')
                ->color(match (true) {
                    $avgRating >= 4 => 'success',
                    $avgRating >= 3 => 'warning',
                    $avgRating > 0  => 'danger',
                    default         => 'gray',
                })
                ->chart($chart),

            Stat::make('Trabajadores', $totalTrabajadores)
                ->description('Respondieron al formulario de descargos')
                ->descriptionIcon('heroicon-o-user')
                ->color('info'),

            Stat::make('Clientes', $totalClientes)
                ->description('Respondieron al registrar un proceso')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('primary'),

            Stat::make('NPS', $npsScore !== null ? $npsScore . ' pts' : '—')
                ->description($totalNps > 0
                    ? "{$promotores} promotores · {$detractores} detractores"
                    : 'Sin puntuaciones NPS aún'
                )
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color(match (true) {
                    $npsScore === null => 'gray',
                    $npsScore >= 50    => 'success',
                    $npsScore >= 0     => 'warning',
                    default            => 'danger',
                }),

            Stat::make('Con comentario', $conSugerencias)
                ->description($total > 0
                    ? round(($conSugerencias / $total) * 100) . '% del total'
                    : 'Sin feedback aún'
                )
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('warning'),
        ];
    }
}
