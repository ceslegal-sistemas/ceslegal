{{--
    Resolución de impugnación - mismo tratamiento "Legal Design" de los contratos y el
    preaviso (franja teal con icono, cajas de color con callouts, tablas de datos) más el
    membrete de la empresa (logo, marca de agua y pie de página con sus datos de contacto).
    Antes era un documento de texto plano sin logo, marca de agua ni pie.

    Variables:
      $proceso, $impugnacion, $trabajador, $empresa
      $fecha (Carbon, hoy), $decisionTexto, $sancionOriginalTexto, $nuevaSancionTexto (?string)
--}}
@php
    $iconosDir = public_path('images/contrato-legal-design');
    $icono = function (string $nombre) use ($iconosDir) {
        $ruta = $iconosDir . DIRECTORY_SEPARATOR . $nombre . '.svg';

        return is_file($ruta) ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($ruta)) : '';
    };

    $decision = $impugnacion->decision_final;
    $resumen = match ($decision) {
        'confirma_sancion' => 'Revisamos con cuidado su impugnación y la sanción se mantiene: ' . $sancionOriginalTexto . '.',
        'revoca_sancion' => 'Revisamos con cuidado su impugnación y la sanción queda sin efecto: ' . $sancionOriginalTexto . ' fue revocada.',
        'modifica_sancion' => 'Revisamos con cuidado su impugnación y la sanción cambia: de ' . $sancionOriginalTexto . ' a ' . $nuevaSancionTexto . '.',
        default => 'Ya se emitió la decisión sobre su impugnación.',
    };
    $claseDecision = $decision === 'revoca_sancion' ? 'caja--simple' : 'caja--importante';
    $medio = $impugnacion->medio_recepcion === 'portal_web' ? 'portal en línea de LUPE Legal (identidad verificada con un código enviado a su correo)' : 'otro medio, registrada por la empresa';
    $fechaImpugnacion = $impugnacion->fecha_impugnacion?->timezone('America/Bogota')->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $fechaNotificacion = $proceso->fecha_notificacion?->timezone('America/Bogota')->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $numPruebas = collect($impugnacion->pruebas_adicionales ?? [])->count();
