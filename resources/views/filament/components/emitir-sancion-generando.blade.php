{{--
    Pantalla de espera ÚNICA mientras se genera la recomendación de sanción y
    corre la revisión V6 completa (análisis + 8 motores + posible corrección) en
    segundo plano (GenerarRecomendacionYRevisarV6Job). El modal muestra SOLO esto
    - no el formulario real - hasta que termine, para que el cliente espere una
    sola vez y el formulario aparezca ya con la recomendación final lista, sin
    cambios visibles después. Se actualiza sola (wire:poll) hasta que el job
    termine; normalmente toma ~1-2 min.
--}}
<style>
:root{--esg-bg:rgba(0,0,0,.02);--esg-border:rgba(0,0,0,.08);--esg-text:rgba(17,24,39,.85);--esg-muted:rgba(17,24,39,.55);}
html.dark{--esg-bg:rgba(255,255,255,.03);--esg-border:rgba(255,255,255,.08);--esg-text:rgba(255,255,255,.9);--esg-muted:rgba(255,255,255,.55);}
@keyframes esg-spin{to{transform:rotate(360deg)}}
.esg-spinner{width:38px;height:38px;border-radius:50%;border:3px solid rgba(220,38,38,.15);border-top-color:#dc2626;animation:esg-spin .9s linear infinite;}
html.dark .esg-spinner{border-color:rgba(251,113,133,.2);border-top-color:#fb7185;}
</style>

<div wire:poll.3000ms style="padding:2.5rem 1.5rem;text-align:center;background:var(--esg-bg);border:1px solid var(--esg-border);border-radius:1rem;">
    <div class="esg-spinner" style="margin:0 auto 1.25rem;"></div>
    <p style="font-size:.95rem;font-weight:700;color:var(--esg-text);margin:0 0 .4rem;">Generando la recomendación de sanción</p>
    <p style="font-size:.8125rem;color:var(--esg-muted);margin:0 0 .25rem;line-height:1.6;max-width:32rem;margin-left:auto;margin-right:auto;">
        La IA está analizando el caso y revisando la recomendación en detalle (pruebas, coherencia, riesgo judicial).
        Suele tardar 1-2 minutos.
    </p>
    <p style="font-size:.75rem;color:var(--esg-muted);opacity:.85;margin:0;">
        Esta ventana se actualiza sola - no necesita hacer nada más.
    </p>
</div>
