<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Estado de Descargos</title>
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
            background-color: {{ $estado === 'descargos_realizados' ? '#059669' : '#dc2626' }};
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
            border-left: 4px solid {{ $estado === 'descargos_realizados' ? '#059669' : '#dc2626' }};
            border-radius: 4px;
        }

        .important {
            background-color: #fef3c7;
            border-left-color: #f59e0b;
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
            color: {{ $estado === 'descargos_realizados' ? '#059669' : '#dc2626' }};
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
            background-color: {{ $estado === 'descargos_realizados' ? '#d1fae5' : '#fee2e2' }};
            color: {{ $estado === 'descargos_realizados' ? '#065f46' : '#991b1b' }};
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">
            @if($estado === 'descargos_realizados')
                Descargos Completados
            @else
                Descargos No Realizados
            @endif
        </h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        @if($estado === 'descargos_realizados')
            <p>Le informamos que hemos recibido correctamente sus descargos correspondientes al proceso disciplinario
                <strong>{{ $proceso->codigo }}</strong>.</p>

            <div class="info-box">
                <h2>Estado del Proceso</h2>
                <p><span class="status-badge">Descargos Completados</span></p>
                <p>Sus respuestas y la documentación aportada han sido registradas exitosamente en el sistema.</p>
            </div>

            <div class="important">
                <h2>Siguientes Pasos</h2>
                <p>El equipo jurídico revisará la información proporcionada y procederá a emitir la decisión correspondiente.</p>
                <p>Recibirá una notificación adicional cuando se tome una decisión sobre su caso.</p>
            </div>

        @else
            <p>Le informamos que el plazo para presentar sus descargos en el proceso disciplinario
                <strong>{{ $proceso->codigo }}</strong> ha vencido sin que se recibiera su respuesta.</p>

            <div class="info-box">
                <h2>Estado del Proceso</h2>
                <p><span class="status-badge">Descargos No Realizados</span></p>
                <p>No se registró su asistencia ni respuesta a la diligencia de descargos programada.</p>
            </div>

            <div class="important">
                <h2>Consecuencias</h2>
                <p>De acuerdo con el procedimiento disciplinario, al no presentar descargos:</p>
                <ul>
                    <li>Se procederá a tomar una decisión con base en la información disponible</li>
                    <li>Se considerará que usted renunció a su derecho de defensa en esta etapa</li>
                    <li>El proceso continuará su curso normal</li>
                </ul>
            </div>
        @endif

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            @if($proceso->fecha_descargos_programada)
                <p><strong>Fecha programada de descargos:</strong>
                    {{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                </p>
            @endif
        </div>

        <p>Si tiene alguna pregunta o requiere información adicional, por favor comuníquese con el área de Recursos
            Humanos de {{ $empresa->razon_social }}.</p>

        <p>Atentamente,</p>
        <p><strong>{{ $empresa->razon_social }}</strong><br>
            Área de Recursos Humanos</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático generado por el sistema de gestión de procesos disciplinarios.</p>
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
