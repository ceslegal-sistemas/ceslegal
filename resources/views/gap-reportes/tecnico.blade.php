<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Técnico</title>
    <style>
        @page { margin: 2.5cm 2.5cm 2.5cm 3cm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000000;
            text-align: justify;
        }
        .encabezado {
            text-align: center;
            border-bottom: 2pt solid #000000;
            padding-bottom: 10pt;
            margin-bottom: 14pt;
        }
        .enc-empresa { font-size: 13pt; font-weight: bold; }
        .enc-nit     { font-size: 10pt; margin-top: 2pt; }
        .enc-titulo  { font-size: 13pt; font-weight: bold; margin-top: 8pt; text-transform: uppercase; }
        .enc-subtit  { font-size: 10pt; margin-top: 2pt; }

        .meta-tabla { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .meta-tabla td { padding: 3pt 0; font-size: 10.5pt; vertical-align: top; }
        .meta-tabla td:first-child { font-weight: bold; width: 38%; }

        h3 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 16pt 0 7pt 0;
            padding-bottom: 3pt;
            border-bottom: 1pt solid #000000;
        }

        p { margin-bottom: 8pt; text-align: justify; }

        .resumen {
            border-left: 3pt solid #000000;
            padding: 6pt 10pt;
            margin-bottom: 12pt;
            font-size: 10.5pt;
        }

        /* KPI */
        .kpi-t { width: 100%; border-collapse: collapse; margin-bottom: 12pt; font-size: 10.5pt; }
        .kpi-t th { border: 1pt solid #000; padding: 5pt 7pt; background: #e8e8e8; text-align: center; }
        .kpi-t td { border: 1pt solid #000; padding: 6pt 7pt; text-align: center; font-weight: bold; font-size: 14pt; }

        /* Tabla de brechas */
        .gap-t { width: 100%; border-collapse: collapse; margin-bottom: 12pt; font-size: 10pt; }
        .gap-t th {
            border: 1pt solid #000;
            padding: 5pt 7pt;
            background: #e8e8e8;
            text-align: left;
            font-size: 9.5pt;
        }
        .gap-t td { border: 1pt solid #000; padding: 5pt 7pt; vertical-align: top; }
        .gap-t tr:nth-child(even) td { background: #f5f5f5; }

        /* Acciones */
        .accion-item { margin-bottom: 7pt; font-size: 10.5pt; }

        /* Tarjeta hallazgo técnico */
        .hallazgo {
            border: 1pt solid #cccccc;
            margin-bottom: 12pt;
            page-break-inside: avoid;
        }
        .hallazgo-cab {
            background: #e8e8e8;
            border-bottom: 1pt solid #cccccc;
            padding: 5pt 8pt;
        }
        .hallazgo-cab table { width: 100%; border-collapse: collapse; }
        .hallazgo-cab td { vertical-align: middle; padding: 0; }
        .hallazgo-titulo { font-weight: bold; font-size: 10.5pt; }
        .hallazgo-meta   { text-align: right; white-space: nowrap; font-size: 10pt; }
        .hallazgo-cuerpo { padding: 7pt 9pt; }

        .sub-lbl {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 5pt 0 3pt 0;
        }
        .h-item { font-size: 10pt; padding-left: 10pt; margin-bottom: 2pt; }

        .norm-tag {
            display: inline-block;
            border: 1pt solid #aaaaaa;
            padding: 1pt 5pt;
            font-size: 8.5pt;
            font-family: 'Courier New', monospace;
            color: #333333;
            margin-right: 3pt;
            margin-bottom: 2pt;
            background: #f5f5f5;
        }

        /* Nota técnica */
        .nota-tec {
            border-left: 3pt solid #000000;
            background: #f5f5f5;
            padding: 7pt 10pt;
            margin-top: 14pt;
            font-size: 9.5pt;
            color: #444444;
            line-height: 1.5;
        }

        /* Pie */
        .pie {
            margin-top: 22pt;
            border-top: 1pt solid #000000;
            padding-top: 6pt;
            font-size: 9pt;
            color: #555555;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $fechaCorta = $auditoria->updated_at?->format('d/m/Y') ?? now()->format('d/m/Y');
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

    $scoreText = $score >= 70 ? 'Cumplimiento Satisfactorio' : ($score >= 40 ? 'Requiere Mejoras' : 'Riesgo Jurídico Alto');
@endphp

<div class="encabezado">
    <div class="enc-empresa">{{ $empresa->razon_social }}</div>
    <div class="enc-nit">NIT {{ $empresa->nit }}</div>
    <div class="enc-titulo">Análisis GAP de Cumplimiento Normativo</div>
    <div class="enc-subtit">Versión Técnica — Confidencial &nbsp;·&nbsp; {{ $ref }} &nbsp;·&nbsp; {{ $fechaCorta }}</div>
</div>

<table class="meta-tabla">
    <tr><td>Empresa auditada:</td>    <td>{{ $empresa->razon_social }}</td></tr>
    <tr><td>NIT:</td>                 <td>{{ $empresa->nit }}</td></tr>
    <tr><td>Fecha de auditoría:</td>  <td>{{ $fechaCorta }}</td></tr>
    <tr><td>Referencia:</td>          <td>{{ $ref }}</td></tr>
    <tr><td>Secciones evaluadas:</td> <td>{{ array_sum($conteos) }}</td></tr>
    <tr><td>Puntuación obtenida:</td> <td><strong>{{ $score }}/100 — {{ $scoreText }}</strong></td></tr>
</table>

<h3>I. Resumen de Brechas Identificadas</h3>

<table class="kpi-t">
    <tr>
        <th>Riesgo Alto</th>
        <th>Riesgo Medio</th>
        <th>Riesgo Bajo</th>
        <th>Sin Brecha</th>
    </tr>
    <tr>
        <td>{{ $conteos['alto'] }}</td>
        <td>{{ $conteos['medio'] }}</td>
        <td>{{ $conteos['bajo'] }}</td>
        <td>{{ $conteos['sin_gap'] }}</td>
    </tr>
</table>

@if($resumen)
<h3>II. Resumen Ejecutivo</h3>
<div class="resumen">{{ $resumen }}</div>
@endif

@if(!empty($todosLosGaps))
<h3>III. Tabla de Brechas por Sección</h3>
<table class="gap-t">
    <thead>
        <tr>
            <th style="width:34%">Sección del RIT</th>
            <th style="width:10%; text-align:center">Score</th>
            <th style="width:12%">Riesgo</th>
            <th>Recomendación Principal</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'] as $nivel => $etiqueta)
            @foreach($gapsAgrupados[$nivel] as $sec)
            <tr>
                <td>{{ $sec['titulo'] }}</td>
                <td style="text-align:center; font-weight:bold">{{ $sec['score'] }}/100</td>
                <td style="text-align:center">{{ $etiqueta }}</td>
                <td>{{ $sec['recomendaciones'][0] ?? '—' }}</td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@endif

@if(!empty($acciones))
<h3>IV. Plan de Acciones Prioritarias</h3>
@foreach($acciones as $i => $item)
<p class="accion-item">{{ $i + 1 }}. <strong>{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}</p>
@endforeach
@endif

{{-- ══ V. HALLAZGOS DETALLADOS — exclusivo versión técnica ══ --}}
@if(!empty($todosLosGaps))
<h3 style="margin-top:22pt; page-break-before:always">V. Hallazgos Detallados con Trazabilidad Normativa</h3>

@foreach(['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'] as $nivel => $etiqueta)
    @foreach($gapsAgrupados[$nivel] as $sec)
    <div class="hallazgo">
        <div class="hallazgo-cab">
            <table>
                <tr>
                    <td class="hallazgo-titulo">{{ $sec['titulo'] }}</td>
                    <td class="hallazgo-meta">
                        Riesgo: <strong>{{ $etiqueta }}</strong>
                        &nbsp;&middot;&nbsp; Score: <strong>{{ $sec['score'] }}/100</strong>
                    </td>
                </tr>
            </table>
        </div>
        <div class="hallazgo-cuerpo">

            @if(!empty($sec['hallazgos']))
            <p class="sub-lbl">Hallazgos</p>
            @foreach($sec['hallazgos'] as $h)
            <p class="h-item">&#x2022; {{ $h }}</p>
            @endforeach
            @endif

            @if(!empty($sec['recomendaciones']))
            <p class="sub-lbl" style="margin-top:6pt">Recomendaciones</p>
            @foreach($sec['recomendaciones'] as $r)
            <p class="h-item">&#x2192; {{ $r }}</p>
            @endforeach
            @endif

            @if(!empty($sec['articulos_referencia']))
            <p class="sub-lbl" style="margin-top:6pt">Trazabilidad Normativa</p>
            @foreach($sec['articulos_referencia'] as $art)
            <span class="norm-tag">{{ $art }}</span>
            @endforeach
            @endif

        </div>
    </div>
    @endforeach
@endforeach
@endif

<div class="nota-tec">
    <strong>Nota de confidencialidad técnica:</strong>
    Este documento contiene el análisis detallado de cumplimiento normativo del Reglamento Interno de Trabajo
    de {{ $empresa->razon_social }}, elaborado con base en los fragmentos de la biblioteca jurídica de CES Legal.
    El análisis fue generado de manera automatizada y debe ser revisado por un profesional del derecho antes de
    tomar decisiones. La trazabilidad normativa no constituye asesoría jurídica independiente.
</div>

<div class="pie">
    Documento técnico confidencial &mdash; Uso exclusivo de CES Legal y {{ $empresa->razon_social }}
    &nbsp;&middot;&nbsp; {{ $ref }} &nbsp;&middot;&nbsp; {{ $fechaCorta }}
</div>

</body>
</html>
