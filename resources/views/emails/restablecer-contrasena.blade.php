<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure su contraseña - LUPE Legal</title>
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
            background-color: #E11D48;
            color: white;
            padding: 28px 30px 22px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 20px;
            font-weight: bold;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .content {
            padding: 28px 30px;
        }
        .cta-block {
            text-align: center;
            margin: 0 0 24px 0;
        }
        .btn-primary {
            display: inline-block;
            background-color: #E11D48;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            padding: 14px 32px;
            border-radius: 6px;
            letter-spacing: 0.2px;
        }
        .cta-url {
            margin-top: 16px;
            font-size: 11px;
            color: #6b7280;
            word-break: break-all;
        }
        .cta-url a { color: #2563eb; }
        .info-box {
            background-color: #f9fafb;
            border-left: 4px solid #E11D48;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #4b5563;
            margin: 20px 0 0 0;
        }
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
            <h1>{{ $nombreEmpresa }}</h1>
            <p>Configure su contraseña de acceso</p>
        </div>

        <div class="content">

            <p style="margin:0 0 18px 0;">
                Estimado(a) <strong>{{ $nombre }}</strong>,<br>
                se creó su cuenta de acceso para gestionar los procesos disciplinarios de <strong>{{ $nombreEmpresa }}</strong> en la plataforma LUPE Legal. Para ingresar por primera vez, configure su contraseña haciendo clic en el siguiente botón.
            </p>

            <div class="cta-block">
                <a href="{{ $url }}" class="btn-primary">
                    Configurar contraseña
                </a>
                <div class="cta-url">
                    Si el botón no abre, copie este enlace en su navegador:<br>
                    <a href="{{ $url }}">{{ $url }}</a>
                </div>
            </div>

            <div class="info-box">
                Este enlace es válido por <strong>{{ $minutosExpiracion }} minutos</strong>. Si usted no solicitó esta cuenta, puede ignorar este correo.
            </div>

        </div>

        <div class="footer">
            <p style="margin:0 0 4px 0;">Este correo fue generado automáticamente. Por favor no responda.</p>
            <p style="margin:0;">LUPE Legal</p>
        </div>

    </div>
</body>

</html>
