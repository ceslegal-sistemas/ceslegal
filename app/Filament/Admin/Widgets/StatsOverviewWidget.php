<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\TerminoLegal;
use App\Services\TerminoLegalService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $terminoService = app(TerminoLegalService::class);
        $user = Auth::user();

        // Filtrar por empresa si no es admin
        $procesosQuery = ProcesoDisciplinario::query();
        $solicitudesQuery = SolicitudContrato::query();

        if ($user->role !== 'admin') {
            $procesosQuery->where('empresa_id', $user->empresa_id);
            $solicitudesQuery->where('empresa_id', $user->empresa_id);
        }

        // Si es abogado, filtrar solo sus casos asignados
        if ($user->role === 'abogado') {
            $procesosQuery->where('abogado_id', $user->id);
            $solicitudesQuery->where('abogado_id', $user->id);
        }

        // Estadísticas de Procesos Disciplinarios
        $totalProcesos = $procesosQuery->count();
        $procesosActivos = (clone $procesosQuery)->whereNotIn('estado', ['cerrado', 'archivado'])->count();
        $procesosImpugnados = (clone $procesosQuery)->where('impugnado', true)->where('estado', 'impugnado')->count();

        // Estadísticas de Solicitudes de Contrato
        $totalSolicitudes = $solicitudesQuery->count();
        $solicitudesPendientes = (clone $solicitudesQuery)->whereIn('estado', ['solicitado', 'en_analisis', 'revision_objeto'])->count();

        // Estadísticas de Términos Legales
        $terminosVencidos = TerminoLegal::where('estado', 'vencido')->count();
        $terminosProximos = $terminoService->getTerminosProximosVencer(2)->count();

        return [
            Stat::make('Procesos Disciplinarios Activos', $procesosActivos)
                ->description($totalProcesos . ' procesos en total')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('primary')
                ->chart([7, 12, 8, 15, 18, 12, $procesosActivos]),

            Stat::make('Términos Próximos a Vencer', $terminosProximos)
                ->description('Requieren atención urgente')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([2, 1, 3, 2, 1, 0, $terminosProximos]),

            Stat::make('Términos Vencidos', $terminosVencidos)
                ->description('Necesitan acción inmediata')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart([1, 2, 1, 3, 2, 1, $terminosVencidos]),

            Stat::make('Procesos Impugnados', $procesosImpugnados)
                ->description('En proceso de revisión')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Solicitudes de Contrato Pendientes', $solicitudesPendientes)
                ->description($totalSolicitudes . ' solicitudes en total')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success')
                ->chart([3, 5, 4, 7, 6, 5, $solicitudesPendientes]),
        ];
    }
}
