<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de verificación</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #4f46e5; padding: 24px; text-align: center; }
        .header h2 { color: #fff; margin: 0; font-size: 18px; }
        .body { padding: 32px 24px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .code-box { background: #f0f0ff; border: 2px dashed #4f46e5; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
        .code-box h1 { font-size: 40px; letter-spacing: 12px; color: #4f46e5; margin: 0; font-family: monospace; }
        .expiry { font-size: 13px; color: #666; text-align: center; margin-bottom: 24px; }
        .instructions { font-size: 13px; color: #555; line-height: 1.6; background: #f9f9f9; border-radius: 6px; padding: 12px 16px; }
        .footer { font-size: 11px; color: #999; text-align: center; padding: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Verificación de identidad</h2>
        </div>
        <div class="body">
            <p class="greeting">Hola, <strong>{{ $nombreTrabajador }}</strong>.</p>
            <p style="font-size:14px;color:#555;">
                Ha recibido este mensaje porque se le ha citado a una diligencia de descargos
                (Expediente: <strong>{{ $procesoCodigo }}</strong>). Para acceder al formulario,
                ingrese el siguiente código:
            </p>

            <div class="code-box">
                <h1>{{ $codigo }}</h1>
            </div>

            <p class="expiry">Este código es válido por <strong>10 minutos</strong>.</p>

            <div class="instructions">
                <strong>Instrucciones:</strong>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    <li>Ingrese el código en la pantalla de verificación.</li>
                    <li>No comparta este código con nadie.</li>
                    <li>Si no solicitó este código, ignórelo.</li>
                </ul>
            </div>
        </div>
        <div class="footer">
            CES Legal &mdash; Plataforma de gestión disciplinaria laboral
        </div>
    </div>
</body>
</html>
