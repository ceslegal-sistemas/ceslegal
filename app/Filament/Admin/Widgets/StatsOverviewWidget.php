<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\TerminoLegal;
use App\Services\TerminoLegalService;
use App\Services\EstadoProcesoService;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Widgets\Concerns\HasStatsSkeleton;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    use HasStatsSkeleton;

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

        // (#7) Tendencia REAL: procesos creados en los últimos 7 meses (sparkline)
        // y variación de este mes vs. el anterior (▲/▼). Los estados no tienen
        // histórico, así que la tendencia se basa en la creación de procesos.
        $serieNuevos = [];
        for ($i = 6; $i >= 0; $i--) {
            $mes = now()->subMonths($i);
            $serieNuevos[] = (clone $procesosQuery)
                ->whereYear('created_at', $mes->year)
                ->whereMonth('created_at', $mes->month)
                ->count();
        }
        $nuevosEsteMes   = end($serieNuevos) ?: 0;
        $nuevosMesAnt    = $serieNuevos[count($serieNuevos) - 2] ?? 0;
        $delta           = $nuevosEsteMes - $nuevosMesAnt;
        $deltaDesc       = $delta > 0
            ? "▲ {$delta} vs. mes anterior"
            : ($delta < 0 ? "▼ " . abs($delta) . " vs. mes anterior" : 'Sin cambios vs. mes anterior');
        $deltaIcon       = $delta > 0
            ? 'heroicon-m-arrow-trending-up'
            : ($delta < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus-small');
        $deltaColor      = $delta > 0 ? 'danger' : ($delta < 0 ? 'success' : 'gray');

        return [
            Stat::make('Procesos Activos', $procesosActivos)
                ->description("{$totalProcesos} en total · {$deltaDesc}")
                ->descriptionIcon($deltaIcon)
                ->color($deltaColor)
                ->url($this->getActiveProcessesUrl())
                ->chart($serieNuevos),

            Stat::make('En Apertura', $procesosApertura)
                ->description('Procesos iniciados')
                ->descriptionIcon('heroicon-m-folder-open')
                ->color('gray')
                ->url($this->getFilterUrl('apertura')),

            Stat::make('En Descargos', $procesosDescargos)
                ->description('Pendientes o realizados')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->url($this->getFilterUrl(['descargos_pendientes', 'descargos_realizados'])),

            Stat::make('Sanción Emitida', $procesosSancionEmitida)
                ->description('Esperando cierre o impugnación')
                ->descriptionIcon('heroicon-m-scale')
                ->color('info')
                ->url($this->getFilterUrl('sancion_emitida')),

            Stat::make('Impugnados', $procesosImpugnados)
                ->description($procesosImpugnados > 0 ? 'Requieren revisión urgente' : 'Sin impugnaciones')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($procesosImpugnados > 0 ? 'danger' : 'gray')
                ->url($this->getFilterUrl('impugnacion_realizada')),
        ];
    }
}
