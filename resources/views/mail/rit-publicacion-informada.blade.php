<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publicación del Reglamento Interno</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #e11d48; padding: 24px; text-align: center; }
        .header h2 { color: #fff; margin: 0; font-size: 18px; }
        .body { padding: 32px 24px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .info-box { background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
        .info-box p { font-size: 15px; color: #1d4ed8; margin: 0; font-weight: bold; }
        .footer { font-size: 11px; color: #999; text-align: center; padding: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        @if($empresa?->logo_path)
            <div class="header" style="background:#fff;border-bottom:1px solid #eee">
                <img src="{{ \Illuminate\Support\Facades\URL::signedRoute('logo-empresa.mostrar', ['empresa' => $empresa->id]) }}"
                     alt="{{ $nombreEmpresa }}" style="max-height:48px;max-width:220px;display:inline-block">
            </div>
        @else
            <div class="header">
                <h2>Reglamento Interno de Trabajo</h2>
            </div>
        @endif
        <div class="body">
            <p class="greeting">Hola, <strong>{{ $nombreTrabajador }}</strong>.</p>
            <p style="font-size:14px;color:#555;">
                Este correo confirma que quedaste registrado(a) como informado(a) de la
                publicación del Reglamento Interno de Trabajo de
                <strong>{{ $nombreEmpresa }}</strong>.
            </p>

            <div class="info-box">
                <p>✓ Publicación confirmada</p>
            </div>

            <p style="font-size:13px;color:#777;">
                Si tienes alguna objeción sobre el Reglamento, comunícala directamente a tu
                empresa dentro de los términos que la ley establece. Próximamente recibirás
                un nuevo correo para completar la socialización del Reglamento.
            </p>
        </div>
        <div class="footer">
            LUPE Legal &mdash; Plataforma de gestión disciplinaria laboral
        </div>
    </div>
</body>
</html>
