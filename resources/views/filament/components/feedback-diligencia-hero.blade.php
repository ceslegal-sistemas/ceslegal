{{--
    Branding LUPE Legal para el modal de feedback post-diligencia (antes:
    heading/icono/descripción genéricos de Filament, sin identidad de marca -
    ver backlog-branding-3-pantallas-2026-09-16). Reemplaza ->modalHeading()/
    ->modalDescription()/->modalIcon() por este Placeholder bare, mismo
    patrón ya usado en "Declaración del Autorizador" y en la tarjeta de
    logros del Dashboard (dashboard-logro-descargos-notice.blade.php, mismo
    ícono/badge de color "completado").
--}}
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-sub">
            <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#86efac,secondary:#86efac"
                style="width:16px;height:16px;flex-shrink:0"></lord-icon>
            Diligencia Completada
        </span>
        <h1 class="rit-title">¿Cómo te fue?</h1>
        <p class="rit-sub">Tu opinión nos ayuda a mejorar. Todos los campos son obligatorios.</p>
    </div>
</div>
