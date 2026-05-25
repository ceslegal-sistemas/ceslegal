@include('filament.components.pinfo-styles')

<style>
.norit-cta {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .5rem 1.125rem; border-radius: .5rem;
    background: #d97706; color: #fff;
    font-size: .8125rem; font-weight: 600;
    text-decoration: none; transition: background .15s;
    flex-shrink: 0; white-space: nowrap;
}
.norit-cta:hover { background: #b45309; }
html:not(.dark) .norit-cta { background: #b45309; }
html:not(.dark) .norit-cta:hover { background: #92400e; }
</style>

<div class="pt-card" style="border-left-color:#f59e0b; margin-bottom:1.5rem;">

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1.25rem; flex-wrap:wrap;">

        <div style="flex:1; min-width:0;">

            <div style="display:flex; align-items:center; gap:.625rem; margin-bottom:.625rem;">
                <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fbbf24,secondary:#fde68a" data-pt-icon
                    data-pt-dark="primary:#fbbf24,secondary:#fde68a"
                    data-pt-light="primary:#b45309,secondary:#d97706"
                    style="width:32px;height:32px;flex-shrink:0">
                </lord-icon>
                <p class="pt-title">Su empresa no tiene RIT activo</p>
            </div>

            <p class="pt-body">
                Sin Reglamento Interno de Trabajo, la empresa <strong>solo puede terminar contratos</strong>
                como medida disciplinaria (Art. 105 CST). No puede aplicar llamados de atención ni suspensiones.
            </p>

            <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

                <div class="pt-bullet">
                    <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:20px;height:20px;flex-shrink:0">
                    </lord-icon>
                    <span>Con RIT puede <strong>llamar la atención, suspender o terminar</strong> el contrato según la gravedad de la falta.</span>
                </div>

                <div class="pt-bullet">
                    <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
                        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:20px;height:20px;flex-shrink:0">
                    </lord-icon>
                    <span>Nuestro asistente de IA construye el RIT completo con <strong>16 capítulos</strong> en minutos, listo para presentar al Ministerio.</span>
                </div>

            </div>

            <p class="pt-footer" style="border-top-color:rgba(245,158,11,.2);">
                El RIT es obligatorio para empresas con más de 5 trabajadores (Art. 105 CST).
            </p>

        </div>

        <a href="{{ route('filament.admin.resources.reglamento-internos.create') }}" class="norit-cta">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            Construir RIT con IA
        </a>

    </div>

</div>
