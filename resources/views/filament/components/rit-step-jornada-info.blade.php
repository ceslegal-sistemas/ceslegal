@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            style="width:28px;height:28px;color:#6366f1;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="pt-title">¿Cuándo trabajan sus empleados?</p>
    </div>

    <p class="pt-body">
        El capítulo de jornada laboral es uno de los más revisados por el Ministerio de Trabajo.
        Ser preciso aquí protege a la empresa de reclamaciones por horas extras no pagadas.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Si tiene personal de oficina <strong>y</strong> personal operativo con turnos, puede describir
                ambos horarios en los campos de abajo.</span>
        </div>

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Los <strong>gerentes y jefes de confianza</strong> están exentos del límite de 8 horas diarias
                — hay un campo específico para ellos.</span>
        </div>

    </div>

    <p class="pt-footer">
        No necesita citar artículos del CST. El sistema los incluye automáticamente en el texto generado.
    </p>

</div>
