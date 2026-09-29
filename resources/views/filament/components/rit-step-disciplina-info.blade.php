{{--
    Rediseño (2026-09-28, pedido explícito del usuario: mismo estilo .rit-hero
    de "Socializa el RIT", sin la línea de acento izquierda de .pt-card).
    Contenido, iconos y textos exactamente los mismos de antes.
--}}
@include('filament.components.pinfo-styles')
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem;">
            <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:32px;height:32px;flex-shrink:0">
            </lord-icon>
            <h1 class="rit-title" style="margin:0;font-size:1.05rem;">Las reglas del juego</h1>
        </div>

        <p class="rit-sub" style="margin-bottom:.75rem;">
            Este es el capítulo más importante del RIT. Sin una lista clara de faltas y sanciones,
            la empresa <strong>no puede sancionar válidamente</strong> - cualquier descargo puede ganarse en un juzgado.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span>Escriba las faltas en <strong>lenguaje normal</strong> - no necesita citar leyes ni artículos.</span>
            </div>

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span>Las sanciones deben ser <strong>proporcionales</strong>: una falta leve NO puede llevar a
                    despido directo.</span>
            </div>

        </div>

        <p class="pt-footer">
            Ya incluimos las faltas más comunes como sugerencias. Puede agregarlas con un clic o escribir las propias.
        </p>

    </div>
</div>
