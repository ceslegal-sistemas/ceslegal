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
            <lord-icon
                src="https://cdn.lordicon.com/fqbvgezn.json"
                trigger="loop" delay="500" stroke="bold" state="hover-roll"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:32px;height:32px;flex-shrink:0">
            </lord-icon>
            <h1 class="rit-title" style="margin:0;font-size:1.05rem;">Testigos del hecho</h1>
        </div>

        <p class="rit-sub" style="margin-bottom:.75rem;">
            Los testigos pueden ser determinantes en el proceso si el trabajador
            controvierte los hechos durante la audiencia.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
            <div class="pt-bullet">
                <lord-icon
                    src="https://cdn.lordicon.com/shcfcebj.json"
                    trigger="loop" delay="500" stroke="bold" state="in-reveal"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                </lord-icon>
                <span>Registre el <strong>nombre completo y cargo</strong> de cada testigo.</span>
            </div>
            <div class="pt-bullet">
                <lord-icon
                    src="https://cdn.lordicon.com/jdgfsfzr.json"
                    trigger="loop" delay="500" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                </lord-icon>
                <span>Solo incluya personas que <strong>presenciaron directamente</strong> los hechos.</span>
            </div>
        </div>

        <p class="pt-footer">
            Esta información quedará registrada en el expediente del proceso disciplinario.
        </p>

    </div>
</div>