@endphp
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2.4cm 2.3cm; }
        * { box-sizing: border-box; }
        html, body { font-family: 'Tahoma', 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; color: #2A2A2A; }
        body { margin: 0; padding: 0; line-height: 1.45; text-align: justify; }
        p { margin: 0 0 7pt 0; }
        strong, b { font-weight: bold; }

        /* ===== Encabezados (franja teal con icono) ===== */
        table.documento-header { width: 100%; border-collapse: collapse; background: #1B5E63; margin: 0 0 10pt 0; border-radius: 4px; }
        table.documento-header td { padding: 9pt 12pt; color: #fff; vertical-align: middle; }
        table.documento-header td.icono-td { width: 34px; }
        table.documento-header img { width: 24px; height: 24px; }
        table.documento-header .titulo { font-size: 13pt; font-weight: bold; }
        table.seccion-header { width: 100%; border-collapse: collapse; background: #1B5E63; margin: 14pt 0 7pt 0; border-radius: 4px; page-break-after: avoid; }
        table.seccion-header td { padding: 6pt 10pt; color: #fff; vertical-align: middle; }
        table.seccion-header td.icono-td { width: 30px; }
        table.seccion-header img { width: 18px; height: 18px; }
        table.seccion-header .titulo { font-size: 10.5pt; font-weight: bold; }

        .encabezado-empresa { text-align: center; margin: 0 0 8pt 0; }
        .encabezado-empresa .nombre { font-size: 12pt; font-weight: bold; margin: 0; }
        .encabezado-empresa .meta { font-size: 9pt; color: #555; margin: 1pt 0 0 0; }

        /* ===== Tabla de datos ===== */
        table.tabla-datos { width: 100%; border-collapse: collapse; margin: 0 0 10pt 0; border: 1px solid #D8E2E1; }
        table.tabla-datos td { border-bottom: 1px solid #D8E2E1; padding: 5pt 8pt; vertical-align: top; }
        table.tabla-datos tr:last-child td { border-bottom: none; }
        table.tabla-datos td.label { font-weight: bold; width: 36%; background: #F5FAFA; }

        /* ===== Cajas de color ===== */
        .caja { border-radius: 4px; padding: 8pt 10pt; margin: 0 0 10pt 0; page-break-inside: avoid; }
        .caja--simple { background: #E4F1F1; }
        .caja--importante { background: #FBEAEA; }
        .caja--nota { background: #FBF0DD; }
        .caja-titulo { font-weight: bold; margin: 0 0 3pt 0; }
        .caja-titulo img { width: 11pt; height: 11pt; vertical-align: -1.5pt; margin-right: 3pt; }
        .caja p:last-child { margin-bottom: 0; }
        .decision { text-align: center; font-size: 12.5pt; font-weight: bold; letter-spacing: 0.4pt; margin: 2pt 0 6pt 0; }
        .cita { font-style: italic; }
        .no-justificar { text-align: left; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>

    <table class="documento-header">
        <tr>
            <td class="icono-td"><img src="{{ $icono('parte-08-white') }}" alt=""></td>
            <td><span class="titulo">Resolución de impugnación</span></td>
        </tr>
    </table>

    <div class="encabezado-empresa">
        <p class="nombre">{{ $empresa->nombre_completo }}</p>
        <p class="meta">NIT {{ $empresa->nit }} · Proceso {{ $proceso->codigo }} · {{ $fecha->isoFormat('D [de] MMMM [de] YYYY') }}</p>
    </div>

    {{-- En pocas palabras --}}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En pocas palabras</p>
        <p>{{ $resumen }}</p>
    </div>

    <table class="tabla-datos">
        <tr><td class="label">Trabajador(a)</td><td>{{ $trabajador->nombre_completo }}</td></tr>
        <tr><td class="label">{{ $trabajador->tipo_documento }}</td><td>{{ $trabajador->numero_documento }}</td></tr>
        <tr><td class="label">Cargo</td><td>{{ $trabajador->cargo }}</td></tr>
        <tr><td class="label">Sanción impugnada</td><td>{{ $sancionOriginalTexto }}@if ($fechaNotificacion) · notificada el {{ $fechaNotificacion }}@endif</td></tr>
        <tr><td class="label">Impugnación</td><td>{{ $impugnacion->radicado() }}@if ($fechaImpugnacion) · presentada el {{ $fechaImpugnacion }}@endif</td></tr>
        <tr><td class="label">Decisión</td><td><strong>{{ $decisionTexto }}</strong>@if ($nuevaSancionTexto): {{ $nuevaSancionTexto }}@endif</td></tr>
    </table>

    <p class="no-justificar"><strong>Señor(a):</strong> {{ $trabajador->nombre_completo }}</p>
    <p class="no-justificar"><strong>Asunto:</strong> Resolución de la impugnación presentada contra la sanción disciplinaria del proceso {{ $proceso->codigo }}.</p>

    {{-- 1 --}}
    <table class="seccion-header"><tr><td class="icono-td"><img src="{{ $icono('parte-01-white') }}" alt=""></td><td><span class="titulo">1. Antecedentes</span></td></tr></table>
    <p>
        @if ($fechaNotificacion) El {{ $fechaNotificacion }} @else En su momento @endif se le notificó la sanción disciplinaria de
        <strong>{{ $sancionOriginalTexto }}</strong>, como resultado del proceso disciplinario {{ $proceso->codigo }}.
        @if ($fechaImpugnacion) El {{ $fechaImpugnacion }} @else Posteriormente @endif usted presentó una impugnación a través del {{ $medio }}.
        @if ($numPruebas > 0) Con ella aportó {{ $numPruebas }} archivo(s) como prueba. @endif
    </p>

    {{-- 2 --}}
    <table class="seccion-header"><tr><td class="icono-td"><img src="{{ $icono('parte-02-white') }}" alt=""></td><td><span class="titulo">2. Lo que usted alegó</span></td></tr></table>
    <div class="caja caja--nota">
        <p class="caja-titulo"><img src="{{ $icono('callout-nota') }}" alt="">Sus motivos</p>
        <p class="cita">{!! nl2br(e($impugnacion->motivos_impugnacion)) !!}</p>
    </div>

    {{-- 3 --}}
    <table class="seccion-header"><tr><td class="icono-td"><img src="{{ $icono('parte-03-white') }}" alt=""></td><td><span class="titulo">3. Análisis y fundamento</span></td></tr></table>
    <p>Se revisaron los argumentos de su impugnación, las pruebas aportadas y el expediente completo del proceso disciplinario. Esto es lo que se tuvo en cuenta para decidir:</p>
    <p>{!! nl2br(e($impugnacion->fundamento_decision)) !!}</p>

    {{-- 4 --}}
    <table class="seccion-header"><tr><td class="icono-td"><img src="{{ $icono('parte-04-white') }}" alt=""></td><td><span class="titulo">4. Decisión</span></td></tr></table>
    <div class="caja {{ $claseDecision }}">
        <p class="caja-titulo"><img src="{{ $icono($decision === 'revoca_sancion' ? 'callout-simple' : 'callout-importante') }}" alt="">Decisión final</p>
        <p class="decision">{{ $decisionTexto }}</p>
        @if ($decision === 'confirma_sancion')
            <p>Se confirma en todas sus partes la sanción disciplinaria de <strong>{{ $sancionOriginalTexto }}</strong> impuesta en el proceso {{ $proceso->codigo }}.</p>
        @elseif ($decision === 'revoca_sancion')
            <p>Se revoca la sanción disciplinaria de <strong>{{ $sancionOriginalTexto }}</strong> impuesta en el proceso {{ $proceso->codigo }}, dejándola sin efecto alguno.</p>
        @elseif ($decision === 'modifica_sancion')
            <p>Se modifica la sanción disciplinaria: pasa de <strong>{{ $sancionOriginalTexto }}</strong> a <strong>{{ $nuevaSancionTexto }}</strong>.</p>
        @endif
    </div>

    {{-- 5 --}}
    <table class="seccion-header"><tr><td class="icono-td"><img src="{{ $icono('parte-05-white') }}" alt=""></td><td><span class="titulo">5. Qué significa para usted</span></td></tr></table>
    @if ($decision === 'confirma_sancion')
        <p>La sanción mantiene plena vigencia y debe cumplirse en los términos en que se le notificó inicialmente.</p>
    @elseif ($decision === 'revoca_sancion')
        <p>Al revocarse la sanción, el proceso disciplinario queda cerrado y este caso no tiene efectos negativos en su expediente laboral.</p>
    @elseif ($decision === 'modifica_sancion')
        <p>La nueva sanción de <strong>{{ $nuevaSancionTexto }}</strong> se aplica a partir de la fecha de esta notificación, en los términos del Reglamento Interno de Trabajo.</p>
    @endif
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Esta decisión es definitiva y pone fin al proceso disciplinario {{ $proceso->codigo }}. Si tiene preguntas, comuníquese con el área de Recursos Humanos de {{ $empresa->nombre_completo }}.</p>
    </div>

    <div class="avoid-break">
        <p>Cordialmente,</p>
        <p style="margin-top:35pt;">
            ________________________________<br>
            <strong>{{ $empresa->representante_legal }}</strong><br>
            Representante legal<br>
            {{ $empresa->nombre_completo }}<br>
            NIT {{ $empresa->nit }}
        </p>
    </div>

    {{-- Membrete: logo, marca de agua y pie de página con los datos de contacto de la empresa --}}
    @include('pdfs.components.membrete-empresa', ['empresa' => $empresa])

</body>
</html>
