{{-- Selector "¿tiene logo?" en cards, mismo patrón que rit-opcion-cards.blade.php.
     Escribe en el campo oculto data.logo_opcion (->live()). El logo nunca es
     obligatorio, por eso solo hay 2 opciones (a diferencia del RIT). --}}
<script src="https://cdn.lordicon.com/lordicon.js"></script>

@once
<style>
    [x-cloak]{display:none!important}
    .lop-q{font-weight:600;font-size:.95rem;color:#0f172a;margin:0 0 .6rem}
    html.dark .lop-q{color:#f1f5f9}
    .lop-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}
    .lop-card{display:flex;flex-direction:column;align-items:center;text-align:center;gap:.35rem;
        padding:1.25rem 1rem 1.1rem;border-radius:1rem;cursor:pointer;
        border:1.5px solid rgba(0,0,0,.10);background:#fff;transition:border-color .18s,box-shadow .18s,transform .18s}
    .lop-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10)}
    html.dark .lop-card{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.12)}
    .lop-card.lop-on{border-color:#e11d48;box-shadow:0 8px 26px rgba(225,29,72,.18)}
    html.dark .lop-card.lop-on{border-color:#fb7185}
    .lop-ico{width:84px;height:84px;pointer-events:none}
    .lop-title{font-weight:700;font-size:.92rem;color:#0f172a;margin-top:.1rem}
    html.dark .lop-title{color:#e2e8f0}
    .lop-sub{font-size:.76rem;line-height:1.4;color:#475569;max-width:28ch}
    html.dark .lop-sub{color:#94a3b8}
    .lop-check{margin-top:.25rem;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
        color:#be123c;opacity:0;transition:opacity .18s}
    .lop-card.lop-on .lop-check{opacity:1}
    html.dark .lop-check{color:#fb7185}
</style>
@endonce

<div>
    <p class="lop-q">¿Ya tiene el logo de su empresa a la mano?</p>

    <div x-data="{ sel: $wire.entangle('data.logo_opcion') }" class="lop-grid">
        <button type="button" class="lop-card" :class="sel === 'tiene' && 'lop-on'" x-on:click="sel = 'tiene'">
            <lord-icon class="lop-ico" src="https://cdn.lordicon.com/fikcyfpp.json" trigger="loop" delay="400"
                colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
            <div class="lop-title">Sí, ya lo tengo</div>
            <div class="lop-sub">Suba una imagen PNG, JPG o SVG.</div>
            <div class="lop-check">Seleccionado</div>
        </button>

        <button type="button" class="lop-card" :class="sel === 'despues' && 'lop-on'" x-on:click="sel = 'despues'">
            <lord-icon class="lop-ico" src="https://cdn.lordicon.com/bduzytli.json" trigger="loop" delay="400"
                colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
            <div class="lop-title">Lo agregaré después</div>
            <div class="lop-sub">Puede subirlo más adelante desde Mi Empresa.</div>
            <div class="lop-check">Seleccionado</div>
        </button>
    </div>
</div>
