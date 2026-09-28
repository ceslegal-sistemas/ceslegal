{{--
    Acta de Socialización del Reglamento Interno de Trabajo - documento
    jurídico probatorio (2026-09-28, pedido explícito del usuario/su equipo)
    de que un trabajador leyó, comprendió (quiz) y aceptó el RIT en una
    fecha/hora específica. Formal y sobrio (documento legal, no "Legal
    Design" de marketing) - mismo criterio ya aplicado al organigrama del
    RIT: tablas en blanco y negro, sin colores de marca.

    Variables esperadas:
      $aceptacion (App\Models\AceptacionReglamentoInterno)
      $trabajador (App\Models\Trabajador)
      $empresa (App\Models\Empresa)
      $fotoBase64Inline (?string) - data URI, Dompdf sí soporta base64 en PDF
        (a diferencia de los clientes de correo - ver
        gotcha-storage-disco-local-private y el fix de logo en email).
--}}
@php
    $quiz = $aceptacion->quiz_resultado ?? [];
@endphp
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2cm 2.2cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #1a1a1a; line-height: 1.45; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 4pt; }
        h2 { font-size: 10.5pt; border-bottom: 1pt solid #000; padding-bottom: 3pt; margin: 14pt 0 8pt; }
        .sub { text-align: center; font-size: 9pt; color: #444; margin: 0 0 16pt; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
        table.datos td { padding: 3pt 4pt; vertical-align: top; font-size: 9pt; }
        table.datos td.label { font-weight: bold; width: 34%; }
        .hash-box { background: #f2f2f2; border: 0.5pt solid #999; padding: 6pt 8pt; font-family: 'Courier New', monospace; font-size: 7.5pt; word-break: break-all; margin: 6pt 0 14pt; }
        table.quiz { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        table.quiz th, table.quiz td { border: 0.5pt solid #000; padding: 4pt 6pt; font-size: 8.5pt; text-align: left; vertical-align: top; }
        table.quiz th { background: #eee; font-weight: bold; }
        .foto-box { text-align: center; margin: 10pt 0; }
        .foto-box img { width: 140pt; border: 1pt solid #000; }
        .declaracion { border: 1pt solid #000; padding: 8pt 10pt; margin: 10pt 0; font-size: 9pt; }
        .firma { margin-top: 30pt; }
        .footer-legal { margin-top: 18pt; font-size: 7.5pt; color: #555; border-top: 0.5pt solid #999; padding-top: 6pt; }
    </style>
</head>
<body>

    <h1>ACTA DE SOCIALIZACIÓN DEL REGLAMENTO INTERNO DE TRABAJO</h1>
    <p class="sub">{{ $empresa?->razon_social }} - NIT {{ $empresa?->nit }}</p>

    <h2>1. Identificación del trabajador</h2>
    <table class="datos">
        <tr>
            <td class="label">Nombre completo:</td>
            <td>{{ $trabajador->nombre_completo }}</td>
            <td class="label">Documento:</td>
            <td>{{ $trabajador->tipo_documento }} {{ $trabajador->numero_documento }}</td>
        </tr>
        <tr>
            <td class="label">Cargo:</td>
            <td>{{ $trabajador->cargo }}</td>
            <td class="label">Fecha y hora de aceptación:</td>
            <td>{{ $aceptacion->aceptado_en?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm:ss A') }}</td>
        </tr>
        <tr>
            <td class="label">Dirección IP:</td>
            <td>{{ $aceptacion->ip_aceptacion ?? 'No registrada' }}</td>
            <td class="label">Navegador/dispositivo:</td>
            <td style="font-size:7.5pt">{{ \Illuminate\Support\Str::limit((string) $aceptacion->user_agent, 80) }}</td>
        </tr>
    </table>

    <h2>2. Integridad del documento aceptado</h2>
    <p>El texto íntegro del Reglamento Interno de Trabajo presentado al trabajador en el momento de la
        aceptación quedó respaldado por el siguiente hash criptográfico (SHA-256), calculado sobre el
        contenido exacto mostrado. Cualquier alteración posterior del texto del Reglamento produce un
        hash distinto, permitiendo verificar que este documento corresponde a la versión efectivamente
        aceptada.</p>
    <div class="hash-box">SHA-256: {{ $aceptacion->texto_rit_hash }}</div>

    @if(!empty($quiz))
        <h2>3. Quiz de comprensión</h2>
        <p>Antes de aceptar, el trabajador respondió correctamente las siguientes preguntas de
            verdadero/falso sobre el contenido del Reglamento:</p>
        <table class="quiz">
            <tr>
                <th style="width:5%">#</th>
                <th style="width:45%">Pregunta</th>
                <th style="width:15%">Respuesta correcta</th>
                <th style="width:15%">Intentos usados</th>
                <th style="width:20%">Acertó al primer intento</th>
            </tr>
            @foreach($quiz as $i => $pregunta)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $pregunta['pregunta'] ?? '' }}</td>
                    <td>{{ ($pregunta['respuesta_correcta'] ?? false) ? 'Verdadero' : 'Falso' }}</td>
                    <td>{{ count($pregunta['intentos'] ?? []) }}</td>
                    <td>{{ count($pregunta['intentos'] ?? []) === 1 ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>4. Declaración del trabajador</h2>
    <div class="declaracion">
        Declaro que leí y entendí el Reglamento Interno de Trabajo de <strong>{{ $empresa?->razon_social }}</strong>,
        cuyo contenido íntegro corresponde al hash certificado en la Sección 2 de esta acta.
    </div>

    @if($fotoBase64Inline)
        <h2>5. Verificación fotográfica</h2>
        <p>Fotografía capturada en vivo (con prueba de parpadeo) en el momento exacto de esta
            aceptación, como evidencia adicional de identidad.</p>
        <div class="foto-box">
            <img src="{{ $fotoBase64Inline }}" alt="Foto de aceptación">
        </div>
    @endif

    <div class="firma">
        <p>_________________________________________</p>
        <p>{{ $trabajador->nombre_completo }}</p>
        <p>{{ $trabajador->tipo_documento }} {{ $trabajador->numero_documento }}</p>
    </div>

    <div class="footer-legal">
        Documento generado automáticamente por LUPE Legal el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm A') }}
        como registro de la aceptación electrónica del Reglamento Interno de Trabajo, de conformidad con
        la Ley 527 de 1999 sobre validez de mensajes de datos. Acta N.° {{ $aceptacion->id }}.
    </div>

</body>
</html>
