@php
    // Página de error 403 propia - Laravel no trae ninguna por defecto y
    // Filament tampoco, así que cualquier 403 (falta de permiso, rol
    // equivocado, etc.) caía en la página genérica y fea del framework.
    // Hallazgo real del usuario: un cliente que llegaba con una sesión
    // vencida a una URL vieja se quedaba sin ninguna salida.
    //
    // No depende de Filament::getPanel() a propósito: una página de error
    // debe tener las menos dependencias posibles (si algo más está roto,
    // esta página igual debe renderizar). Las rutas /admin y /empresa son
    // estables (paths fijos de AdminPanelProvider/EmpresaPanelProvider).
    $user = auth()->user();
    $urlInicio = $user && ($user->role ?? null) === 'cliente' ? url('/empresa') : url('/admin');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso no permitido - LUPE Legal</title>
    <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">
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
        .error403-wrap {
            width: 100%;
            max-width: 30rem;
        }
        .error403-wrap .rit-title {
            font-size: 1.5rem;
        }
        .error403-wrap .rit-sub {
            font-size: 0.9rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error403-wrap">
        <div class="rit-hero">
            <div class="rit-orb-b"></div>
            <div class="rit-orb-g"></div>
            <div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <span class="rit-badge rit-badge-danger">
                    <lord-icon src="{{ asset('lordicons/wired-outline-1140-warning-triangle-hover-enlarge.json') }}"
                        trigger="loop" delay="800" stroke="bold" state="hover-enlarge"
                        colors="primary:#fb7185,secondary:#fb7185"
                        style="width:16px;height:16px;flex-shrink:0">
                    </lord-icon>
                    Acceso no permitido
                </span>

                <h1 class="rit-title">No tiene permiso para ver esta página</h1>
                <p class="rit-sub">
                    Puede que la sesión haya cambiado desde la última vez que la visitó, o que esta
                    página no esté disponible para su usuario. Vuelva al inicio para seguir navegando
                    con normalidad.
                </p>

                <div class="rit-actions">
                    <a href="{{ $urlInicio }}" class="rit-btn rit-btn-primary">
                        <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                        Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
