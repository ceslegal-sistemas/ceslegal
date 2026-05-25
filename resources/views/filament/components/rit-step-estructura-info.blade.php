@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/jdgfsfzr.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">¿Quién manda en su empresa?</p>
    </div>

    <p class="pt-body">
        El RIT debe especificar qué cargos tienen autoridad para corregir o sancionar empleados.
        Sin esto, cualquier sanción puede impugnarse legalmente.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Liste los cargos <strong>tal como los llaman internamente</strong> — no necesita usar nombres formales.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Solo marque <strong>"puede sancionar"</strong> en los cargos que realmente toman esas
                decisiones (gerentes, jefes de área).</span>
        </div>

    </div>

    <p class="pt-footer">
        Tip: Si tiene pocos cargos (p. ej. Gerente y Operario), simplemente agréguelos.
        No necesita un organigrama formal.
    </p>

</div>
