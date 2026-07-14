{{-- Selector "¿tiene RIT?" en cards con Lordicon (loop). Escribe en el campo oculto
     data.rit_opcion (->live()). La card de CST solo se muestra si la empresa NO está
     obligada. Requiere lordicon.js (cargado por el panel). --}}
<script src="https://cdn.lordicon.com/lordicon.js"></script>

@once
<style>
    [x-cloak]{display:none!important}
    .rop-q{font-weight:600;font-size:.95rem;color:#0f172a;margin:0 0 .6rem}
    html.dark .rop-q{color:#f1f5f9}
    .rop-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}
    .rop-card{display:flex;flex-direction:column;align-items:center;text-align:center;gap:.35rem;
        padding:1.25rem 1rem 1.1rem;border-radius:1rem;cursor:pointer;
        border:1.5px solid rgba(0,0,0,.10);background:#fff;transition:border-color .18s,box-shadow .18s,transform .18s}
    .rop-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10)}
    html.dark .rop-card{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.12)}
    .rop-card.rop-on{border-color:#e11d48;box-shadow:0 8px 26px rgba(225,29,72,.18)}
    html.dark .rop-card.rop-on{border-color:#fb7185}
    .rop-ico{width:84px;height:84px;pointer-events:none}
    .rop-title{font-weight:700;font-size:.92rem;color:#0f172a;margin-top:.1rem}
    html.dark .rop-title{color:#e2e8f0}
    .rop-sub{font-size:.76rem;line-height:1.4;color:#475569;max-width:28ch}
    html.dark .rop-sub{color:#94a3b8}
    .rop-check{margin-top:.25rem;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
        color:#be123c;opacity:0;transition:opacity .18s}
    .rop-card.rop-on .rop-check{opacity:1}
    html.dark .rop-check{color:#fb7185}
</style>
@endonce

<div>
    <p class="rop-q">¿Su empresa tiene Reglamento Interno de Trabajo (RIT)?</p>

    <div x-data="{ sel: $wire.entangle('data.rit_opcion') }" class="rop-grid">
        <button type="button" class="rop-card" :class="sel === 'tiene' && 'rop-on'" x-on:click="sel = 'tiene'">
            <lord-icon class="rop-ico" src="https://cdn.lordicon.com/fikcyfpp.json" trigger="loop" delay="400"
                colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
            <div class="rop-title">Sí, ya lo tengo</div>
            <div class="rop-sub">Suba el .docx o .pdf. Se auditará automáticamente.</div>
            <div class="rop-check">Seleccionado</div>
        </button>

        <button type="button" class="rop-card" :class="sel === 'construir' && 'rop-on'" x-on:click="sel = 'construir'">
            <lord-icon class="rop-ico" src="https://cdn.lordicon.com/exymduqj.json" trigger="loop" delay="400"
                colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
            <div class="rop-title">No, construirlo con IA</div>
            <div class="rop-sub">Cuestionario guiado; la IA redactará el RIT completo.</div>
            <div class="rop-check">Seleccionado</div>
        </button>

        @if (! ($esObligada ?? false))
            <button type="button" class="rop-card" :class="sel === 'despues' && 'rop-on'" x-on:click="sel = 'despues'">
                <lord-icon class="rop-ico" src="https://cdn.lordicon.com/bduzytli.json" trigger="loop" delay="400"
                    colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
                <div class="rop-title">No, me rijo por el CST</div>
                <div class="rop-sub">No está obligada; puede subir o construir el RIT más adelante.</div>
                <div class="rop-check">Seleccionado</div>
            </button>
        @endif
    </div>
</div>
