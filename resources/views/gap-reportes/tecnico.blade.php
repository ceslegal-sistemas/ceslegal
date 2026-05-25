<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page { margin: 2.5cm 3cm 2.5cm 3cm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    line-height: 1.4;
    color: #000000;
}
.ftr {
    position: fixed;
    bottom: -2.2cm;
    left: 3cm; right: 3cm;
    height: 1.6cm;
    text-align: right;
    font-size: 8pt;
}
.titulo {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-bottom: 3pt;
}
.empresa-info {
    text-align: center;
    font-size: 9pt;
    margin-bottom: 3pt;
}
.sec-hdr {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 14pt;
    margin-bottom: 6pt;
}
.sub-hdr {
    font-weight: bold;
    font-size: 9pt;
    margin-top: 10pt;
    margin-bottom: 4pt;
    page-break-after: avoid;
}
.cuerpo {
    text-align: justify;
    font-size: 9pt;
    margin-bottom: 6pt;
}
.item {
    text-align: justify;
    font-size: 9pt;
    margin-bottom: 2pt;
    margin-left: 10pt;
}
.lbl {
    font-weight: bold;
    font-size: 9pt;
    margin-bottom: 2pt;
    margin-top: 4pt;
}
.tag {
    font-family: 'Courier New', Courier, monospace;
    font-size: 7.5pt;
    border: 0.3pt solid #000000;
    padding: 0 3pt;
    background: #f5f5f5;
}
.nota-final {
    text-align: justify;
    font-size: 8pt;
    margin-top: 20pt;
    font-style: italic;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8pt;
    font-size: 9pt;
}
th {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
}
td {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    text-align: left;
    vertical-align: top;
}
.tc { text-align: center; }
.tb { font-weight: bold; }
</style>
</head>
<body>
<?php
$eDireccion = htmlspecialchars($empresa->direccion ?? '', ENT_QUOTES, 'UTF-8');
$eCiudad    = htmlspecialchars($empresa->ciudad ?? '', ENT_QUOTES, 'UTF-8');
$eDpto      = htmlspecialchars($empresa->departamento ?? '', ENT_QUOTES, 'UTF-8');
$eTelefono  = htmlspecialchars($empresa->telefono ?? '', ENT_QUOTES, 'UTF-8');
$eEmail     = htmlspecialchars($empresa->email_contacto ?? '', ENT_QUOTES, 'UTF-8');
$eLugar     = trim($eCiudad . ($eDpto ? ', ' . $eDpto : ''));
$fLine1     = implode('. ', array_filter([$eDireccion, $eLugar]));
$fLine2     = implode('   ', array_filter([
    $eTelefono ? 'Tel. ' . $eTelefono : '',
    $eEmail    ? 'Email. ' . $eEmail   : '',
]));
$nAlto   = count($gapsAgrupados['alto']);
$nMedio  = count($gapsAgrupados['medio']);
$nBajo   = count($gapsAgrupados['bajo']);
$nSinGap = count($gapsAgrupados['sin_gap']);
$total   = $nAlto + $nMedio + $nBajo + $nSinGap;
?>

<div class="ftr"><?= $fLine1 ?><br><?= $fLine2 ?></div>

<p class="titulo">REPORTE DE AN&Aacute;LISIS GAP DE CUMPLIMIENTO NORMATIVO</p>
<p class="titulo">VERSI&Oacute;N T&Eacute;CNICA</p>
<p class="empresa-info">{{ $empresa->nombre_completo }} &mdash; NIT: {{ $empresa->nit }}</p>
<p class="empresa-info">Fecha de auditor&iacute;a: {{ $auditoria->created_at->format('d/m/Y') }} &mdash; Puntaje global: {{ $auditoria->score }}/100</p>

<p class="sec-hdr">RESUMEN DE BRECHAS</p>
<table>
  <tr>
    <th>RIESGO ALTO<br>(Score 0&ndash;39)</th>
    <th>RIESGO MEDIO<br>(Score 40&ndash;69)</th>
    <th>RIESGO BAJO<br>(Score 70&ndash;99)</th>
    <th>SIN BRECHA<br>(Score 100)</th>
    <th>TOTAL SECCIONES</th>
  </tr>
  <tr>
    <td class="tc tb"><?= $nAlto ?></td>
    <td class="tc tb"><?= $nMedio ?></td>
    <td class="tc tb"><?= $nBajo ?></td>
    <td class="tc tb"><?= $nSinGap ?></td>
    <td class="tc tb"><?= $total ?></td>
  </tr>
