{{--
    Estilos compartidos del "hero" de marca LUPE Legal (degradado + badges +
    botones), extraídos de mi-reglamento-interno.blade.php para reutilizar en
    otros Resources (ver SolicitudContratoResource) sin duplicar el bloque
    completo en cada vista. Incluir una sola vez por página con @include.
--}}
<style>
.rit-hero{position:relative;overflow:hidden;border-radius:1.25rem;padding:2rem 1.75rem;background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%)}
html:not(.dark) .rit-hero{background:#fff;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 28px rgba(0,0,0,.08)}
.rit-orb-b{position:absolute;width:280px;height:280px;top:-80px;right:-60px;border-radius:50%;background:radial-gradient(circle,rgba(225,29,72,.45),transparent 70%);filter:blur(28px);pointer-events:none;animation:rit-fb 14s ease-in-out infinite}
.rit-orb-g{position:absolute;width:200px;height:200px;bottom:-60px;left:-40px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.2),transparent 70%);filter:blur(24px);pointer-events:none;animation:rit-fg 18s ease-in-out infinite}
@keyframes rit-fb{0%,100%{transform:translate(0,0)}40%{transform:translate(-18px,14px)}70%{transform:translate(12px,-10px)}}
@keyframes rit-fg{0%,100%{transform:translate(0,0)}35%{transform:translate(14px,-16px)}65%{transform:translate(-10px,8px)}}
html:not(.dark) .rit-orb-b{background:radial-gradient(circle,rgba(251,113,133,.15),transparent 70%)!important}
html:not(.dark) .rit-orb-g{background:radial-gradient(circle,rgba(201,168,76,.18),transparent 70%)!important}
.rit-overlay{position:absolute;inset:0;pointer-events:none;z-index:1;background:radial-gradient(ellipse 80% 90% at 50% 50%,rgba(3,8,20,.75) 0%,rgba(3,8,20,.4) 55%,transparent 100%)}
html:not(.dark) .rit-overlay{background:radial-gradient(ellipse 75% 85% at 50% 40%,rgba(255,255,255,.75) 0%,rgba(255,255,255,.35) 55%,transparent 100%)}
.rit-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.35rem .9rem;border-radius:2rem;border:1px solid}
.rit-badge-ia{background:rgba(251,113,133,.13);border-color:rgba(251,113,133,.3);color:#fb7185}
html:not(.dark) .rit-badge-ia{background:rgba(225,29,72,.08);border-color:rgba(225,29,72,.2);color:#be123c}
.rit-badge-sub{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-badge-sub{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-badge-none{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.25);color:#94a3b8}
html:not(.dark) .rit-badge-none{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.2);color:#475569}
.rit-badge-danger{background:rgba(239,68,68,.13);border-color:rgba(239,68,68,.3);color:#fca5a5}
html:not(.dark) .rit-badge-danger{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.2);color:#b91c1c}
.rit-title{font-size:1.25rem;font-weight:700;color:#f1f5f9;margin:.5rem 0 .25rem;letter-spacing:-.015em}
html:not(.dark) .rit-title{color:#1c1917}
.rit-sub{font-size:.8125rem;color:#94a3b8;margin:0}
html:not(.dark) .rit-sub{color:#475569}
.rit-actions{display:flex;flex-wrap:wrap;gap:.625rem;margin-top:1.25rem;position:relative;z-index:2}
.rit-btn{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.55rem 1.125rem;border-radius:.625rem;border:1px solid;cursor:pointer;text-decoration:none;transition:opacity .15s}
.rit-btn:hover{opacity:.85}
.rit-btn:disabled{opacity:.55;cursor:default}
.rit-btn-primary{background:rgba(251,113,133,.18);border-color:rgba(251,113,133,.35);color:#fecdd3}
html:not(.dark) .rit-btn-primary{background:rgba(225,29,72,.1);border-color:rgba(225,29,72,.25);color:#be123c}
.rit-btn-secondary{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);color:#e2e8f0}
html:not(.dark) .rit-btn-secondary{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:#374151}
.rit-btn-success{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-btn-success{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-btn-danger{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:#fca5a5}
html:not(.dark) .rit-btn-danger{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:#b91c1c}
@keyframes rit-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
</style>
