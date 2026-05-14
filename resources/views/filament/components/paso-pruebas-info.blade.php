@include('filament.components.pinfo-styles')

@php
    $razon_social = auth()->user()?->empresa?->razon_social ?? 'su organización';
@endphp

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
        <p class="pt-title">Pruebas del hecho</p>
    </div>

    <p class="pt-body">
        Las pruebas —archivos adjuntos y testigos— fortalecen el proceso y son determinantes
        si el trabajador impugna la decisión.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/hmpomorl.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Adjunte archivos <strong>PDF, imágenes o Word</strong> (máx. 5 archivos, 5 MB c/u).</span>
        </div>
        <div class="pt-bullet">
            <lord-icon
                src="https://cdn.lordicon.com/fqbvgezn.json"
                trigger="loop" delay="500" stroke="bold" state="hover-roll"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
            </lord-icon>
            <span>Si hubo testigos, registre su <strong>nombre completo y cargo</strong>. Solo personas que presenciaron directamente los hechos.</span>
        </div>
    </div>

    <p class="pt-footer">
        Los archivos se almacenan de forma segura y solo son accesibles por el equipo de <strong class="t-gold">{{ $razon_social }}</strong>
    </p>

</div>
