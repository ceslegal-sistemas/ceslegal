<?php

namespace App\Filament\Admin\Pages;

use App\Models\AreaPractica;
use App\Models\Empresa;
use App\Models\InformeJuridico;
use App\Models\TipoGestion;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class EstadisticasInformes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Estadísticas';

    protected static ?string $title = 'Estadísticas de Informes de Gestión';

    protected static ?string $navigationGroup = 'Gestión Jurídica';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.admin.pages.estadisticas-informes';

    /**
     * Verificar si el usuario puede acceder a esta página (Shield)
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_EstadisticasInformes') ?? false;
    }

    public ?int $filtroAnio = null;
    public ?int $filtroEmpresa = null;
    public ?string $filtroMes = null;

    public function mount(): void
    {
        $this->filtroAnio = now()->year;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filtroAnio')
                    ->label('Año')
                    ->options(function () {
                        $years = InformeJuridico::selectRaw('DISTINCT anio')
                            ->orderBy('anio', 'desc')
                            ->pluck('anio', 'anio')
                            ->toArray();

                        if (empty($years)) {
                            $years[now()->year] = now()->year;
                        }

                        return $years;
                    })
                    ->default(now()->year)
                    ->live()
                    ->native(false),

                Select::make('filtroMes')
                    ->label('Mes')
                    ->options([
                        'enero' => 'Enero',
                        'febrero' => 'Febrero',
                        'marzo' => 'Marzo',
                        'abril' => 'Abril',
                        'mayo' => 'Mayo',
                        'junio' => 'Junio',
                        'julio' => 'Julio',
                        'agosto' => 'Agosto',
                        'septiembre' => 'Septiembre',
                        'octubre' => 'Octubre',
                        'noviembre' => 'Noviembre',
                        'diciembre' => 'Diciembre',
                    ])
                    ->placeholder('Todos los meses')
                    ->live()
                    ->native(false),

                Select::make('filtroEmpresa')
                    ->label('Empresa')
                    ->options(fn () => Empresa::where('active', true)->pluck('razon_social', 'id'))
                    ->placeholder('Todas las empresas')
                    ->searchable()
                    ->live()
                    ->native(false),
            ])
            ->columns(3);
    }

    protected function getBaseQuery()
    {
        $query = InformeJuridico::query();

        if ($this->filtroAnio) {
            $query->where('anio', $this->filtroAnio);
        }

        if ($this->filtroMes) {
            $query->where('mes', $this->filtroMes);
        }

        if ($this->filtroEmpresa) {
            $query->where('empresa_id', $this->filtroEmpresa);
        }

        return $query;
    }

    // ==================== STATS CARDS ====================

    public function getTotalInformes(): int
    {
        return $this->getBaseQuery()->count();
    }

    public function getTotalHoras(): string
    {
        $minutos = $this->getBaseQuery()->sum('tiempo_minutos') ?? 0;
        $horas = intdiv($minutos, 60);
        $mins = $minutos % 60;
        return "{$horas}h {$mins}m";
    }

    public function getTotalMinutos(): int
    {
        return $this->getBaseQuery()->sum('tiempo_minutos') ?? 0;
    }

    public function getPromedioTiempo(): string
    {
        $promedio = $this->getBaseQuery()->avg('tiempo_minutos') ?? 0;
        return round($promedio) . ' min';
    }

    public function getInformesEntregados(): int
    {
        return $this->getBaseQuery()->where('estado', 'entregado')->count();
    }

    public function getInformesPendientes(): int
    {
        return $this->getBaseQuery()->where('estado', 'pendiente')->count();
    }

    public function getInformesEnProceso(): int
    {
        return $this->getBaseQuery()->where('estado', 'en_proceso')->count();
    }

    public function getPorcentajeEntregados(): float
    {
        $total = $this->getTotalInformes();
        if ($total === 0) return 0;
        return round(($this->getInformesEntregados() / $total) * 100, 1);
    }

    public function getTotalEmpresas(): int
    {
        return $this->getBaseQuery()->distinct('empresa_id')->count('empresa_id');
    }

    public function getTotalAbogados(): int
    {
        return $this->getBaseQuery()->distinct('created_by')->count('created_by');
    }

    // ==================== GRÁFICAS ====================

    public function getInformesPorMes(): array
    {
        $meses = [
            'enero' => 'Ene', 'febrero' => 'Feb', 'marzo' => 'Mar',
            'abril' => 'Abr', 'mayo' => 'May', 'junio' => 'Jun',
            'julio' => 'Jul', 'agosto' => 'Ago', 'septiembre' => 'Sep',
            'octubre' => 'Oct', 'noviembre' => 'Nov', 'diciembre' => 'Dic',
        ];

        $query = InformeJuridico::query();
        if ($this->filtroAnio) $query->where('anio', $this->filtroAnio);
        if ($this->filtroEmpresa) $query->where('empresa_id', $this->filtroEmpresa);

        $data = $query
            ->selectRaw('mes, COUNT(*) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $result = [];
        foreach ($meses as $mes => $label) {
            $result[] = [
                'mes' => $label,
                'total' => $data[$mes] ?? 0,
            ];
        }

        return $result;
    }

    public function getHorasPorMes(): array
    {
        $meses = [
            'enero' => 'Ene', 'febrero' => 'Feb', 'marzo' => 'Mar',
            'abril' => 'Abr', 'mayo' => 'May', 'junio' => 'Jun',
            'julio' => 'Jul', 'agosto' => 'Ago', 'septiembre' => 'Sep',
            'octubre' => 'Oct', 'noviembre' => 'Nov', 'diciembre' => 'Dic',
        ];

        $query = InformeJuridico::query();
        if ($this->filtroAnio) $query->where('anio', $this->filtroAnio);
        if ($this->filtroEmpresa) $query->where('empresa_id', $this->filtroEmpresa);

        $data = $query
            ->selectRaw('mes, SUM(tiempo_minutos) as minutos')
            ->groupBy('mes')
            ->pluck('minutos', 'mes')
            ->toArray();

        $result = [];
        foreach ($meses as $mes => $label) {
            $result[] = [
                'mes' => $label,
                'horas' => round(($data[$mes] ?? 0) / 60, 1),
            ];
        }

        return $result;
    }

    public function getInformesPorArea(): array
    {
        return $this->getBaseQuery()
            ->join('areas_practica', 'informes_juridicos.area_practica_id', '=', 'areas_practica.id')
            ->selectRaw('areas_practica.nombre, areas_practica.color, COUNT(*) as total')
            ->groupBy('areas_practica.id', 'areas_practica.nombre', 'areas_practica.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($item) => [
                'nombre' => $item->nombre,
                'total' => $item->total,
                'color' => $item->color,
            ])
            ->toArray();
    }

    public function getHorasPorArea(): array
    {
        return $this->getBaseQuery()
            ->join('areas_practica', 'informes_juridicos.area_practica_id', '=', 'areas_practica.id')
            ->selectRaw('areas_practica.nombre, areas_practica.color, SUM(tiempo_minutos) as minutos')
            ->groupBy('areas_practica.id', 'areas_practica.nombre', 'areas_practica.color')
            ->orderByDesc('minutos')
            ->get()
            ->map(fn ($item) => [
                'nombre' => $item->nombre,
                'horas' => round(($item->minutos ?? 0) / 60, 1),
                'color' => $item->color,
            ])
            ->toArray();
    }

    public function getInformesPorTipo(): array
    {
        return $this->getBaseQuery()
            ->join('tipos_gestion', 'informes_juridicos.tipo_gestion_id', '=', 'tipos_gestion.id')
            ->selectRaw('tipos_gestion.nombre, COUNT(*) as total')
            ->groupBy('tipos_gestion.id', 'tipos_gestion.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'nombre' => $item->nombre,
                'total' => $item->total,
            ])
            ->toArray();
    }

    public function getInformesPorAbogado(): array
    {
        return $this->getBaseQuery()
            ->join('users', 'informes_juridicos.created_by', '=', 'users.id')
            ->selectRaw('users.name, COUNT(*) as total, SUM(tiempo_minutos) as minutos')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'nombre' => $item->name,
                'total' => $item->total,
                'horas' => round(($item->minutos ?? 0) / 60, 1),
            ])
            ->toArray();
    }

    public function getInformesPorEmpresa(): array
    {
        if ($this->filtroEmpresa) {
            return [];
        }

        return $this->getBaseQuery()
            ->join('empresas', 'informes_juridicos.empresa_id', '=', 'empresas.id')
            ->selectRaw('empresas.razon_social, COUNT(*) as total, SUM(tiempo_minutos) as minutos')
            ->groupBy('empresas.id', 'empresas.razon_social')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'empresa' => $item->razon_social,
                'total' => $item->total,
                'horas' => round(($item->minutos ?? 0) / 60, 1),
            ])
            ->toArray();
    }

    public function getInformesPorEstado(): array
    {
        $estados = [
            'entregado' => ['label' => 'Entregados', 'color' => '#22c55e'],
            'pendiente' => ['label' => 'Pendientes', 'color' => '#eab308'],
            'en_proceso' => ['label' => 'En Proceso', 'color' => '#3b82f6'],
        ];

        $data = $this->getBaseQuery()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $result = [];
        foreach ($estados as $key => $info) {
            if (isset($data[$key]) && $data[$key] > 0) {
                $result[] = [
                    'estado' => $info['label'],
                    'total' => $data[$key],
                    'color' => $info['color'],
                ];
            }
        }

        return $result;
    }

    public function getTendenciaAnual(): array
    {
        $anioActual = $this->filtroAnio ?? now()->year;
        $anioAnterior = $anioActual - 1;

        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $mesesKeys = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        $query = InformeJuridico::query();
        if ($this->filtroEmpresa) $query->where('empresa_id', $this->filtroEmpresa);

        $dataActual = (clone $query)->where('anio', $anioActual)
            ->selectRaw('mes, COUNT(*) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $dataAnterior = (clone $query)->where('anio', $anioAnterior)
            ->selectRaw('mes, COUNT(*) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $result = [];
        foreach ($mesesKeys as $i => $mes) {
            $result[] = [
                'mes' => $meses[$i],
                'actual' => $dataActual[$mes] ?? 0,
                'anterior' => $dataAnterior[$mes] ?? 0,
            ];
        }

        return [
            'labels' => $meses,
            'actual' => array_column($result, 'actual'),
            'anterior' => array_column($result, 'anterior'),
            'anioActual' => $anioActual,
            'anioAnterior' => $anioAnterior,
        ];
    }

    public function getPromedioTiempoPorTipo(): array
    {
        return $this->getBaseQuery()
            ->join('tipos_gestion', 'informes_juridicos.tipo_gestion_id', '=', 'tipos_gestion.id')
            ->selectRaw('tipos_gestion.nombre, AVG(tiempo_minutos) as promedio')
            ->whereNotNull('tiempo_minutos')
            ->where('tiempo_minutos', '>', 0)
            ->groupBy('tipos_gestion.id', 'tipos_gestion.nombre')
            ->orderByDesc('promedio')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'nombre' => $item->nombre,
                'promedio' => round($item->promedio ?? 0),
            ])
            ->toArray();
    }
}
