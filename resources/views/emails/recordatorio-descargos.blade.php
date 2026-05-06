<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio - Audiencia de Descargos Manana</title>
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
        .header {
            background-color: #b45309;
            color: white;
            padding: 28px 30px 22px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: bold;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            opacity: 0.85;
        }
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
        .content {
            padding: 28px 30px;
        }
        /* MANANA BADGE */
        .manana-badge {
            background-color: #dc2626;
            color: white;
            border-radius: 8px;
            padding: 18px 20px;
            text-align: center;
            margin: 0 0 22px 0;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.3px;
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
            font-size: 32px;
            font-weight: bold;
            color: #b45309;
        }
        .fecha-block .modalidad {
            margin-top: 8px;
            font-size: 13px;
            color: #78350f;
        }
        /* CTA */
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
        .cta-url a { color: #2563eb; }
        /* INFO */
        .info-box {
            background-color: #f9fafb;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 14px 16px;
            margin: 0 0 20px 0;
            font-size: 14px;
        }
        .info-box p { margin: 4px 0; }
        /* AVISO ROJO */
        .aviso-rojo {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #450a0a;
            margin: 0 0 20px 0;
        }
        .aviso-rojo ul { margin: 6px 0 0 0; padding-left: 18px; }
        .aviso-rojo li { margin-bottom: 4px; }
        /* LISTA PREPARACION */
        .prep-list {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #1e3a8a;
            margin: 0 0 20px 0;
        }
        .prep-list ul { margin: 6px 0 0 0; padding-left: 18px; }
        .prep-list li { margin-bottom: 4px; }
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

        <div class="header">
            <h1>Recordatorio: Su audiencia es manana</h1>
            <p>{{ $empresa->razon_social }} &mdash; Proceso {{ $proceso->codigo }}</p>
        </div>

        <div class="urgente-band">Accion requerida manana &mdash; lea este correo completo</div>

        <div class="content">

            <p style="margin:0 0 18px 0;">
                Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,<br>
                le recordamos que <strong>manana</strong> tiene programada su audiencia de descargos.
            </p>

            {{-- BADGE MANANA --}}
            <div class="manana-badge">
                SU AUDIENCIA ES MANANA
            </div>

            {{-- FECHA Y HORA --}}
            @if($proceso->fecha_descargos_programada)
            <div class="fecha-block">
                <div class="label">Fecha y hora</div>
                <div class="fecha">{{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
                <div class="hora">{{ \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') }} hrs</div>
                <div class="modalidad">Modalidad: <strong>{{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</strong></div>
            </div>
            @endif

            {{-- CTA --}}
            @if($linkDescargos)
            <div class="cta-block">
                <div class="cta-label">Su enlace para manana</div>
                <p class="cta-desc">
                    Manana, a la hora indicada, haga clic aqui para ingresar al formulario.<br>
                    <strong>Este enlace solo funciona el dia de la audiencia.</strong>
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

            {{-- INFO PROCESO --}}
            <div class="info-box">
                <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
                <p><strong>Codigo del proceso:</strong> {{ $proceso->codigo }}</p>
                <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            </div>

            {{-- PREPARACION --}}
            <div class="prep-list">
                <strong>Preparese para manana:</strong>
                <ul>
                    <li>Tenga a la mano este correo con el boton de acceso.</li>
                    <li>Prepare documentos o evidencias que quiera presentar.</li>
                    <li>Asegurese de tener conexion a internet estable.</li>
                    <li>Reserve aproximadamente <strong>45 minutos</strong> para completar el formulario.</li>
                </ul>
            </div>

            {{-- AVISO LEGAL --}}
            <div class="aviso-rojo">
                <strong>Importante:</strong>
                <ul>
                    <li>Su asistencia es <strong>obligatoria</strong>.</li>
                    <li>Si no se presenta ni responde el formulario, el proceso continuara sin su participacion.</li>
                    <li>Tiene derecho a presentar pruebas y argumentos en su defensa.</li>
                </ul>
            </div>

            <p style="font-size:14px; margin:0 0 6px 0;">
                Si tiene preguntas, comuniquese con Recursos Humanos de <strong>{{ $empresa->razon_social }}</strong> <strong>hoy</strong>, antes de la diligencia.
            </p>

            <p style="font-size:14px; margin:16px 0 0 0;">
                Atentamente,<br>
                <strong>{{ $empresa->razon_social }}</strong><br>
                <span style="color:#6b7280;">Area de Recursos Humanos</span>
            </p>

        </div>

        <div class="footer">
            <p style="margin:0 0 4px 0;">Este correo fue generado automaticamente. Por favor no responda.</p>
            <p style="margin:0;">Use los canales oficiales de la empresa para comunicarse.</p>
        </div>

    </div>

    @if(isset($trackingToken))
    <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}"
         width="1" height="1"
         style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;"
         alt="" />
    @endif
</body>

</html>
