@include('filament.components.pinfo-styles')

@php
    $razon_social = auth()->user()?->empresa?->razon_social ?? 'su organización';
@endphp

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/uphbloed.json"
            trigger="loop" delay="500" stroke="bold"
            colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-icon
            data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
            data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Fecha, hora y lugar</p>
    </div>

    <p class="pt-body">
        Estos datos son fundamentales para el expediente jurídico y para la correcta
        calificación de la conducta.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/warimioc.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Si el hecho ocurrió en <strong>varios días</strong>, indique la fecha más reciente aquí.</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/onmwuuox.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>El lugar determina si ocurrió <strong>dentro del centro de trabajo</strong>.</span>
        </div>
    </div>

    <p class="pt-footer">
        El horario laboral es relevante para determinar la competencia disciplinaria de la empresa <strong class="t-gold">{{ $razon_social }}</strong>.
    </p>

</div>
