{{-- Estilos de los modales de impugnación (Ver / Resolver) - lenguaje visual de "Mi Reglamento Interno". --}}
@include('filament.components.lupe-hero-styles')
@include('filament.components.documento-viewer-styles')
@verbatim
<style>
.imv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.7rem}
.imv-stat{border-radius:.9rem;padding:.8rem 1rem;background:rgba(225,29,72,.05);border:1px solid rgba(225,29,72,.14)}
html.dark .imv-stat{background:rgba(225,29,72,.1);border-color:rgba(225,29,72,.22)}
.imv-k{margin:0;font-size:.66rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#be123c}
html.dark .imv-k{color:#fda4af}
.imv-v{margin:.15rem 0 0;font-size:.92rem;font-weight:600;color:#1c1917;line-height:1.35}
html.dark .imv-v{color:#f1f5f9}
.imv-sec{margin:1.25rem 0 .55rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#78716c}
html.dark .imv-sec{color:#94a3b8}
.imv-texto{font-family:'Georgia','Times New Roman',serif;font-size:.9rem;line-height:1.85;color:#292524;white-space:pre-wrap;word-break:break-word;margin:0}
html.dark .imv-texto{color:#cbd5e1}
.imv-file{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.6rem .85rem;border-radius:.75rem;background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.08);font-size:.85rem;color:#374151}
html.dark .imv-file{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1);color:#e2e8f0}
.imv-file span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.imv-row{display:flex;gap:.75rem;align-items:flex-start;padding:.75rem 0;border-bottom:1px solid rgba(0,0,0,.07)}
html.dark .imv-row{border-bottom-color:rgba(255,255,255,.08)}
.imv-row:last-child{border-bottom:0}
.imv-row p{margin:0;font-size:.85rem;line-height:1.55;color:#44403c}
html.dark .imv-row p{color:#cbd5e1}
.imv-row strong{color:#1c1917} html.dark .imv-row strong{color:#f1f5f9}
.imv-pill{flex-shrink:0;font-size:.64rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.22rem .6rem;border-radius:2rem;border:1px solid}
.imv-p-ok{background:rgba(22,163,74,.1);border-color:rgba(22,163,74,.28);color:#166534} html.dark .imv-p-ok{color:#86efac}
.imv-p-mid{background:rgba(217,119,6,.1);border-color:rgba(217,119,6,.3);color:#b45309} html.dark .imv-p-mid{color:#fbbf24}
.imv-p-no{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.28);color:#475569} html.dark .imv-p-no{color:#94a3b8}
.imv-warn{border-radius:.9rem;padding:.9rem 1.05rem;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);font-size:.85rem;line-height:1.6;color:#78350f}
html.dark .imv-warn{background:rgba(217,119,6,.12);color:#fde68a}
.imv-warn b{display:block;margin-bottom:.2rem;font-size:.66rem;letter-spacing:.07em;text-transform:uppercase}
.imv-lista{margin:.2rem 0 0;padding-left:1.2rem;font-size:.86rem;line-height:1.7;color:#44403c} html.dark .imv-lista{color:#cbd5e1}
.imv-nota{font-size:.78rem;color:#78716c;margin:.9rem 0 0;line-height:1.5} html.dark .imv-nota{color:#94a3b8}
@keyframes imv-giro{to{transform:rotate(360deg)}}
.imv-giro{width:1.15rem;height:1.15rem;border-radius:50%;border:2px solid rgba(225,29,72,.25);border-top-color:#e11d48;animation:imv-giro .8s linear infinite;flex-shrink:0}
</style>
@endverbatim
