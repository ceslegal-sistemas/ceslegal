<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Técnico</title>
    <style>
        /* ══ Página ═══════════════════════════════════════════════════════════════ */
        @page {
            size: letter portrait;
            margin-top: 3cm;
            margin-bottom: 2.8cm;
            margin-left: 2.5cm;
            margin-right: 2cm;
        }
        @page cover { margin: 0; }

        /* ══ Running header ══════════════════════════════════════════════════════ */
        .hdr {
            position: fixed;
            top: -2.2cm;
            left: 0; right: 0;
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
            font-size: 7pt; color: #5a6a7a; text-align: right;
        }

        /* ══ Running footer ══════════════════════════════════════════════════════ */
        .ftr {
            position: fixed;
            bottom: -2.1cm;
            left: 0; right: 0;
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
            color: #ffffff; line-height: 1.18;
            margin-bottom: 0.4cm;
        }
        .cv-rule { display: block; width: 3cm; height: 2.5pt; background: #c9a84c; margin: 1cm auto; }
        .cv-empresa { font-size: 15pt; font-weight: 700; color: #c9a84c; margin-bottom: 0.3cm; }
        .cv-nit { font-size: 9pt; color: #7e9bb5; letter-spacing: 0.04em; }
        .cv-score-wrap {
            border: 2.5pt solid #c9a84c;
            padding: 0.5cm 1.2cm;
            margin: 0.9cm auto 0;
        }
        .cv-score-num { font-size: 36pt; font-weight: 700; line-height: 1; }
        .cv-score-lbl { font-size: 7pt; color: #94a3b8; margin-top: 3pt; letter-spacing: 0.1em; }
        .cv-score-txt { font-size: 8pt; font-weight: 700; margin-top: 4pt; }
        .cv-badge {
            display: inline-block; font-size: 6.5pt; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            background: #7f1d1d; color: #fef2f2;
            padding: 3pt 8pt; margin-top: 1.2cm;
        }
        .cv-bottom {
            background: #ffffff; border-top: 3.5pt solid #c9a84c;
            padding: 1.2cm 2.5cm;
        }
        .cv-meta table { width: 100%; border-collapse: collapse; }
        .cv-meta td { text-align: center; vertical-align: middle; padding: 0 0.8cm; }
        .cv-meta .sep { width: 1pt; background: #e5e7eb; padding: 0; }
        .cv-meta .lbl {
            display: block; font-size: 6pt;
            letter-spacing: 0.16em; text-transform: uppercase;
            color: #9ca3af; margin-bottom: 3pt;
        }
        .cv-meta .val { display: block; font-size: 9.5pt; font-weight: 700; color: #0d1f3c; }

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
            color: #ffffff;
        }

        /* ══ Resumen box ══════════════════════════════════════════════════════════ */
        .rb {
            background: #f9fafb;
            border-top: 1pt solid #e5e7eb; border-right: 1pt solid #e5e7eb;
            border-bottom: 1pt solid #e5e7eb; border-left: 3pt solid #c9a84c;
            padding: 9pt 11pt; font-size: 8.5pt;
            line-height: 1.65; color: #374151; margin-bottom: 10pt;
        }

        /* ══ KPI cards ════════════════════════════════════════════════════════════ */
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .kpi-table td { padding: 4pt; vertical-align: top; width: 25%; }

        /* ══ Barra ════════════════════════════════════════════════════════════════ */
        .bar-track { width: 100%; background: #e5e7eb; height: 5pt; border-collapse: collapse; }
        .bar-track td { height: 5pt; font-size: 0; line-height: 0; }

        /* ══ Tabla brechas ════════════════════════════════════════════════════════ */
        .gap-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 12pt; }
        .gap-table th {
            background: #0d1f3c; color: #f1f5f9;
            padding: 5pt 7pt; text-align: left; font-size: 7.5pt;
        }
        .gap-table td { padding: 5pt 7pt; border-bottom: 1pt solid #e5e7eb; }

        /* ══ Badges riesgo ════════════════════════════════════════════════════════ */
        .ba { background: #fee2e2; color: #991b1b; border: 1pt solid #fca5a5; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bm { background: #fef9c3; color: #92400e; border: 1pt solid #fde047; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bb { background: #dbeafe; color: #1e40af; border: 1pt solid #93c5fd; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }
        .bo { background: #dcfce7; color: #166534; border: 1pt solid #86efac; padding: 2pt 6pt; font-size: 6.5pt; font-weight: 700; }

        /* ══ Acciones ═════════════════════════════════════════════════════════════ */
        .accion-table { width: 100%; border-collapse: collapse; margin-bottom: 5pt; }
        .accion-num { width: 20pt; vertical-align: top; padding-top: 1pt; }
        .accion-num-inner {
            background: #0d1f3c; color: #c9a84c;
            width: 16pt; height: 16pt;
            text-align: center; font-size: 7pt; font-weight: 700; padding-top: 3pt;
        }

        /* ══ Tarjetas de hallazgo (técnico) ═══════════════════════════════════════ */
        .hallazgo-card {
            border: 1pt solid #e2e8f0;
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }
        .hallazgo-head {
            background: #0d1f3c;
            padding: 6pt 10pt;
        }
        .hallazgo-head table { width: 100%; border-collapse: collapse; }
        .hallazgo-head td { vertical-align: middle; }
        .hallazgo-titulo {
            font-size: 9pt; font-weight: 700; color: #ffffff;
        }
        .hallazgo-meta {
            text-align: right; white-space: nowrap;
        }
        .hallazgo-body { padding: 8pt 10pt; }
        .sub-label {
            font-size: 6.5pt; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; color: #7f1d1d; margin-bottom: 3pt;
        }
        .sub-label-norm {
            font-size: 6.5pt; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; color: #0d1f3c; margin-bottom: 3pt;
        }
        .h-item { font-size: 8pt; color: #374151; padding-left: 8pt; margin-bottom: 2pt; }
        .norm-tag {
            display: inline-block;
            background: #f1f5f9; border: 1pt solid #cbd5e1;
            padding: 1pt 5pt; font-size: 6.5pt;
            font-family: 'DejaVu Sans Mono', monospace;
            color: #334155; margin-right: 3pt; margin-bottom: 2pt;
        }

        /* ══ Nota técnica ════════════════════════════════════════════════════════ */
        .nota-tec {
            background: #f8fafc; border: 1pt solid #e2e8f0;
            border-left: 3pt solid #0d1f3c;
            padding: 8pt 10pt; margin-top: 12pt;
            font-size: 7.5pt; color: #6b7280; line-height: 1.6;
        }

        /* ══ Sello ════════════════════════════════════════════════════════════════ */
        .sello {
            background: #fef2f2; border: 1pt solid #fecaca;
            text-align: center; padding: 5pt 10pt; margin: 14pt 0 8pt;
            font-size: 7pt; font-weight: 700; color: #7f1d1d; letter-spacing: 0.04em;
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
        <td class="hr">Análisis GAP · Versión Técnica — Confidencial</td>
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
        <div class="cv-pretitle">Informe Técnico de Cumplimiento Normativo</div>
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
        <div style="margin-top:1cm"><span class="cv-badge">VERSIÓN TÉCNICA — CONFIDENCIAL</span></div>
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
                   style="background:#fee2e2; border:1pt solid #fca5a5; border-top:3pt solid #dc2626; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#dc2626; line-height:1">{{ $conteos['alto'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Alto</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#fef9c3; border:1pt solid #fde047; border-top:3pt solid #d97706; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#d97706; line-height:1">{{ $conteos['medio'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Medio</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dbeafe; border:1pt solid #93c5fd; border-top:3pt solid #2563eb; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#2563eb; line-height:1">{{ $conteos['bajo'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Riesgo Bajo</p>
                </td></tr>
            </table>
        </td>
        <td>
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dcfce7; border:1pt solid #86efac; border-top:3pt solid #16a34a; text-align:center">
                <tr><td>
                    <p style="font-size:24pt; font-weight:700; color:#16a34a; line-height:1">{{ $conteos['sin_gap'] }}</p>
                    <p style="font-size:6.5pt; font-weight:700; color:#374151; margin-top:3pt; text-transform:uppercase; letter-spacing:0.06em">Sin Brecha</p>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="bar-track" style="margin-bottom:3pt"><tr>
    <td width="{{ $score }}%" style="background:{{ $scoreColor }}">&nbsp;</td>
    @if($score < 100)<td width="{{ 100 - $score }}%">&nbsp;</td>@endif
</tr></table>
<p style="font-size:7pt; color:#6b7280; margin-bottom:12pt">
    Índice de Cumplimiento: <strong>{{ $score }}/100</strong> &nbsp;·&nbsp; {{ $scoreText }}
</p>

{{-- ══ II. RESUMEN EJECUTIVO ══ --}}
@if($resumen)
<div class="sh">
    <span class="sh-num">Sección II</span>
    <span class="sh-tit">Resumen Ejecutivo</span>
</div>
<div class="rb">{{ $resumen }}</div>
@endif

{{-- ══ III. TABLA DE BRECHAS ══ --}}
@if(!empty($todosLosGaps))
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
            @php $bg = $rowIdx % 2 === 0 ? '#ffffff' : '#fff8f1'; $rowIdx++; @endphp
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
        <td style="font-size:8pt; color:#374151; line-height:1.55; vertical-align:top">
            <strong style="color:#0d1f3c">{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}
        </td>
    </tr>
</table>
@endforeach
@endif

{{-- ══ V. HALLAZGOS DETALLADOS (sección exclusiva técnica) ══ --}}
@if(!empty($todosLosGaps))
<div class="sh" style="margin-top:22pt; page-break-before:always">
    <span class="sh-num">Sección V — Técnica</span>
    <span class="sh-tit">Hallazgos Detallados por Sección con Trazabilidad Normativa</span>
</div>

@foreach(['alto' => ['Alto','#dc2626','#fee2e2'], 'medio' => ['Medio','#d97706','#fef9c3'], 'bajo' => ['Bajo','#2563eb','#dbeafe']] as $nivel => [$etiqueta, $color, $bgCard])
    @foreach($gapsAgrupados[$nivel] as $sec)
    <div class="hallazgo-card">
        {{-- Encabezado tarjeta --}}
        <div class="hallazgo-head">
            <table>
                <tr>
                    <td class="hallazgo-titulo">{{ $sec['titulo'] }}</td>
                    <td class="hallazgo-meta">
                        <span class="b{{ substr($nivel,0,1) }}">{{ $etiqueta }}</span>
                        &nbsp;<span style="font-size:8pt; font-weight:700; color:#c9a84c">{{ $sec['score'] }}/100</span>
                    </td>
                </tr>
            </table>
        </div>
        {{-- Cuerpo tarjeta --}}
        <div class="hallazgo-body">

            @if(!empty($sec['hallazgos']))
            <p class="sub-label">Hallazgos</p>
            @foreach($sec['hallazgos'] as $h)
            <p class="h-item">&#x2022; {{ $h }}</p>
            @endforeach
            @endif

            @if(!empty($sec['recomendaciones']))
            <p class="sub-label" style="margin-top:7pt">Recomendaciones</p>
            @foreach($sec['recomendaciones'] as $r)
            <p class="h-item">&#x2192; {{ $r }}</p>
            @endforeach
            @endif

            @if(!empty($sec['articulos_referencia']))
            <p class="sub-label-norm" style="margin-top:7pt">Trazabilidad Normativa</p>
            @foreach($sec['articulos_referencia'] as $art)
            <span class="norm-tag">{{ $art }}</span>
            @endforeach
            @endif

        </div>
    </div>
    @endforeach
@endforeach
@endif

{{-- Nota técnica de confidencialidad --}}
<div class="nota-tec">
    <strong>Nota de confidencialidad técnica:</strong>
    Este documento contiene el análisis detallado de cumplimiento normativo del Reglamento Interno de Trabajo
    de {{ $empresa->razon_social }}, elaborado con base en los fragmentos de la biblioteca jurídica de CES Legal.
    El análisis fue generado de manera automatizada y debe ser revisado por un profesional del derecho antes de
    tomar decisiones. La trazabilidad normativa indica los artículos y normas citadas durante la auditoría;
    no constituye asesoría jurídica independiente.
</div>

<div class="sello">
    DOCUMENTO TÉCNICO CONFIDENCIAL — Uso exclusivo de CES Legal y {{ $empresa->razon_social }}
</div>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-top:1pt solid #e5e7eb; margin-top:4pt">
    <tr>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt">CES Legal &nbsp;·&nbsp; {{ $fechaHora }}</td>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt; text-align:center">{{ $ref }}</td>
        <td style="font-size:7pt; color:#9ca3af; padding-top:4pt; text-align:right">Análisis GAP — Versión Técnica</td>
    </tr>
</table>

</body>
</html>
