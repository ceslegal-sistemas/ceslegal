<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Ejecutivo</title>
    <style>
        /* ══ Página ═══════════════════════════════════════════════════════════════ */
        @page {
            size: letter portrait;
            margin: 2.5cm;
        }
        @page cover { margin: 0; }

        /* ══ Running header ══════════════════════════════════════════════════════ */
        .hdr {
            position: fixed;
            top: -2.2cm;
            left: 2.5cm; right: 2.5cm;
            height: 1.5cm;
            border-bottom: 0.5pt solid #c9a84c;
        }
        .hdr table { width: 100%; height: 100%; border-collapse: collapse; }
        .hdr td { vertical-align: bottom; padding-bottom: 4pt; }
        .hdr .hl {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt; color: #5a6a7a;
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .hdr .hr {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt; color: #5a6a7a;
            text-align: right;
        }

        /* ══ Running footer ══════════════════════════════════════════════════════ */
        .ftr {
            position: fixed;
            bottom: -2.1cm;
            left: 2.5cm; right: 2.5cm;
            height: 1.4cm;
            border-top: 0.5pt solid #e2e5ea;
        }
        .ftr table { width: 100%; border-collapse: collapse; }
        .ftr td {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt; color: #9ca3af;
            padding-top: 5pt; vertical-align: top;
        }

        /* ══ Base ═════════════════════════════════════════════════════════════════ */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt; line-height: 1.6;
            color: #111827;
        }

        /* ══ Portada ══════════════════════════════════════════════════════════════ */
        .cover { page: cover; page-break-after: always; }
        .cv-top {
            background: #0d1f3c;
            padding: 4cm 2.5cm 2.8cm 2.5cm;
            text-align: center;
        }
        .cv-eyebrow {
            font-size: 7pt; letter-spacing: 0.22em; text-transform: uppercase;
            color: #6b8cad; margin-bottom: 1.8cm;
        }
        .cv-pretitle {
            font-size: 8pt; font-weight: 700; letter-spacing: 0.18em;
            text-transform: uppercase; color: #c9a84c; margin-bottom: 0.5cm;
        }
        .cv-title {
            font-size: 24pt; font-weight: 700;
            color: #ffffff; line-height: 1.18; letter-spacing: 0.01em;
            margin-bottom: 0.4cm;
        }
        .cv-rule { display: block; width: 3cm; height: 2.5pt; background: #c9a84c; margin: 1cm auto; }
        .cv-empresa {
            font-size: 15pt; font-weight: 700;
            color: #c9a84c; letter-spacing: 0.02em; margin-bottom: 0.3cm;
        }
        .cv-nit { font-size: 9pt; color: #7e9bb5; letter-spacing: 0.04em; }
        .cv-score-wrap {
            margin: 1cm auto 0;
            border: 2.5pt solid #c9a84c;
            display: inline-block;
            padding: 0.5cm 1.2cm;
            background: rgba(12,31,60,0.6);
        }
        .cv-score-num { font-size: 36pt; font-weight: 700; line-height: 1; }
        .cv-score-lbl { font-size: 7pt; color: #94a3b8; margin-top: 3pt; letter-spacing: 0.1em; }
        .cv-score-txt { font-size: 8pt; font-weight: 700; margin-top: 4pt; }
        .cv-badge {
            display: inline-block;
            font-size: 6.5pt; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            background: #991b1b; color: #fef2f2;
            padding: 3pt 8pt; margin-top: 1.2cm;
        }
        .cv-bottom {
            background: #ffffff;
            border-top: 3.5pt solid #c9a84c;
            padding: 1.2cm 2.5cm;
        }
        .cv-meta table { width: 100%; border-collapse: collapse; }
        .cv-meta td {
            text-align: center; vertical-align: middle;
            padding: 0 0.8cm;
        }
        .cv-meta .sep {
            width: 1pt; background: #e5e7eb;
            padding: 0;
        }
        .cv-meta .lbl {
            display: block; font-size: 6pt;
            letter-spacing: 0.16em; text-transform: uppercase;
            color: #9ca3af; margin-bottom: 3pt;
        }
        .cv-meta .val {
            display: block; font-size: 9.5pt;
            font-weight: 700; color: #0d1f3c;
        }

        /* ══ Sección heading ══════════════════════════════════════════════════════ */
        .sh {
            background: #0d1f3c;
            padding: 7pt 12pt 8pt 12pt;
            margin-top: 18pt; margin-bottom: 10pt;
            page-break-inside: avoid; page-break-after: avoid;
        }
        .sh-num {
            display: block; font-size: 6.5pt; font-weight: 700;
            letter-spacing: 0.18em; text-transform: uppercase;
            color: #c9a84c; margin-bottom: 2pt;
        }
        .sh-tit {
            display: block; font-size: 10.5pt; font-weight: 700;
            color: #ffffff; letter-spacing: 0.01em;
        }

        /* ══ Resumen box ══════════════════════════════════════════════════════════ */
        .rb {
            background: #f9fafb;
            border-top: 1pt solid #e5e7eb;
            border-right: 1pt solid #e5e7eb;
            border-bottom: 1pt solid #e5e7eb;
            border-left: 3pt solid #c9a84c;
            padding: 9pt 11pt; font-size: 8.5pt;
            line-height: 1.65; color: #374151;
            margin-bottom: 10pt;
        }

        /* ══ KPI cards ════════════════════════════════════════════════════════════ */
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .kpi-table td { padding: 4pt; vertical-align: top; width: 25%; }

        /* ══ Barra de cumplimiento ════════════════════════════════════════════════ */
        .bar-wrap { margin-bottom: 12pt; }
        .bar-track { width: 100%; background: #e5e7eb; height: 5pt; border-collapse: collapse; }
        .bar-track td { height: 5pt; font-size: 0; line-height: 0; }
        .bar-lbl { font-size: 7pt; color: #6b7280; margin-top: 3pt; }

        /* ══ Tabla de brechas ════════════════════════════════════════════════════ */
        .gap-table {
            width: 100%; border-collapse: collapse;
            font-size: 8pt; margin-bottom: 12pt;
        }
        .gap-table th {
            background: #0d1f3c; color: #f1f5f9;
            padding: 5pt 7pt; text-align: left; font-size: 7.5pt;
        }
        .gap-table td {
            padding: 5pt 7pt;
            border-bottom: 1pt solid #e5e7eb;
        }

        /* ══ Badge riesgo ═════════════════════════════════════════════════════════ */
        .ba { background: #fee2e2; color: #991b1b; border: 1pt solid #fca5a5; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bm { background: #fef9c3; color: #92400e; border: 1pt solid #fde047; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bb { background: #dbeafe; color: #1e40af; border: 1pt solid #93c5fd; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bo { background: #dcfce7; color: #166534; border: 1pt solid #86efac; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }

        /* ══ Acciones ═════════════════════════════════════════════════════════════ */
        .accion-table { width: 100%; border-collapse: collapse; margin-bottom: 5pt; }
        .accion-num {
            width: 20pt; vertical-align: top; padding-top: 1pt;
        }
        .accion-num-inner {
            background: #0d1f3c; color: #c9a84c;
            width: 16pt; height: 16pt;
            text-align: center; font-size: 7pt; font-weight: 700;
            padding-top: 3pt;
        }
        .accion-txt { font-size: 8pt; color: #374151; line-height: 1.55; }

        /* ══ Footer sello ════════════════════════════════════════════════════════ */
        .sello {
            background: #fef2f2; border: 1pt solid #fecaca;
            text-align: center; padding: 5pt 10pt; margin: 14pt 0 8pt;
            font-size: 7pt; font-weight: 700; color: #991b1b;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body>
@php
    $fechaCorta = $auditoria->updated_at?->format('d/m/Y') ?? now()->format('d/m/Y');
    $fechaHora  = $auditoria->updated_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
    $score      = $auditoria->score ?? 0;
    $resumen    = $auditoria->resumen_general ?? '';
    $ref        = 'GAP-' . str_pad($auditoria->id, 5, '0', STR_PAD_LEFT);

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
    $scoreText  = $score >= 70 ? 'Cumplimiento Satisfactorio' : ($score >= 40 ? 'Requiere Mejoras' : 'Riesgo Jurídico Alto');
@endphp

{{-- Encabezado corrido --}}
<div class="hdr">
    <table><tr>
        <td class="hl">{{ $empresa->razon_social }}</td>
        <td class="hr">Análisis GAP · Versión Ejecutiva</td>
    </tr></table>
</div>

{{-- Pie corrido --}}
<div class="ftr">
    <table><tr>
        <td>CES Legal · Derecho Laboral Colombiano</td>
        <td style="text-align:center">{{ $ref }}</td>
        <td style="text-align:right">{{ $fechaCorta }}</td>
    </tr></table>
</div>

{{-- ══ PORTADA ══ --}}
<div class="cover">
    <div class="cv-top">
        <div class="cv-eyebrow">República de Colombia · CES Legal · Derecho Laboral</div>
        <div class="cv-pretitle">Informe de Cumplimiento Normativo</div>
        <div class="cv-title">ANÁLISIS GAP<br>DE CUMPLIMIENTO<br>NORMATIVO</div>
        <span class="cv-rule"></span>
        <div class="cv-empresa">{{ $empresa->razon_social }}</div>
        <div class="cv-nit">NIT {{ $empresa->nit }}</div>
        <table cellpadding="0" cellspacing="0" align="center" style="margin-top:0.9cm">
            <tr><td>
                <div class="cv-score-wrap">
                    <div class="cv-score-num" style="color:{{ $scoreColor }}">{{ $score }}</div>
                    <div class="cv-score-lbl">PUNTUACIÓN / 100</div>
                    <div class="cv-score-txt" style="color:{{ $scoreColor }}">{{ $scoreText }}</div>
                </div>
            </td></tr>
        </table>
        <div style="margin-top:1cm"><span class="cv-badge">VERSIÓN EJECUTIVA</span></div>
    </div>
    <div class="cv-bottom">
        <div class="cv-meta">
            <table><tr>
                <td>
                    <span class="lbl">Empresa Auditada</span>
                    <span class="val">{{ $empresa->razon_social }}</span>
                </td>
                <td class="sep"></td>
                <td>
                    <span class="lbl">Fecha de Auditoría</span>
                    <span class="val">{{ $fechaCorta }}</span>
                </td>
                <td class="sep"></td>
                <td>
                    <span class="lbl">Referencia</span>
                    <span class="val">{{ $ref }}</span>
                </td>
                <td class="sep"></td>
                <td>
                    <span class="lbl">Secciones Evaluadas</span>
                    <span class="val">{{ array_sum($conteos) }}</span>
                </td>
            </tr></table>
        </div>
    </div>
</div>

{{-- ══ I. RESUMEN DE BRECHAS ══ --}}
<div class="sh">
    <span class="sh-num">Sección I</span>
    <span class="sh-tit">Resumen de Brechas Identificadas</span>
</div>

<table class="kpi-table">
    <tr>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#fee2e2; border-top:3pt solid #dc2626; border:1pt solid #fca5a5; border-top-width:3pt; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#dc2626; line-height:1">{{ $conteos['alto'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Alto</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#fef9c3; border-top:3pt solid #d97706; border:1pt solid #fde047; border-top-width:3pt; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#d97706; line-height:1">{{ $conteos['medio'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Medio</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dbeafe; border-top:3pt solid #2563eb; border:1pt solid #93c5fd; border-top-width:3pt; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#2563eb; line-height:1">{{ $conteos['bajo'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Bajo</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dcfce7; border-top:3pt solid #16a34a; border:1pt solid #86efac; border-top-width:3pt; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#16a34a; line-height:1">{{ $conteos['sin_gap'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Sin Brecha</p>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Barra de cumplimiento --}}
<div class="bar-wrap">
    <table class="bar-track"><tr>
        <td width="{{ $score }}%" style="background:{{ $scoreColor }}">&nbsp;</td>
        @if($score < 100)<td width="{{ 100 - $score }}%">&nbsp;</td>@endif
    </tr></table>
    <p class="bar-lbl">Índice de Cumplimiento: <strong>{{ $score }}/100</strong> &nbsp;·&nbsp; {{ $scoreText }}</p>
</div>

{{-- ══ II. RESUMEN EJECUTIVO ══ --}}
@if($resumen)
<div class="sh">
    <span class="sh-num">Sección II</span>
    <span class="sh-tit">Resumen Ejecutivo</span>
</div>
<div class="rb">{{ $resumen }}</div>
@endif

{{-- ══ III. TABLA DE BRECHAS ══ --}}
@php $hayGaps = !empty($todosLosGaps); @endphp
@if($hayGaps)
<div class="sh">
    <span class="sh-num">Sección III</span>
    <span class="sh-tit">Tabla de Brechas por Sección</span>
</div>
<table class="gap-table">
    <thead>
        <tr>
            <th style="width:30%">Sección del RIT</th>
            <th style="width:9%; text-align:center">Score</th>
            <th style="width:11%">Riesgo</th>
            <th>Recomendación Principal</th>
        </tr>
    </thead>
    <tbody>
        @php $rowIdx = 0; @endphp
        @foreach(['alto' => ['Alto','#dc2626'], 'medio' => ['Medio','#d97706'], 'bajo' => ['Bajo','#2563eb']] as $nivel => [$etiqueta, $color])
            @foreach($gapsAgrupados[$nivel] as $sec)
            @php $bg = $rowIdx % 2 === 0 ? '#ffffff' : '#f8fafc'; $rowIdx++; @endphp
            <tr style="background:{{ $bg }}">
                <td style="border-left:3pt solid {{ $color }}; font-weight:600">{{ $sec['titulo'] }}</td>
                <td style="text-align:center; font-weight:700; color:{{ $color }}">{{ $sec['score'] }}/100</td>
                <td><span class="b{{ substr($nivel,0,1) }}">{{ $etiqueta }}</span></td>
                <td style="color:#374151">{{ $sec['recomendaciones'][0] ?? '—' }}</td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@else
<div class="rb" style="border-left-color:#16a34a; background:#f0fdf4; color:#166534">
    No se identificaron brechas de cumplimiento. El RIT cumple con todos los requisitos normativos evaluados.
</div>
@endif

{{-- ══ IV. PLAN DE ACCIONES ══ --}}
@if(!empty($acciones))
<div class="sh">
    <span class="sh-num">Sección IV</span>
    <span class="sh-tit">Plan de Acciones Prioritarias</span>
</div>
@foreach($acciones as $i => $item)
<table class="accion-table">
    <tr>
        <td class="accion-num">
            <div class="accion-num-inner">{{ $i + 1 }}</div>
        </td>
        <td class="accion-txt">
            <strong style="color:#0d1f3c">{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}
        </td>
    </tr>
</table>
@endforeach
@endif

{{-- Sello confidencial --}}
<div class="sello">
    DOCUMENTO CONFIDENCIAL — Uso exclusivo de {{ $empresa->razon_social }} y CES Legal
</div>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-top:1pt solid #e5e7eb; margin-top:4pt">
    <tr>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt">CES Legal &nbsp;·&nbsp; {{ $fechaHora }}</td>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt; text-align:center">{{ $ref }}</td>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt; text-align:right">Análisis GAP de Cumplimiento Normativo</td>
    </tr>
</table>

</body>
</html>
