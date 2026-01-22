<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citación a Audiencia de Descargos</title>
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
            background-color: #2563eb;
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
            color: #2563eb;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Citación a Audiencia de Descargos</h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        <p>Por medio del presente, se le notifica que ha sido citado(a) a una audiencia de descargos relacionada con el
            proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            {{-- <p><strong>Área:</strong> {{ $trabajador->area ?? 'N/A' }}</p> --}}
        </div>

        @if ($proceso->fecha_descargos_programada)
            <div class="important">
                <h2>Fecha y Hora de la Audiencia</h2>
                <p style="font-size: 18px; margin: 10px 0;">
                    <strong>{{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') . ' a las ' . \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') }}</strong>
                </p>
                <p><strong>Modalidad:</strong> {{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</p>
            </div>
        @endif

        <div class="info-box">
            <h2>Documento Adjunto</h2>
            <p>Encontrará adjunto a este correo el documento oficial de citación con todos los detalles del proceso y
                sus derechos.</p>
            <p><strong>Por favor, lea cuidadosamente el documento adjunto.</strong></p>
        </div>

        @if ($linkDescargos)
            <div class="info-box" style="background-color: #dbeafe; border-left-color: #3b82f6;">
                <h2>Acceso a Descargos en Línea</h2>
                <p>Podrá presentar sus descargos a través de nuestro formulario en línea, el cual estará disponible
                    <strong>únicamente el día de la audiencia</strong>.</p>

                @if ($fechaAccesoPermitida)
                    <p><strong>Fecha de acceso permitida:</strong><br>
                        {{ $fechaAccesoPermitida->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
                @endif

                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ $linkDescargos }}"
                        style="display: inline-block; padding: 15px 30px; background-color: #3b82f6; color: white;
                          text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        Acceder a Formulario de Descargos
                    </a>
                </div>

                <p style="font-size: 13px; color: #6b7280;">
                    <strong>Nota importante:</strong> Este enlace es personal e intransferible. Solo estará activo el
                    día programado para la audiencia.
                    Guarde este enlace para poder acceder cuando corresponda.
                </p>
            </div>
        @endif

        <div class="important">
            <h2>Importante</h2>
            <ul>
                <li>Es <strong>obligatoria</strong> su asistencia a la audiencia de descargos</li>
                <li>Tiene derecho a presentar las pruebas que considere pertinentes</li>
                <li>Revise el documento adjunto para conocer todos sus derechos</li>
                @if ($linkDescargos)
                    <li>Podrá presentar sus descargos en línea a través del enlace proporcionado arriba</li>
                @endif
            </ul>
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
