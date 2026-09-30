<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reglamento Interno aceptado</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #e11d48; padding: 24px; text-align: center; }
        .header h2 { color: #fff; margin: 0; font-size: 18px; }
        .body { padding: 32px 24px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .check-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
        .check-box p { font-size: 15px; color: #15803d; margin: 0; font-weight: bold; }
        .footer { font-size: 11px; color: #999; text-align: center; padding: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        {{-- Logo real de la empresa si lo tiene cargado (bug real reportado
             por el usuario, 2026-09-30: el correo mostraba el rojo genérico
             de LUPE en vez del logo de la empresa). Fondo blanco para el
             logo (no todos los logos se ven bien sobre rojo) - el rojo de
             marca queda solo como respaldo cuando no hay logo. URL firmada
             PERMANENTE (sin expiración) - un correo puede leerse meses
             después, una firma temporal lo habría dejado roto. Sin
             ->middleware('auth') en esa ruta a propósito: quien abre el
             correo no tiene sesión de panel. --}}
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
                Este correo confirma que quedaste registrado(a) como trabajador(a) de
                <strong>{{ $nombreEmpresa }}</strong> que conoce y aceptó el Reglamento
                Interno de Trabajo vigente.
            </p>

            <div class="check-box">
                <p>✓ Registro y aceptación guardados correctamente</p>
            </div>

            <p style="font-size:13px;color:#777;">
                Si tienes dudas sobre el Reglamento Interno, comunícate directamente con tu empresa.
            </p>
        </div>
        <div class="footer">
            LUPE Legal &mdash; Plataforma de gestión disciplinaria laboral
        </div>
    </div>
</body>
</html>
