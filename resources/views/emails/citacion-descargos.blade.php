<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citación a Audiencia de Descargos</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.5;
            color: #374151;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        /* HEADER */
        .header {
            background-color: #1e40af;
            color: white;
            padding: 28px 30px 22px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            opacity: 0.85;
        }
        /* ALERTA URGENTE */
        .urgente-band {
            background-color: #dc2626;
            color: white;
            text-align: center;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        /* CONTENIDO */
        .content {
            padding: 28px 30px;
        }
        /* BLOQUE FECHA */
        .fecha-block {
            background-color: #fef9c3;
            border: 2px solid #eab308;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 0 0 24px 0;
        }
        .fecha-block .label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #92400e;
            margin-bottom: 6px;
        }
        .fecha-block .fecha {
            font-size: 20px;
            font-weight: bold;
            color: #1c1917;
            margin-bottom: 4px;
            text-transform: capitalize;
        }
        .fecha-block .hora {
            font-size: 28px;
            font-weight: bold;
            color: #b45309;
        }
        .fecha-block .modalidad {
            margin-top: 8px;
            font-size: 13px;
            color: #78350f;
        }
        /* BLOQUE CTA — EL MAS IMPORTANTE */
        .cta-block {
            background-color: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 8px;
            padding: 24px 20px;
            text-align: center;
            margin: 0 0 24px 0;
        }
        .cta-block .cta-label {
            font-size: 13px;
            font-weight: bold;
            color: #15803d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .cta-block .cta-desc {
            font-size: 14px;
            color: #374151;
            margin: 0 0 18px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #16a34a;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 17px;
            font-weight: bold;
            padding: 16px 36px;
            border-radius: 6px;
            letter-spacing: 0.2px;
        }
        .cta-url {
            margin-top: 14px;
            font-size: 11px;
            color: #6b7280;
            word-break: break-all;
        }
        .cta-url a {
            color: #2563eb;
        }
        /* PASOS */
        .steps {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 18px 20px;
            margin: 0 0 22px 0;
        }
        .steps .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .steps .step:last-child { margin-bottom: 0; }
        .step-num {
            background-color: #1e40af;
            color: white;
            width: 24px;
            height: 24px;
            min-width: 24px;
            border-radius: 50%;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            line-height: 24px;
            margin-right: 12px;
            margin-top: 1px;
        }
        .step-text {
            font-size: 14px;
            color: #374151;
        }
        /* INFO PROCESO */
        .info-box {
            background-color: #f9fafb;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 14px 16px;
            margin: 0 0 20px 0;
            font-size: 14px;
        }
        .info-box p { margin: 4px 0; }
        /* AVISO LEGAL */
        .aviso {
            background-color: #fff7ed;
            border-left: 4px solid #ea580c;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #431407;
            margin: 0 0 20px 0;
        }
        .aviso ul { margin: 6px 0 0 0; padding-left: 18px; }
        .aviso li { margin-bottom: 4px; }
        /* FOOTER */
        .footer {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 18px 30px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        {{-- HEADER --}}
        <div class="header">
            <h1>Citacion a Audiencia de Descargos</h1>
            <p>{{ $empresa->razon_social }} &mdash; Proceso {{ $proceso->codigo }}</p>
        </div>

        <div class="urgente-band">Accion requerida de su parte</div>

        <div class="content">

            <p style="margin:0 0 20px 0;">
                Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,<br>
                usted ha sido citado(a) formalmente a una audiencia de descargos. A continuacion encontrara la fecha, la hora y el enlace para participar.
            </p>

            {{-- FECHA Y HORA --}}
            @if($proceso->fecha_descargos_programada)
            <div class="fecha-block">
                <div class="label">Fecha y hora de su audiencia</div>
                <div class="fecha">{{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
                <div class="hora">{{ \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') }} hrs</div>
                <div class="modalidad">Modalidad: <strong>{{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</strong></div>
            </div>
            @endif

            {{-- BOTON CTA — PROTAGONISTA --}}
            @if($linkDescargos)
            <div class="cta-block">
                <div class="cta-label">Enlace para sus descargos en linea</div>
                <p class="cta-desc">
                    El <strong>dia de la audiencia</strong> ingrese aqui para presentar sus descargos.<br>
                    Guardelo ahora &mdash; solo funciona ese dia.
                </p>
                <a href="{{ $linkDescargos }}" class="btn-primary">
                    Acceder al formulario de descargos
                </a>
                <div class="cta-url">
                    Si el boton no abre, copie este enlace en su navegador:<br>
                    <a href="{{ $linkDescargos }}">{{ $linkDescargos }}</a>
                </div>
            </div>
            @endif

            {{-- PASOS --}}
            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">Lea el <strong>documento adjunto</strong> a este correo para conocer los cargos y sus derechos.</div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">El dia de la audiencia (<strong>{{ $proceso->fecha_descargos_programada ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM') : 'fecha indicada' }}</strong>), haga clic en el boton verde de arriba.</div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">Complete el formulario en linea y envie sus descargos.</div>
                </div>
            </div>

            {{-- INFO PROCESO --}}
            <div class="info-box">
                <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
                <p><strong>Codigo del proceso:</strong> {{ $proceso->codigo }}</p>
                <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            </div>

            {{-- TABLA DE SANCIONES — ARTICULO 20 (datos del RIT de la empresa) --}}
            @php
                $rit            = $empresa->reglamentoInterno;
                $cuestionario   = $rit?->respuestas_cuestionario ?? [];
                $faltasLeves    = $cuestionario['faltas_leves']        ?? [];
                $faltasGraves   = $cuestionario['faltas_graves']       ?? [];
                $sancionesRIT   = $cuestionario['sanciones_contempladas'] ?? $cuestionario['sanciones'] ?? [];

                // Mapeo de claves del wizard a texto legible
                $sancionTexto = [
                    'llamado_verbal'  => 'Llamado de Atención Verbal',
                    'llamado_escrito' => 'Llamado de Atención Escrito',
                    'suspension_1_8'  => 'Suspensión 1 a 8 días sin sueldo',
                    'suspension_1_15' => 'Suspensión 1 a 15 días sin sueldo',
                    'suspension_1_30' => 'Suspensión 1 a 30 días sin sueldo',
                    'suspension_1_40' => 'Suspensión 1 a 40 días sin sueldo',
                    'suspension_1_60' => 'Suspensión 1 a 60 días sin sueldo',
                    'terminacion'     => 'Terminación del Contrato con Justa Causa',
                ];

                // Sanción para faltas leves (llamados de atención disponibles)
                $sancionLeve = collect($sancionesRIT)
                    ->filter(fn($s) => str_starts_with($s, 'llamado'))
                    ->map(fn($s) => $sancionTexto[$s] ?? $s)
                    ->join(' / ') ?: 'Llamado de Atención';

                // Sanciones para faltas graves (suspensiones y terminación)
                $sancionGrave = collect($sancionesRIT)
                    ->filter(fn($s) => str_starts_with($s, 'suspension') || $s === 'terminacion')
                    ->map(fn($s) => $sancionTexto[$s] ?? $s)
                    ->join(' / ') ?: 'Suspensión / Terminación';

                $tieneTabla = !empty($faltasLeves) || !empty($faltasGraves);
            @endphp
            @if($tieneTabla)
            <div style="margin: 0 0 20px 0;">
                <p style="font-size:13px; font-weight:bold; color:#1f2937; margin:0 0 6px 0; text-transform:uppercase; letter-spacing:0.4px;">
                    Consecuencias de las Faltas &mdash; Articulo 20
                </p>
                <table style="width:100%; border-collapse:collapse; font-size:12px; font-family:Arial,sans-serif;">
                    <tr>
                        <td colspan="3" style="border:1px solid #374151; padding:7px 8px; text-align:center; background-color:#f3f4f6;">
                            <strong>TABLA DE SANCIONES LABORALES</strong><br>
                            <span style="font-size:10px; color:#4b5563;">(Todas las sanciones contenidas en esta tabla solo se aplicaran previa garantia del debido proceso establecido en el Reglamento Interno, conforme a la Ley 2466 de 2025.)</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" style="border:1px solid #374151; padding:5px 8px; text-align:center; font-size:11px;">
                            <strong>{{ $empresa->razon_social }}</strong> &nbsp;|&nbsp; NIT: {{ $empresa->nit }}
                        </td>
                    </tr>
                    <tr style="background-color:#e5e7eb;">
                        <th style="border:1px solid #374151; padding:5px 8px; text-align:center; width:18%; font-size:11px;">Tipo de Falta</th>
                        <th style="border:1px solid #374151; padding:5px 8px; text-align:left;   width:55%; font-size:11px;">Conductas reguladas por el Reglamento Interno</th>
                        <th style="border:1px solid #374151; padding:5px 8px; text-align:center; width:27%; font-size:11px;">Sancion aplicable</th>
                    </tr>

                    @if(!empty($faltasLeves))
                    <tr>
                        <td style="border:1px solid #374151; padding:5px 8px; text-align:center; font-weight:bold; color:#15803d; vertical-align:top;">
                            LEVE
                        </td>
                        <td style="border:1px solid #374151; padding:5px 8px; vertical-align:top;">
                            <ul style="margin:0; padding-left:16px;">
                                @foreach($faltasLeves as $falta)
                                <li style="margin-bottom:2px;">{{ $falta }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td style="border:1px solid #374151; padding:5px 8px; text-align:center; vertical-align:top; font-size:11px;">
                            {{ $sancionLeve }}
                        </td>
                    </tr>
                    @endif

                    @if(!empty($faltasGraves))
                    <tr>
                        <td style="border:1px solid #374151; padding:5px 8px; text-align:center; font-weight:bold; color:#b91c1c; vertical-align:top;">
                            GRAVE
                        </td>
                        <td style="border:1px solid #374151; padding:5px 8px; vertical-align:top;">
                            <ul style="margin:0; padding-left:16px;">
                                @foreach($faltasGraves as $falta)
                                <li style="margin-bottom:2px;">{{ $falta }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td style="border:1px solid #374151; padding:5px 8px; text-align:center; vertical-align:top; font-size:11px;">
                            {{ $sancionGrave }}
                        </td>
                    </tr>
                    @endif
                </table>
                <p style="font-size:10px; color:#6b7280; margin:4px 0 0 0;">Tabla conforme al Reglamento Interno de Trabajo de {{ $empresa->razon_social }}, de conformidad con la Ley 2466 de 2025. Toda sancion se aplicara previa garantia del debido proceso.</p>
            </div>
            @endif

            {{-- AVISO LEGAL --}}
            <div class="aviso">
                <strong>Importante:</strong>
                <ul>
                    <li>Su asistencia a esta audiencia es <strong>obligatoria</strong>.</li>
                    <li>Si no se presenta ni responde el formulario, el proceso continuara sin su participacion.</li>
                    <li>Tiene derecho a presentar pruebas y argumentos en su defensa.</li>
                </ul>
            </div>

            <p style="font-size:14px; margin:0 0 6px 0;">Si tiene preguntas, comuniquese con el area de Recursos Humanos de <strong>{{ $empresa->razon_social }}</strong> antes de la fecha programada.</p>

            <p style="font-size:14px; margin:16px 0 0 0;">
                Atentamente,<br>
                <strong>{{ $empresa->razon_social }}</strong><br>
                <span style="color:#6b7280;">Area de Recursos Humanos</span>
            </p>

        </div>{{-- /content --}}

        <div class="footer">
            <p style="margin:0 0 4px 0;">Este correo fue generado automaticamente por el sistema de gestion de procesos disciplinarios.</p>
            <p style="margin:0;">Por favor no responda a este correo. Use los canales oficiales de la empresa.</p>
        </div>

    </div>

    {{-- Pixel de seguimiento --}}
    @if(isset($trackingToken))
    <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}"
         width="1" height="1"
         style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;"
         alt="" />
    @endif
</body>

</html>
