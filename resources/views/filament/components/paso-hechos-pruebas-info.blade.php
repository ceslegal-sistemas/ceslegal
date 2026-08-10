@include('filament.components.pinfo-styles')

@php
    $razon_social = auth()->user()?->empresa?->razon_social ?? 'su organización';
@endphp

<div class="pt-card">

    {{-- Sub-bloque 1: Descripción del hecho --}}
    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/hmpomorl.json"
            trigger="loop" delay="500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
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
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Sea <strong>específico</strong>: qué hizo el trabajador, cómo se enteró la empresa.</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/vgwutnhw.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>El panel lateral analizará su texto <strong>en tiempo real</strong> para guiarle.</span>
        </div>
    </div>

    <p class="pt-footer">
        Las evidencias y los testigos se registran más abajo, en este mismo paso.
    </p>

    {{-- Divisor entre los 2 sub-bloques, dentro de la misma tarjeta --}}
    <div style="height:1px;background:rgba(251,113,133,.18);margin:1rem 0 .875rem;"></div>

    {{-- Sub-bloque 2: Pruebas del hecho --}}
    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/hmpomorl.json"
            trigger="loop" delay="500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Pruebas del hecho</p>
    </div>

    <p class="pt-body">
        Las pruebas -archivos adjuntos y testigos- fortalecen el proceso y son determinantes
        si el trabajador impugna la decisión.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/hmpomorl.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Adjunte archivos <strong>PDF, imágenes o Word</strong> (máx. 5 archivos, 5 MB c/u).</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/fqbvgezn.json"
                trigger="loop" delay="500" stroke="bold" state="hover-roll"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Si hubo testigos, registre su <strong>nombre completo y cargo</strong>. Solo personas que presenciaron directamente los hechos.</span>
        </div>
    </div>

    <p class="pt-footer">
        Los archivos se almacenan de forma segura y solo son accesibles por el equipo de <strong class="t-gold">{{ $razon_social }}</strong>
    </p>

</div>
