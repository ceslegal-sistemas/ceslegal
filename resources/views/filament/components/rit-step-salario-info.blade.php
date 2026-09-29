{{--
    Rediseño (2026-09-28, pedido explícito del usuario: mismo estilo .rit-hero
    de "Socializa el RIT", sin la línea de acento izquierda de .pt-card - el
    acento "oro" que tenía el borde ahora vive solo en el color del icono
    principal y en .t-gold, igual que antes).
--}}
@include('filament.components.pinfo-styles')
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem;">
            <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fbbf24,secondary:#fde68a" data-pt-icon
                data-pt-dark="primary:#fbbf24,secondary:#fde68a"
                data-pt-light="primary:#d97706,secondary:#fbbf24"
                style="width:32px;height:32px;flex-shrink:0">
            </lord-icon>
            <h1 class="rit-title" style="margin:0;font-size:1.05rem;">Salario, pagos y beneficios</h1>
        </div>

        <p class="rit-sub" style="margin-bottom:.75rem;">
            El capítulo de salario protege a la empresa de reclamaciones por pagos no documentados.
            Un beneficio que da habitualmente - aunque no sea obligatorio - debe quedar en el RIT para que
            no se convierta en <strong class="t-gold">"salario"</strong> a efectos legales.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span>Si paga <strong>semanal a operativos</strong> y <strong>quincenal a administrativos</strong>,
                    puede indicar ambas periodicidades.</span>
            </div>

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span>Los <strong>bonos, auxilios de alimentación o transporte</strong> que da de forma habitual
                    deben registrarse aquí como beneficios extralegales.</span>
            </div>

        </div>

        <p class="pt-footer">
            Los permisos y licencias también hacen parte de este capítulo - hay campos al final del paso.
        </p>

    </div>
</div>
