{{--
    Preaviso de no renovación de contrato a término fijo - texto real
    provisto por el usuario (FORMATO DE PREAVISO-1 DE SEPTIEMBRE DE
    2026.docx). Texto literal - no resumir ni reformular. Sin IA de por
    medio: se sustituyen variables sobre el texto exacto.

    Tratamiento visual "Legal Design" (2026-09-04, mismo look que
    termino-fijo/termino-indefinido/obra-labor): franja teal de título,
    caja "Importante" resaltando la fecha de finalización del contrato, y
    membrete de empresa - a pedido explícito del usuario ("necesito que
    use el mismo formato y plantilla del contrato... estética Legal
    Design completa"). Sigue siendo una carta de una sola página, sin
    PARTE 01/02/etc. ni mapa del contrato.

    Variables esperadas:
      $municipioEmpresa, $departamentoEmpresa
      $fechaCarta
      $nombreTrabajador, $tipoDocumentoLabel, $numeroDocumento
      $fechaContratoOriginalTexto
      $fechaFinContratoTexto
      $nombreEmpresa, $nit, $representanteLegal
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

        .no-justificar {
            text-align: left;
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
    </style>
</head>

<body>

    <table class="documento-header">
        <tr>
            <td class="icono-td"><img src="{{ $icono('parte-08-white') }}" alt=""></td>
            <td><span class="titulo">Comunicación de No Prórroga al contrato de trabajo</span></td>
        </tr>
    </table>

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

    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Tu contrato de trabajo finaliza el <strong>{{ $fechaFinContratoTexto }}</strong>. Ese día recibirás
            la autorización para el examen médico de egreso, tu liquidación, el certificado laboral, la carta
            de autorización de retiro de cesantías y la constancia de pago de aportes a seguridad social de
            los últimos 3 meses.</p>
    </div>

    <p>Le deseamos los mejores éxitos en sus actividades futuras.</p>

    <p>Cordialmente,</p>

    <p style="margin-top:35pt;">
        ________________________________.<br>
        {{ $nombreEmpresa }}.<br>
        NIT. {{ $nit }}.<br>
        {{ $representanteLegal }}.<br>
        Representante legal.
    </p>

    @isset($empresa)
    @include('pdfs.components.membrete-empresa', ['empresa' => $empresa])
@endisset

</body>

</html>
