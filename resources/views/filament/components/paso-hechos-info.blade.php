@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/hmpomorl.json"
            trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Descripción del hecho</p>
    </div>

    <p class="pt-body">
        Cuente con sus propias palabras qué ocurrió. No se preocupe por el lenguaje jurídico,
        la IA lo transformará después.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/bpptgtfr.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Sea <strong>específico</strong>: qué hizo el trabajador, cómo se enteró la empresa.</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/vgwutnhw.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>El panel lateral analizará su texto <strong>en tiempo real</strong> para guiarle.</span>
        </div>
    </div>

    <p class="pt-footer">
        La fecha, el lugar y los testigos se recopilarán en los pasos siguientes.
    </p>

</div>
