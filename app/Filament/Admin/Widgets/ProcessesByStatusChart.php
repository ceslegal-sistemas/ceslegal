<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use App\Services\EstadoProcesoService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ProcessesByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Distribución de Procesos por Estado';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '350px';

    protected function getData(): array
    {
        $user = Auth::user();
        $estadoService = app(EstadoProcesoService::class);

        $query = ProcesoDisciplinario::query();

        // Filtrar por empresa si no es admin o super_admin
        if (!in_array($user->role, ['abogado', 'super_admin'])) {
            $query->where('empresa_id', $user->empresa_id);
        }

        // Si es abogado, filtrar solo sus casos asignados
        // if ($user->role === 'abogado') {
        //     $query->where('abogado_id', $user->id);
        // }

        // Obtener todos los estados posibles (flujo simplificado)
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

        // Mapeo de colores por estado
        $colorMapping = [
            'apertura' => ['rgba(156, 163, 175, 0.7)', 'rgb(156, 163, 175)'],
            'descargos_pendientes' => ['rgba(249, 115, 22, 0.7)', 'rgb(249, 115, 22)'],
            'descargos_realizados' => ['rgba(14, 165, 233, 0.7)', 'rgb(14, 165, 233)'],
            'descargos_no_realizados' => ['rgba(239, 68, 68, 0.7)', 'rgb(239, 68, 68)'],
            'sancion_emitida' => ['rgba(59, 130, 246, 0.7)', 'rgb(59, 130, 246)'],
            'impugnacion_realizada' => ['rgba(168, 85, 247, 0.7)', 'rgb(168, 85, 247)'],
            'cerrado' => ['rgba(34, 197, 94, 0.7)', 'rgb(34, 197, 94)'],
            'archivado' => ['rgba(75, 85, 99, 0.7)', 'rgb(75, 85, 99)'],
        ];

        foreach ($estadosPosibles as $estado) {
            $count = (clone $query)->where('estado', $estado)->count();

            // Solo incluir estados que tengan procesos
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $estadoService->getDescripcionEstado($estado);

                $colors = $colorMapping[$estado] ?? ['rgba(156, 163, 175, 0.7)', 'rgb(156, 163, 175)'];
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
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 15,
                        'font' => [
                            'size' => 11,
                        ],
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'callbacks' => [
                        'label' => 'function(context) {
                            let label = context.label || "";
                            let value = context.parsed || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return label + ": " + value + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
