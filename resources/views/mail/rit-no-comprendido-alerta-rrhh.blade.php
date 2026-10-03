<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta: trabajador no comprende el Reglamento Interno</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #e11d48; padding: 24px; text-align: center; }
        .header h2 { color: #fff; margin: 0; font-size: 18px; }
        .body { padding: 32px 24px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .alert-box { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
        .alert-box p { font-size: 15px; color: #b91c1c; margin: 0; font-weight: bold; }
        .footer { font-size: 11px; color: #999; text-align: center; padding: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Alerta de Recursos Humanos</h2>
        </div>
        <div class="body">
            <p class="greeting">Hola,</p>
            <p style="font-size:14px;color:#555;">
                El trabajador <strong>{{ $nombreTrabajador }}</strong> (documento
                <strong>{{ $documentoTrabajador }}</strong>) manifestó, en dos oportunidades, no
                comprender el Reglamento Interno de Trabajo de <strong>{{ $empresa?->razon_social }}</strong>,
                a pesar de habérsele presentado un video explicativo, un resumen en lenguaje
                sencillo y el documento completo con los cambios resaltados.
            </p>

            <div class="alert-box">
                <p>⚠ Proceso de socialización bloqueado</p>
            </div>

            <p style="font-size:13px;color:#777;">
                El sistema no pudo continuar con el registro de socialización de este
                trabajador. Les corresponde a ustedes, como área de Recursos Humanos, decidir
                los siguientes pasos.
            </p>
        </div>
        <div class="footer">
            LUPE Legal &mdash; Plataforma de gestión disciplinaria laboral
        </div>
    </div>
</body>
</html>
