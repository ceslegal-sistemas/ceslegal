<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ProcessesByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Procesos Disciplinarios por Estado';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $user = Auth::user();

        $query = ProcesoDisciplinario::query();

        // Filtrar por empresa si no es admin
        if ($user->role !== 'admin') {
            $query->where('empresa_id', $user->empresa_id);
        }

        // Si es abogado, filtrar solo sus casos asignados
        if ($user->role === 'abogado') {
            $query->where('abogado_id', $user->id);
        }

        $estados = [
            'apertura' => 'Apertura',
            'traslado' => 'Traslado',
            'descargos_pendientes' => 'Descargos Pendientes',
            'descargos_realizados' => 'Descargos Realizados',
            'analisis_juridico' => 'Análisis Jurídico',
            'pendiente_gerencia' => 'Pendiente Gerencia',
            'sancion_definida' => 'Sanción Definida',
            'notificado' => 'Notificado',
            'impugnado' => 'Impugnado',
            'cerrado' => 'Cerrado',
            'archivado' => 'Archivado',
        ];

        $data = [];
        $labels = [];

        foreach ($estados as $key => $label) {
            $count = (clone $query)->where('estado', $key)->count();
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $label;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad de Procesos',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.5)',   // Blue
                        'rgba(251, 191, 36, 0.5)',   // Amber
                        'rgba(249, 115, 22, 0.5)',   // Orange
                        'rgba(14, 165, 233, 0.5)',   // Sky
                        'rgba(99, 102, 241, 0.5)',   // Indigo
                        'rgba(168, 85, 247, 0.5)',   // Purple
                        'rgba(236, 72, 153, 0.5)',   // Pink
                        'rgba(34, 197, 94, 0.5)',    // Green
                        'rgba(239, 68, 68, 0.5)',    // Red
                        'rgba(107, 114, 128, 0.5)',  // Gray
                        'rgba(156, 163, 175, 0.5)',  // Gray light
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(251, 191, 36)',
                        'rgb(249, 115, 22)',
                        'rgb(14, 165, 233)',
                        'rgb(99, 102, 241)',
                        'rgb(168, 85, 247)',
                        'rgb(236, 72, 153)',
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                        'rgb(107, 114, 128)',
                        'rgb(156, 163, 175)',
                    ],
                    'borderWidth' => 1,
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
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
