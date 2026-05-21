<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte GAP Ejecutivo</title>
    <style>
        @page { size: A4; margin: 0 14mm 14mm 14mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.55;
            color: #111827;
            margin: 0; padding: 0;
        }
        a { color: inherit; text-decoration: none; }

        /* Sección heading */
        .sh {
            background: #f3f4f6;
            border-left: 4px solid #b91c1c;
            padding: 5px 10px;
            margin: 14px 0 8px;
            font-size: 10pt;
            font-weight: 700;
            color: #111827;
        }
        .sh-n { color: #b91c1c; margin-right: 3px; }

        /* Resumen box */
        .rb {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            border-left: 3px solid #b91c1c;
            padding: 9px 11px;
            font-size: 8.5pt;
            line-height: 1.65;
            color: #374151;
            margin-bottom: 12px;
        }

        /* Badge de riesgo */
        .badge {
            padding: 2px 7px;
            font-size: 7pt;
            font-weight: 700;
            border-radius: 3px;
        }
        .ba { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .bm { background: #fef9c3; color: #92400e; border: 1px solid #fde047; }
        .bb { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .bo { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

        /* Sello confidencial */
        .sello {
            background: #fef2f2;
            border: 1px solid #fecaca;
            text-align: center;
            padding: 5px 10px;
            margin: 16px 0 8px;
            font-size: 7pt;
            font-weight: 700;
            color: #991b1b;
            letter-spacing: 0.5px;
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

    $scoreColor = $score >= 70 ? '#16a34a' : ($score >= 40 ? '#d97706' : '#dc2626');
    $scoreText  = $score >= 70 ? 'Cumplimiento Satisfactorio' : ($score >= 40 ? 'Requiere Mejoras' : 'Riesgo Jurídico Alto');
@endphp

{{-- ══════════════════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a; margin-bottom:0">
    <tr>
        <td style="padding:16px 16px 14px; vertical-align:middle; color:#ffffff; width:66%">
            <p style="font-size:6.5pt; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#fca5a5; margin-bottom:5px">
                CES Legal &nbsp;·&nbsp; Derecho Laboral Colombiano
            </p>
            <p style="font-size:14pt; font-weight:700; color:#f8fafc; line-height:1.2">
                Análisis GAP de Cumplimiento Normativo
            </p>
            <p style="font-size:7.5pt; color:#94a3b8; margin-top:4px">
                Reglamento Interno de Trabajo &nbsp;·&nbsp; CST · Ley 1010/2006 · Ley 2365/2024
            </p>
            <p style="margin-top:8px">
                <span style="background:#991b1b; color:#fef2f2; font-size:6.5pt; font-weight:700; letter-spacing:1.5px; padding:2px 8px">
                    VERSIÓN EJECUTIVA
                </span>
            </p>
        </td>
        <td style="padding:14px 16px; vertical-align:middle; text-align:center; width:34%">
            <table cellpadding="0" cellspacing="0" align="center"
                   style="border:2px solid #334155; background:#1e293b; min-width:100px">
                <tr>
                    <td style="padding:10px 16px; text-align:center">
                        <p style="font-size:28pt; font-weight:700; color:{{ $scoreColor }}; line-height:1">
                            {{ $score }}
                        </p>
                        <p style="font-size:7pt; color:#64748b; margin-top:1px">PUNTUACIÓN / 100</p>
                        <p style="font-size:7.5pt; font-weight:700; color:{{ $scoreColor }}; margin-top:5px">
                            {{ $scoreText }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" height="3" style="background:#b91c1c; font-size:0; line-height:0">&nbsp;</td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     METADATA STRIP
══════════════════════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0"
       style="border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; margin-bottom:14px; background:#fff">
    <tr>
        <td style="padding:8px 10px; border-right:1px solid #e5e7eb; width:40%; vertical-align:top">
            <p style="font-size:6.5pt; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#9ca3af; margin-bottom:2px">Empresa Auditada</p>
            <p style="font-size:9pt; font-weight:700; color:#111827">{{ $empresa->razon_social }}</p>
            @if($empresa->nit)<p style="font-size:7.5pt; color:#6b7280; margin-top:1px">NIT: {{ $empresa->nit }}</p>@endif
        </td>
        <td style="padding:8px 10px; border-right:1px solid #e5e7eb; width:20%; vertical-align:top">
            <p style="font-size:6.5pt; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#9ca3af; margin-bottom:2px">Fecha de Auditoría</p>
            <p style="font-size:9pt; font-weight:700; color:#111827">{{ $fechaCorta }}</p>
        </td>
        <td style="padding:8px 10px; border-right:1px solid #e5e7eb; width:22%; vertical-align:top">
            <p style="font-size:6.5pt; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#9ca3af; margin-bottom:2px">Referencia</p>
            <p style="font-size:9pt; font-weight:700; color:#111827">{{ $ref }}</p>
        </td>
        <td style="padding:8px 10px; width:18%; vertical-align:top">
            <p style="font-size:6.5pt; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#9ca3af; margin-bottom:2px">Tipo</p>
            <p style="font-size:9pt; font-weight:700; color:#111827">Ejecutivo</p>
            <p style="font-size:7.5pt; color:#6b7280; margin-top:1px">Confidencial</p>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     I. RESUMEN DE BRECHAS
══════════════════════════════════════════════════════════ --}}
<div class="sh"><span class="sh-n">I.</span> Resumen de Brechas Identificadas</div>

<table width="100%" cellpadding="0" cellspacing="5" style="margin-bottom:8px">
    <tr>
        <td style="width:25%; vertical-align:top">
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#fee2e2; border-top:3px solid #dc2626; border-right:1px solid #fca5a5; border-bottom:1px solid #fca5a5; border-left:1px solid #fca5a5; text-align:center">
                <tr><td>
                    <p style="font-size:22pt; font-weight:700; color:#dc2626; line-height:1">{{ $conteos['alto'] }}</p>
                    <p style="font-size:7pt; font-weight:700; color:#374151; margin-top:3px">Riesgo Alto</p>
                </td></tr>
            </table>
        </td>
        <td style="width:25%; vertical-align:top">
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#fef9c3; border-top:3px solid #d97706; border-right:1px solid #fde047; border-bottom:1px solid #fde047; border-left:1px solid #fde047; text-align:center">
                <tr><td>
                    <p style="font-size:22pt; font-weight:700; color:#d97706; line-height:1">{{ $conteos['medio'] }}</p>
                    <p style="font-size:7pt; font-weight:700; color:#374151; margin-top:3px">Riesgo Medio</p>
                </td></tr>
            </table>
        </td>
        <td style="width:25%; vertical-align:top">
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dbeafe; border-top:3px solid #2563eb; border-right:1px solid #93c5fd; border-bottom:1px solid #93c5fd; border-left:1px solid #93c5fd; text-align:center">
                <tr><td>
                    <p style="font-size:22pt; font-weight:700; color:#2563eb; line-height:1">{{ $conteos['bajo'] }}</p>
                    <p style="font-size:7pt; font-weight:700; color:#374151; margin-top:3px">Riesgo Bajo</p>
                </td></tr>
            </table>
        </td>
        <td style="width:25%; vertical-align:top">
            <table width="100%" cellpadding="9" cellspacing="0"
                   style="background:#dcfce7; border-top:3px solid #16a34a; border-right:1px solid #86efac; border-bottom:1px solid #86efac; border-left:1px solid #86efac; text-align:center">
                <tr><td>
                    <p style="font-size:22pt; font-weight:700; color:#16a34a; line-height:1">{{ $conteos['sin_gap'] }}</p>
                    <p style="font-size:7pt; font-weight:700; color:#374151; margin-top:3px">Sin Brecha</p>
                </td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Barra de cumplimiento --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:14px">
    <tr>
        <td style="padding-bottom:3px">
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#e5e7eb; height:5px; border-radius:3px; overflow:hidden">
                <tr>
                    <td width="{{ $score }}%" height="5"
                        style="background:{{ $scoreColor }}; font-size:0; line-height:0">&nbsp;</td>
                    @if($score < 100)
                    <td width="{{ 100 - $score }}%" height="5" style="font-size:0; line-height:0">&nbsp;</td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="font-size:7pt; color:#6b7280">
            Índice de Cumplimiento: {{ $score }}/100 &nbsp;·&nbsp; {{ $scoreText }}
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     II. RESUMEN EJECUTIVO
══════════════════════════════════════════════════════════ --}}
@if($resumen)
<div class="sh"><span class="sh-n">II.</span> Resumen Ejecutivo</div>
<div class="rb">{{ $resumen }}</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     III. TABLA DE BRECHAS
══════════════════════════════════════════════════════════ --}}
@php $hayGaps = !empty($todosLosGaps); @endphp
@if($hayGaps)
<div class="sh"><span class="sh-n">III.</span> Tabla de Brechas por Sección</div>
<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse; margin-bottom:14px; font-size:8pt">
    <thead>
        <tr style="background:#1e293b">
            <th style="color:#f1f5f9; padding:5px 8px; text-align:left; font-size:7.5pt; width:27%">Sección del RIT</th>
            <th style="color:#f1f5f9; padding:5px 8px; text-align:center; font-size:7.5pt; width:9%">Score</th>
            <th style="color:#f1f5f9; padding:5px 8px; text-align:left; font-size:7.5pt; width:11%">Riesgo</th>
            <th style="color:#f1f5f9; padding:5px 8px; text-align:left; font-size:7.5pt">Recomendación Principal</th>
        </tr>
    </thead>
    <tbody>
        @php $rowIdx = 0; @endphp
        @foreach(['alto' => ['Alto','#dc2626'], 'medio' => ['Medio','#d97706'], 'bajo' => ['Bajo','#2563eb']] as $nivel => [$etiqueta, $color])
            @foreach($gapsAgrupados[$nivel] as $sec)
            @php $bg = $rowIdx % 2 === 0 ? '#ffffff' : '#f9fafb'; $rowIdx++; @endphp
            <tr style="background:{{ $bg }}">
                <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb; border-left:3px solid {{ $color }}; font-weight:600">
                    {{ $sec['titulo'] }}
                </td>
                <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb; text-align:center; font-weight:700">
                    {{ $sec['score'] }}/100
                </td>
                <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb">
                    <span class="badge b{{ substr($nivel,0,1) }}">{{ $etiqueta }}</span>
                </td>
                <td style="padding:5px 8px; border-bottom:1px solid #e5e7eb">
                    {{ $sec['recomendaciones'][0] ?? '—' }}
                </td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
@else
<div class="rb" style="border-left-color:#16a34a; background:#f0fdf4; border-color:#86efac; color:#166534">
    No se identificaron brechas de cumplimiento. El RIT cumple con todos los requisitos normativos evaluados.
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     IV. PLAN DE ACCIONES
══════════════════════════════════════════════════════════ --}}
@if(!empty($acciones))
<div class="sh"><span class="sh-n">IV.</span> Plan de Acciones Prioritarias</div>
@foreach($acciones as $i => $item)
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px">
    <tr>
        <td width="22" style="vertical-align:top; padding-top:1px">
            <div style="background:#1e293b; color:#ffffff; width:17px; height:17px; text-align:center; font-size:7.5pt; font-weight:700; border-radius:9px; padding-top:2px">
                {{ $i + 1 }}
            </div>
        </td>
        <td style="vertical-align:top; font-size:8pt; color:#374151; line-height:1.55">
            <strong style="color:#111827">{{ $item['seccion'] }}:</strong> {{ $item['accion'] }}
        </td>
    </tr>
</table>
@endforeach
@endif

{{-- ══════════════════════════════════════════════════════════
     SELLO + FOOTER
══════════════════════════════════════════════════════════ --}}
<div class="sello">
    DOCUMENTO CONFIDENCIAL — Uso exclusivo de {{ $empresa->razon_social }} y CES Legal
</div>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-top:1px solid #e5e7eb; padding-top:5px; margin-top:4px">
    <tr>
        <td style="font-size:7pt; color:#9ca3af">CES Legal &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}</td>
        <td style="font-size:7pt; color:#9ca3af; text-align:center">{{ $ref }}</td>
        <td style="font-size:7pt; color:#9ca3af; text-align:right">Análisis GAP de Cumplimiento Normativo</td>
    </tr>
</table>

</body>
</html>
