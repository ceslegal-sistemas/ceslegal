<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trabajador No Se Presentó a Descargos</title>
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
            background-color: #dc2626;
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

        .alert-box {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-neutral {
            background-color: #f3f4f6;
            border-left: 4px solid #6b7280;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .next-steps {
            background-color: #dbeafe;
            border-left: 4px solid #3b82f6;
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

        .trabajador-info {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }

        .trabajador-info h3 {
            color: #92400e;
            margin: 0 0 10px 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Aviso Importante</h1>
        <p style="margin: 10px 0 0 0; font-size: 16px;">El trabajador no se presentó a la diligencia de descargos</p>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $cliente->name }}</strong>,</p>

        <p>Le informamos que el trabajador citado a la diligencia de descargos del proceso <strong>{{ $proceso->codigo }}</strong> <strong>no se presentó</strong> en la fecha y hora programadas.</p>

        <div class="trabajador-info">
            <h3>Datos del Trabajador</h3>
            <p style="margin: 5px 0;"><strong>Nombre:</strong> {{ $trabajador->nombre_completo }}</p>
            <p style="margin: 5px 0;"><strong>Documento:</strong> {{ $trabajador->tipo_documento }} {{ $trabajador->numero_documento }}</p>
            <p style="margin: 5px 0;"><strong>Cargo:</strong> {{ $trabajador->cargo }}</p>
        </div>

        <div class="alert-box">
            <h2 style="color: #dc2626; margin-top: 0;">Estado del Proceso</h2>
            <p>El proceso disciplinario <strong>{{ $proceso->codigo }}</strong> ha sido actualizado al estado <strong>"Descargos No Realizados"</strong>.</p>
            <p style="margin-bottom: 0;">Esto significa que el trabajador no ejerció su derecho a presentar descargos dentro del plazo establecido.</p>
        </div>

        <div class="info-box">
            <h2>Detalles de la Diligencia Programada</h2>
            <p><strong>Fecha programada:</strong> {{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
            <p><strong>Hora programada:</strong> {{ \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('h:i A') }}</p>
            <p><strong>Modalidad:</strong> {{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</p>
        </div>

        <div class="next-steps">
            <h2 style="color: #1e40af;">¿Qué sigue ahora?</h2>
            <p>El proceso disciplinario continuará según lo establecido en el reglamento interno de trabajo:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>El abogado asignado al caso evaluará las pruebas y evidencias disponibles</li>
                <li>Se procederá a emitir la sanción correspondiente basándose en los hechos documentados</li>
                <li>El trabajador será notificado de la decisión tomada</li>
            </ul>
            <p style="margin-bottom: 0;"><strong>Nota:</strong> La no asistencia del trabajador a los descargos no impide que el proceso continúe ni que se aplique la sanción que corresponda.</p>
        </div>

        <div class="info-neutral">
            <h2>Información del Proceso</h2>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Estado actual:</strong> Descargos No Realizados</p>
        </div>

        <p>Puede consultar el detalle completo del proceso y su avance a través del sistema CES Legal.</p>

        <p>Si tiene alguna pregunta sobre el proceso o necesita información adicional, no dude en contactarnos.</p>

        <p>Atentamente,</p>
        <p><strong>Equipo CES Legal</strong><br>
            Gestión de Procesos Disciplinarios</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático generado por el sistema de gestión de procesos disciplinarios.</p>
        <p>Por favor, no responda a este correo. Para comunicarse, utilice los canales oficiales.</p>
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
