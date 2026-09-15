@include('filament.components.lupe-hero-styles')

<style>
.norit-footer-divider { border-top: 1px solid rgba(255,255,255,.08); }
html:not(.dark) .norit-footer-divider { border-top-color: rgba(0,0,0,.08); }
</style>

<div class="rit-hero" style="margin-bottom:1.5rem">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <span class="rit-badge rit-badge-danger">
            <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#fca5a5,secondary:#fca5a5" data-pt-icon
                data-pt-dark="primary:#fca5a5,secondary:#fca5a5"
                data-pt-light="primary:#b91c1c,secondary:#b91c1c"
                style="width:16px;height:16px;flex-shrink:0">
            </lord-icon>
            Sin RIT activo
        </span>

        <h1 class="rit-title">Su empresa no tiene RIT activo</h1>
        <p class="rit-sub">
            Sin Reglamento Interno de Trabajo, la empresa <strong>solo puede terminar contratos</strong>
            como medida disciplinaria (Art. 105 CST). No puede aplicar llamados de atención ni suspensiones.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin:1rem 0;">
            <div style="display:flex;align-items:flex-start;gap:.625rem;">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                </lord-icon>
                <span class="rit-sub" style="margin:0">Con RIT puede <strong>llamar la atención, suspender o terminar</strong> el contrato según la gravedad de la falta.</span>
            </div>
            <div style="display:flex;align-items:flex-start;gap:.625rem;">
                <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                </lord-icon>
                <span class="rit-sub" style="margin:0">Nuestro asistente de IA construye el RIT completo con <strong>16 capítulos</strong> en minutos, listo para presentar al Ministerio.</span>
            </div>
        </div>

        <div class="rit-actions">
            <a href="{{ \App\Filament\Admin\Resources\ReglamentoInternoResource::getUrl('create') }}" class="rit-btn rit-btn-primary">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    style="width:16px;height:16px;flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                Construir RIT con IA
            </a>
        </div>

        <p class="rit-sub norit-footer-divider" style="margin-top:1rem;padding-top:.75rem;font-size:.75rem;">
            El RIT es obligatorio para empresas con más de 5 trabajadores (Art. 105 CST).
        </p>

    </div>
</div>
