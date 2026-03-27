@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/fqbvgezn.json"
            trigger="loop" delay="500" stroke="bold" state="hover-roll"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Testigos del hecho</p>
    </div>

    <p class="pt-body">
        Los testigos pueden ser determinantes en el proceso si el trabajador
        controvierte los hechos durante la audiencia.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/shcfcebj.json"
                trigger="loop" delay="500" stroke="bold" state="in-reveal"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Registre el <strong>nombre completo y cargo</strong> de cada testigo.</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/jdgfsfzr.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Solo incluya personas que <strong>presenciaron directamente</strong> los hechos.</span>
        </div>
    </div>

    <p class="pt-footer">
        Esta información quedará registrada en el expediente del proceso disciplinario.
    </p>

</div>
