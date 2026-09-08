{{--
    Rediseño pedido por el usuario (2026-09-07): mismo lenguaje visual
    .rit-hero que "Logros de cumplimiento" (dashboard-logro-descargos-notice.blade.php),
    en vez del estilo .pt-card anterior (más plano, no coincidía con el
    resto del Dashboard). El modal insistente (dashboard-sugerencias-rit-modal.blade.php)
    no se toca - el usuario pidió específicamente este banner.
--}}
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="margin-bottom:1.5rem">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <span class="rit-badge rit-badge-danger">
            <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#fca5a5,secondary:#fca5a5" data-pt-icon
                data-pt-dark="primary:#fca5a5,secondary:#fca5a5"
                data-pt-light="primary:#b91c1c,secondary:#b91c1c"
                style="width:16px;height:16px;flex-shrink:0">
            </lord-icon>
            {{ $totalSugerencias === 1 ? 'Actualización legal pendiente' : "{$totalSugerencias} actualizaciones legales pendientes" }}
        </span>

        <h1 class="rit-title">
            {{ $totalSugerencias === 1
                ? 'Hay una actualización legal que aplica a su Reglamento Interno'
                : "Hay {$totalSugerencias} actualizaciones legales que aplican a su Reglamento Interno" }}
        </h1>
        <p class="rit-sub">
            Salió normativa nueva y ya identificamos los cambios puntuales que le corresponden a su RIT.
            Recuerde: una vez actualizado, debe socializar los cambios con sus trabajadores para que sepan qué cambió.
        </p>

        <div class="rit-actions">
            <a href="{{ url('/empresa/mi-reglamento-interno') }}" class="rit-btn rit-btn-danger">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                </svg>
                Revisar y actualizar
            </a>
        </div>
    </div>
</div>
