@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Las reglas del juego</p>
    </div>

    <p class="pt-body">
        Este es el capítulo más importante del RIT. Sin una lista clara de faltas y sanciones,
        la empresa <strong>no puede sancionar válidamente</strong> — cualquier descargo puede ganarse en un juzgado.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Escriba las faltas en <strong>lenguaje normal</strong> — no necesita citar leyes ni artículos.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
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
