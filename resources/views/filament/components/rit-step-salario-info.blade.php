@include('filament.components.pinfo-styles')

<div class="pt-card" style="border-left-color:#c9a84c;">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold"
            colors="primary:#fbbf24,secondary:#fde68a" data-pt-icon
            data-pt-dark="primary:#fbbf24,secondary:#fde68a"
            data-pt-light="primary:#d97706,secondary:#fbbf24"
            style="width:32px;height:32px;flex-shrink:0">
        </lord-icon>
        <p class="pt-title">Salario, pagos y beneficios</p>
    </div>

    <p class="pt-body">
        El capítulo de salario protege a la empresa de reclamaciones por pagos no documentados.
        Un beneficio que da habitualmente — aunque no sea obligatorio — debe quedar en el RIT para que
        no se convierta en <strong class="t-gold">"salario"</strong> a efectos legales.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Si paga <strong>semanal a operativos</strong> y <strong>quincenal a administrativos</strong>,
                puede indicar ambas periodicidades.</span>
        </div>

        <div class="pt-bullet">
            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                style="width:20px;height:20px;flex-shrink:0">
            </lord-icon>
            <span>Los <strong>bonos, auxilios de alimentación o transporte</strong> que da de forma habitual
                deben registrarse aquí como beneficios extralegales.</span>
        </div>

    </div>

    <p class="pt-footer">
        Los permisos y licencias también hacen parte de este capítulo — hay campos al final del paso.
    </p>

</div>
