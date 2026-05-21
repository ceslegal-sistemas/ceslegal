<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Ejecutivo — CES Legal</title>
    <style>
        @page { size: A4; margin: 18mm 14mm 14mm 14mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; line-height: 1.55; color: #1e293b; }

        /* ═══ HEADER ═══════════════════════════════════════════ */
        .hdr {
            background: #0f172a;
            color: #fff;
            padding: 15px 16px 13px;
            margin: -18px -14px 14px -14px;
            border-bottom: 3px solid #991b1b;
        }
        .hdr-tbl { display: table; width: 100%; }
        .hdr-left { display: table-cell; width: 68%; vertical-align: middle; padding-right: 14px; }
        .hdr-right { display: table-cell; width: 32%; vertical-align: middle; text-align: right; }

        .ces-eyebrow {
            font-size: 6.5pt; font-weight: 700; letter-spacing: 2.5px;
            text-transform: uppercase; color: #fca5a5; margin-bottom: 5px;
        }
        .hdr-title { font-size: 14.5pt; font-weight: 700; color: #f8fafc; line-height: 1.15; }
        .hdr-sub { font-size: 7.5pt; color: #94a3b8; margin-top: 4px; line-height: 1.45; }
        .hdr-badge {
            display: inline-block; margin-top: 7px;
            background: #991b1b; color: #fef2f2;
            font-size: 6.5pt; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; padding: 2px 8px; border-radius: 2px;
        }
        .score-box {
            display: inline-block; border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 5px; padding: 9px 14px; text-align: center;
            background: rgba(255,255,255,0.06);
        }
        .score-num { font-size: 27pt; font-weight: 700; line-height: 1; }
        .score-denom { font-size: 9pt; color: rgba(255,255,255,0.45); font-weight: 400; }
        .score-lbl { font-size: 6.5pt; color: rgba(255,255,255,0.45); margin-top: 1px; letter-spacing: 0.5px; }
        .score-status { font-size: 7pt; font-weight: 700; margin-top: 5px; }

        /* ═══ METADATA STRIP ════════════════════════════════════ */
        .meta-strip {
            display: table; width: 100%;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }
        .meta-cell {
            display: table-cell; padding: 8px 10px;
            border-right: 1px solid #e2e8f0; vertical-align: top;
        }
        .meta-cell:last-child { border-right: none; }
        .meta-lbl {
            font-size: 6.5pt; font-weight: 700; letter-spacing: 0.6px;
            text-transform: uppercase; color: #94a3b8; margin-bottom: 2px;
        }
        .meta-val { font-size: 8.5pt; font-weight: 700; color: #0f172a; }
        .meta-sub { font-size: 7pt; color: #64748b; margin-top: 1px; }

        /* ═══ SECTION HEADER ════════════════════════════════════ */
        .sec-hdr { display: table; width: 100%; margin: 14px 0 8px; }
        .sec-num-c { display: table-cell; width: 26px; vertical-align: top; padding-top: 1px; }
        .sec-num {
            width: 20px; height: 20px; background: #991b1b; color: #fff;
            font-size: 7.5pt; font-weight: 700; text-align: center;
            padding-top: 3px; border-radius: 3px; display: block;
        }
        .sec-txt-c {
            display: table-cell; vertical-align: middle;
            border-bottom: 1.5px solid #e2e8f0; padding-bottom: 4px;
        }
        .sec-title { font-size: 10.5pt; font-weight: 700; color: #0f172a; }

        /* ═══ KPI CARDS ═════════════════════════════════════════ */
        .kpi-row { display: table; width: 100%; margin-bottom: 8px; }
        .kpi-col { display: table-cell; width: 25%; padding: 0 3px; vertical-align: top; }
        .kpi-col:first-child { padding-left: 0; }
        .kpi-col:last-child { padding-right: 0; }
        .kpi-card { border-top: 3px solid; border: 1px solid; padding: 9px 7px; text-align: center; border-radius: 3px; }
        .kpi-alto  { border-color: #fca5a5; border-top-color: #dc2626; background: #fef2f2; }
        .kpi-medio { border-color: #fde68a; border-top-color: #d97706; background: #fffbeb; }
        .kpi-bajo  { border-color: #bfdbfe; border-top-color: #2563eb; background: #eff6ff; }
        .kpi-ok    { border-color: #bbf7d0; border-top-color: #16a34a; background: #f0fdf4; }
        .kpi-num { font-size: 22pt; font-weight: 700; line-height: 1.1; }
        .kpi-alto  .kpi-num { color: #dc2626; }
        .kpi-medio .kpi-num { color: #d97706; }
        .kpi-bajo  .kpi-num { color: #2563eb; }
        .kpi-ok    .kpi-num { color: #16a34a; }
        .kpi-lbl { font-size: 7pt; font-weight: 700; color: #475569; margin-top: 3px; }

        /* ═══ SCORE BAR ═════════════════════════════════════════ */
        .prog-wrap { margin: 0 0 12px; }
        .prog-track { height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .prog-fill { height: 5px; border-radius: 3px; }
        .prog-lbl { font-size: 7pt; color: #64748b; margin-top: 3px; }

        /* ═══ RESUMEN ════════════════════════════════════════════ */
        .resumen-box {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-left: 3px solid #991b1b;
            padding: 9px 11px; font-size: 8.5pt;
            line-height: 1.65; color: #374151; margin-bottom: 12px;
        }

        /* ═══ GAP TABLE ══════════════════════════════════════════ */
        .gap-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .gap-tbl thead tr { background: #1e293b; }
        .gap-tbl th {
            color: #f1f5f9; font-size: 7.5pt; font-weight: 700;
            padding: 5px 8px; text-align: left;
        }
        .gap-tbl td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 8pt; }
        .gap-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
        .td-alto  { border-left: 3px solid #dc2626; }
        .td-medio { border-left: 3px solid #d97706; }
        .td-bajo  { border-left: 3px solid #2563eb; }

        .badge {
            display: inline-block; padding: 2px 6px; border-radius: 3px;
            font-size: 7pt; font-weight: 700; border: 1px solid;
        }
        .badge-alto  { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
        .badge-medio { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .badge-bajo  { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .badge-ok    { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }

        /* ═══ PLAN ACCIONES ══════════════════════════════════════ */
        .plan-row { display: table; width: 100%; margin-bottom: 6px; }
        .plan-num-c { display: table-cell; width: 24px; vertical-align: top; }
        .plan-num {
            display: block; width: 18px; height: 18px;
            background: #1e293b; color: #fff;
            font-size: 7.5pt; font-weight: 700; text-align: center;
            padding-top: 2px; border-radius: 9px;
        }
        .plan-text { display: table-cell; vertical-align: top; font-size: 8pt; color: #374151; line-height: 1.55; }

        /* ═══ SELLO Y FOOTER ═════════════════════════════════════ */
        .sello {
            border: 1px solid #fecaca; background: #fef2f2;
            text-align: center; padding: 5px 10px; margin: 14px 0 8px;
            font-size: 7pt; font-weight: 700; color: #991b1b; letter-spacing: 0.7px;
        }
        .footer-line { height: 1px; background: #e2e8f0; margin-bottom: 5px; }
        .footer-tbl { display: table; width: 100%; }
        .footer-l { display: table-cell; font-size: 7pt; color: #94a3b8; }
        .footer-c { display: table-cell; text-align: center; font-size: 7pt; color: #94a3b8; }
        .footer-r { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; }
    </style>
</head>
<body>
@php
    $fechaCorta = $auditoria->updated_at?->format('d/m/Y') ?? now()->format('d/m/Y');
    $score      = $auditoria->score ?? 0;
    $resumen    = $auditoria->resumen_general ?? '';

    $conteos = [
        'alto'    => count($gapsAgrupados['alto']),
        'medio'   => count($gapsAgrupados['medio']),
        'bajo'    => count($gapsAgrupados['bajo']),
        'sin_gap' => count($gapsAgrupados['sin_gap']),
    ];

    $todosLosGaps = array_merge($gapsAgrupados['alto'], $gapsAgrupados['medio'], $gapsAgrupados['bajo']);

    $acciones = [];
    foreach ($todosLosGaps as $sec) {
        if (!empty($sec['recomendaciones'])) {
            $acciones[] = ['seccion' => $sec['titulo'], 'accion' => $sec['recomendaciones'][0]];
            if (count($acciones) >= 10) break;
        }
    }

    $scoreColor = $score >= 70 ? '#16a34a' : ($score >= 40 ? '#d97706' : '#dc2626');
    $scoreText  = $score >= 70 ? 'Cumplimiento Satisfactorio' : ($score >= 40 ? 'Cumplimiento Parcial — Requiere Mejoras' : 'Riesgo Jurídico Alto');
    $ref = 'GAP-' . str_pad($auditoria->id, 5, '0', STR_PAD_LEFT);
@endphp

{{-- ═══ HEADER ══════════════════════════════════════════════ --}}
<div class="hdr">
    <div class="hdr-tbl">
        <div class="hdr-left">
            <div class="ces-eyebrow">CES Legal &nbsp;·&nbsp; Consultoría en Derecho Laboral Colombiano</div>
            <div class="hdr-title">Análisis GAP de Cumplimiento Normativo</div>
            <div class="hdr-sub">Reglamento Interno de Trabajo &nbsp;·&nbsp; Evaluación contra CST, Ley 1010/2006 y Ley 2365/2024</div>
            <div class="hdr-badge">Versión Ejecutiva</div>
        </div>
        <div class="hdr-right">
            <div class="score-box">
                <div class="score-num" style="color:{{ $scoreColor }}">{{ $score }}<span class="score-denom">/100</span></div>
                <div class="score-lbl">PUNTUACIÓN DE CUMPLIMIENTO</div>
                <div class="score-status" style="color:{{ $scoreColor }}">{{ $scoreText }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ METADATA ══════════════════════════════════════════════ --}}
<div class="meta-strip">
    <div class="meta-cell" style="width:42%">
        <div class="meta-lbl">Empresa Auditada</div>
        <div class="meta-val">{{ $empresa->razon_social }}</div>
        @if($empresa->nit)<div class="meta-sub">NIT: {{ $empresa->nit }}</div>@endif
    </div>
    <div class="meta-cell" style="width:20%">
        <div class="meta-lbl">Fecha de Auditoría</div>
        <div class="meta-val">{{ $fechaCorta }}</div>
    </div>
    <div class="meta-cell" style="width:20%">
        <div class="meta-lbl">Referencia</div>
        <div class="meta-val">{{ $ref }}</div>
    </div>
    <div class="meta-cell" style="width:18%">
        <div class="meta-lbl">Tipo de Reporte</div>
        <div class="meta-val">Ejecutivo</div>
        <div class="meta-sub">Confidencial</div>
    </div>
</div>

{{-- ═══ I. BRECHAS IDENTIFICADAS ══════════════════════════════ --}}
<div class="sec-hdr">
    <div class="sec-num-c"><div class="sec-num">I</div></div>
    <div class="sec-txt-c"><span class="sec-title">Resumen de Brechas Identificadas</span></div>
</div>
<div class="kpi-row">
    <div class="kpi-col"><div class="kpi-card kpi-alto"><div class="kpi-num">{{ $conteos['alto'] }}</div><div class="kpi-lbl">Riesgo Alto</div></div></div>
    <div class="kpi-col"><div class="kpi-card kpi-medio"><div class="kpi-num">{{ $conteos['medio'] }}</div><div class="kpi-lbl">Riesgo Medio</div></div></div>
    <div class="kpi-col"><div class="kpi-card kpi-bajo"><div class="kpi-num">{{ $conteos['bajo'] }}</div><div class="kpi-lbl">Riesgo Bajo</div></div></div>
    <div class="kpi-col"><div class="kpi-card kpi-ok"><div class="kpi-num">{{ $conteos['sin_gap'] }}</div><div class="kpi-lbl">Sin Brecha</div></div></div>
</div>
<div class="prog-wrap">
    <div class="prog-track"><div class="prog-fill" style="width:{{ $score }}%; background:{{ $scoreColor }}"></div></div>
    <div class="prog-lbl">Índice de Cumplimiento: {{ $score }}/100 &nbsp;·&nbsp; {{ $scoreText }}</div>
</div>

{{-- ═══ II. RESUMEN EJECUTIVO ══════════════════════════════════ --}}
@if($resumen)
<div class="sec-hdr">
    <div class="sec-num-c"><div class="sec-num">II</div></div>
    <div class="sec-txt-c"><span class="sec-title">Resumen Ejecutivo</span></div>
</div>
<div class="resumen-box">{{ $resumen }}</div>
@endif

{{-- ═══ III. TABLA DE BRECHAS ══════════════════════════════════ --}}
@php $hayGaps = !empty($todosLosGaps); @endphp
@if($hayGaps)
<div class="sec-hdr">
    <div class="sec-num-c"><div class="sec-num">III</div></div>
    <div class="sec-txt-c"><span class="sec-title">Tabla de Brechas por Sección</span></div>
</div>
<table class="gap-tbl">
    <thead>
        <tr>
            <th style="width:28%">Sección del RIT</th>
            <th style="width:9%; text-align:center">Score</th>
            <th style="width:12%">Nivel de Riesgo</th>
            <th>Recomendación Principal</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['alto'=>'Alto','medio'=>'Medio','bajo'=>'Bajo'] as $nivel => $etiqueta)
            @foreach($gapsAgrupados[$nivel] as $sec)
            <tr>
                <td class="td-{{ $nivel }}" style="font-weight:600">{{ $sec['titulo'] }}</td>
                <td style="text-align:center; font-weight:700">{{ $sec['score'] }}/100</td>
                <td><span class="badge badge-{{ $nivel }}">{{ $etiqueta }}</span></td>
                <td>{{ $sec['recomendaciones'][0] ?? '—' }}</td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@else
<div class="resumen-box" style="border-left-color:#16a34a; background:#f0fdf4; border-color:#bbf7d0; color:#166534">
    No se identificaron brechas de cumplimiento. El Reglamento Interno de Trabajo cumple con todos los requisitos normativos evaluados.
</div>
@endif

{{-- ═══ IV. PLAN DE ACCIONES ═══════════════════════════════════ --}}
@if(!empty($acciones))
<div class="sec-hdr">
    <div class="sec-num-c"><div class="sec-num">IV</div></div>
    <div class="sec-txt-c"><span class="sec-title">Plan de Acciones Prioritarias</span></div>
</div>
@foreach($acciones as $i => $item)
<div class="plan-row">
    <div class="plan-num-c"><div class="plan-num">{{ $i + 1 }}</div></div>
    <div class="plan-text"><strong>{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}</div>
</div>
@endforeach
@endif

{{-- ═══ SELLO + FOOTER ══════════════════════════════════════════ --}}
<div class="sello">DOCUMENTO CONFIDENCIAL — Uso exclusivo de {{ $empresa->razon_social }} y CES Legal</div>
<div class="footer-line"></div>
<div class="footer-tbl">
    <div class="footer-l">CES Legal &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}</div>
    <div class="footer-c">{{ $ref }}</div>
    <div class="footer-r">Análisis GAP de Cumplimiento Normativo</div>
</div>

</body>
</html>
