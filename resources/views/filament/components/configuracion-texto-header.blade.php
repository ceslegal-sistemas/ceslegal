{{--
    Encabezado de "Editar Texto Configurable" con el lenguaje visual de marca
    (.rit-hero) - antes esta pantalla eran 3 Section genéricas de Filament
    (cajas negras sin identidad), pedido del usuario (2026-10-16) de que el
    panel de administración de textos legales se vea con el mismo
    rediseño UI/UX del resto de LUPE Legal.

    Variables: $clave, $grupo, $descripcion (del record).
--}}
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-ia">
            <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="hover" stroke="bold"
                colors="primary:#fecdd3,secondary:#fecdd3" data-pt-icon
                data-pt-dark="primary:#fecdd3,secondary:#fecdd3"
                data-pt-light="primary:#be123c,secondary:#be123c"
                style="width:14px;height:14px;flex-shrink:0"></lord-icon>
            {{ $grupo }}
        </span>
        <h1 class="rit-title" style="font-family:ui-monospace,monospace;font-size:1.05rem;margin-top:.5rem;">{{ $clave }}</h1>
        @if($descripcion)
            <p class="rit-sub" style="margin-top:.4rem;">{{ $descripcion }}</p>
        @endif
    </div>
</div>
