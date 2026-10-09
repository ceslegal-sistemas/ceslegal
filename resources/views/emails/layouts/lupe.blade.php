{{--
    Layout de correos con la marca LUPE Legal: el mismo "hero card" del panel
    (fondo oscuro cálido con brillo rosa/dorado, badge, título, CTA en degradado
    rojo→naranja, mascota) en vez de cabeceras planas y cajas con barra vertical.

    Uso (en cada correo):
        @extends('emails.layouts.lupe', [
            'preheader' => 'Texto corto de vista previa',
            'badge'     => 'Impugnación',
            'titulo'    => 'Título del correo',
            'subtitulo' => 'Frase de apoyo',          // opcional
            'cta'       => ['label' => '...', 'url' => '...'],  // opcional
            'empresa'   => $empresa,                   // opcional (nombre al pie)
        ])
        @section('contenido') ... @endsection

    Compatibilidad: tablas + estilos en línea. Los degradados/brillos son un extra
    (Apple Mail, Gmail web/app); Outlook y clientes que los ignoran caen al color
    sólido oscuro de bgcolor, igual de legible. Sin imágenes de fondo.
--}}
@php
    $badge = $badge ?? 'LUPE Legal';
    $subtitulo = $subtitulo ?? null;
    $cta = $cta ?? null;
    $preheader = $preheader ?? ($titulo ?? '');
    $empresaNombre = isset($empresa) ? ($empresa->razon_social ?? null) : null;
    $mascota = asset('images/lupe-logo.png');
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <title>{{ $titulo ?? 'LUPE Legal' }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .lupe-wrap { width: 100% !important; }
            .lupe-hero-pad { padding: 26px 22px !important; }
            .lupe-card-pad { padding: 24px 20px !important; }
            .lupe-mascota { display: none !important; }
            .lupe-title { font-size: 24px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#fdf2f4;-webkit-text-size-adjust:100%;">
    {{-- Vista previa en la bandeja de entrada (oculta en el cuerpo) --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#fdf2f4;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf2f4" style="background-color:#fdf2f4;">
        <tr>
            <td align="center" style="padding:28px 12px 36px;">

                <table role="presentation" class="lupe-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                    {{-- ═══ HERO ═══ --}}
                    <tr>
                        <td bgcolor="#1f1114" class="lupe-hero-pad"
                            style="background-color:#1f1114;background-image:radial-gradient(circle at 100% 0%, rgba(225,29,72,0.55) 0%, rgba(225,29,72,0) 52%),radial-gradient(circle at 0% 100%, rgba(201,168,76,0.30) 0%, rgba(201,168,76,0) 46%),linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%);border-radius:22px;padding:34px 34px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="top" style="font-family:Arial,Helvetica,sans-serif;">
                                        {{-- Marca --}}
                                        <p style="margin:0 0 18px;font-size:12px;font-weight:700;letter-spacing:3px;color:#fb7185;text-transform:uppercase;">LUPE <span style="color:#c9a84c;">Legal</span></p>

                                        {{-- Badge --}}
                                        <span style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#fecdd3;background-color:#3a1620;border:1px solid #6b2236;border-radius:30px;padding:6px 14px;">{{ $badge }}</span>

                                        <h1 class="lupe-title" style="margin:16px 0 0;font-size:28px;line-height:1.2;font-weight:800;letter-spacing:-0.5px;color:#ffffff;">{{ $titulo ?? '' }}</h1>

                                        @if ($subtitulo)
                                            <p style="margin:12px 0 0;font-size:15px;line-height:1.55;color:#cbd5e1;">{{ $subtitulo }}</p>
                                        @endif

                                        @if ($cta)
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
                                                <tr>
                                                    <td bgcolor="#e11d48" style="border-radius:12px;background-color:#e11d48;background-image:linear-gradient(135deg,#e11d48,#f97316);">
                                                        <a href="{{ $cta['url'] }}" target="_blank"
                                                           style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $cta['label'] }} &rarr;</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                    <td class="lupe-mascota" width="110" valign="bottom" align="right" style="padding-left:12px;">
                                        <img src="{{ $mascota }}" width="96" alt="" style="display:block;width:96px;height:auto;border:0;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr><td style="height:14px;line-height:14px;font-size:14px;">&nbsp;</td></tr>

                    {{-- ═══ CONTENIDO ═══ --}}
                    <tr>
                        <td bgcolor="#ffffff" class="lupe-card-pad"
                            style="background-color:#ffffff;border-radius:22px;border:1px solid #fbd5dc;padding:32px 34px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#374151;">
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Enlace de respaldo del CTA: por si el botón no funciona --}}
                    @if ($cta)
                        <tr>
                            <td style="padding:18px 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#6b7280;text-align:center;">
                                ¿El botón no funciona? Copie y pegue este enlace en su navegador:<br>
                                <a href="{{ $cta['url'] }}" target="_blank" style="color:#be123c;word-break:break-all;">{{ $cta['url'] }}</a>
                            </td>
                        </tr>
                    @endif

                    {{-- ═══ PIE ═══ --}}
                    <tr>
                        <td align="center" style="padding:22px 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#9ca3af;text-align:center;">
                            @if ($empresaNombre)
                                <span style="color:#6b7280;">Enviado en nombre de <strong>{{ $empresaNombre }}</strong></span><br>
                            @endif
                            LUPE Legal &mdash; Plataforma de gestión disciplinaria laboral
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
