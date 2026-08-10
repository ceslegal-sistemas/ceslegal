@include('filament.components.pinfo-styles')

@php
    $razon_social = auth()->user()?->empresa?->razon_social ?? 'su organización';
@endphp

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/bushiqea.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Datos del empleado y fecha, hora y lugar</p>
    </div>

    <p class="pt-body">
        @if ($esCliente)
            Seleccione al trabajador de su empresa que será citado al proceso,
        @else
            Seleccione primero la empresa y luego el trabajador involucrado,
        @endif
        y confirme la fecha, hora aproximada y lugar del hecho - estos datos son
        fundamentales para el expediente jurídico y la correcta calificación de la conducta.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/okqjaags.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>El empleado completará el formulario virtual durante la audiencia.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Sus respuestas quedarán <strong>registradas automáticamente</strong> en el sistema.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/warimioc.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Si el hecho ocurrió en <strong>varios días</strong>, indique la fecha más reciente.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/onmwuuox.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>El lugar determina si ocurrió <strong>dentro del centro de trabajo</strong>.</span>
        </div>

    </div>

    <p class="pt-footer">
        Al finalizar este proceso, el sistema enviará la citación al correo del trabajador automáticamente.
        El horario laboral es relevante para determinar la competencia disciplinaria de la empresa <strong class="t-gold">{{ $razon_social }}</strong>.
    </p>

</div>
