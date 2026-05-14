<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Informe de Gestión Jurídica</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.5;
            color: #1f2937;
            background: #fff;
        }

        .page {
            padding: 0;
        }

        /* Header profesional */
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            padding: 20px 25px;
            margin: -20px -15px 20px -15px;
            position: relative;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 70%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }

        .header h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }

        .header .subtitle {
            font-size: 11px;
            opacity: 0.9;
        }

        .header .report-number {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 10px;
        }

        .header .report-number strong {
            display: block;
            font-size: 14px;
        }

        /* Info empresa card */
        .empresa-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .empresa-card h2 {
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #4f46e5;
        }

        .empresa-info-grid {
            display: table;
            width: 100%;
        }

        .empresa-info-row {
            display: table-row;
        }

        .empresa-info-cell {
            display: table-cell;
            padding: 4px 0;
        }

        .empresa-info-label {
            font-weight: 600;
            color: #64748b;
            width: 100px;
        }

        .empresa-info-value {
            color: #334155;
        }

        /* Periodo badge */
        .periodo-badge {
            display: inline-block;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border: 1px solid #c7d2fe;
            border-left: 4px solid #4f46e5;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .periodo-badge strong {
            color: #4f46e5;
        }

        /* KPI Cards */
        .kpi-section {
            margin-bottom: 20px;
        }

        .kpi-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
        }

        .kpi-card {
            display: table-cell;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            width: 25%;
        }

        .kpi-card.primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border: none;
        }

        .kpi-card.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 700;
            line-height: 1.2;
        }

        .kpi-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
            opacity: 0.9;
        }

        /* Section titles */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin: 20px 0 10px 0;
            padding: 8px 12px;
            background: linear-gradient(90deg, #f1f5f9 0%, transparent 100%);
            border-left: 4px solid #4f46e5;
            border-radius: 0 4px 4px 0;
        }

        /* Charts section */
        .charts-section {
            margin: 20px 0;
        }

        .charts-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .chart-container {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }

        .chart-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }

        .chart-title {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 10px;
            text-align: center;
        }

        .chart-svg {
            text-align: center;
        }

        /* Summary tables */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }

        .summary-table th {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 9px;
        }

        .summary-table th:first-child {
            border-radius: 6px 0 0 0;
        }

        .summary-table th:last-child {
            border-radius: 0 6px 0 0;
        }

        .summary-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .summary-table tr:last-child td:first-child {
            border-radius: 0 0 0 6px;
        }

        .summary-table tr:last-child td:last-child {
            border-radius: 0 0 6px 0;
        }

        .summary-table .number {
            text-align: center;
            font-weight: 600;
            color: #4f46e5;
        }

        .summary-table .time {
            text-align: center;
            color: #059669;
            font-weight: 500;
        }

        /* Main detail table */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 8px;
        }

        .main-table th {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 8px;
        }

        .main-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .main-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .main-table .descripcion {
            max-width: 180px;
            word-wrap: break-word;
        }

        /* Status badges */
        .estado-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 7px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .estado-entregado {
            background: #d1fae5;
            color: #065f46;
        }

        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
        }

        .estado-en_proceso {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }

        .footer-logo {
            font-weight: 700;
            color: #4f46e5;
            font-size: 10px;
            margin-bottom: 5px;
        }

        /* Page break */
        .page-break {
            page-break-after: always;
        }

        /* Análisis BI Styles */
        .bi-section {
            margin: 20px 0;
            background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
            border: 1px solid #fde047;
            border-left: 4px solid #eab308;
            border-radius: 8px;
            padding: 15px 20px;
        }

        .bi-section-title {
            font-size: 11px;
            font-weight: 700;
            color: #854d0e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .bi-section-title::before {
            content: "💡";
            margin-right: 8px;
        }

        .bi-resumen {
            font-size: 9px;
            color: #422006;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .bi-hallazgos {
            background: white;
            border-radius: 6px;
            padding: 12px;
            margin: 10px 0;
        }

        .bi-hallazgos-title {
            font-size: 9px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .bi-hallazgo-item {
            font-size: 8px;
            color: #334155;
            padding: 4px 0 4px 15px;
            position: relative;
            line-height: 1.4;
        }

        .bi-hallazgo-item::before {
            content: "→";
            position: absolute;
            left: 0;
            color: #4f46e5;
            font-weight: bold;
        }

        .bi-recomendaciones {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #6ee7b7;
            border-radius: 6px;
            padding: 12px;
            margin: 10px 0;
        }

        .bi-recomendaciones-title {
            font-size: 9px;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 8px;
        }

        .bi-recomendacion-item {
            font-size: 8px;
            color: #047857;
            padding: 4px 0 4px 15px;
            position: relative;
            line-height: 1.4;
        }

        .bi-recomendacion-item::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }

        .bi-tendencias {
            font-size: 8px;
            color: #64748b;
            font-style: italic;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }

        /* Métricas BI Cards */
        .metricas-bi-section {
            margin: 20px 0;
        }

        .metricas-bi-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
        }

        .metrica-bi-card {
            display: table-cell;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            width: 25%;
        }

        .metrica-bi-card.warning {
            background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
            border-color: #fde047;
        }

        .metrica-bi-card.success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: #6ee7b7;
        }

        .metrica-bi-value {
            font-size: 18px;
            font-weight: 700;
            color: #0369a1;
            line-height: 1.2;
        }

        .metrica-bi-card.warning .metrica-bi-value {
            color: #a16207;
        }

        .metrica-bi-card.success .metrica-bi-value {
            color: #047857;
        }

        .metrica-bi-label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 4px;
            color: #64748b;
        }

        /* Alertas */
        .bi-alertas {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fca5a5;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 10px 0;
        }

        .bi-alertas-title {
            font-size: 9px;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 6px;
        }

        .bi-alerta-item {
            font-size: 8px;
            color: #b91c1c;
            padding: 3px 0 3px 15px;
            position: relative;
        }

        .bi-alerta-item::before {
            content: "⚠";
            position: absolute;
            left: 0;
        }

        /* Bar chart for months */
        .bar-chart-section {
            margin: 15px 0;
        }

        .bar-chart-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }

        /* Progress bar style for estados */
        .estado-progress {
            display: table;
            width: 100%;
            margin: 10px 0;
        }

        .estado-item {
            display: table-row;
        }

        .estado-label {
            display: table-cell;
            width: 100px;
            padding: 5px 0;
            font-weight: 500;
        }

        .estado-bar-container {
            display: table-cell;
            padding: 5px 10px;
            vertical-align: middle;
        }

        .estado-bar {
            height: 18px;
            border-radius: 9px;
            position: relative;
        }

        .estado-bar-value {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 8px;
            font-weight: 600;
            color: white;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header profesional -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Informe de Gestión Jurídica</h1>
                    <div class="subtitle">Reporte detallado de actividades y gestiones legales</div>
                </div>
                <div class="header-right">
                    <div class="report-number">
                        <strong>{{ $anio }}</strong>
                        {{ $mes }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Empresa Card -->
        <div class="empresa-card">
            <h2>{{ $empresa->razon_social }}</h2>
            <div class="empresa-info-grid">
                <div class="empresa-info-row">
                    <div class="empresa-info-cell empresa-info-label">NIT:</div>
                    <div class="empresa-info-cell empresa-info-value">{{ $empresa->nit }}</div>
                    @if($empresa->direccion)
                    <div class="empresa-info-cell empresa-info-label" style="padding-left: 20px;">Dirección:</div>
                    <div class="empresa-info-cell empresa-info-value">{{ $empresa->direccion }}</div>
                    @endif
                </div>
                @if($empresa->ciudad || $empresa->telefono)
                <div class="empresa-info-row">
                    @if($empresa->ciudad)
                    <div class="empresa-info-cell empresa-info-label">Ciudad:</div>
                    <div class="empresa-info-cell empresa-info-value">{{ $empresa->ciudad }}</div>
                    @endif
                    @if($empresa->telefono)
                    <div class="empresa-info-cell empresa-info-label" style="padding-left: 20px;">Teléfono:</div>
                    <div class="empresa-info-cell empresa-info-value">{{ $empresa->telefono }}</div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <!-- Periodo Badge -->
        <div class="periodo-badge">
            <strong>Periodo:</strong> {{ $anio }} - {{ $mes }}
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Generado:</strong> {{ $fechaGeneracion }}
        </div>

        <!-- KPI Cards -->
        <div class="kpi-section">
            <div class="kpi-grid">
                <div class="kpi-card primary">
                    <div class="kpi-value">{{ $totalGestiones }}</div>
                    <div class="kpi-label">Total Gestiones</div>
                </div>
                <div class="kpi-card success">
                    <div class="kpi-value">{{ $tiempoTotal }}</div>
                    <div class="kpi-label">Tiempo Invertido</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value">{{ count($resumenPorArea) }}</div>
                    <div class="kpi-label">Áreas de Práctica</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value">{{ count($resumenPorTipo) }}</div>
                    <div class="kpi-label">Tipos de Gestión</div>
                </div>
            </div>
        </div>

        <!-- Métricas de Business Intelligence -->
        @if(isset($metricasBI))
        <div class="metricas-bi-section">
            <div class="section-title">Indicadores de Desempeño</div>
            <div class="metricas-bi-grid">
                <div class="metrica-bi-card success">
                    <div class="metrica-bi-value">{{ $metricasBI['tasa_cumplimiento'] }}%</div>
                    <div class="metrica-bi-label">Tasa de Cumplimiento</div>
                </div>
                <div class="metrica-bi-card">
                    <div class="metrica-bi-value">{{ $metricasBI['tiempo_promedio_gestion'] }}</div>
                    <div class="metrica-bi-label">Tiempo Promedio/Gestión</div>
                </div>
                <div class="metrica-bi-card warning">
                    <div class="metrica-bi-value">{{ $metricasBI['gestiones_pendientes'] }}</div>
                    <div class="metrica-bi-label">Gestiones Pendientes</div>
                </div>
                <div class="metrica-bi-card">
                    <div class="metrica-bi-value">{{ $metricasBI['horas_invertidas'] }}h</div>
                    <div class="metrica-bi-label">Horas Invertidas</div>
                </div>
            </div>
        </div>

        <!-- Gráfica de Gauge y Tiempo por Área -->
        @if(!empty($chartGaugeSvg) || !empty($chartTiempoAreaSvg))
        <div class="charts-section" style="margin-top: 15px;">
            <div class="charts-grid">
                @if(!empty($chartGaugeSvg))
                <div class="chart-container" style="width: 35%;">
                    <div class="chart-box" style="text-align: center;">
                        <div class="chart-title">Indicador de Cumplimiento</div>
                        <div class="chart-svg">{!! $chartGaugeSvg !!}</div>
                    </div>
                </div>
                @endif
                @if(!empty($chartTiempoAreaSvg))
                <div class="chart-container" style="width: 65%;">
                    <div class="chart-box">
                        <div class="chart-title">Tiempo Invertido por Área</div>
                        <div class="chart-svg">{!! $chartTiempoAreaSvg !!}</div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
        @endif

        <!-- Análisis Ejecutivo con IA -->
        @if(isset($analisisBI) && !empty($analisisBI['resumen_ejecutivo']))
        <div class="bi-section">
            <div class="bi-section-title">Análisis Ejecutivo</div>
            <div class="bi-resumen">{{ $analisisBI['resumen_ejecutivo'] }}</div>

            @if(!empty($analisisBI['hallazgos_clave']))
            <div class="bi-hallazgos">
                <div class="bi-hallazgos-title">Hallazgos Clave</div>
                @foreach($analisisBI['hallazgos_clave'] as $hallazgo)
                <div class="bi-hallazgo-item">{{ $hallazgo }}</div>
                @endforeach
            </div>
            @endif

            @if(!empty($analisisBI['indicadores_alerta']))
            <div class="bi-alertas">
                <div class="bi-alertas-title">Indicadores de Alerta</div>
                @foreach($analisisBI['indicadores_alerta'] as $alerta)
                <div class="bi-alerta-item">{{ $alerta }}</div>
                @endforeach
            </div>
            @endif

            @if(!empty($analisisBI['recomendaciones']))
            <div class="bi-recomendaciones">
                <div class="bi-recomendaciones-title">Recomendaciones</div>
                @foreach($analisisBI['recomendaciones'] as $recomendacion)
                <div class="bi-recomendacion-item">{{ $recomendacion }}</div>
                @endforeach
            </div>
            @endif

            @if(!empty($analisisBI['tendencias']))
            <div class="bi-tendencias">
                <strong>Tendencia:</strong> {{ $analisisBI['tendencias'] }}
            </div>
            @endif
        </div>
        @endif

        <!-- Gráficas de distribución -->
        @if(!empty($chartAreaSvg) || !empty($chartEstadoSvg))
        <div class="charts-section">
            <div class="section-title">Distribución de Gestiones</div>
            <div class="charts-grid">
                @if(!empty($chartAreaSvg))
                <div class="chart-container">
                    <div class="chart-box">
                        <div class="chart-title">Por Área de Práctica</div>
                        <div class="chart-svg">{!! $chartAreaSvg !!}</div>
                    </div>
                </div>
                @endif
                @if(!empty($chartEstadoSvg))
                <div class="chart-container">
                    <div class="chart-box">
                        <div class="chart-title">Por Estado</div>
                        <div class="chart-svg">{!! $chartEstadoSvg !!}</div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Gráfica mensual -->
        @if(!empty($chartMesSvg))
        <div class="bar-chart-section">
            <div class="section-title">Evolución Mensual</div>
            <div class="bar-chart-box">
                <div class="chart-title">Gestiones por Mes</div>
                <div class="chart-svg">{!! $chartMesSvg !!}</div>
            </div>
        </div>
        @endif

        <!-- Resumen por Área -->
        @if(count($resumenPorArea) > 0)
        <div class="section-title">Gestiones por Área de Práctica</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Área de Práctica</th>
                    <th style="width: 25%; text-align: center;">Cantidad</th>
                    <th style="width: 25%; text-align: center;">Tiempo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorArea as $item)
                <tr>
                    <td>
                        <span style="display: inline-block; width: 10px; height: 10px; background: {{ $item['color'] }}; border-radius: 2px; margin-right: 8px;"></span>
                        {{ $item['area'] }}
                    </td>
                    <td class="number">{{ $item['cantidad'] }}</td>
                    <td class="time">{{ $item['tiempo_formateado'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Resumen por Tipo -->
        @if(count($resumenPorTipo) > 0)
        <div class="section-title">Gestiones por Tipo</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Tipo de Gestión</th>
                    <th style="width: 25%; text-align: center;">Cantidad</th>
                    <th style="width: 25%; text-align: center;">Tiempo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorTipo as $item)
                <tr>
                    <td>
                        <span style="display: inline-block; width: 10px; height: 10px; background: {{ $item['color'] }}; border-radius: 2px; margin-right: 8px;"></span>
                        {{ $item['tipo'] }}
                    </td>
                    <td class="number">{{ $item['cantidad'] }}</td>
                    <td class="time">{{ $item['tiempo_formateado'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Resumen por Estado -->
        @if(count($resumenPorEstado) > 0)
        <div class="section-title">Gestiones por Estado</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Estado</th>
                    <th style="width: 40%; text-align: center;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorEstado as $item)
                <tr>
                    <td>
                        <span style="display: inline-block; width: 10px; height: 10px; background: {{ $item['color'] }}; border-radius: 50%; margin-right: 8px;"></span>
                        {{ $item['estado'] }}
                    </td>
                    <td class="number">{{ $item['cantidad'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Resumen por Mes -->
        @if($resumenPorMes && count($resumenPorMes) > 0)
        <div class="section-title">Gestiones por Mes</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Mes</th>
                    <th style="width: 30%; text-align: center;">Cantidad</th>
                    <th style="width: 30%; text-align: center;">Tiempo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorMes as $item)
                <tr>
                    <td>{{ $item['mes'] }}</td>
                    <td class="number">{{ $item['cantidad'] }}</td>
                    <td class="time">{{ $item['tiempo_formateado'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($informes->count() > 0)
        <div class="page-break"></div>

        <!-- Página de Detalle -->
        <div class="header" style="margin-top: 0;">
            <div class="header-content">
                <div class="header-left">
                    <h1>Detalle de Gestiones</h1>
                    <div class="subtitle">{{ $empresa->razon_social }} | {{ $anio }} - {{ $mes }}</div>
                </div>
                <div class="header-right">
                    <div class="report-number">
                        <strong>{{ $totalGestiones }}</strong>
                        Registros
                    </div>
                </div>
            </div>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th style="width: 10%;">Mes</th>
                    <th style="width: 15%;">Área</th>
                    <th style="width: 15%;">Tipo</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 12%;">Estado</th>
                    <th style="width: 10%;">Tiempo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($informes as $index => $informe)
                <tr>
                    <td style="text-align: center; font-weight: 600; color: #64748b;">{{ $index + 1 }}</td>
                    <td>{{ $informe->mes_texto }}</td>
                    <td>{{ $informe->area_practica_texto }}</td>
                    <td>{{ $informe->tipo_gestion_texto }}</td>
                    <td class="descripcion">{{ Str::limit($informe->descripcion, 120) }}</td>
                    <td>
                        <span class="estado-badge estado-{{ $informe->estado }}">
                            {{ $informe->estado_texto }}
                        </span>
                    </td>
                    <td style="text-align: center; font-weight: 500; color: #059669;">{{ $informe->tiempo_formateado }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Oportunidades de Mejora (si existen) -->
        @if(isset($analisisBI) && !empty($analisisBI['oportunidades_mejora']))
        <div class="bi-recomendaciones" style="margin-top: 20px;">
            <div class="bi-recomendaciones-title" style="color: #1e40af;">Oportunidades de Mejora Identificadas</div>
            @foreach($analisisBI['oportunidades_mejora'] as $oportunidad)
            <div class="bi-recomendacion-item" style="color: #1e40af;">{{ $oportunidad }}</div>
            @endforeach
        </div>
        @endif

        <!-- Área más demandante -->
        @if(isset($metricasBI))
        <div style="margin-top: 15px; padding: 10px 15px; background: #f1f5f9; border-radius: 6px; font-size: 8px;">
            <strong>Área más demandante:</strong> {{ $metricasBI['area_mas_demandante'] }} ({{ $metricasBI['area_mas_demandante_cantidad'] }} gestiones)
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Tipo más frecuente:</strong> {{ $metricasBI['tipo_mas_frecuente'] }} ({{ $metricasBI['tipo_mas_frecuente_cantidad'] }} gestiones)
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Tendencia:</strong> <span style="text-transform: capitalize;">{{ $metricasBI['tendencia_mensual'] }}</span>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">CES LEGAL</div>
            <p>Documento generado automáticamente con análisis de Business Intelligence - {{ $fechaGeneracion }}</p>
            <p>Este documento es de carácter informativo y confidencial.</p>
        </div>
    </div>
</body>
</html>
