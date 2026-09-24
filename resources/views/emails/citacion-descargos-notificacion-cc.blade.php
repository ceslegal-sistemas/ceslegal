<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citación a descargos - {{ $trabajador->nombre_completo }}</title>
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
            background-color: #1e40af;
            color: white;
            padding: 24px 30px 18px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 19px;
            font-weight: bold;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .content {
            padding: 26px 30px;
        }
        .info-box {
            background-color: #f9fafb;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 14px 16px;
            margin: 0 0 20px 0;
            font-size: 14px;
        }
        .info-box p { margin: 4px 0; }
        .footer {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 16px 30px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        @include('emails.components.logo-empresa-header', ['empresa' => $empresa])

        <div class="header">
            <h1>Citación a descargos - Copia informativa</h1>
            <p>{{ $empresa->razon_social }} &mdash; Proceso {{ $proceso->codigo }}</p>
        </div>

        <div class="content">
            <p style="margin:0 0 20px 0;">
                Le informamos que se citó formalmente a <strong>{{ $trabajador->nombre_completo }}</strong>
                ({{ $trabajador->cargo }}) a una audiencia de descargos. Este correo es solo informativo;
                el trabajador ya recibió la citación formal con el enlace y las instrucciones para participar.
            </p>

            <div class="info-box">
                <p><strong>Trabajador:</strong> {{ $trabajador->nombre_completo }}</p>
                <p><strong>Cargo:</strong> {{ $trabajador->cargo }}</p>
                <p><strong>Código del proceso:</strong> {{ $proceso->codigo }}</p>
                @if($proceso->fecha_descargos_programada)
                <p><strong>Fecha de la audiencia:</strong> {{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
                @endif
            </div>

            <p style="font-size:14px; margin:0;">
                Adjuntamos el documento de citación enviado al trabajador para su conocimiento.
            </p>

            <p style="font-size:14px; margin:16px 0 0 0;">
                Atentamente,<br>
                <strong>{{ $empresa->razon_social }}</strong><br>
                <span style="color:#6b7280;">Área de Recursos Humanos</span>
            </p>
        </div>

        <div class="footer">
            <p style="margin:0 0 4px 0;">Este correo fue generado automáticamente por el sistema de gestión de procesos disciplinarios.</p>
            <p style="margin:0;">Por favor no responda a este correo. Use los canales oficiales de la empresa.</p>
        </div>

    </div>
</body>

</html>
