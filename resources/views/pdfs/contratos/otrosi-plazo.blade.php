{{--
    Otrosí de Plazo (prórroga de contrato a término fijo) - texto real
    provisto por el usuario (FORMATO DE OTROSÍ DE PLAZO-2 DE SEPTIEMBRE DE
    2026.docx). El texto es literal - no resumir ni reformular ninguna
    cláusula. Sin IA de por medio: se sustituyen variables sobre el texto
    exacto, mismo criterio ya aplicado a las 3 plantillas de contrato base.

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
--}}
<html>

<head>
    <style>
        @page {
            margin: 2.5cm 2.3cm;
        }

        html,
        body {
            font-family: 'Tahoma', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5pt;
            color: #000;
        }

        body {
            margin: 0;
            padding: 0;
            line-height: 1.4;
            text-align: justify;
        }

        h1 {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 16pt 0;
        }

        p {
            margin: 0 0 9pt 0;
        }

        .clausula-titulo {
            font-weight: bold;
        }

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

    <h1>Otrosí de Plazo No. {{ $numeroOtrosi }} entre {{ $nombreEmpresa }}, y {{ $nombreTrabajador }}</h1>

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

</body>

</html>
