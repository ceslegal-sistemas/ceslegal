<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\TerminoLegal;
use App\Services\TerminoLegalService;
use App\Services\EstadoProcesoService;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    /**
     * Genera URL con filtro de estado para la lista de procesos
     */
    protected function getFilterUrl(string|array $estados): string
    {
        $baseUrl = ProcesoDisciplinarioResource::getUrl('index');

        if (is_array($estados)) {
            $params = [];
            foreach ($estados as $index => $estado) {
                $params["tableFilters[estado][values][{$index}]"] = $estado;
            }
            return $baseUrl . '?' . http_build_query($params);
        }

        return $baseUrl . '?tableFilters[estado][values][0]=' . $estados;
    }

    /**
     * Genera URL para ver todos los procesos (sin filtro o con filtro especial)
     */
    protected function getActiveProcessesUrl(): string
    {
        $baseUrl = ProcesoDisciplinarioResource::getUrl('index');
        // Filtrar excluyendo cerrados y archivados - mostramos los estados activos
        $estadosActivos = [
            'apertura',
            'descargos_pendientes',
            'descargos_realizados',
            'descargos_no_realizados',
            'sancion_emitida',
            'impugnacion_realizada',
        ];

        $params = [];
        foreach ($estadosActivos as $index => $estado) {
            $params["tableFilters[estado][values][{$index}]"] = $estado;
        }

        return $baseUrl . '?' . http_build_query($params);
    }

    protected function getStats(): array
    {
        $terminoService = app(TerminoLegalService::class);
        $estadoService = app(EstadoProcesoService::class);
        $user = Auth::user();

        // Filtrar por empresa si no es admin o super_admin
        $procesosQuery = ProcesoDisciplinario::query();
        $solicitudesQuery = SolicitudContrato::query();

        if (!in_array($user->role, ['super_admin', 'abogado'])) {
            $procesosQuery->where('empresa_id', $user->empresa_id);
            $solicitudesQuery->where('empresa_id', $user->empresa_id);
        }

        // Estadísticas de Procesos Disciplinarios (ESTADOS SIMPLIFICADOS)
        $totalProcesos = $procesosQuery->count();
        $procesosActivos = (clone $procesosQuery)->whereNotIn('estado', ['cerrado', 'archivado'])->count();
        $procesosApertura = (clone $procesosQuery)->where('estado', 'apertura')->count();
        $procesosDescargos = (clone $procesosQuery)->whereIn('estado', ['descargos_pendientes', 'descargos_realizados'])->count();
        $procesosSancionEmitida = (clone $procesosQuery)->where('estado', 'sancion_emitida')->count();
        $procesosImpugnados = (clone $procesosQuery)->where('estado', 'impugnacion_realizada')->count();

        // Estadísticas de Solicitudes de Contrato
        $totalSolicitudes = $solicitudesQuery->count();
        $solicitudesPendientes = (clone $solicitudesQuery)->whereIn('estado', ['solicitado', 'en_analisis', 'revision_objeto'])->count();

        // Estadísticas de Términos Legales
        $terminosVencidos = TerminoLegal::where('estado', 'vencido')->count();
        $terminosProximos = $terminoService->getTerminosProximosVencer(2)->count();

        return [
            Stat::make('Procesos Activos', $procesosActivos)
                ->description($totalProcesos . ' procesos en total')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('primary')
                ->url($this->getActiveProcessesUrl())
                ->chart([7, 12, 8, 15, 18, 12, $procesosActivos]),

            Stat::make('En Apertura', $procesosApertura)
                ->description('Procesos iniciados')
                ->descriptionIcon('heroicon-m-folder-open')
                ->color('gray')
                ->url($this->getFilterUrl('apertura'))
                ->chart([2, 3, 2, 1, 2, 3, $procesosApertura]),

            Stat::make('En Descargos', $procesosDescargos)
                ->description('Pendientes o realizados')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->url($this->getFilterUrl(['descargos_pendientes', 'descargos_realizados']))
                ->chart([3, 5, 4, 3, 2, 4, $procesosDescargos]),

            Stat::make('Sanción Emitida', $procesosSancionEmitida)
                ->description('Esperando cierre o impugnación')
                ->descriptionIcon('heroicon-m-scale')
                ->color('info')
                ->url($this->getFilterUrl('sancion_emitida'))
                ->chart([2, 3, 4, 2, 3, 4, $procesosSancionEmitida]),

            Stat::make('Impugnados', $procesosImpugnados)
                ->description('Requieren revisión urgente')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('danger')
                ->url($this->getFilterUrl('impugnacion_realizada'))
                ->chart([0, 1, 0, 1, 2, 1, $procesosImpugnados]),
        ];
    }
}
