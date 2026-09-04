{{--
    Tarjeta de progreso de logros "Plazos de Descargos Cumplidos" - mismo
    lenguaje visual .rit-hero que los demás avisos del Dashboard. Solo para
    rol 'cliente' (ver dashboard.blade.php) - cumplimiento proactivo de
    plazos, deliberadamente NO premia volumen de sanciones.
--}}
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="margin-bottom:1.5rem">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <span class="rit-badge rit-badge-sub">
            <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#86efac,secondary:#86efac" data-pt-icon
                data-pt-dark="primary:#86efac,secondary:#86efac"
                data-pt-light="primary:#166534,secondary:#166534"
                style="width:16px;height:16px;flex-shrink:0">
            </lord-icon>
            Logros de cumplimiento
        </span>

        @if($estadoLogros['actual'])
            @php
                $nombre = $estadoLogros['actual']['nombre'];
                $count = $estadoLogros['actual']['count'];
                $meta = $estadoLogros['actual']['meta'];
                $progreso = $estadoLogros['actual']['progreso'];
            @endphp

            <h1 class="rit-title">{{ $nombre }} - {{ $count }} de {{ $meta }}</h1>
            <p class="rit-sub">
                Cada vez que se emite una sanción sin haber dejado vencer ningún plazo legal del proceso,
                avanza hacia este logro. Le faltan {{ $meta - $count }}
                {{ ($meta - $count) === 1 ? 'proceso' : 'procesos' }} más resueltos a tiempo.
            </p>

            <div style="margin-top:1rem;height:8px;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;position:relative;z-index:2">
                <div style="height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#86efac);width:{{ max(4, $progreso) }}%"></div>
            </div>
        @else
            <h1 class="rit-title">¡Todos los logros de cumplimiento desbloqueados!</h1>
            <p class="rit-sub">
                Ha cerrado {{ $estadoLogros['completados'] }} de {{ $estadoLogros['total'] }} metas de plazos
                cumplidos a tiempo. Excelente gestión disciplinaria.
            </p>
        @endif
    </div>
</div>
