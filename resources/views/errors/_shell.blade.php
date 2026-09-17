{{--
    Cascarón compartido por todas las páginas de error propias (403, 404, 405,
    419, 500) - antes vivía completo y duplicado dentro de 403.blade.php.
    Variables esperadas:
      $codigo        string  "403", "404", etc. (se muestra como etiqueta pequeña)
      $tituloPagina  string  <title> de la pestaña del navegador
      $badgeClase    string  variante del badge .rit-badge-* (ver lupe-hero-styles)
      $badgeIconUrl  string  URL del lordicon del badge
      $badgeTexto    string  texto corto dentro del badge
      $titulo        string  encabezado principal (h1)
      $mensaje       string  texto de apoyo, sin detalle técnico
      $mostrarContacto bool  si se agrega la línea de contacto real (solo 500)

    No depende de Filament::getPanel() a propósito (heredado de 403.blade.php):
    una página de error debe tener las menos dependencias posibles - si algo
    más está roto, esta página igual debe renderizar. Las rutas /admin y
    /empresa son estables (paths fijos de AdminPanelProvider/EmpresaPanelProvider).
--}}
@php
    $mostrarContacto = $mostrarContacto ?? false;
    $user = auth()->user();
    $urlInicio = $user && ($user->role ?? null) === 'cliente' ? url('/empresa') : url('/admin');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPagina }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">

    {{--
        Misma clave 'theme' (light/dark/system) que usa el theme-switcher
        nativo de Filament (vendor/filament/filament/resources/views/
        components/theme-switcher) - si el usuario ya eligió un modo desde
        el panel, esta página lo respeta, y viceversa. Se aplica ANTES de
        cargar los estilos para no parpadear en el modo equivocado al
        cargar la página.
    --}}
    <script>
        (function () {
            var tema = localStorage.getItem('theme') || 'system';
            var esOscuro = tema === 'dark' || (tema === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', esOscuro);
        })();
    </script>

    <script src="https://cdn.lordicon.com/lordicon.js"></script>
    @include('filament.components.lupe-hero-styles')
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0b0708;
        }
        html:not(.dark) body {
            background: #f8fafc;
        }
        .error-theme-switcher {
            position: fixed;
            top: 1rem;
            right: 1rem;
            display: inline-flex;
            gap: 0.125rem;
            padding: 0.25rem;
            border-radius: 0.625rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        html:not(.dark) .error-theme-switcher {
            background: #fff;
            border-color: rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }
        .error-theme-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 0.5rem;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
        }
        .error-theme-btn:hover {
            color: #e2e8f0;
        }
        html:not(.dark) .error-theme-btn {
            color: #94a3b8;
        }
        html:not(.dark) .error-theme-btn:hover {
            color: #475569;
        }
        .error-theme-btn.is-active {
            background: rgba(225, 29, 72, 0.15);
            color: #fb7185;
        }
        html:not(.dark) .error-theme-btn.is-active {
            background: rgba(225, 29, 72, 0.08);
            color: #e11d48;
        }
        .error-wrap {
            width: 100%;
            max-width: 30rem;
        }
        .error-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .error-logo img {
            height: 88px;
            width: auto;
        }
        .error-codigo {
            text-align: center;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 0.75rem;
        }
        .error-wrap .rit-title {
            font-size: 1.5rem;
        }
        .error-wrap .rit-sub {
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .error-contacto {
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.65);
        }
        html:not(.dark) .error-contacto {
            border-top-color: rgba(0, 0, 0, 0.08);
            color: rgba(17, 24, 39, 0.65);
        }
        .error-contacto a {
            color: inherit;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="error-theme-switcher">
        <button type="button" class="error-theme-btn" data-tema="light" title="Claro" aria-label="Modo claro">
            <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
        </button>
        <button type="button" class="error-theme-btn" data-tema="dark" title="Oscuro" aria-label="Modo oscuro">
            <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
        </button>
        <button type="button" class="error-theme-btn" data-tema="system" title="Sistema" aria-label="Modo del sistema">
            <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
        </button>
    </div>

    <div class="error-wrap">
        <div class="error-logo">
            <img src="{{ asset('images/lupe-logo.png') }}" alt="LUPE Legal">
        </div>
        <p class="error-codigo">Error {{ $codigo }}</p>

        <div class="rit-hero">
            <div class="rit-orb-b"></div>
            <div class="rit-orb-g"></div>
            <div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <span class="rit-badge {{ $badgeClase }}">
                    <lord-icon src="{{ $badgeIconUrl }}"
                        trigger="loop" delay="800" stroke="bold" state="hover-enlarge"
                        colors="primary:#fb7185,secondary:#fb7185"
                        style="width:16px;height:16px;flex-shrink:0">
                    </lord-icon>
                    {{ $badgeTexto }}
                </span>

                <h1 class="rit-title">{{ $titulo }}</h1>
                <p class="rit-sub">{{ $mensaje }}</p>

                <div class="rit-actions">
                    <a href="{{ $urlInicio }}" class="rit-btn rit-btn-primary">
                        <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                        Volver al inicio
                    </a>
                </div>

                @if($mostrarContacto)
                    <div class="error-contacto">
                        ¿Necesita ayuda? Escríbanos por WhatsApp al
                        <a href="https://wa.me/573196777103">+57 319 6777103</a>
                        o al correo <a href="mailto:admin@ceslegal.co">admin@ceslegal.co</a>.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            function temaActual() {
                return localStorage.getItem('theme') || 'system';
            }

            function aplicar(tema) {
                var esOscuro = tema === 'dark' || (tema === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', esOscuro);

                document.querySelectorAll('.error-theme-btn').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.dataset.tema === tema);
                });
            }

            document.querySelectorAll('.error-theme-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    localStorage.setItem('theme', btn.dataset.tema);
                    aplicar(btn.dataset.tema);
                });
            });

            aplicar(temaActual());
        })();
    </script>
</body>
</html>
