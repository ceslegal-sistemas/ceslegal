{{--
    Estilos compartidos del "hero" de marca LUPE Legal (degradado + badges +
    botones), extraídos de mi-reglamento-interno.blade.php para reutilizar en
    otros Resources (ver SolicitudContratoResource) sin duplicar el bloque
    completo en cada vista. Incluir una sola vez por página con @include.
--}}
<style>
.rit-hero{position:relative;overflow:hidden;border-radius:1.25rem;padding:2rem 1.75rem;background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%)}
{{--
    Bug real reportado por el usuario (2026-09-08): en modo claro el
    "hero" se veía como una tarjeta blanca plana, sin ningún indicio de
    rediseño - dos causas combinadas: (1) el fondo era #fff puro sin
    ningún tinte de marca, y (2) .rit-overlay (pensado para oscurecer el
    degradado oscuro detrás del texto) en modo claro hacía un lavado
    blanco casi opaco (75% en el centro) que tapaba cualquier color de
    los orbes/fondo, sin importar qué tan vívidos fueran. Se sube la
    opacidad de los orbes, se le da un tinte cálido de marca al fondo, y
    se baja mucho el lavado blanco del overlay para que el tinte y los
    orbes sí se noten - manteniendo el look "limpio" apropiado para modo
    claro (no se copia el degradado oscuro completo).
--}}
html:not(.dark) .rit-hero{background:linear-gradient(150deg,#fff1f2 0%,#fff7ed 55%,#ffffff 100%);border:1px solid rgba(225,29,72,.16);box-shadow:0 6px 26px rgba(225,29,72,.1)}
.rit-orb-b{position:absolute;width:280px;height:280px;top:-80px;right:-60px;border-radius:50%;background:radial-gradient(circle,rgba(225,29,72,.45),transparent 70%);filter:blur(28px);pointer-events:none;animation:rit-fb 14s ease-in-out infinite}
.rit-orb-g{position:absolute;width:200px;height:200px;bottom:-60px;left:-40px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.2),transparent 70%);filter:blur(24px);pointer-events:none;animation:rit-fg 18s ease-in-out infinite}
@keyframes rit-fb{0%,100%{transform:translate(0,0)}40%{transform:translate(-18px,14px)}70%{transform:translate(12px,-10px)}}
@keyframes rit-fg{0%,100%{transform:translate(0,0)}35%{transform:translate(14px,-16px)}65%{transform:translate(-10px,8px)}}
html:not(.dark) .rit-orb-b{background:radial-gradient(circle,rgba(225,29,72,.35),transparent 70%)!important}
html:not(.dark) .rit-orb-g{background:radial-gradient(circle,rgba(201,168,76,.35),transparent 70%)!important}
.rit-overlay{position:absolute;inset:0;pointer-events:none;z-index:1;background:radial-gradient(ellipse 80% 90% at 50% 50%,rgba(3,8,20,.75) 0%,rgba(3,8,20,.4) 55%,transparent 100%)}
html:not(.dark) .rit-overlay{background:radial-gradient(ellipse 75% 85% at 50% 40%,rgba(255,255,255,.3) 0%,rgba(255,255,255,.12) 55%,transparent 100%)}
.rit-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.35rem .9rem;border-radius:2rem;border:1px solid}
.rit-badge-ia{background:rgba(251,113,133,.13);border-color:rgba(251,113,133,.3);color:#fb7185}
html:not(.dark) .rit-badge-ia{background:rgba(225,29,72,.08);border-color:rgba(225,29,72,.2);color:#be123c}
.rit-badge-sub{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-badge-sub{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-badge-none{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.25);color:#94a3b8}
html:not(.dark) .rit-badge-none{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.2);color:#475569}
.rit-badge-danger{background:rgba(239,68,68,.13);border-color:rgba(239,68,68,.3);color:#fca5a5}
html:not(.dark) .rit-badge-danger{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.2);color:#b91c1c}
.rit-badge-warning{background:rgba(217,119,6,.13);border-color:rgba(251,191,36,.32);color:#fbbf24}
html:not(.dark) .rit-badge-warning{background:rgba(180,83,9,.08);border-color:rgba(217,119,6,.22);color:#b45309}
.rit-badge-info{background:rgba(37,99,235,.14);border-color:rgba(96,165,250,.32);color:#93c5fd}
html:not(.dark) .rit-badge-info{background:rgba(29,78,216,.08);border-color:rgba(37,99,235,.22);color:#1d4ed8}
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
{{--
    Variante "CTA sólida" (2026-09-15): las variantes rit-btn-* de arriba son
    translúcidas, pensadas para acciones secundarias del hero. Cuando el hero
    tiene UNA sola acción crítica (ej. "Construir RIT con IA" cuando no hay
    RIT activo), esa acción necesita más peso visual que un botón traslúcido
    más - degradado sólido de marca, texto blanco, sombra, mismo lenguaje que
    .pg-btn-primary usado en la guía "Tu proceso".
--}}
.rit-btn-cta{background:linear-gradient(135deg,#e11d48,#f97316);border-color:transparent;color:#fff!important;font-weight:700;padding:.75rem 1.5rem;font-size:.875rem;box-shadow:0 8px 22px rgba(225,29,72,.35);transition:filter .15s,transform .15s}
.rit-btn-cta:hover{opacity:1;filter:brightness(1.08);transform:translateY(-1px)}
html:not(.dark) .rit-btn-cta{box-shadow:0 8px 22px rgba(225,29,72,.22)}
@media(prefers-reduced-motion:reduce){.rit-btn-cta:hover{transform:none}}
@keyframes rit-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.rit-link-input{flex:1;min-width:200px;padding:.5rem .75rem;border-radius:.5rem;border:1px solid rgba(255,255,255,.15);font-size:.8125rem;background:rgba(255,255,255,.06);color:#e2e8f0}
html:not(.dark) .rit-link-input{border-color:rgba(0,0,0,.12);background:#fff;color:#1c1917}
{{--
    Grid responsivo de 2 columnas (originalmente solo en
    mi-reglamento-interno.blade.php - movido aquí para reutilizarlo en
    cualquier vista que necesite emparejar 2 tarjetas .rit-hero/.esa-card
    en una misma fila sin duplicar la regla, ej. emitir-sancion-pasos.blade.php).
--}}
.rit-two-col{display:grid;grid-template-columns:1fr;gap:1.25rem}
@media(min-width:768px){.rit-two-col{grid-template-columns:1fr 1fr}}
{{--
    Avisos/notices sin "franja vertical" (2026-10-22): varios componentes
    del panel (ObligacionRit, auditoría de RIT, validaciones de Emitir
    Sanción, errores de IA, etc.) reinventaban cada uno su propia tarjeta
    de aviso con un borde de color sólido a la izquierda (`border-left:
    3-4px solid`) - pedido explícito del usuario: quitar ese patrón en
    todo el proyecto y usar el estilo LUPE. En vez de un borde lateral, el
    tinte de color cubre TODA la tarjeta (fondo + borde completo, sin
    lado "cortado") - mismo lenguaje visual que ya usan los badges
    .rit-badge-* de arriba, solo que en formato tarjeta con ícono y texto.
    Los colores de cada variante son EXACTAMENTE los mismos que sus
    .rit-badge-* correspondientes (nunca se inventan tonos nuevos).
--}}
.rit-notice{display:flex;gap:.7rem;align-items:flex-start;padding:.85rem 1.05rem;border-radius:.85rem;border:1px solid}
.rit-notice-ico{width:28px;height:28px;flex-shrink:0;margin-top:.05rem}
.rit-notice h4{margin:0 0 .15rem;font-weight:700;font-size:.8125rem;line-height:1.3}
.rit-notice p{margin:0;font-size:.78rem;line-height:1.55;color:#57534e}
html.dark .rit-notice p{color:#d6d3d1}
.rit-notice-success{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22)}
.rit-notice-success h4{color:#166534}
html.dark .rit-notice-success{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28)}
html.dark .rit-notice-success h4{color:#86efac}
.rit-notice-warning{background:rgba(180,83,9,.08);border-color:rgba(217,119,6,.22)}
.rit-notice-warning h4{color:#b45309}
html.dark .rit-notice-warning{background:rgba(217,119,6,.13);border-color:rgba(251,191,36,.32)}
html.dark .rit-notice-warning h4{color:#fbbf24}
.rit-notice-danger{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.2)}
.rit-notice-danger h4{color:#b91c1c}
html.dark .rit-notice-danger{background:rgba(239,68,68,.13);border-color:rgba(239,68,68,.3)}
html.dark .rit-notice-danger h4{color:#fca5a5}
.rit-notice-info{background:rgba(29,78,216,.08);border-color:rgba(37,99,235,.22)}
.rit-notice-info h4{color:#1d4ed8}
html.dark .rit-notice-info{background:rgba(37,99,235,.14);border-color:rgba(96,165,250,.32)}
html.dark .rit-notice-info h4{color:#93c5fd}
.rit-notice-neutral{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.2)}
.rit-notice-neutral h4{color:#475569}
html.dark .rit-notice-neutral{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.25)}
html.dark .rit-notice-neutral h4{color:#94a3b8}
{{--
    Selector de canal de notificacion (correo/WhatsApp) - pedido del usuario
    (2026-10-27, corregido el mismo dia tras feedback: "ese rediseño no es
    nada a lo de lupe legal" - la primera versión usaba azul/verde genérico,
    inventado, en vez de la paleta real). Replica EXACTAMENTE el patrón ya
    usado por .rop-card/.lop-card (rit-opcion-cards.blade.php,
    logo-opcion-cards.blade.php): tarjeta blanca/neutra, borde y sombra rosa
    de marca (#e11d48) solo en el estado seleccionado, "Seleccionado" en
    mayúsculas que aparece solo al marcar - mismo rojo/rosa en TODAS las
    opciones, no un color por canal. :has() en vez de Alpine x-data porque
    este selector vive dentro de un CheckboxList nativo de Filament (con
    wire:model real, validación y multi-valor) - esos otros dos componentes
    usan $wire.entangle() directo porque son botones a mano en un wizard
    propio, algo que un Table Action de Filament no expone de forma estable.
--}}
.canal-selector .fi-fo-checkbox-list{gap:.65rem!important}
.canal-selector .fi-fo-checkbox-list-option-label{cursor:pointer;display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;border-radius:1rem;border:1.5px solid rgba(0,0,0,.10);background:#fff;transition:border-color .18s,box-shadow .18s,transform .18s}
html.dark .canal-selector .fi-fo-checkbox-list-option-label{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.12)}
.canal-selector .fi-fo-checkbox-list-option-label:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10)}
.canal-selector .fi-fo-checkbox-list-option-label:has(input:checked){border-color:#e11d48;box-shadow:0 8px 26px rgba(225,29,72,.18)}
html.dark .canal-selector .fi-fo-checkbox-list-option-label:has(input:checked){border-color:#fb7185}
.canal-selector .fi-fo-checkbox-list-option-label:has(input:disabled){cursor:not-allowed;opacity:.55}
.canal-selector .fi-fo-checkbox-list-option-label:has(input:disabled):hover{transform:none;box-shadow:none}
.canal-selector .fi-fo-checkbox-list-option-label input[type=checkbox]{width:1.15rem;height:1.15rem;flex-shrink:0;margin-top:0!important;accent-color:#e11d48}
.canal-opcion{display:flex;align-items:center;gap:.65rem;min-width:0;flex:1}
.canal-opcion-ico{width:34px;height:34px;flex-shrink:0;border-radius:.6rem;display:flex;align-items:center;justify-content:center;background:rgba(225,29,72,.08);color:#e11d48}
html.dark .canal-opcion-ico{background:rgba(251,113,133,.14);color:#fb7185}
.canal-opcion-ico svg{width:18px;height:18px}
.canal-opcion-texto{min-width:0}
.canal-opcion-titulo{display:block;font-weight:700;font-size:.8125rem;color:#0f172a}
html.dark .canal-opcion-titulo{color:#e2e8f0}
.canal-opcion-detalle{display:block;font-size:.76rem;line-height:1.4;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
html.dark .canal-opcion-detalle{color:#94a3b8}
.canal-opcion-check{flex-shrink:0;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#be123c;opacity:0;transition:opacity .18s}
html.dark .canal-opcion-check{color:#fb7185}
.canal-selector .fi-fo-checkbox-list-option-label:has(input:checked) .canal-opcion-check{opacity:1}
.canal-opcion-pendiente{display:inline-block;margin-left:.4rem;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#b45309}
html.dark .canal-opcion-pendiente{color:#fbbf24}
</style>
