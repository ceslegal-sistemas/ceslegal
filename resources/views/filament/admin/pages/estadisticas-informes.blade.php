<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-6">
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>
    </div>

    {{-- Stats Cards - Primera Fila --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ $this->getTotalInformes() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Informes</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ $this->getInformesEntregados() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Entregados</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ $this->getInformesPendientes() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Pendientes</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ $this->getInformesEnProceso() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">En Proceso</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $this->getTotalHoras() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tiempo Total</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Stats Cards - Segunda Fila --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $this->getPromedioTiempo() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Promedio por Informe</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $this->getPorcentajeEntregados() }}%</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">% Entregados</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $this->getTotalEmpresas() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Empresas Atendidas</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $this->getTotalAbogados() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Abogados Activos</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Gráfica Principal: Tendencia Anual --}}
    <div class="mb-6" wire:key="chart-tendencia-{{ $filtroAnio }}-{{ $filtroEmpresa }}">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-trending-up class="w-5 h-5" />
                    Tendencia Anual: Comparativa con Año Anterior
                </div>
            </x-slot>

            <div class="h-72">
                <canvas id="chartTendencia"></canvas>
            </div>
        </x-filament::section>
    </div>

    {{-- Gráficas de Actividad Mensual --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" wire:key="charts-mes-{{ $filtroAnio }}-{{ $filtroEmpresa }}">
        {{-- Informes por Mes --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                    Informes por Mes
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartMeses"></canvas>
            </div>
        </x-filament::section>

        {{-- Horas por Mes --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-clock class="w-5 h-5" />
                    Horas por Mes
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartHorasMes"></canvas>
            </div>
        </x-filament::section>
    </div>

    {{-- Gráficas de Distribución --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" wire:key="charts-dist-{{ $filtroAnio }}-{{ $filtroEmpresa }}">
        {{-- Informes por Área --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-rectangle-stack class="w-5 h-5" />
                    Informes por Área de Práctica
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartAreas"></canvas>
            </div>
        </x-filament::section>

        {{-- Horas por Área --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-clock class="w-5 h-5" />
                    Horas por Área de Práctica
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartHorasArea"></canvas>
            </div>
        </x-filament::section>
    </div>

    {{-- Gráficas de Tipos y Estados --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" wire:key="charts-tipo-{{ $filtroAnio }}-{{ $filtroEmpresa }}">
        {{-- Top 10 Tipos de Gestión --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5" />
                    Top 10 Tipos de Gestión
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartTipos"></canvas>
            </div>
        </x-filament::section>

        {{-- Distribución por Estado --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-chart-pie class="w-5 h-5" />
                    Distribución por Estado
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartEstados"></canvas>
            </div>
        </x-filament::section>
    </div>

    {{-- Gráfica de Promedio de Tiempo por Tipo --}}
    <div class="mb-6" wire:key="chart-promedio-{{ $filtroAnio }}-{{ $filtroEmpresa }}">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calculator class="w-5 h-5" />
                    Promedio de Tiempo por Tipo de Gestión (minutos)
                </div>
            </x-slot>

            <div class="h-64">
                <canvas id="chartPromedioTipo"></canvas>
            </div>
        </x-filament::section>
    </div>

    {{-- Tabla de Abogados --}}
    @php $abogados = $this->getInformesPorAbogado(); @endphp
    @if(count($abogados) > 0)
    <x-filament::section class="mb-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-user-group class="w-5 h-5" />
                Rendimiento por Abogado
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left py-2 px-3">Abogado</th>
                        <th class="text-center py-2 px-3">Informes</th>
                        <th class="text-center py-2 px-3">Horas</th>
                        <th class="text-center py-2 px-3">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($abogados as $item)
                    <tr class="border-b dark:border-gray-700">
                        <td class="py-2 px-3 font-medium">{{ $item['nombre'] }}</td>
                        <td class="py-2 px-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-100">
                                {{ $item['total'] }}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-center text-gray-500">{{ $item['horas'] }}h</td>
                        <td class="py-2 px-3 text-center text-gray-500">
                            {{ $item['total'] > 0 ? round(($item['horas'] * 60) / $item['total']) : 0 }} min
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @endif

    {{-- Tabla de Empresas --}}
    @php $empresas = $this->getInformesPorEmpresa(); @endphp
    @if(count($empresas) > 0)
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-building-office class="w-5 h-5" />
                Informes por Empresa
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left py-2 px-3">Empresa</th>
                        <th class="text-center py-2 px-3">Informes</th>
                        <th class="text-center py-2 px-3">Horas</th>
                        <th class="text-center py-2 px-3">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($empresas as $item)
                    <tr class="border-b dark:border-gray-700">
                        <td class="py-2 px-3 font-medium">{{ $item['empresa'] }}</td>
                        <td class="py-2 px-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-100">
                                {{ $item['total'] }}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-center text-gray-500">{{ $item['horas'] }}h</td>
                        <td class="py-2 px-3 text-center text-gray-500">
                            {{ $item['total'] > 0 ? round(($item['horas'] * 60) / $item['total']) : 0 }} min
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @endif

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        (function() {
            const datosMeses = @json($this->getInformesPorMes());
            const datosHorasMes = @json($this->getHorasPorMes());
            const datosAreas = @json($this->getInformesPorArea());
            const datosHorasArea = @json($this->getHorasPorArea());
            const datosTipos = @json($this->getInformesPorTipo());
            const datosEstados = @json($this->getInformesPorEstado());
            const datosTendencia = @json($this->getTendenciaAnual());
            const datosPromedioTipo = @json($this->getPromedioTiempoPorTipo());

            const coloresArea = {
                'danger': '#ef4444',
                'info': '#3b82f6',
                'warning': '#eab308',
                'success': '#22c55e',
                'primary': '#6366f1',
                'gray': '#6b7280',
            };

            // Gráfico de Tendencia Anual
            const ctxTendencia = document.getElementById('chartTendencia');
            if (ctxTendencia) {
                new Chart(ctxTendencia, {
                    type: 'line',
                    data: {
                        labels: datosTendencia.labels,
                        datasets: [
                            {
                                label: datosTendencia.anioActual,
                                data: datosTendencia.actual,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: datosTendencia.anioAnterior,
                                data: datosTendencia.anterior,
                                borderColor: '#9ca3af',
                                backgroundColor: 'rgba(156, 163, 175, 0.1)',
                                tension: 0.3,
                                fill: true,
                                borderDash: [5, 5],
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } }
                        }
                    }
                });
            }

            // Gráfico de Meses
            const ctxMeses = document.getElementById('chartMeses');
            if (ctxMeses) {
                new Chart(ctxMeses, {
                    type: 'bar',
                    data: {
                        labels: datosMeses.map(d => d.mes),
                        datasets: [{
                            label: 'Informes',
                            data: datosMeses.map(d => d.total),
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });
            }

            // Gráfico de Horas por Mes
            const ctxHorasMes = document.getElementById('chartHorasMes');
            if (ctxHorasMes) {
                new Chart(ctxHorasMes, {
                    type: 'line',
                    data: {
                        labels: datosHorasMes.map(d => d.mes),
                        datasets: [{
                            label: 'Horas',
                            data: datosHorasMes.map(d => d.horas),
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            tension: 0.3,
                            fill: true,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // Gráfico de Áreas
            const ctxAreas = document.getElementById('chartAreas');
            if (ctxAreas && datosAreas.length > 0) {
                new Chart(ctxAreas, {
                    type: 'doughnut',
                    data: {
                        labels: datosAreas.map(d => d.nombre),
                        datasets: [{
                            data: datosAreas.map(d => d.total),
                            backgroundColor: datosAreas.map(d => coloresArea[d.color] || '#6b7280'),
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'right' } }
                    }
                });
            }

            // Gráfico de Horas por Área
            const ctxHorasArea = document.getElementById('chartHorasArea');
            if (ctxHorasArea && datosHorasArea.length > 0) {
                new Chart(ctxHorasArea, {
                    type: 'bar',
                    data: {
                        labels: datosHorasArea.map(d => d.nombre),
                        datasets: [{
                            label: 'Horas',
                            data: datosHorasArea.map(d => d.horas),
                            backgroundColor: datosHorasArea.map(d => coloresArea[d.color] || '#6b7280'),
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // Gráfico de Tipos
            const ctxTipos = document.getElementById('chartTipos');
            if (ctxTipos && datosTipos.length > 0) {
                new Chart(ctxTipos, {
                    type: 'bar',
                    data: {
                        labels: datosTipos.map(d => d.nombre),
                        datasets: [{
                            label: 'Informes',
                            data: datosTipos.map(d => d.total),
                            backgroundColor: '#8b5cf6',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });
            }

            // Gráfico de Estados
            const ctxEstados = document.getElementById('chartEstados');
            if (ctxEstados && datosEstados.length > 0) {
                new Chart(ctxEstados, {
                    type: 'pie',
                    data: {
                        labels: datosEstados.map(d => d.estado),
                        datasets: [{
                            data: datosEstados.map(d => d.total),
                            backgroundColor: datosEstados.map(d => d.color),
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'right' } }
                    }
                });
            }

            // Gráfico de Promedio por Tipo
            const ctxPromedioTipo = document.getElementById('chartPromedioTipo');
            if (ctxPromedioTipo && datosPromedioTipo.length > 0) {
                new Chart(ctxPromedioTipo, {
                    type: 'bar',
                    data: {
                        labels: datosPromedioTipo.map(d => d.nombre),
                        datasets: [{
                            label: 'Promedio (min)',
                            data: datosPromedioTipo.map(d => d.promedio),
                            backgroundColor: '#f59e0b',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true } }
                    }
                });
            }
        })();
    </script>
</x-filament-panels::page>
