@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/moedrfvp.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Su empresa en el Reglamento</p>
    </div>

    <p class="pt-body">
        Los datos de su empresa aparecerán en el encabezado oficial del documento.
        Ya cargamos la información del registro — solo confirme o complete lo que falte.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>La <strong>actividad económica</strong> define los riesgos laborales específicos que debe
                cubrir el RIT.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Si tiene varias sedes, el reglamento <strong>aplica a todas</strong> — solo indique cuántos
                trabajadores hay en cada una.</span>
        </div>

    </div>

    <p class="pt-footer">
        Tiempo estimado: 2 minutos. — Los campos con asterisco (*) son obligatorios.
    </p>

</div>
