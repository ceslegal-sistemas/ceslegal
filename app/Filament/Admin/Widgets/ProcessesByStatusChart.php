<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ProcessesByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Procesos por Estado';

    protected static ?string $description = 'Haga clic en un estado para ver los procesos';

    protected static ?int $sort = 4;

    protected static string $view = 'filament.admin.widgets.processes-by-status-chart';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '350px';

    // Etiquetas claras para el usuario
    const ETIQUETAS_ESTADO = [
        'apertura' => 'Iniciados',
        'descargos_pendientes' => 'Citación enviada',
        'descargos_realizados' => 'Descargos completados',
        'descargos_no_realizados' => 'No asistió',
        'sancion_emitida' => 'Sanción emitida',
        'impugnacion_realizada' => 'Impugnados',
        'cerrado' => 'Cerrados',
        'archivado' => 'Archivados',
    ];

    const COLORES_ESTADO = [
        'apertura' => ['rgba(156, 163, 175, 0.7)', 'rgb(156, 163, 175)'],
        'descargos_pendientes' => ['rgba(249, 115, 22, 0.7)', 'rgb(249, 115, 22)'],
        'descargos_realizados' => ['rgba(14, 165, 233, 0.7)', 'rgb(14, 165, 233)'],
        'descargos_no_realizados' => ['rgba(239, 68, 68, 0.7)', 'rgb(239, 68, 68)'],
        'sancion_emitida' => ['rgba(59, 130, 246, 0.7)', 'rgb(59, 130, 246)'],
        'impugnacion_realizada' => ['rgba(168, 85, 247, 0.7)', 'rgb(168, 85, 247)'],
        'cerrado' => ['rgba(34, 197, 94, 0.7)', 'rgb(34, 197, 94)'],
        'archivado' => ['rgba(75, 85, 99, 0.7)', 'rgb(75, 85, 99)'],
    ];

    protected function getData(): array
    {
        $user = Auth::user();

        $query = ProcesoDisciplinario::query();

        if (!in_array($user->role, ['abogado', 'super_admin'])) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $estadosPosibles = [
            'apertura',
            'descargos_pendientes',
            'descargos_realizados',
            'descargos_no_realizados',
            'sancion_emitida',
            'impugnacion_realizada',
            'cerrado',
            'archivado',
        ];

        $data = [];
        $labels = [];
        $backgroundColors = [];
        $borderColors = [];

        foreach ($estadosPosibles as $estado) {
            $count = (clone $query)->where('estado', $estado)->count();

            if ($count > 0) {
                $data[] = $count;
                $labels[] = self::ETIQUETAS_ESTADO[$estado] ?? $estado;

                $colors = self::COLORES_ESTADO[$estado] ?? ['rgba(156, 163, 175, 0.7)', 'rgb(156, 163, 175)'];
                $backgroundColors[] = $colors[0];
                $borderColors[] = $colors[1];
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad de Procesos',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                    'hoverBackgroundColor' => $borderColors,
                    'hoverBorderColor' => '#fff',
                    'hoverBorderWidth' => 3,
                    'hoverOffset' => 15,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'maintainAspectRatio' => false,
            'responsive' => true,
        ];
    }

    public function getEstadosParaBotones(): array
    {
        $user = Auth::user();
        $query = ProcesoDisciplinario::query();

        if (!in_array($user->role, ['abogado', 'super_admin'])) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $estadosPosibles = [
            'apertura',
            'descargos_pendientes',
            'descargos_realizados',
            'descargos_no_realizados',
            'sancion_emitida',
            'impugnacion_realizada',
            'cerrado',
            'archivado',
        ];

        $estados = [];
        foreach ($estadosPosibles as $estado) {
            $count = (clone $query)->where('estado', $estado)->count();
            if ($count > 0) {
                $estados[] = [
                    'key' => $estado,
                    'label' => self::ETIQUETAS_ESTADO[$estado] ?? $estado,
                    'count' => $count,
                    'color' => self::COLORES_ESTADO[$estado][1] ?? 'rgb(156, 163, 175)',
                ];
            }
        }

        return $estados;
    }
}
