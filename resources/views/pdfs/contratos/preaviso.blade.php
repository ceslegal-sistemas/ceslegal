{{--
    Preaviso de no renovación de contrato a término fijo - texto real
    provisto por el usuario (FORMATO DE PREAVISO-1 DE SEPTIEMBRE DE
    2026.docx). Texto literal - no resumir ni reformular. Sin IA de por
    medio: se sustituyen variables sobre el texto exacto.

    Variables esperadas:
      $municipioEmpresa, $departamentoEmpresa
      $fechaCarta
      $nombreTrabajador, $tipoDocumentoLabel, $numeroDocumento
      $fechaContratoOriginalTexto
      $fechaFinContratoTexto
      $nombreEmpresa, $nit, $representanteLegal
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

        p {
            margin: 0 0 9pt 0;
        }

        .no-justificar {
            text-align: left;
        }
    </style>
</head>

<body>

    <p class="no-justificar">{{ $municipioEmpresa }}, {{ $departamentoEmpresa }}, {{ $fechaCarta }}.</p>

    <p class="no-justificar">
        Señor:<br>
        {{ $nombreTrabajador }}.<br>
        {{ ucfirst($tipoDocumentoLabel) }} No. {{ $numeroDocumento }}.<br>
        E.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;M.
    </p>

    <p class="no-justificar"><strong>ASUNTO.</strong> Comunicación de No Prórroga al contrato de trabajo
        suscrito el {{ $fechaContratoOriginalTexto }}.</p>

    <p>Respetado(a) {{ $nombreTrabajador }}:</p>

    <p>Por medio de la presente, le informamos que {{ $nombreEmpresa }}, ha decidido no prorrogar su
        contrato de trabajo, en concordancia al artículo 46 del Código Sustantivo de Trabajo modificado por el
        artículo 6 de la ley 2466 de 2025, por lo cual, se le informa con más de Treinta (30) días de
        anticipación la decisión que ha tomado la empresa, de igual manera, se le informa que su contrato de
        trabajo finalizará el día {{ $fechaFinContratoTexto }}, fecha en la cual, se le hará entrega de la
        autorización para la realización del examen médico de egreso, liquidación de acreencias laborales,
        certificado laboral, carta de autorización de retiro de cesantías y constancia del pago de los aportes
        a seguridad social de los últimos tres (3) meses.</p>

    <p>Le deseamos los mejores éxitos en sus actividades futuras.</p>

    <p>Cordialmente,</p>

    <p style="margin-top:35pt;">
        ________________________________.<br>
        {{ $nombreEmpresa }}.<br>
        NIT. {{ $nit }}.<br>
        {{ $representanteLegal }}.<br>
        Representante legal.
    </p>

</body>

</html>
