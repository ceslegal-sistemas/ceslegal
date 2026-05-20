<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Ejecutivo</title>
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.5;
            color: #1f2937;
            background: #fff;
        }

        /* Header */
        .header {
            background: #b91c1c;
            color: white;
            padding: 18px 22px;
            margin: -18px -14px 18px -14px;
        }
        .header-tbl { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: middle; width: 72%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 28%; }
        .header h1 { font-size: 17px; font-weight: 700; margin-bottom: 3px; letter-spacing: 0.4px; }
        .header .version-badge {
            display: inline-block;
            background: rgba(255,255,255,0.18);
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .header .score-box {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            padding: 10px 14px;
            border-radius: 5px;
            text-align: center;
        }
        .header .score-num { font-size: 28px; font-weight: 700; line-height: 1; }
        .header .score-label { font-size: 8px; opacity: 0.85; margin-top: 2px; }

        /* Empresa card */
        .empresa-card {
            display: table;
            width: 100%;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 5px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .empresa-cell { display: table-cell; vertical-align: top; width: 50%; }
        .empresa-label { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .empresa-value { font-size: 9.5px; font-weight: 600; color: #111827; margin-top: 1px; }

        /* Sección títulos */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #b91c1c;
            border-bottom: 2px solid #fecaca;
            padding-bottom: 4px;
            margin: 14px 0 8px 0;
        }

        /* KPI row */
        .kpi-tbl { display: table; width: 100%; border-spacing: 6px 0; }
        .kpi-cell {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px 6px;
            border-radius: 5px;
            vertical-align: middle;
        }
        .kpi-cell.alto   { background: #fee2e2; border: 1px solid #fca5a5; }
        .kpi-cell.medio  { background: #fef9c3; border: 1px solid #fde047; }
        .kpi-cell.bajo   { background: #dbeafe; border: 1px solid #93c5fd; }
        .kpi-cell.ok     { background: #dcfce7; border: 1px solid #86efac; }
        .kpi-num  { font-size: 22px; font-weight: 700; line-height: 1; }
        .kpi-cell.alto   .kpi-num { color: #dc2626; }
        .kpi-cell.medio  .kpi-num { color: #ca8a04; }
        .kpi-cell.bajo   .kpi-num { color: #1d4ed8; }
        .kpi-cell.ok     .kpi-num { color: #16a34a; }
        .kpi-label { font-size: 7.5px; font-weight: 600; margin-top: 3px; color: #374151; }

        /* Resumen */
        .resumen-box {
            background: #f9fafb;
            border-left: 3px solid #b91c1c;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-size: 8.5px;
            line-height: 1.6;
            color: #374151;
        }

        /* Tabla GAP */
        .gap-table { width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 14px; }
        .gap-table th {
            background: #b91c1c;
            color: white;
            padding: 5px 7px;
            text-align: left;
            font-weight: 600;
            font-size: 7.5px;
        }
        .gap-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .gap-table tr:nth-child(even) td { background: #fef2f2; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: 700;
        }
        .badge-alto   { background: #fee2e2; color: #b91c1c; }
        .badge-medio  { background: #fef9c3; color: #92400e; }
        .badge-bajo   { background: #dbeafe; color: #1e40af; }
        .badge-ok     { background: #dcfce7; color: #166534; }

        /* Plan de acciones */
        .accion-row { display: table; width: 100%; margin-bottom: 5px; }
        .accion-num { display: table-cell; width: 22px; font-weight: 700; color: #b91c1c; vertical-align: top; font-size: 8.5px; }
        .accion-text { display: table-cell; vertical-align: top; font-size: 8px; color: #374151; line-height: 1.5; }

        /* Footer */
        .footer {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 7px;
            display: table;
            width: 100%;
        }
        .footer-left { display: table-cell; font-size: 7px; color: #9ca3af; vertical-align: bottom; }
        .footer-right { display: table-cell; text-align: right; font-size: 7px; color: #9ca3af; vertical-align: bottom; }
        .confidential {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 7px;
            color: #b91c1c;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
@php
    $fechaAuditoria = $auditoria->updated_at?->format('d/m/Y') ?? now()->format('d/m/Y');
    $score          = $auditoria->score ?? 0;
    $resumen        = $auditoria->resumen_general ?? '';

    $conteos = [
        'alto'    => count($gapsAgrupados['alto']),
        'medio'   => count($gapsAgrupados['medio']),
        'bajo'    => count($gapsAgrupados['bajo']),
        'sin_gap' => count($gapsAgrupados['sin_gap']),
    ];

    // Tabla de todos los gaps ordenados por riesgo
    $todosLosGaps = array_merge(
        $gapsAgrupados['alto'],
        $gapsAgrupados['medio'],
        $gapsAgrupados['bajo']
    );

    // Top 10 acciones prioritarias (recomendación 1 de cada sección con gap, ordenadas por riesgo)
    $acciones = [];
    foreach ($todosLosGaps as $seccion) {
        if (!empty($seccion['recomendaciones'])) {
            $acciones[] = [
                'seccion' => $seccion['titulo'],
                'accion'  => $seccion['recomendaciones'][0],
            ];
            if (count($acciones) >= 10) break;
        }
    }
@endphp

<!-- Header -->
<div class="header">
    <div class="header-tbl">
        <div class="header-left">
            <h1>ANÁLISIS GAP DE CUMPLIMIENTO NORMATIVO</h1>
            <div style="font-size:9px; opacity:0.9; margin-top:4px;">{{ $empresa->razon_social }}</div>
            <div class="version-badge">VERSIÓN EJECUTIVA</div>
        </div>
        <div class="header-right">
            <div class="score-box">
                <div class="score-num">{{ $score }}</div>
                <div class="score-label">SCORE / 100</div>
            </div>
        </div>
    </div>
</div>

<!-- Datos empresa -->
<div class="empresa-card">
    <div class="empresa-cell">
        <div class="empresa-label">Empresa</div>
        <div class="empresa-value">{{ $empresa->razon_social }}</div>
        @if($empresa->nit)
        <div style="font-size:8px; color:#6b7280; margin-top:2px;">NIT: {{ $empresa->nit }}</div>
        @endif
    </div>
    <div class="empresa-cell">
        <div class="empresa-label">Fecha de Auditoría</div>
        <div class="empresa-value">{{ $fechaAuditoria }}</div>
        <div style="font-size:8px; color:#6b7280; margin-top:2px;">Auditoría #{{ $auditoria->id }}</div>
    </div>
</div>

<!-- KPIs -->
<div class="section-title">Resumen de Brechas Identificadas</div>
<div class="kpi-tbl">
    <div class="kpi-cell alto">
        <div class="kpi-num">{{ $conteos['alto'] }}</div>
        <div class="kpi-label">Riesgo Alto</div>
    </div>
    <div class="kpi-cell medio">
        <div class="kpi-num">{{ $conteos['medio'] }}</div>
        <div class="kpi-label">Riesgo Medio</div>
    </div>
    <div class="kpi-cell bajo">
        <div class="kpi-num">{{ $conteos['bajo'] }}</div>
        <div class="kpi-label">Riesgo Bajo</div>
    </div>
    <div class="kpi-cell ok">
        <div class="kpi-num">{{ $conteos['sin_gap'] }}</div>
        <div class="kpi-label">Sin Brecha</div>
    </div>
</div>

<!-- Resumen ejecutivo -->
@if($resumen)
<div class="section-title" style="margin-top:14px;">Resumen Ejecutivo</div>
<div class="resumen-box">{{ $resumen }}</div>
@endif

<!-- Tabla de gaps -->
@php $hayGaps = !empty($todosLosGaps); @endphp
@if($hayGaps)
<div class="section-title">Tabla de Brechas por Sección</div>
<table class="gap-table">
    <thead>
        <tr>
            <th style="width:28%">Sección</th>
            <th style="width:8%">Score</th>
            <th style="width:12%">Riesgo</th>
            <th style="width:52%">Recomendación Principal</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'] as $nivel => $etiqueta)
            @foreach($gapsAgrupados[$nivel] as $seccion)
            <tr>
                <td>{{ $seccion['titulo'] }}</td>
                <td style="text-align:center;font-weight:600">{{ $seccion['score'] }}/100</td>
                <td><span class="badge badge-{{ $nivel }}">{{ $etiqueta }}</span></td>
                <td>{{ $seccion['recomendaciones'][0] ?? '—' }}</td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@else
<div class="resumen-box" style="border-left-color:#16a34a;">
    No se identificaron brechas de cumplimiento. El RIT cumple con todos los requisitos normativos evaluados.
</div>
@endif

<!-- Plan de acciones -->
@if(!empty($acciones))
<div class="section-title">Plan de Acciones Prioritarias</div>
@foreach($acciones as $i => $item)
<div class="accion-row">
    <div class="accion-num">{{ $i + 1 }}.</div>
    <div class="accion-text">
        <strong>{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}
    </div>
</div>
@endforeach
@endif

<!-- Confidencial -->
<div style="margin-top:16px;">
    <div class="confidential">DOCUMENTO CONFIDENCIAL — Uso exclusivo de {{ $empresa->razon_social }}</div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="footer-left">
        Generado por CES Legal · {{ now()->format('d/m/Y H:i') }}
    </div>
    <div class="footer-right">
        Análisis GAP de Cumplimiento Normativo
    </div>
</div>
</body>
</html>
