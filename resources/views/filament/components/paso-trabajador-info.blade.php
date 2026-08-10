@include('filament.components.pinfo-styles')

<div>

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/bushiqea.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Datos del empleado</p>
    </div>

    <p class="pt-body">
        @if ($esCliente)
            Seleccione al trabajador de su empresa que será citado al proceso.
        @else
            Seleccione primero la empresa y luego el trabajador involucrado.
        @endif
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

    </div>

    <p class="pt-footer">
        Al finalizar este proceso, el sistema enviará la citación al correo del trabajador automáticamente.
    </p>

</div>
