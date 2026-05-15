{{--
    Tarjeta de error — análisis IA no disponible
    Se muestra cuando Gemini devuelve error después de los reintentos.
--}}
<style>
:root {
    --iae-label: rgba(0,0,0,0.45);
    --iae-text:  rgba(17,24,39,0.78);
    --iae-bold:  rgba(17,24,39,0.90);
    --iae-bg:    rgba(234,179,8,0.07);
    --iae-border: rgba(234,179,8,0.25);
    --iae-shimmer: rgba(0,0,0,0.04);
}
html.dark {
    --iae-label: rgba(255,255,255,0.30);
    --iae-text:  rgba(255,255,255,0.68);
    --iae-bold:  rgba(255,255,255,0.88);
    --iae-bg:    rgba(234,179,8,0.08);
    --iae-border: rgba(234,179,8,0.25);
    --iae-shimmer: rgba(255,255,255,0.10);
}
.iae-card {
    border-radius: 14px;
    border: 1px solid var(--iae-border);
    border-left: 3px solid #eab308;
    background: linear-gradient(135deg, var(--iae-bg) 0%, transparent 100%);
    overflow: hidden;
    position: relative;
}
.iae-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--iae-shimmer), transparent);
}
.iae-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--iae-label);
    margin: 0 0 4px;
}
</style>

<div class="iae-card">
    <div style="padding: 16px 18px;">
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <lord-icon
                src="https://cdn.lordicon.com/edcgvlnw.json"
                trigger="loop" delay="1200" stroke="bold"
                colors="primary:#eab308,secondary:#fde047"
                style="width:38px;height:38px;flex-shrink:0;margin-top:-2px">
            </lord-icon>
            <div style="flex:1;min-width:0;">
                <p class="iae-label">Análisis IA</p>
                <p style="font-size:15px;font-weight:800;color:#ca8a04;line-height:1.2;margin:0 0 8px;">
                    Servicio temporalmente no disponible
                </p>
                <p style="font-size:13px;color:var(--iae-text);line-height:1.6;margin:0;">
                    El sistema de análisis jurídico IA está experimentando alta demanda en este momento.
                    Puede continuar seleccionando la sanción manualmente, o
                    <strong style="color:var(--iae-bold);">cierre este modal e intente nuevamente en unos segundos</strong>
                    para que la IA pueda analizar el caso.
                </p>
            </div>
        </div>
    </div>
</div>
