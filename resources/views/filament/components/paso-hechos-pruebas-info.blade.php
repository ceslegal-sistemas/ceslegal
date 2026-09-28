{{--
    Rediseño (2026-09-28, pedido explícito del usuario: "necesito que sea
    como el estilo de los hero... sin esa línea al principio odio esa
    línea") - se quita el .pt-card (borde izquierdo de acento) y se envuelve
    en el MISMO .rit-hero ya usado en "Socializa el RIT"/"Logros de
    cumplimiento" (orbes + degradado, sin inventar ningún badge ni texto
    nuevo). Contenido, iconos y textos exactamente los mismos de antes.
--}}
@include('filament.components.pinfo-styles')
@include('filament.components.lupe-hero-styles')

@php
    $razon_social = auth()->user()?->empresa?->razon_social ?? 'su organización';
@endphp

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem;">
            <lord-icon
                src="https://cdn.lordicon.com/hmpomorl.json"
                trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                style="width:32px;height:32px;flex-shrink:0">
            </lord-icon>
            <h1 class="rit-title" style="margin:0;font-size:1.05rem;">Descripción del hecho y pruebas</h1>
        </div>

        <p class="rit-sub" style="margin-bottom:.75rem;">
            Cuente con sus propias palabras qué ocurrió - no se preocupe por el lenguaje jurídico,
            la IA lo transformará después. Las pruebas -archivos adjuntos y testigos- fortalecen
            el proceso y son determinantes si el trabajador impugna la decisión.
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
            Las evidencias y los testigos se registran más abajo, en este mismo paso.
            Los archivos se almacenan de forma segura y solo son accesibles por el equipo de <strong class="t-gold">{{ $razon_social }}</strong>.
        </p>

    </div>
</div>
