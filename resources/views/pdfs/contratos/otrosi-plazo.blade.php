{{--
    Otrosí de Plazo (prórroga de contrato a término fijo) - texto real
    provisto por el usuario (FORMATO DE OTROSÍ DE PLAZO-2 DE SEPTIEMBRE DE
    2026.docx). El texto es literal - no resumir ni reformular ninguna
    cláusula. Sin IA de por medio: se sustituyen variables sobre el texto
    exacto, mismo criterio ya aplicado a las 3 plantillas de contrato base.

    Tratamiento visual "Legal Design" (2026-09-04, mismo look que
    termino-fijo/termino-indefinido/obra-labor): franja teal de título,
    caja "Importante" resaltando la nueva fecha de finalización, y
    membrete de empresa - a pedido explícito del usuario ("necesito que
    use el mismo formato y plantilla del contrato... estética Legal
    Design completa"). El documento sigue siendo de una sola "sección"
    (no tiene PARTE 01/02/etc. ni mapa del contrato - es un otrosí corto,
    no un contrato completo).

    Variables esperadas:
      $numeroOtrosi
      $nombreEmpresa, $nit, $representanteLegal
      $municipioEmpresa, $departamentoEmpresa
      $nombreTrabajador, $tipoDocumentoLabel, $numeroDocumento
      $fechaContratoOriginalTexto (fecha del contrato original)
      $duracionInicialTexto (duración del período que se vence)
      $duracionProrrogaTexto (duración de la nueva prórroga)
      $fechaFinAnteriorTexto, $fechaFinNuevaTexto
      $fechaFirma
      $empresa (Model, para el membrete - puede no venir en vistas antiguas)
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

        .clausula-titulo {
            font-weight: bold;
        }

        /* ===== Encabezado de título (mismo lenguaje visual que las
             PARTE de los contratos Legal Design) ===== */
        table.documento-header {
            width: 100%; border-collapse: collapse; background: #1B5E63;
            margin: 0 0 16pt 0; border-radius: 4px;
        }
        table.documento-header td { padding: 9pt 12pt; color: #fff; vertical-align: middle; }
        table.documento-header td.icono-td { width: 34px; }
        table.documento-header img { width: 24px; height: 24px; }
        table.documento-header .titulo { font-size: 12.5pt; font-weight: bold; }

        /* ===== Caja "Importante" ===== */
        .caja { border-radius: 4px; padding: 8pt 10pt; margin: 12pt 0; page-break-inside: avoid; }
        .caja--importante { background: #FBEAEA; }
        .caja-titulo { font-weight: bold; margin: 0 0 3pt 0; }
        .caja-titulo img { width: 11pt; height: 11pt; vertical-align: -1.5pt; margin-right: 3pt; }
        .caja p:last-child { margin-bottom: 0; }

        table.firma {
            width: 100%;
            margin-top: 50pt;
            border-collapse: collapse;
        }

        table.firma td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding-top: 30pt;
            font-size: 10.5pt;
        }
    </style>
</head>

<body>

    <table class="documento-header">
        <tr>
            <td class="icono-td"><img src="{{ $icono('parte-07-white') }}" alt=""></td>
            <td><span class="titulo">Otrosí de Plazo No. {{ $numeroOtrosi }} entre {{ $nombreEmpresa }}, y {{ $nombreTrabajador }}</span></td>
        </tr>
    </table>

    <p>Entre {{ $representanteLegal }}, identificado como aparece al pie de su firma, en su condición de
        Gerente de la empresa {{ $nombreEmpresa }}, sociedad con domicilio en el municipio de
        {{ $municipioEmpresa }}, {{ $departamentoEmpresa }}; identificada con el NIT. {{ $nit }}, quien en
        adelante se denomina EL EMPLEADOR por una parte y por la otra {{ $nombreTrabajador }} identificado
        como aparece al pie de su firma, quien se denomina en adelante EL TRABAJADOR, convenimos celebrar el
        presente otrosí al contrato de trabajo suscrito el {{ $fechaContratoOriginalTexto }} por las partes,
        previas las siguientes consideraciones:</p>

    <p>Que, entre el EMPLEADOR y EL TRABAJADOR, el {{ $fechaContratoOriginalTexto }} se celebró un contrato
        de trabajo a término fijo, el cual tenía un plazo de {{ $duracionInicialTexto }}.</p>

    <p>Que el contrato inicialmente pactado se ha decidido prorrogar por un término de
        {{ $duracionProrrogaTexto }}.</p>

    <p>Que las PARTES vienen a aclarar en el presente otrosí o adendo, la correspondiente prórroga del
        contrato de trabajo.</p>

    <p>Conforme las anteriores consideraciones, las PARTES, ACUERDAN:</p>

    <p><span class="clausula-titulo">PRIMERA. - RATIFICACIONES:</span> Las PARTES ratifican en todo su
        contenido y tienen como ciertos los hechos enunciados en las CONSIDERACIONES y/o DECLARACIONES
        conjuntas de este documento.</p>

    <p><span class="clausula-titulo">SEGUNDA. - DURACIÓN:</span> Con la presente cláusula se modifica la
        duración del contrato inicialmente pactado entre las partes; el cual finalizaba el
        {{ $fechaFinAnteriorTexto }}, por lo anterior, se prorrogará el contrato de trabajo hasta el
        {{ $fechaFinNuevaTexto }}. No obstante, si antes de la fecha de vencimiento del término estipulado,
        ninguna de las partes avisare por escrito a la otra su determinación de no prorrogar el contrato, con
        una antelación no inferior a treinta (30) días, éste se entenderá renovado por un período igual al
        inicialmente pactado. Para todos los efectos, este contrato podrá prorrogarse hasta por tres (3)
        períodos iguales o inferiores al inicialmente pactado, al cabo de los cuales el término de renovación
        no puede ser inferior a un (1) año, y así sucesivamente.</p>

    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Con este otrosí, tu contrato queda prorrogado hasta el <strong>{{ $fechaFinNuevaTexto }}</strong>.
            Si ninguna de las partes avisa por escrito lo contrario con al menos 30 días de anticipación a esa
            fecha, el contrato se renovará automáticamente por un período igual.</p>
    </div>

    <p><span class="clausula-titulo">TERCERA. – EFECTOS:</span> Para todos los efectos legales se deja
        constancia que las demás cláusulas del contrato principal de trabajo no sufren ninguna modificación y
        en consecuencia continúan vigentes.</p>

    <p>Las partes expresan que este documento contiene los acuerdos a que han llegado de manera voluntaria,
        sin ningún vicio de la voluntad como error, fuerza o dolo y en prueba de ello imponen su firma en dos
        ejemplares del mismo tenor y valor, en el municipio y fecha que se indican a continuación:</p>

    <p><strong>MUNICIPIO:</strong> {{ $municipioEmpresa }}, {{ $departamentoEmpresa }}.
        &nbsp;&nbsp;&nbsp;&nbsp;<strong>FECHA:</strong> {{ $fechaFirma }}.</p>

    <table class="firma">
        <tr>
            <td>
                <strong>EL EMPLEADOR</strong><br>
                {{ $nombreEmpresa }}<br>
                NIT. {{ $nit }}<br>
                {{ $representanteLegal }}<br>
                Representante legal
            </td>
            <td>
                <strong>EL TRABAJADOR</strong><br>
                {{ $nombreTrabajador }}<br>
                {{ ucfirst($tipoDocumentoLabel) }} No. {{ $numeroDocumento }}
            </td>
        </tr>
    </table>

    <p style="margin-top:30pt;">Con la presente firma certifico que EL EMPLEADOR ha hecho entrega de una
        copia del OTROSÍ DE PLAZO No. {{ $numeroOtrosi }}, al señor(a) {{ $nombreTrabajador }}.</p>

    @isset($empresa)
    @include('pdfs.components.membrete-empresa', ['empresa' => $empresa])
@endisset

</body>

</html>
