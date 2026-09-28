{{--
    Acta de Terminación de Contrato de Trabajo (con/sin justa causa) - mismo
    tratamiento visual "Legal Design" que preaviso.blade.php (franja de
    título, caja "Importante" con el dato clave, membrete de empresa).

    La cita legal (Art. 62 CST con justa causa / Art. 64 CST sin justa causa)
    se muestra tal como existe en la base de datos real (articulos_legales) -
    nunca parafraseada por el sistema.

    Variables esperadas:
      $municipioEmpresa, $departamentoEmpresa
      $fechaCarta
      $nombreTrabajador, $numeroDocumento
      $nombreEmpresa, $nit, $representanteLegal
      $terminacion (Model TerminacionContrato)
      $fechaTerminacionTexto
      $codigoArticulo, $textoArticuloVerbatim
      $empresa (Model, para el membrete)
--}}
@php
    $iconosDir = public_path('images/contrato-legal-design');
    $icono = function (string $nombre) use ($iconosDir) {
        $ruta = $iconosDir . DIRECTORY_SEPARATOR . $nombre . '.svg';
        if (!is_file($ruta)) {
            return '';
        }
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($ruta));
    };
    $esConJustaCausa = $terminacion->tipo === 'con_justa_causa';
@endphp
<html>

<head>
    <style>
        @page {
            margin: 2.5cm 2.3cm;
        }

        * { box-sizing: border-box; }

        html,
        body {
            font-family: 'Tahoma', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5pt;
            color: #2A2A2A;
        }

        body {
            margin: 0;
            padding: 0;
            line-height: 1.4;
            text-align: justify;
        }

        p {
            margin: 0 0 9pt 0;
        }

        .no-justificar {
            text-align: left;
        }

        table.documento-header {
            width: 100%; border-collapse: collapse; background: #1B5E63;
            margin: 0 0 16pt 0; border-radius: 4px;
        }
        table.documento-header td { padding: 9pt 12pt; color: #fff; vertical-align: middle; }
        table.documento-header td.icono-td { width: 34px; }
        table.documento-header img { width: 24px; height: 24px; }
        table.documento-header .titulo { font-size: 12.5pt; font-weight: bold; }

        .caja { border-radius: 4px; padding: 8pt 10pt; margin: 12pt 0; page-break-inside: avoid; }
        .caja--importante { background: #FBEAEA; }
        .caja-titulo { font-weight: bold; margin: 0 0 3pt 0; }
        .caja-titulo img { width: 11pt; height: 11pt; vertical-align: -1.5pt; margin-right: 3pt; }
        .caja p:last-child { margin-bottom: 0; }

        .cita-verbatim {
            margin: 6pt 0 9pt 0;
            padding: 8pt 10pt;
            border-left: 3px solid #1B5E63;
            background: #F5F7F7;
            font-size: 9.5pt;
            font-style: italic;
        }

        .avoid-break { page-break-inside: avoid; }
    </style>
</head>

<body>

    <table class="documento-header">
        <tr>
            <td class="icono-td"><img src="{{ $icono('parte-08-white') }}" alt=""></td>
            <td><span class="titulo">Acta de Terminación de Contrato de Trabajo</span></td>
        </tr>
    </table>

    <p class="no-justificar">{{ $municipioEmpresa }}, {{ $departamentoEmpresa }}, {{ $fechaCarta }}.</p>

    <p class="no-justificar">
        Señor(a):<br>
        {{ $nombreTrabajador }}.<br>
        C.C./Documento No. {{ $numeroDocumento }}.<br>
        E.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;M.
    </p>

    <p class="no-justificar"><strong>ASUNTO.</strong> Terminación
        {{ $esConJustaCausa ? 'con justa causa' : 'sin justa causa' }} del contrato de trabajo.</p>

    <p>Respetado(a) {{ $nombreTrabajador }}:</p>

    @if($esConJustaCausa)
        <p>Por medio de la presente, {{ $nombreEmpresa }} le informa que su contrato de trabajo termina
            de manera unilateral y con justa causa, a partir del día {{ $fechaTerminacionTexto }}, con
            fundamento en el siguiente motivo:</p>

        <p>{{ $terminacion->motivo }}</p>
    @else
        <p>Por medio de la presente, {{ $nombreEmpresa }} le informa que su contrato de trabajo termina
            de manera unilateral y sin justa causa, a partir del día {{ $fechaTerminacionTexto }}. En
            consecuencia, se reconoce la indemnización que en derecho corresponde.</p>
    @endif

    @if($textoArticuloVerbatim !== '')
        <div class="cita-verbatim">
            <strong>{{ $codigoArticulo }}</strong> del Código Sustantivo del Trabajo: «{{ $textoArticuloVerbatim }}»
        </div>
    @endif

    @unless($esConJustaCausa)
        <div class="caja caja--importante">
            <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Indemnización reconocida</p>
            <p>{{ $terminacion->detalle_calculo }}</p>
            <p><strong>Valor total: ${{ number_format((float) $terminacion->monto_indemnizacion, 2) }}</strong></p>
        </div>
    @endunless

    <div class="avoid-break">
        <p>Le deseamos los mejores éxitos en sus actividades futuras.</p>

        <p>Cordialmente,</p>

        <p style="margin-top:35pt;">
            ________________________________.<br>
            {{ $nombreEmpresa }}.<br>
            NIT. {{ $nit }}.<br>
            {{ $representanteLegal }}.<br>
            Representante legal.
        </p>
    </div>

    @isset($empresa)
    @include('pdfs.components.membrete-empresa', ['empresa' => $empresa])
@endisset

</body>

</html>
