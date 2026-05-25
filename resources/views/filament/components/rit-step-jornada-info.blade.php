@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/uphbloed.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">¿Cuándo trabajan sus empleados?</p>
    </div>

    <p class="pt-body">
        El capítulo de jornada laboral es uno de los más revisados por el Ministerio de Trabajo.
        Ser preciso aquí protege a la empresa de reclamaciones por horas extras no pagadas.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Si tiene personal de oficina <strong>y</strong> personal operativo con turnos, puede describir
                ambos horarios en los campos de abajo.</span>
        </div>

    </div>

    <p class="pt-footer">
        No necesita citar artículos del CST. El sistema los incluye automáticamente en el texto generado.
    </p>

</div>
