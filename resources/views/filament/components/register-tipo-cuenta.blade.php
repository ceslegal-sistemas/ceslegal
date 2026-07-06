{{-- Selector "tipo de cuenta" en dos cards con Lordicon. Escribe en el campo
     oculto `data.tipo_cuenta` (que es ->live()), disparando la visibilidad de los
     pasos empresa/bufete del wizard. Requiere lordicon.js (cargado por el panel). --}}
<script src="https://cdn.lordicon.com/lordicon.js"></script>

@once
<style>
    .rtc-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.25rem}
    @media(max-width:640px){.rtc-grid{grid-template-columns:1fr}}
    .rtc-card{display:flex;flex-direction:column;align-items:center;text-align:center;gap:.4rem;
        padding:1.5rem 1.25rem 1.35rem;border-radius:1rem;cursor:pointer;
        border:1.5px solid rgba(0,0,0,.10);background:#fff;transition:border-color .18s,box-shadow .18s,transform .18s;}
    .rtc-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(37,99,235,.10)}
    html.dark .rtc-card{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.12)}
    .rtc-card.rtc-on{border-color:#2563eb;box-shadow:0 8px 26px rgba(37,99,235,.18)}
    html.dark .rtc-card.rtc-on{border-color:#60a5fa}
    .rtc-ico{width:118px;height:118px;pointer-events:none}
    .rtc-title{font-weight:700;font-size:1rem;color:#0f172a;margin-top:.15rem}
    html.dark .rtc-title{color:#e2e8f0}
    .rtc-sub{font-size:.8rem;line-height:1.45;color:#475569;max-width:26ch}
    html.dark .rtc-sub{color:#94a3b8}
    .rtc-check{margin-top:.35rem;font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
        color:#2563eb;opacity:0;transition:opacity .18s}
    .rtc-card.rtc-on .rtc-check{opacity:1}
    html.dark .rtc-check{color:#60a5fa}
</style>
@endonce

<div x-data="{ sel: $wire.entangle('data.tipo_cuenta') }" class="rtc-grid">
    <button type="button" class="rtc-card" :class="sel === 'empresa' && 'rtc-on'" x-on:click="sel = 'empresa'">
        <lord-icon class="rtc-ico" src="https://cdn.lordicon.com/ymrcxweu.json" trigger="loop" delay="500"
            colors="primary:#2563eb,secondary:#93c5fd"></lord-icon>
        <div class="rtc-title">Soy una empresa</div>
        <div class="rtc-sub">Gestione sus propios procesos disciplinarios laborales.</div>
        <div class="rtc-check">Seleccionado</div>
    </button>

    <button type="button" class="rtc-card" :class="sel === 'bufete' && 'rtc-on'" x-on:click="sel = 'bufete'">
        <lord-icon class="rtc-ico" src="https://cdn.lordicon.com/bduzytli.json" trigger="loop" delay="500"
            colors="primary:#2563eb,secondary:#93c5fd"></lord-icon>
        <div class="rtc-title">Soy un bufete de abogados</div>
        <div class="rtc-sub">Administre procesos disciplinarios de múltiples empresas clientes.</div>
        <div class="rtc-check">Seleccionado</div>
    </button>
</div>
