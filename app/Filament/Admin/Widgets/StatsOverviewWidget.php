<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\TerminoLegal;
use App\Services\TerminoLegalService;
use App\Services\EstadoProcesoService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $terminoService = app(TerminoLegalService::class);
        $estadoService = app(EstadoProcesoService::class);
        $user = Auth::user();

        // Filtrar por empresa si no es admin o super_admin
        $procesosQuery = ProcesoDisciplinario::query();
        $solicitudesQuery = SolicitudContrato::query();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
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
        $procesosDescargos = (clone $procesosQuery)->whereIn('estado', ['descargos_pendientes', 'descargos_realizados'])->count();
        $procesosAnalisis = (clone $procesosQuery)->whereIn('estado', ['analisis_juridico', 'pendiente_gerencia'])->count();
        $procesosImpugnados = (clone $procesosQuery)->where('estado', 'impugnado')->count();

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

            Stat::make('En Descargos', $procesosDescargos)
                ->description('Pendientes o realizados')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->chart([3, 5, 4, 3, 2, 4, $procesosDescargos]),

            Stat::make('En Análisis', $procesosAnalisis)
                ->description('Análisis jurídico o gerencia')
                ->descriptionIcon('heroicon-m-scale')
                ->color('info')
                ->chart([2, 3, 4, 2, 3, 4, $procesosAnalisis]),

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
                ->color('danger'),
        ];
    }
}
