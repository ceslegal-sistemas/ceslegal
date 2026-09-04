{{--
    Contrato Individual de Trabajo a Término Fijo - "Legal Design" del jefe.
    El texto de cada una de las 18 "PARTE" es una reescritura en lenguaje
    llano hecha por el jefe (abogado) del contrato original de 29 cláusulas
    - decisión explícita del usuario: este texto REEMPLAZA el contrato
    literal (ya no conviven ambos). El literal de 29 cláusulas sigue
    disponible en el historial de git si hiciera falta recuperarlo.

    Fidelidad de diseño: colores, orden de secciones y textos son los del
    docx real del jefe ("Contrato de Trabajo a Término Fijo - Legal Design
    (3).docx"), no una reinterpretación - ver mockup aprobado por el
    usuario antes de este cambio. Los 19 íconos son PLACEHOLDER (extraídos
    del propio docx del jefe) hasta que el usuario entregue los definitivos
    de Lordicon en formato estático (PNG/SVG - Lottie no renderiza en
    Dompdf), en cuyo caso solo hay que reemplazar los archivos en
    public/images/contrato-legal-design/ sin tocar esta vista.

    Variables esperadas (todas provistas por
    SolicitudContratoIAService::generarHTMLDesdeVista()):
      $nombreEmpresa, $nit, $direccionEmpresa, $telefonoEmpresa
      $representanteLegal, $representanteLegalCedula (puede ser null)
      $nombreTrabajador, $tipoDocumentoLabel, $numeroDocumento
      $direccionTrabajador, $telefonoTrabajador, $emailTrabajador (pueden ser null)
      $cargo, $salarioFormateado, $salarioEnLetras
      $periodoPagoLabel, $periodoPagoFrase
      $lugarLabores, $lugarContratacion
      $fechaInicio, $fechaFin, $duracionTexto, $fechaFirma
      $objetoJuridico (HTML ya escapado por el llamador, puede ir vacío)
      $diaDescansoObligatorio
      $faltasGravesOrigen, $faltasGravesGrave, $faltasGravesGravisima
--}}
@php
    // Íconos reales de Lordicon (colección doodle-motif, SVG estático - no
    // Lottie, que no renderiza en Dompdf), elegidos y descargados por el
    // usuario. Cada "PARTE" tiene una versión normal (trazo negro, para el
    // mapa del contrato sobre fondo claro) y una versión "-white" (colores
    // invertidos, para la franja teal sólida del encabezado de cada parte).
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
            margin: 2cm 2.3cm;
        }

        * { box-sizing: border-box; }

        html, body {
            font-family: 'Tahoma', 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            color: #2A2A2A;
        }

        body {
            margin: 0;
            padding: 0;
            line-height: 1.4;
            text-align: justify;
        }

        p { margin: 0 0 7pt 0; }
        strong, b { font-weight: bold; }

        /* ===== Portada ===== */
        .portada { text-align: center; margin-bottom: 4pt; }
        .portada img { width: 64px; height: 64px; margin-bottom: 8pt; }
        .portada h1 {
            font-size: 15pt; font-weight: bold; text-transform: uppercase;
            margin: 0 0 14pt 0; line-height: 1.25;
        }

        /* ===== Títulos de sección genéricos (info, cómo leer, mapa) ===== */
        .seccion-titulo {
            font-size: 11.5pt; font-weight: bold; margin: 14pt 0 8pt 0;
        }

        /* ===== Tabla de datos (info general + duración/prórrogas) ===== */
        table.tabla-datos {
            width: 100%; border-collapse: collapse; margin: 0 0 12pt 0;
            border: 1px solid #D8E2E1;
        }
        table.tabla-datos td {
            border-bottom: 1px solid #D8E2E1; padding: 5pt 8pt; vertical-align: top;
        }
        table.tabla-datos tr:last-child td { border-bottom: none; }
        table.tabla-datos td.label {
            font-weight: bold; width: 40%; background: #F5FAFA;
        }

        /* ===== Cajas de color ===== */
        .caja { border-radius: 4px; padding: 7pt 9pt; margin: 0 0 9pt 0; page-break-inside: avoid; }
        .caja--simple { background: #E4F1F1; }
        .caja--importante { background: #FBEAEA; }
        .caja--nota { background: #FBF0DD; }
        .caja-titulo { font-weight: bold; margin: 0 0 3pt 0; }
        .caja-titulo img { width: 11pt; height: 11pt; vertical-align: -1.5pt; margin-right: 3pt; }
        .caja p:last-child { margin-bottom: 0; }

        /* ===== Mapa del contrato ===== */
        table.tabla-mapa { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
        table.tabla-mapa td {
            width: 50%; background: #F5FAFA; padding: 5pt 8pt; vertical-align: middle;
            border: 3px solid #fff;
        }
        table.tabla-mapa img { width: 16px; height: 16px; vertical-align: middle; margin-right: 5pt; }
        table.tabla-mapa .mapa-texto { font-size: 8.5pt; }
        a.mapa-link { color: inherit; text-decoration: none; display: block; }

        /* ===== Encabezado de cada PARTE ===== */
        table.parte-header {
            width: 100%; border-collapse: collapse; background: #1B5E63;
            margin: 16pt 0 9pt 0; border-radius: 4px;
        }
        table.parte-header td { padding: 7pt 10pt; color: #fff; vertical-align: middle; }
        table.parte-header td.parte-icono-td { width: 30px; }
        table.parte-header img { width: 22px; height: 22px; }
        .parte-eyebrow { display: block; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4pt; opacity: 0.85; }
        .parte-titulo { font-size: 11.5pt; font-weight: bold; }

        /* ===== Bloques de viñetas por tema (Parte 03 y Parte 08) ===== */
        .bloque-bullets { border: 1px solid #D8E2E1; border-radius: 4px; padding: 6pt 9pt; margin-bottom: 7pt; page-break-inside: avoid; }
        .bloque-bullets-titulo { font-weight: bold; margin: 0 0 3pt 0; }
        .bloque-bullets ul { margin: 0; padding-left: 14pt; }
        .bloque-bullets li { margin-bottom: 2pt; }

        /* ===== Glosario ===== */
        table.tabla-glosario { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
        table.tabla-glosario td {
            border-bottom: 1px solid #D8E2E1; padding: 5pt 8pt; vertical-align: top;
        }
        table.tabla-glosario td.termino { font-weight: bold; width: 32%; }

        /* ===== Firmas ===== */
        table.firma { width: 100%; margin-top: 40pt; border-collapse: collapse; }
        table.firma td { width: 50%; vertical-align: top; padding-top: 30pt; line-height: 1.35; }
        table.firma td .linea { border-top: 1px solid #000; margin: 0 15pt 5pt 15pt; }

        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>

<body>

    {{-- ===================== PORTADA ===================== --}}
    <div class="portada">
        <img src="{{ $icono('portada') }}" alt="">
        <h1>Contrato de Trabajo a Término Fijo</h1>
    </div>

    {{-- ===================== INFORMACIÓN GENERAL ===================== --}}
    <p class="seccion-titulo">Información general del contrato</p>
    <table class="tabla-datos">
        <tr><td class="label">Empleador</td><td>{{ $nombreEmpresa }} &middot; NIT. {{ $nit }}</td></tr>
        <tr><td class="label">Trabajador(a)</td><td>{{ $nombreTrabajador }}</td></tr>
        <tr><td class="label">N.&ordm; de identificación</td><td>{{ ucfirst($tipoDocumentoLabel) }} N.&ordm; {{ $numeroDocumento }}</td></tr>
        <tr><td class="label">Dirección</td><td>{{ $direccionTrabajador ?: 'No registrada' }}</td></tr>
        <tr><td class="label">Teléfono</td><td>{{ $telefonoTrabajador ?: 'No registrado' }}</td></tr>
        <tr><td class="label">Correo electrónico</td><td>{{ $emailTrabajador ?: 'No registrado' }}</td></tr>
        <tr><td class="label">Cargo</td><td>{{ $cargo }}</td></tr>
        <tr><td class="label">Salario mensual</td><td>$ {{ $salarioFormateado }} COP ({{ $salarioEnLetras }})</td></tr>
        <tr><td class="label">Lugar de trabajo</td><td>{{ $lugarLabores }}</td></tr>
        <tr><td class="label">Duración</td><td>{{ $duracionTexto }}</td></tr>
        <tr><td class="label">Fecha de inicio</td><td>{{ $fechaInicio }}</td></tr>
        <tr><td class="label">Fecha de finalización</td><td>{{ $fechaFin }}</td></tr>
    </table>

    {{-- ===================== CÓMO LEER ESTE DOCUMENTO ===================== --}}
    <p class="seccion-titulo">Cómo leer este documento</p>
    <p>Para que el contrato sea fácil de entender, usamos dos tipos de recuadros de color a lo largo del documento:</p>
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Así explicamos, en lenguaje cotidiano, de qué trata cada sección del contrato.</p>
    </div>
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Advertencias sobre consecuencias legales que debes tener muy presentes.</p>
    </div>

    {{-- ===================== MAPA DEL CONTRATO ===================== --}}
    <p class="seccion-titulo">Mapa del contrato</p>
    <p>Así está organizado este contrato. Puedes ir directo a la sección que necesites.</p>
    <table class="tabla-mapa">
        <tr>
            <td><a class="mapa-link" href="#parte-01"><img src="{{ $icono('parte-01') }}" alt=""><span class="mapa-texto"><strong>1.</strong> Quiénes firman y qué normas rigen este contrato.</span></a></td>
            <td><a class="mapa-link" href="#parte-02"><img src="{{ $icono('parte-02') }}" alt=""><span class="mapa-texto"><strong>2.</strong> Cómo y dónde te notifica la empresa.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-03"><img src="{{ $icono('parte-03') }}" alt=""><span class="mapa-texto"><strong>3.</strong> Qué se espera de ti en el día a día.</span></a></td>
            <td><a class="mapa-link" href="#parte-04"><img src="{{ $icono('parte-04') }}" alt=""><span class="mapa-texto"><strong>4.</strong> Cuánto y cómo te pagan.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-05"><img src="{{ $icono('parte-05') }}" alt=""><span class="mapa-texto"><strong>5.</strong> Horarios, horas extra y turnos.</span></a></td>
            <td><a class="mapa-link" href="#parte-06"><img src="{{ $icono('parte-06') }}" alt=""><span class="mapa-texto"><strong>6.</strong> Tu día de descanso y los recargos.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-07"><img src="{{ $icono('parte-07') }}" alt=""><span class="mapa-texto"><strong>7.</strong> Cuánto dura el contrato y sus prórrogas.</span></a></td>
            <td><a class="mapa-link" href="#parte-08"><img src="{{ $icono('parte-08') }}" alt=""><span class="mapa-texto"><strong>8.</strong> Justas causas y faltas graves.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-09"><img src="{{ $icono('parte-09') }}" alt=""><span class="mapa-texto"><strong>9.</strong> Qué pasa si te enfermas.</span></a></td>
            <td><a class="mapa-link" href="#parte-10"><img src="{{ $icono('parte-10') }}" alt=""><span class="mapa-texto"><strong>10.</strong> La reserva de la información de la empresa.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-11"><img src="{{ $icono('parte-11') }}" alt=""><span class="mapa-texto"><strong>11.</strong> Lo que creas en tu trabajo y el uso de tu imagen.</span></a></td>
            <td><a class="mapa-link" href="#parte-12"><img src="{{ $icono('parte-12') }}" alt=""><span class="mapa-texto"><strong>12.</strong> Los equipos que te entrega la empresa.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-13"><img src="{{ $icono('parte-13') }}" alt=""><span class="mapa-texto"><strong>13.</strong> Cómo se usan tus datos personales.</span></a></td>
            <td><a class="mapa-link" href="#parte-14"><img src="{{ $icono('parte-14') }}" alt=""><span class="mapa-texto"><strong>14.</strong> Capacitación y ajustes en tus condiciones.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-15"><img src="{{ $icono('parte-15') }}" alt=""><span class="mapa-texto"><strong>15.</strong> Qué se te puede descontar del salario.</span></a></td>
            <td><a class="mapa-link" href="#parte-16"><img src="{{ $icono('parte-16') }}" alt=""><span class="mapa-texto"><strong>16.</strong> Vigencia y modificaciones del contrato.</span></a></td>
        </tr>
        <tr>
            <td><a class="mapa-link" href="#parte-17"><img src="{{ $icono('parte-17') }}" alt=""><span class="mapa-texto"><strong>17.</strong> El cierre formal del contrato.</span></a></td>
            <td></td>
        </tr>
    </table>

    @php
        $parteHeader = function ($n, $titulo) use ($icono) {
            $numero = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            return '<table id="parte-' . $numero . '" class="parte-header avoid-break"><tr>'
                . '<td class="parte-icono-td"><img src="' . e($icono('parte-' . $numero . '-white')) . '" alt=""></td>'
                . '<td><span class="parte-eyebrow">PARTE ' . $numero . ' &middot;</span><span class="parte-titulo">' . e($titulo) . '</span></td>'
                . '</tr></table>';
        };
    @endphp

    {{-- ===================== PARTE 01 · Las partes y la ley aplicable ===================== --}}
    {!! $parteHeader(1, 'Las partes y la ley aplicable') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Este contrato es un acuerdo entre la empresa (el Empleador) y tú (el Trabajador o Trabajadora). Al firmarlo, ambas partes se comprometen a cumplir lo pactado, dentro del marco de la ley laboral colombiana.</p>
    </div>
    <p>Entre los suscritos, {{ $nombreEmpresa }}, identificada con Nit. {{ $nit }}, representada legalmente
        por {{ $representanteLegal }}@if ($representanteLegalCedula), identificado(a) con cédula de ciudadanía No. {{ $representanteLegalCedula }}@endif
        (en adelante &ldquo;el Empleador&rdquo;), y {{ $nombreTrabajador }}, identificado(a) con {{ $tipoDocumentoLabel }}
        No. {{ $numeroDocumento }} (en adelante &ldquo;el Trabajador&rdquo;), acuerdan celebrar este contrato individual de trabajo.</p>
    <p>&bull; Anexo No.1: Manual de funciones y responsabilidades del cargo {{ $cargo }}.</p>
    @if ($objetoJuridico)
        <p>{!! $objetoJuridico !!}</p>
    @endif
    <p><strong>¿Qué ley rige este contrato?</strong></p>
    <p>Este contrato se rige por la legislación colombiana, principalmente por el Código Sustantivo del Trabajo (CST) y sus normas complementarias — incluida la Ley 2466 de 2025 (Reforma Laboral) —, por aplicarse el principio de territorialidad: la relación laboral se forma, se ejecuta y termina en Colombia.</p>

    {{-- ===================== PARTE 02 · Datos de contacto ===================== --}}
    {!! $parteHeader(2, 'Datos de contacto') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>La dirección, el correo y el teléfono que diste al firmar este contrato son el medio oficial de contacto. Si cambian, debes avisarle a la empresa por escrito; si no lo haces, cualquier notificación enviada a tus datos registrados se considerará válida de todas formas.</p>
    </div>
    <p><strong>Tus datos de contacto</strong></p>
    <p>La dirección registrada en la carátula de este contrato se entiende como tu residencia actual y tu domicilio legal para todos los efectos del contrato.</p>
    <p>Debes informar por escrito cualquier cambio de dirección, correo electrónico o número de WhatsApp. Si no lo haces, las notificaciones enviadas a los datos que sí están registrados producirán todos sus efectos legales.</p>
    <p>Toda comunicación de la empresa relacionada con la ejecución o la terminación del contrato es válida si se envía a tu última dirección, correo o WhatsApp registrado, o si te la entregan en persona dejando constancia con tu firma (art. 65 CST, modificado por el art. 29 de la Ley 789/2002).</p>
    <p><strong>Manejo reservado de tu información</strong></p>
    <p>Toda tu información personal se maneja de forma reservada. Sin embargo, al firmar autorizas expresamente a la empresa a compartir los datos de tu hoja de vida con entidades con las que tenga un interés legítimo, y a transferirlos a entidades de la Administración Pública cuando la ley lo permita.</p>
    <p>Debes avisar de inmediato cualquier cambio en tu estado civil, domicilio, número de hijos menores u otra circunstancia que afecte tus aportes a seguridad social. No informar estos cambios se considera falta grave, y la empresa no responderá por las consecuencias de tener tus datos desactualizados.</p>
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Mantén siempre actualizados tu dirección, correo y celular. Una notificación legal se dará por válida aunque no la hayas recibido realmente, si fue enviada a los datos que la empresa tiene registrados.</p>
    </div>

    {{-- ===================== PARTE 03 · Tu cargo y obligaciones ===================== --}}
    {!! $parteHeader(3, 'Tu cargo y obligaciones') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Fuiste contratado(a) para el cargo indicado en la carátula del contrato y en el manual de funciones (Anexo No. 1). A cambio de tu salario, pones tu capacidad de trabajo al servicio exclusivo de la empresa y cumples tus funciones con responsabilidad, lealtad y cuidado.</p>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Tu trabajo y dedicación</p>
        <ul>
            <li>Dedicar tu jornada y tu capacidad normal de trabajo a las funciones de tu cargo (manual de funciones — Anexo No. 1) y a las labores relacionadas, siguiendo las instrucciones de la empresa.</li>
            <li>Prestar tus servicios de forma exclusiva: mientras dure este contrato no puedes trabajar para otro empleador ni por cuenta propia en el mismo oficio.</li>
            <li>Asistir puntualmente a tu jornada, a las reuniones y a las capacitaciones o inducciones a las que te citen.</li>
            <li>Aceptar traslados dentro de las sedes de la empresa en el territorio nacional y cumplir comisiones de servicio cuando se requiera.</li>
            <li>Aceptar los ajustes razonables en tus condiciones laborales (jornada, lugar, funciones o forma de pago) que la empresa determine en ejercicio de su facultad de dirección, siempre que no afecten tu honor o dignidad ni impliquen una desmejora grave (art. 23 CST, modificado por el art. 1 de la Ley 50/1990).</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Cuidado de los bienes y recursos de la empresa</p>
        <ul>
            <li>Conservar y devolver en buen estado los equipos, herramientas, vehículos y demás elementos que te entreguen para trabajar (el deterioro normal por el uso no es tu responsabilidad).</li>
            <li>Usarlos solo para los fines del trabajo, y usar únicamente el software autorizado por la empresa: instalar programas no autorizados o sin licencia te hace responsable de los perjuicios que se ocasionen.</li>
            <li>No compartir tus usuarios y contraseñas de acceso, ni facilitarlos a compañeros o terceros.</li>
            <li>Manejar con cuidado el dinero, los documentos y la información que se te confíen, y rendir cuentas claras de su manejo.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Información, reserva y conflictos de interés</p>
        <ul>
            <li>Guardar reserva de la información sensible a la que tengas acceso por tu cargo, incluso después de terminado el contrato (ver la sección &ldquo;Confidencialidad&rdquo; de este documento).</li>
            <li>Avisar oportunamente sobre irregularidades, conflictos de interés, o gastos e inversiones que no tengan relación con la actividad de la empresa.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Conducta, seguridad y bienestar</p>
        <ul>
            <li>Cumplir el reglamento interno de trabajo, las políticas de la empresa y las normas de seguridad y salud en el trabajo.</li>
            <li>Mantener un trato respetuoso con tus jefes, compañeros, clientes y terceros, dentro y fuera del lugar de trabajo.</li>
            <li>No presentarte a trabajar bajo efectos de alcohol o sustancias psicoactivas, y realizarte las pruebas que la empresa o las autoridades te soliciten sobre este tema.</li>
            <li>Cumplir la normativa aplicable a tu labor, incluyendo, si tu cargo lo requiere, las disposiciones sobre prevención de lavado de activos y financiación del terrorismo (SARLAFT).</li>
        </ul>
    </div>
    <div class="caja caja--nota">
        <p class="caja-titulo"><img src="{{ $icono('callout-nota') }}" alt="">Nota</p>
        <p>Este documento organiza tus obligaciones por temas para que sean más fáciles de entender. El listado de faltas y sanciones disciplinarias completo vigente se encuentra en el reglamento interno de trabajo.</p>
    </div>
    <p><strong>Dos precisiones importantes</strong></p>
    <p>Si la empresa te pide prestar tus servicios a otra sociedad de su mismo grupo empresarial (filiales, matrices o subordinadas), esto no crea un contrato de trabajo distinto: para todos los efectos legales, tu único empleador sigue siendo la empresa que firma este contrato.</p>
    <p>Exigirte el cumplimiento de las obligaciones de este contrato no constituye, por sí solo, acoso laboral (Ley 1010 de 2006, artículo 8, literal i).</p>

    {{-- ===================== PARTE 04 · Salario ===================== --}}
    {!! $parteHeader(4, 'Salario') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Recibes un salario mensual fijo, que ya incluye el pago de tus descansos dominicales y festivos. Te pagan {{ $periodoPagoFrase }}, por consignación o transferencia a tu cuenta bancaria.</p>
    </div>
    <p>Tu salario mensual es de ${{ $salarioFormateado }} COP ({{ $salarioEnLetras }}), como contraprestación por tus servicios.</p>
    <p>Se paga por períodos vencidos de {{ $periodoPagoFrase }}, en {{ $lugarContratacion }}, mediante consignación o transferencia electrónica a la cuenta bancaria que indiques.</p>
    <p>Dentro de este pago ya está incluida la remuneración de los descansos dominicales y festivos (Título VII, Capítulos I, II y III del CST).</p>
    <p>Si la empresa te reconoce beneficios extralegales distintos al salario (por ejemplo, alimentación, vivienda, transporte o vestuario), estos no se consideran salario y no se tienen en cuenta para liquidar tus prestaciones ni para el pago de aportes parafiscales, conforme a los artículos 15 y 16 de la Ley 50 de 1990 y el artículo 17 de la Ley 344 de 1996.</p>

    {{-- ===================== PARTE 05 · Jornada de trabajo ===================== --}}
    {!! $parteHeader(5, 'Jornada de trabajo') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Trabajas la jornada máxima legal, salvo que se pacte algo distinto por escrito. La empresa puede definir y ajustar tus turnos y horarios, y las horas extra o el trabajo en días de descanso solo se reconocen si fueron autorizados previamente por escrito.</p>
    </div>
    <p><strong>Cómo se organiza tu jornada</strong></p>
    <p>Trabajas la jornada máxima legal (42 horas semanales), salvo que se pacte expresamente algo distinto por escrito, cumpliendo los turnos y horarios que defina la empresa, quien puede ajustarlos cuando lo considere conveniente.</p>
    <p>Las horas de la jornada ordinaria pueden repartirse total o parcialmente durante el día (art. 164 CST, modificado por el art. 23 de la Ley 50/1990); los descansos entre secciones de la jornada no se cuentan como tiempo trabajado (art. 167 CST).</p>
    <p>También puedes acordar con la empresa prestar el servicio en turnos de jornada flexible, conforme al artículo 51 de la Ley 789 de 2002.</p>
    <p><strong>Jornada flexible especial</strong></p>
    <p>La empresa y tú pueden acordar distribuir tu jornada semanal en jornadas diarias flexibles, repartidas en máximo 6 días con un día de descanso obligatorio (que puede coincidir con el {{ $diaDescansoObligatorio }}). Bajo este esquema:</p>
    <p>Tu jornada diaria puede variar entre un mínimo de 4 horas continuas y un máximo de 9 horas, sin que se generen recargos por trabajo suplementario, siempre que no superes el promedio semanal.</p>
    <p>Esto solo aplica dentro de la franja horaria de 6:00 a.m. a 9:00 p.m.</p>
    <p>Aunque tú lo aceptes, la empresa no puede contratarte para cumplir dos turnos el mismo día, salvo en labores de supervisión, dirección, confianza o manejo.</p>
    <p><strong>Horas extra, trabajo nocturno, dominical o festivo</strong></p>
    <p>Para que la empresa te reconozca y pague trabajo suplementario (horas extra), nocturno, dominical o festivo, este debe haber sido autorizado previamente y por escrito. Si la necesidad surge de manera imprevista, debes informarlo por escrito a la mayor brevedad para su aprobación. Si el trabajo no fue autorizado o avisado y aprobado como se explica aquí, la empresa no está obligada a reconocerlo.</p>

    {{-- ===================== PARTE 06 · Descanso y recargos ===================== --}}
    {!! $parteHeader(6, 'Descanso y recargos') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Tu día de descanso obligatorio es el {{ $diaDescansoObligatorio }}. Si trabajas ese día o en un festivo, la empresa debe pagarte el recargo correspondiente.</p>
    </div>
    <p>De común acuerdo, y conforme al parágrafo 1&ordm; del artículo 179 del CST (modificado por la Ley 2466 de 2025), se pacta el {{ $diaDescansoObligatorio }} como tu día de descanso obligatorio.</p>
    <p>Si llegas a trabajar en tu día de descanso obligatorio, la empresa te reconocerá y pagará el recargo correspondiente conforme a la ley.</p>
    <p>Si trabajas de forma habitual más de dos {{ $diaDescansoObligatorio }}s al mes, además del recargo tienes derecho a un descanso compensatorio remunerado (art. 181 CST).</p>

    {{-- ===================== PARTE 07 · Duración y período de prueba ===================== --}}
    {!! $parteHeader(7, 'Duración y período de prueba') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>El contrato dura el tiempo pactado en la carátula y se puede renovar. Existe un tope legal: nunca puede superar 4 años en total. Al iniciar, hay un período de prueba en el que cualquiera de las partes puede terminar el contrato sin indemnización.</p>
    </div>
    <table class="tabla-datos">
        <tr><td class="label">Duración inicial</td><td>La indicada en la carátula de este contrato.</td></tr>
        <tr><td class="label">Renovación automática</td><td>Si ninguna de las partes avisa por escrito, con mínimo 30 días de anticipación, que no quiere prorrogarlo, el contrato se renueva por un período igual al inicial.</td></tr>
        <tr><td class="label">Prórrogas</td><td>Se puede prorrogar hasta 3 veces. Desde la 4&ordf; prórroga en adelante, cada una debe ser de mínimo 1 año.</td></tr>
        <tr><td class="label">Tope máximo legal</td><td>La suma de todas las prórrogas nunca puede superar 4 años en total (art. 46 CST, modificado por la Ley 2466 de 2025). Al llegar a ese tope, si la relación continúa, se convierte automáticamente en un contrato a término indefinido.</td></tr>
        <tr><td class="label">Período de prueba</td><td>Equivale a la quinta parte de la duración total del contrato, sin superar 2 meses, contados desde que empiezas a trabajar.</td></tr>
        <tr><td class="label">Durante el período de prueba</td><td>Cualquiera de las partes puede terminar el contrato en cualquier momento, sin previo aviso ni indemnización. Aun así, tienes derecho a todas las prestaciones que la ley determine a tu favor.</td></tr>
    </table>

    <div class="page-break"></div>

    {{-- ===================== PARTE 08 · Terminación ===================== --}}
    {!! $parteHeader(8, 'Terminación') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>El contrato puede terminar por las justas causas que señala la ley, el reglamento interno o este contrato. Antes de sancionarte, la empresa debe seguir un procedimiento que garantice tu derecho a ser escuchado(a) y a defenderte.</p>
    </div>
    <p>Son justas causas para terminar unilateralmente este contrato las señaladas en los artículos 62 y 63 del CST, las que se califiquen como faltas graves en el reglamento interno de trabajo y demás documentos normativos de la empresa, pactos o convenciones colectivas, y las que se detallan a continuación, agrupadas por tema:</p>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Seguridad y sustancias</p>
        <ul>
            <li>Presentarte al trabajo bajo efectos de bebidas embriagantes, drogas alucinógenas o similares, o consumirlas en el lugar de trabajo.</li>
            <li>Presentarte o permanecer en la empresa portando armas, salvo que tu cargo te faculte para ello.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Asistencia y disciplina</p>
        <ul>
            <li>No justificar tu inasistencia al trabajo o abandonar tus labores sin autorización de tu superior.</li>
            <li>Llegar tarde (hasta 15 minutos) sin excusa, por tercera vez.</li>
            <li>Cometer actos de violencia, injuria o irrespeto injustificado contra tus superiores, compañeros o terceros, dentro o fuera de la empresa.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Manejo de dinero, bienes y documentos</p>
        <ul>
            <li>Generar faltantes o descuadres de dinero, o perder, extraviar o deteriorar documentos bajo tu responsabilidad.</li>
            <li>Cobrar subsidios o beneficios a los que no tienes derecho.</li>
            <li>Presentar cuentas de gastos ficticias o reportar como cumplidas tareas o visitas que no realizaste.</li>
            <li>Autorizar o ejecutar, sin ser tu competencia, operaciones que afecten los intereses de la empresa.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Información y confidencialidad</p>
        <ul>
            <li>Violar la reserva de información confidencial que conozcas por tu cargo.</li>
            <li>Usar información privilegiada para tu beneficio o el de un tercero.</li>
            <li>Facilitar tu usuario y contraseña a compañeros o terceros para acceder a recursos informáticos.</li>
            <li>Dar datos falsos al ingresar a la empresa, o consignar datos inexactos en informes internos.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Abuso de posición y ética</p>
        <ul>
            <li>Aceptar o solicitar dádivas o beneficios de clientes, proveedores o terceros a cambio de tratamientos especiales.</li>
            <li>Solicitar u obtener concesiones de empleados bajo tu mando, aprovechando tu posición.</li>
        </ul>
    </div>
    <div class="bloque-bullets">
        <p class="bloque-bullets-titulo">Incumplimiento general</p>
        <ul>
            <li>Violar las obligaciones y prohibiciones establecidas en la ley, el reglamento interno, este contrato, el reglamento de higiene y seguridad, y las demás políticas de la empresa.</li>
            <li>Cualquier acto de negligencia, descuido u omisión grave en el ejercicio de tus funciones.</li>
        </ul>
    </div>
    @if (($faltasGravesOrigen ?? null) === 'rit')
        <div class="bloque-bullets">
            <p class="bloque-bullets-titulo">Faltas graves adicionales según el Reglamento Interno de Trabajo de {{ $nombreEmpresa }}</p>
            <ul>
                @foreach (($faltasGravesGrave ?? []) as $conducta)
                    <li>{{ $conducta }}</li>
                @endforeach
                @foreach (($faltasGravesGravisima ?? []) as $conducta)
                    <li>{{ $conducta }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-derecho') }}" alt="">Tu derecho a ser escuchado(a)</p>
        <p>Antes de imponerte una sanción disciplinaria, la empresa debe seguir el procedimiento legal: informarte por escrito los hechos que se investigan, mostrarte las pruebas en las que se basa, y darte la oportunidad real de defenderte y controvertirlas, respetando principios como la presunción de inocencia, la proporcionalidad y la imparcialidad.</p>
    </div>
    <p>Además, eres responsable del dinero, los documentos, los recursos informáticos y la información que recibas o manejes por razón de tu cargo, sin poder disponer de ellos en tu beneficio ni en el de terceros, y debes rendir cuentas claras de su manejo a la empresa.</p>

    {{-- ===================== PARTE 09 · Incapacidades y exámenes médicos ===================== --}}
    {!! $parteHeader(9, 'Incapacidades y exámenes médicos') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Si te enfermas o tienes un accidente, tu incapacidad debe estar certificada por tu EPS o tu ARL. La empresa también puede pedirte exámenes médicos cuando lo considere necesario.</p>
    </div>
    <p>Solo se aceptan como válidas las incapacidades por enfermedad común, enfermedad profesional o accidente, certificadas o avaladas por la EPS o la ARL a la que estás afiliado(a).</p>
    <p>La empresa puede exigirte, en cualquier momento, la práctica de exámenes médicos, sanitarios o pruebas de laboratorio, y debes suministrar los documentos que la relación laboral requiera.</p>
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Negarte a practicarte los exámenes médicos o pruebas de laboratorio que la empresa te solicite se considera falta grave y puede ser causal de terminación del contrato con justa causa.</p>
    </div>

    {{-- ===================== PARTE 10 · Confidencialidad ===================== --}}
    {!! $parteHeader(10, 'Confidencialidad') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>La información del negocio que conozcas por tu cargo es confidencial. No puedes usarla ni compartirla, ni mientras dure el contrato ni después de que termine.</p>
    </div>
    <p>No puedes revelar, vender, copiar, ni usar en tu beneficio o el de terceros la información confidencial o privilegiada de la empresa, de sus filiales, socios, clientes o terceros relacionados, a la que accedas por razón de tu cargo.</p>
    <p>Se considera confidencial cualquier información, documento o procedimiento de la empresa que no sea de conocimiento público, en especial la relacionada con operaciones, transacciones o negocios sensibles para su operación.</p>
    <p>Esta obligación continúa después de terminado el contrato, sin límite de tiempo.</p>
    <p>Al terminar el contrato, por cualquier causa, debes devolver de inmediato cualquier documento, información o elemento que te hayan entregado para el cumplimiento de tus funciones.</p>
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Incumplir esta cláusula se considera falta grave y justa causa de terminación del contrato (Decreto 2351 de 1965, art. 7, literal a, numeral 6, en concordancia con el numeral 1 del art. 58 del CST), sin perjuicio de las acciones civiles o penales que la empresa o terceros puedan iniciar.</p>
    </div>

    {{-- ===================== PARTE 11 · Propiedad intelectual ===================== --}}
    {!! $parteHeader(11, 'Propiedad intelectual') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Las obras, invenciones o desarrollos que crees en tu trabajo (o usando recursos de la empresa) le pertenecen a la empresa. Tu salario ya incluye la remuneración por esta cesión.</p>
    </div>
    <p><strong>Derechos de autor</strong></p>
    <p>Cedes a la empresa los derechos patrimoniales de autor sobre las obras que crees en cumplimiento de tus funciones, usando herramientas o materias primas de la empresa, o con ayuda de compañeros o terceros vinculados a ella.</p>
    <p>La empresa puede registrar esas obras y explotarlas comercialmente (reproducirlas, transformarlas, adaptarlas, distribuirlas, comunicarlas públicamente, etc.), respetando siempre tus derechos morales de autor, que son tuyos y no se pueden ceder.</p>
    <p>Te comprometes a firmar los documentos necesarios para formalizar esta cesión, sin que la empresa deba pagarte una compensación adicional.</p>
    <p><strong>Propiedad industrial</strong></p>
    <p>Los resultados de tu trabajo que puedan protegerse como propiedad industrial (patentes, modelos de utilidad, diseños industriales, secretos comerciales, entre otros) pertenecen a la empresa.</p>
    <p>Debes colaborar con los trámites y firmar los documentos necesarios para que la empresa obtenga esa protección.</p>
    <p><strong>Uso de tu imagen</strong></p>
    <p>Autorizas a la empresa a usar tu imagen con fines publicitarios, por tiempo indefinido y sin costo, cediendo los derechos sobre las fotos o videos tomados para ese propósito.</p>
    <p>Puedes oponerte por escrito al uso de tu imagen en cualquier momento.</p>
    <div class="caja caja--nota">
        <p class="caja-titulo"><img src="{{ $icono('callout-nota') }}" alt="">Nota legal</p>
        <p>Tanto para los derechos de autor como para la propiedad industrial, la ley entiende que tu salario ya remunera esta cesión, pues los desarrollos surgen en virtud de tu contrato de trabajo (Ley 23 de 1982 y Decisión 486 de 2000 de la CAN, en concordancia con el numeral 1&ordm; del artículo 132 del CST).</p>
    </div>

    {{-- ===================== PARTE 12 · Herramientas de trabajo ===================== --}}
    {!! $parteHeader(12, 'Herramientas de trabajo') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>La empresa te entrega lo necesario para trabajar (equipo de cómputo, papelería, entre otros). Están bajo tu cuidado y debes devolverlas en buen estado.</p>
    </div>
    <p>La empresa se obliga a proveerte los medios de trabajo necesarios (por ejemplo, equipo de cómputo o papelería).</p>
    <p>Estas herramientas quedan bajo tu custodia y cuidado. Su pérdida, daño o destrucción es tu responsabilidad, salvo el deterioro normal por el uso.</p>
    <p>Solo puedes usarlas para las labores relacionadas con tu contrato de trabajo. Darles un uso indebido o no cuidarlas se considera falta grave (Decreto 2351 de 1965, art. 7, literal a, numeral 6, que subrogó el art. 62 del CST).</p>
    <p>Las herramientas son propiedad de la empresa: debes devolverlas cuando te lo pidan y, en todo caso, al terminar el contrato por cualquier causa.</p>
    <p>El suministro de estas herramientas no constituye salario ni beneficio legal o extralegal, conforme a los artículos 15 y 16 de la Ley 50 de 1990 y el artículo 17 de la Ley 344 de 1996. Por tratarse de una liberalidad, la empresa puede modificar, adicionar o suprimir este suministro sin que se considere una desmejora de tus condiciones.</p>
    <div class="caja caja--importante">
        <p class="caja-titulo"><img src="{{ $icono('callout-importante') }}" alt="">Importante</p>
        <p>Si pierdes, dañas o no devuelves una herramienta de trabajo, autorizas a la empresa a descontar su valor comercial de las sumas que te adeude: salarios, prestaciones sociales, vacaciones, intereses de cesantía, u otras acreencias a tu favor, ya sea durante el contrato o al momento de su liquidación.</p>
    </div>

    <div class="page-break"></div>

    {{-- ===================== PARTE 13 · Tratamiento de datos personales ===================== --}}
    {!! $parteHeader(13, 'Tratamiento de datos personales') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Autorizas a la empresa a recolectar y usar tus datos personales —incluyendo datos sensibles— para fines relacionados con tu contrato de trabajo, conforme a la Ley 1581 de 2012 (Habeas Data).</p>
    </div>
    <p>Autorizas a la empresa a recolectar, almacenar, usar, actualizar y tratar tus datos personales: identificación, contacto, género, estado civil, fecha y lugar de nacimiento, salario, cuenta bancaria, historia laboral y académica, datos biométricos y de seguridad social, entre otros.</p>
    <p>Estos datos se usan para: cumplir las obligaciones legales y contractuales de tu contrato; administrar tu nómina, seguridad social y bienestar laboral; atender requerimientos de autoridades administrativas o judiciales; adelantar procesos disciplinarios y evaluaciones de desempeño; y cumplir las políticas internas y el reglamento interno de trabajo.</p>
    <p>Tienes derecho a conocer, actualizar, rectificar y suprimir tus datos personales, y a revocar esta autorización cuando sea procedente, mediante solicitud dirigida a la empresa o ante la Superintendencia de Industria y Comercio.</p>
    <p>Esta autorización sigue vigente después de terminado el contrato, por el tiempo necesario para cumplir obligaciones legales, contables, laborales o de archivo.</p>

    {{-- ===================== PARTE 14 · Políticas y cambios laborales ===================== --}}
    {!! $parteHeader(14, 'Políticas y cambios laborales') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Debes conocer y cumplir las políticas internas de la empresa, asistir a las capacitaciones que te asignen, y aceptar los ajustes razonables que la empresa haga a tus condiciones de trabajo.</p>
    </div>
    <p>Te comprometes a recibir y asimilar las capacitaciones que la empresa considere necesarias para tu cargo, para ascensos o promociones, o para cubrir nuevas necesidades del negocio.</p>
    <p>Declaras conocer y entender las políticas y procedimientos de la empresa relacionados con tus funciones, y te comprometes a mantenerte actualizado(a) sobre ellos, así como a informarte de los nuevos que se establezcan. Si tienes personas a cargo, debes procurar que ellas también estén informadas.</p>
    <p>Aceptas que la empresa pueda ajustar tu jornada, tu lugar de trabajo, tu cargo o funciones, o tu forma de remuneración, en ejercicio de su facultad de dirección, siempre que esos cambios no afecten tu honor o dignidad ni impliquen una desmejora sustancial o un perjuicio grave para ti (art. 23 CST, modificado por el art. 1&ordm; de la Ley 50 de 1990).</p>

    {{-- ===================== PARTE 15 · Descuentos autorizados ===================== --}}
    {!! $parteHeader(15, 'Descuentos autorizados') !!}
    <div class="caja caja--simple">
        <p class="caja-titulo"><img src="{{ $icono('callout-simple') }}" alt="">En palabras simples</p>
        <p>Autorizas a la empresa a hacer los descuentos de ley sobre tu salario y prestaciones, y puedes autorizar por escrito otros descuentos adicionales.</p>
    </div>
    <p>Autorizas a la empresa a realizar las deducciones o descuentos de tus acreencias laborales permitidos por el artículo 150 del CST (modificado por el art. 22 de la Ley 1911 de 2018).</p>
    <p>Puedes autorizar por escrito otros descuentos adicionales sobre tus acreencias laborales, conforme al artículo 151 del CST (modificado por el art. 19 de la Ley 1429 de 2010).</p>
    <p>Al terminar el contrato, puedes autorizar por escrito que se descuenten de tus prestaciones sociales o de cualquier suma a tu favor, los valores que le debas a la empresa por cualquier concepto.</p>

    {{-- ===================== PARTE 16 · Disposiciones finales ===================== --}}
    {!! $parteHeader(16, 'Disposiciones finales') !!}
    <p>Este contrato reemplaza en su totalidad, y deja sin efecto, cualquier otro contrato, acuerdo u oferta anterior entre las partes sobre lo mismo, ya sea verbal o escrito.</p>
    <p>Cualquier modificación futura a este contrato debe hacerse por escrito y formará parte integrante de este documento.</p>

    {{-- ===================== PARTE 17 · Cierre del contrato — Firmas ===================== --}}
    {!! $parteHeader(17, 'Cierre del contrato — Firmas') !!}
    <p>Para constancia de lo anterior, se firma por las partes en {{ $lugarContratacion }}, el día {{ $fechaFirma }}.</p>
    <table class="firma">
        <tr>
            <td>
                <div class="linea"></div>
                <strong>EL EMPLEADOR</strong><br>
                {{ $nombreEmpresa }}<br>
                NIT. {{ $nit }}<br>
                {{ $representanteLegal }}
            </td>
            <td>
                <div class="linea"></div>
                <strong>EL TRABAJADOR / LA TRABAJADORA</strong><br>
                {{ $nombreTrabajador }}<br>
                {{ ucfirst($tipoDocumentoLabel) }} N.&ordm; {{ $numeroDocumento }}
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    {{-- ===================== PARTE 18 · Glosario de términos legales ===================== --}}
    {!! $parteHeader(18, 'Glosario de términos legales') !!}
    <p>Algunos términos técnicos no se pueden traducir sin perder precisión jurídica. Aquí te los explicamos:</p>
    <table class="tabla-glosario">
        <tr><td class="termino">Justa causa</td><td>Motivo válido y reconocido por la ley para que el empleador o el trabajador terminen el contrato de forma unilateral.</td></tr>
        <tr><td class="termino">Parafiscales</td><td>Aportes obligatorios que paga la empresa a entidades como el SENA, el ICBF y las cajas de compensación familiar.</td></tr>
        <tr><td class="termino">SARLAFT</td><td>Sistema de Administración del Riesgo de Lavado de Activos y Financiación del Terrorismo: controles que deben cumplir ciertos cargos y empresas.</td></tr>
        <tr><td class="termino">Derechos patrimoniales de autor</td><td>Derechos económicos sobre una obra (por ejemplo, explotarla, venderla o licenciarla). Se pueden ceder a otra persona o empresa.</td></tr>
        <tr><td class="termino">Derechos morales de autor</td><td>Derecho a ser reconocido como autor de una obra y a que se respete su integridad. No se pueden ceder ni vender: siempre son del creador.</td></tr>
        <tr><td class="termino">Propiedad industrial</td><td>Protección legal de invenciones, marcas, diseños o secretos comerciales usados en una actividad económica.</td></tr>
        <tr><td class="termino">Habeas Data</td><td>Derecho constitucional a conocer, actualizar y rectificar la información que las entidades tienen sobre ti (Ley 1581 de 2012).</td></tr>
        <tr><td class="termino">Horario flexible</td><td>Duración de la jornada distinta a la habitual, entre 4 y 9 horas, sin que se causen horas extras mientras el promedio semanal no exceda la jornada máxima de 42 horas.</td></tr>
        <tr><td class="termino">Trabajo suplementario</td><td>Horas trabajadas por encima de tu jornada ordinaria; también se conoce como &ldquo;horas extra&rdquo;.</td></tr>
        <tr><td class="termino">Recargo</td><td>Valor adicional que se paga por trabajar en horario nocturno, domingo o festivo.</td></tr>
        <tr><td class="termino">Debido proceso disciplinario</td><td>Procedimiento que debe seguir la empresa antes de sancionarte, para garantizar tu derecho a conocer los hechos, las pruebas y a defenderte.</td></tr>
    </table>

    @isset($empresa)
    @include('pdfs.components.membrete-empresa', ['empresa' => $empresa])
@endisset

</body>

</html>
