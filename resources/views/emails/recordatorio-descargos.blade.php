<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio - Diligencia de Descargos Mañana</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #f59e0b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }

        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
        }

        .important {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .alert {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }

        h2 {
            color: #1f2937;
            margin-top: 0;
        }

        .highlight {
            font-weight: bold;
            color: #2563eb;
        }

        .fecha-destacada {
            background-color: #dbeafe;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .fecha-destacada h3 {
            color: #1e40af;
            margin: 0 0 10px 0;
            font-size: 14px;
            text-transform: uppercase;
        }

        .fecha-destacada .fecha {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .fecha-destacada .hora {
            font-size: 20px;
            color: #3b82f6;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Recordatorio Importante</h1>
        <p style="margin: 10px 0 0 0; font-size: 16px;">Su diligencia de descargos es mañana</p>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        <p>Le recordamos que <strong>mañana</strong> tiene programada su diligencia de descargos correspondiente al proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>

        <div class="fecha-destacada">
            <h3>Fecha y Hora de la Diligencia</h3>
            <div class="fecha">{{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
            <div class="hora">{{ \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('h:i A') }}</div>
            <p style="margin: 10px 0 0 0;"><strong>Modalidad:</strong> {{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</p>
        </div>

        <div class="info-box">
            <h2>Datos del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
        </div>

        @if ($linkDescargos)
            <div class="info-box" style="background-color: #dbeafe; border-left-color: #3b82f6;">
                <h2>Enlace para Presentar sus Descargos</h2>
                <p>El día de mañana, podrá acceder al formulario en línea para presentar sus descargos a través del siguiente enlace:</p>

                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ $linkDescargos }}"
                        style="display: inline-block; padding: 15px 30px; background-color: #3b82f6; color: white;
                          text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        Acceder al Formulario de Descargos
                    </a>
                </div>

                <p style="font-size: 13px; color: #6b7280;">
                    <strong>Recuerde:</strong> Este enlace solo estará activo el día de la diligencia programada.
                </p>
            </div>
        @endif

        <div class="alert">
            <h2 style="color: #dc2626; margin-top: 0;">Muy Importante</h2>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Su <strong>asistencia es obligatoria</strong> a la diligencia de descargos</li>
                <li>Si no se presenta, el proceso continuará sin su participación</li>
                <li>Tiene derecho a presentar las pruebas y argumentos que considere necesarios para su defensa</li>
            </ul>
        </div>

        <div class="important">
            <h2>Recomendaciones para Mañana</h2>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Prepare con anticipación los documentos o evidencias que desee presentar</li>
                <li>Tenga a la mano el enlace de acceso al formulario</li>
                <li>Asegúrese de contar con una conexión estable a internet</li>
                <li>Disponga del tiempo necesario para responder todas las preguntas (aproximadamente 45 minutos)</li>
            </ul>
        </div>

        <p>Si tiene alguna duda o requiere información adicional, por favor comuníquese con el área de Recursos Humanos de {{ $empresa->razon_social }} <strong>antes de la diligencia</strong>.</p>

        <p>Atentamente,</p>
        <p><strong>{{ $empresa->razon_social }}</strong><br>
            Área de Recursos Humanos</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático de recordatorio.</p>
        <p>Por favor, no responda a este correo. Para comunicarse, utilice los canales oficiales de la empresa.</p>
    </div>

    {{-- Pixel de seguimiento de apertura (invisible) --}}
    @if(isset($trackingToken))
    <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}"
         width="1" height="1"
         style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;"
         alt="" />
    @endif
</body>

</html>