</table>

@if($auditoria->resumen_general)
<p class="sec-hdr">RESUMEN EJECUTIVO</p>
<p class="cuerpo">{{ $auditoria->resumen_general }}</p>
@endif

<p class="sec-hdr">AN&Aacute;LISIS DE BRECHAS POR SECCI&Oacute;N</p>
<table>
  <tr>
    <th style="width:35%;">SECCI&Oacute;N</th>
    <th style="width:8%;">SCORE</th>
    <th style="width:12%;">NIVEL DE RIESGO</th>
    <th style="width:45%;">RECOMENDACI&Oacute;N PRIORITARIA</th>
  </tr>
  @foreach(['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'] as $nivel => $etiqueta)
    @foreach($gapsAgrupados[$nivel] as $clave => $sec)
      <tr>
        <td>{{ $sec['titulo'] ?? $clave }}</td>
        <td class="tc">{{ $sec['score'] ?? 0 }}</td>
        <td class="tc tb">{{ $etiqueta }}</td>
        <td>{{ ($sec['recomendaciones'] ?? [])[0] ?? '&mdash;' }}</td>
      </tr>
    @endforeach
  @endforeach
  @if($nAlto === 0 && $nMedio === 0 && $nBajo === 0)
    <tr><td colspan="4" class="tc">No se detectaron brechas de cumplimiento.</td></tr>
  @endif
</table>

<?php
$todasLasBrechas = [];
foreach (['alto', 'medio', 'bajo'] as $nivel) {
    foreach ($gapsAgrupados[$nivel] as $clave => $sec) {
        foreach ($sec['recomendaciones'] ?? [] as $rec) {
            $todasLasBrechas[] = [
                'seccion' => $sec['titulo'] ?? $clave,
                'nivel'   => $nivel,
                'rec'     => $rec,
            ];
        }
    }
}
$top10 = array_slice($todasLasBrechas, 0, 10);
?>

@if(count($top10) > 0)
<p class="sec-hdr">PLAN DE ACCIONES PRIORITARIAS</p>
<table>
  <tr>
    <th style="width:5%;">#</th>
    <th style="width:25%;">SECCI&Oacute;N</th>
    <th style="width:10%;">RIESGO</th>
    <th style="width:60%;">ACCI&Oacute;N RECOMENDADA</th>
  </tr>
  @foreach($top10 as $i => $item)
    <tr>
      <td class="tc">{{ $i + 1 }}</td>
      <td>{{ $item['seccion'] }}</td>
      <td class="tc tb">{{ ucfirst($item['nivel']) }}</td>
      <td>{{ $item['rec'] }}</td>
    </tr>
  @endforeach
</table>
@endif

{{-- ══ HALLAZGOS DETALLADOS (exclusivo versión técnica) ══════════════════ --}}
<p class="sec-hdr">HALLAZGOS DETALLADOS POR SECCI&Oacute;N</p>

@foreach(['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'] as $nivel => $etiqueta)
  @foreach($gapsAgrupados[$nivel] as $clave => $sec)

    <p class="sub-hdr">
      {{ $sec['titulo'] ?? $clave }}
      &mdash; Score: {{ $sec['score'] ?? 0 }}/100 &mdash; Riesgo: {{ $etiqueta }}
    </p>

    @if(!empty($sec['hallazgos']))
      <p class="lbl">Hallazgos:</p>
      @foreach($sec['hallazgos'] as $h)
        <p class="item">&bull;&nbsp;{{ $h }}</p>
      @endforeach
    @endif

    @if(!empty($sec['recomendaciones']))
      <p class="lbl">Recomendaciones:</p>
      @foreach($sec['recomendaciones'] as $r)
        <p class="item">&bull;&nbsp;{{ $r }}</p>
      @endforeach
    @endif

    @if(!empty($sec['articulos_referencia']))
      <p class="lbl">Referencias normativas:
        @foreach($sec['articulos_referencia'] as $art)
          <span class="tag">{{ $art }}</span>&nbsp;
        @endforeach
      </p>
    @endif

  @endforeach
@endforeach

<p class="nota-final">
  Documento confidencial. Esta versi&oacute;n t&eacute;cnica est&aacute; dirigida exclusivamente a los profesionales
  de CES Legal y contiene trazabilidad normativa para uso jur&iacute;dico interno.
</p>

</body>
</html>
