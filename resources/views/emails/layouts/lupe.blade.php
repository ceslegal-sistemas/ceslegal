{{--
    Layout de correos con la marca LUPE Legal: el "hero card" de "Mi Reglamento
    Interno" (degradado cálido rosa→durazno→blanco con brillo rosa y dorado, badge,
    título, CTA en degradado rojo→naranja y la mascota) en vez de cabeceras planas
    y cajas con barra vertical.

    CLARO por defecto (así se ve en el panel y es lo que más legible queda en
    cualquier cliente). Si el dispositivo/cliente está en modo oscuro y soporta
    prefers-color-scheme (Apple Mail, iOS Mail, Outlook.com...), las clases .lupe-*
    de abajo lo pasan a la versión oscura de la marca. Gmail ignora esa media query:
    ahí el correo se ve claro (o con su propia inversión automática).

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

    Tablas + estilos en línea; degradados/brillos son un extra - Outlook y clientes
    que los ignoran caen al color sólido de bgcolor, igual de legible.
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
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $titulo ?? 'LUPE Legal' }}</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }

        @media only screen and (max-width: 620px) {
            .lupe-wrap { width: 100% !important; }
            .lupe-hero-pad { padding: 24px 20px 26px !important; }
            .lupe-card-pad { padding: 24px 20px !important; }
            .lupe-title { font-size: 25px !important; }
            .lupe-mascota { width: 64px !important; }
        }

        /* ═══ Modo oscuro (clientes que lo soportan) ═══ */
        @media (prefers-color-scheme: dark) {
            .lupe-body { background-color: #0f0a0b !important; }
            .lupe-hero {
                background-color: #1f1114 !important;
                background-image: radial-gradient(circle at 100% 0%, rgba(225,29,72,0.5) 0%, rgba(225,29,72,0) 52%), radial-gradient(circle at 0% 100%, rgba(201,168,76,0.28) 0%, rgba(201,168,76,0) 46%), linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%) !important;
                border-color: #3a1d27 !important;
            }
            .lupe-marca { color: #fb7185 !important; }
            .lupe-marca span { color: #c9a84c !important; }
            .lupe-badge { background-color: #3a1620 !important; border-color: #6b2236 !important; color: #fecdd3 !important; }
            .lupe-title { color: #ffffff !important; }
            .lupe-sub { color: #cbd5e1 !important; }
            .lupe-card { background-color: #1a1214 !important; border-color: #3a1d27 !important; color: #e5e7eb !important; }
            .lupe-card p, .lupe-card li { color: #d1d5db !important; }
            .lupe-card strong { color: #ffffff !important; }
            .lupe-datos { background-color: #2a1519 !important; }
            .lupe-datos-sep { border-color: #3d2128 !important; }
            .lupe-datos-label { color: #fb7185 !important; }
            .lupe-datos-valor { color: #f3f4f6 !important; }
            .lupe-caja { background-color: #231a1c !important; color: #e5e7eb !important; }
            .lupe-aviso-ambar { background-color: #2b2110 !important; border-color: #5c4513 !important; }
            .lupe-aviso-ambar .lupe-aviso-t { color: #fcd34d !important; } .lupe-aviso-ambar .lupe-aviso-x { color: #fde68a !important; }
            .lupe-aviso-rosa { background-color: #2e1519 !important; border-color: #62222f !important; }
            .lupe-aviso-rosa .lupe-aviso-t { color: #fda4af !important; } .lupe-aviso-rosa .lupe-aviso-x { color: #fecdd3 !important; }
            .lupe-aviso-verde { background-color: #10241a !important; border-color: #1f5236 !important; }
            .lupe-aviso-verde .lupe-aviso-t { color: #86efac !important; } .lupe-aviso-verde .lupe-aviso-x { color: #bbf7d0 !important; }
            .lupe-aviso-azul { background-color: #111d33 !important; border-color: #24407a !important; }
            .lupe-aviso-azul .lupe-aviso-t { color: #93c5fd !important; } .lupe-aviso-azul .lupe-aviso-x { color: #bfdbfe !important; }
            .lupe-code { background-color: #2a1519 !important; }
            .lupe-code span { color: #fda4af !important; }
            .lupe-pie, .lupe-pie span { color: #9ca3af !important; }
            .lupe-link { color: #fda4af !important; }
        }
    </style>
</head>
<body class="lupe-body" style="margin:0;padding:0;background-color:#fdf2f4;-webkit-text-size-adjust:100%;">
    {{-- Vista previa en la bandeja de entrada (oculta en el cuerpo) --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#fdf2f4;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf2f4" class="lupe-body" style="background-color:#fdf2f4;">
        <tr>
            <td align="center" style="padding:28px 12px 36px;">

                <table role="presentation" class="lupe-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                    {{-- ═══ HERO (mismo look claro que "Mi Reglamento Interno") ═══ --}}
                    <tr>
                        <td bgcolor="#fff1f2" class="lupe-hero lupe-hero-pad"
                            style="background-color:#fff1f2;background-image:radial-gradient(circle at 100% 0%, rgba(225,29,72,0.20) 0%, rgba(225,29,72,0) 55%),radial-gradient(circle at 0% 100%, rgba(201,168,76,0.22) 0%, rgba(201,168,76,0) 48%),linear-gradient(150deg,#fff1f2 0%,#fff7ed 55%,#ffffff 100%);border:1px solid #f9c9d3;border-radius:22px;padding:30px 32px 32px;">

                            {{-- Fila superior: marca + mascota (siempre visible, también en celular) --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle" style="font-family:Arial,Helvetica,sans-serif;">
                                        <p class="lupe-marca" style="margin:0;font-size:12px;font-weight:700;letter-spacing:3px;color:#be123c;text-transform:uppercase;">LUPE <span style="color:#a16207;">Legal</span></p>
                                    </td>
                                    <td valign="middle" align="right" width="96">
                                        <img src="{{ $mascota }}" class="lupe-mascota" width="84" alt="LUPE Legal" style="display:block;width:84px;height:auto;border:0;margin-left:auto;">
                                    </td>
                                </tr>
                            </table>

                            <div style="font-family:Arial,Helvetica,sans-serif;">
                                <span class="lupe-badge" style="display:inline-block;margin-top:6px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#be123c;background-color:#ffe4e8;border:1px solid #f7b8c4;border-radius:30px;padding:6px 14px;">{{ $badge }}</span>

                                <h1 class="lupe-title" style="margin:14px 0 0;font-size:29px;line-height:1.2;font-weight:800;letter-spacing:-0.5px;color:#1c1917;">{{ $titulo ?? '' }}</h1>

                                @if ($subtitulo)
                                    <p class="lupe-sub" style="margin:12px 0 0;font-size:15px;line-height:1.55;color:#475569;">{{ $subtitulo }}</p>
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
                            </div>
                        </td>
                    </tr>

                    <tr><td style="height:14px;line-height:14px;font-size:14px;">&nbsp;</td></tr>

                    {{-- ═══ CONTENIDO ═══ --}}
                    <tr>
                        <td bgcolor="#ffffff" class="lupe-card lupe-card-pad"
                            style="background-color:#ffffff;border-radius:22px;border:1px solid #fbd5dc;padding:32px 34px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#374151;">
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Enlace de respaldo del CTA: por si el botón no funciona --}}
                    @if ($cta)
                        <tr>
                            <td class="lupe-pie" style="padding:18px 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#6b7280;text-align:center;">
                                ¿El botón no funciona? Copie y pegue este enlace en su navegador:<br>
                                <a class="lupe-link" href="{{ $cta['url'] }}" target="_blank" style="color:#be123c;word-break:break-all;">{{ $cta['url'] }}</a>
                            </td>
                        </tr>
                    @endif

                    {{-- ═══ PIE ═══ --}}
                    <tr>
                        <td align="center" class="lupe-pie" style="padding:22px 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#9ca3af;text-align:center;">
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
