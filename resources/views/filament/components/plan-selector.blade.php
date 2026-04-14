@php
    $planesData = [
        'basico' => [
            'mensual' => $planes['basico']['precio_mensual_cop'],
            'anual'   => $planes['basico']['precio_anual_cop'],
            'trial'   => $planes['basico']['trial_dias'],
        ],
        'pro' => [
            'mensual' => $planes['pro']['precio_mensual_cop'],
            'anual'   => $planes['pro']['precio_anual_cop'],
            'trial'   => 0,
        ],
        'firma' => [
            'mensual' => $planes['firma']['precio_mensual_cop'],
            'anual'   => $planes['firma']['precio_anual_cop'],
            'trial'   => 0,
        ],
    ];

    $uid = 'ps_' . substr(md5(uniqid()), 0, 8);

    $planes_config = [
        'basico' => [
            'key'      => 'basico',
            'label'    => 'Básico',
            'sublabel' => '7 días gratis',
            'sc'       => '#60a5fa',
            'ib'       => 'rgba(96,165,250,.13)',
            'ibc'      => 'rgba(96,165,250,.28)',
            'tag'      => 'Básico',
            'icon'     => 'https://cdn.lordicon.com/hmpomorl.json',
            'icon_colors' => 'primary:#60a5fa,secondary:#bfdbfe',
            'icon_colors_light' => 'primary:#2563eb,secondary:#93c5fd',
            'badge'    => null,
            'features' => [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => false],
                ['label' => 'Contratos y liquidaciones','ok' => false],
                ['label' => 'RIT incluido',             'ok' => false],
                ['label' => 'Múltiples empresas',       'ok' => false],
            ],
        ],
        'pro' => [
            'key'      => 'pro',
            'label'    => 'Pro',
            'sublabel' => null,
            'sc'       => '#c9a84c',
            'ib'       => 'rgba(201,168,76,.13)',
            'ibc'      => 'rgba(201,168,76,.28)',
            'tag'      => 'Pro',
            'icon'     => 'https://cdn.lordicon.com/lupuorrc.json',
            'icon_colors' => 'primary:#c9a84c,secondary:#fde68a',
            'icon_colors_light' => 'primary:#92710d,secondary:#fbbf24',
            'badge'    => 'Recomendado',
            'features' => [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => true],
                ['label' => 'Contratos y liquidaciones','ok' => true],
                ['label' => 'RIT incluido',             'ok' => true],
                ['label' => 'Múltiples empresas',       'ok' => false],
            ],
        ],
        'firma' => [
            'key'      => 'firma',
            'label'    => 'Firma',
            'sublabel' => 'Bufetes y contadores',
            'sc'       => '#34d399',
            'ib'       => 'rgba(52,211,153,.13)',
            'ibc'      => 'rgba(52,211,153,.28)',
            'tag'      => 'Firma',
            'icon'     => 'https://cdn.lordicon.com/osuxyevn.json',
            'icon_colors' => 'primary:#34d399,secondary:#a7f3d0',
            'icon_colors_light' => 'primary:#059669,secondary:#6ee7b7',
            'badge'    => null,
            'features' => [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => true],
                ['label' => 'Contratos y liquidaciones','ok' => true],
                ['label' => 'RIT incluido',             'ok' => true],
                ['label' => 'Múltiples empresas',       'ok' => true],
            ],
        ],
    ];
@endphp

{{-- Lordicon --}}
<script>
    if (!window._liLoaded) {
        window._liLoaded = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.lordicon.com/lordicon.js';
        document.head.appendChild(s);
    }
</script>

@verbatim
<style>
/* ── Entrada ────────────────────────────────────────────── */
@keyframes ps-up {
    from { opacity:0; transform:translateY(18px) }
    to   { opacity:1; transform:translateY(0)    }
}
.ps-a1 { animation: ps-up .55s cubic-bezier(.16,1,.3,1) both }
.ps-a2 { animation: ps-up .55s .10s cubic-bezier(.16,1,.3,1) both }
.ps-a3 { animation: ps-up .55s .20s cubic-bezier(.16,1,.3,1) both }

/* ── Card base ──────────────────────────────────────────── */
.ps-card {
    border-radius: 1rem;
    padding: 1.25rem 1.125rem 1rem;
    cursor: pointer;
    transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s ease;
    position: relative;
    overflow: hidden;
    transform-style: preserve-3d;
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
}

/* color bar top — animado en hover, permanente si seleccionado */
.ps-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--sc, #c9a84c);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .28s ease;
    z-index: 1;
}
.ps-card:hover::before    { transform: scaleX(1); }
.ps-card.ps-selected::before { transform: scaleX(1); }

/* shimmer radial */
.ps-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(255,255,255,.12) 0%, transparent 62%);
    opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
    z-index: 0;
}
.ps-card:hover::after  { opacity: 1; }
.ps-card.ps-selected::after { opacity: .7; }

/* selected border glow */
.ps-card.ps-selected {
    border-color: var(--sc, #c9a84c);
    box-shadow: 0 0 0 1px var(--sc, #c9a84c), 0 8px 32px rgba(0,0,0,.28);
}

/* ── Light mode overrides ───────────────────────────────── */
html:not(.dark) .ps-card {
    background: rgba(255,255,255,.88);
    border-color: rgba(0,0,0,.08);
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
}
html:not(.dark) .ps-card::after {
    background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(201,168,76,.10) 0%, transparent 62%);
}
html:not(.dark) .ps-card.ps-selected {
    background: rgba(255,255,255,.97);
    box-shadow: 0 0 0 1.5px var(--sc,#c9a84c), 0 6px 24px rgba(0,0,0,.10);
}

/* ── Icon badge ─────────────────────────────────────────── */
.ps-icon-badge {
    width: 44px; height: 44px;
    border-radius: .625rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--ib, rgba(201,168,76,.12));
    border: 1px solid var(--ibc, rgba(201,168,76,.25));
    margin-bottom: .75rem;
}

/* ── Plan tag ───────────────────────────────────────────── */
.ps-plan-tag {
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--sc, #c9a84c);
    opacity: .85;
    margin-bottom: .15rem;
}

/* ── Check ring ─────────────────────────────────────────── */
.ps-check {
    position: absolute;
    top: .75rem; right: .75rem;
    width: 20px; height: 20px;
    border-radius: 50%;
    background: var(--sc, #c9a84c);
    display: flex; align-items: center; justify-content: center;
    opacity: 0;
    transform: scale(.6);
    transition: opacity .2s ease, transform .25s cubic-bezier(.34,1.56,.64,1);
    z-index: 2;
}
.ps-card.ps-selected .ps-check {
    opacity: 1;
    transform: scale(1);
}

/* ── Recomendado badge ──────────────────────────────────── */
.ps-badge-rec {
    position: absolute;
    top: -12px; left: 50%;
    transform: translateX(-50%);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .2rem .7rem;
    border-radius: 99px;
    background: var(--sc, #c9a84c);
    color: #0f172a;
    white-space: nowrap;
    z-index: 3;
    box-shadow: 0 2px 10px rgba(0,0,0,.3);
}

/* ── Color tokens (dark default) ────────────────────────── */
.ps-t-h  { color: #f1f5f9 }
.ps-t-s  { color: #94a3b8 }
.ps-t-ct { color: #f1f5f9; font-size: .9rem; font-weight: 700; margin: 0 0 .15rem; line-height: 1.2 }
.ps-t-cb { color: #94a3b8; font-size: .75rem; margin: 0; line-height: 1.4 }
.ps-t-price { color: #f1f5f9; font-size: 1.4rem; font-weight: 800; line-height: 1; margin: 0 }
.ps-t-sub   { color: #64748b; font-size: .72rem; margin: .25rem 0 0 }

html:not(.dark) .ps-t-h  { color: #0f172a }
html:not(.dark) .ps-t-s  { color: #64748b }
html:not(.dark) .ps-t-ct { color: #0f172a }
html:not(.dark) .ps-t-cb { color: #475569 }
html:not(.dark) .ps-t-price { color: #0f172a }
html:not(.dark) .ps-t-sub   { color: #94a3b8 }

/* ── Toggle billing ─────────────────────────────────────── */
.ps-toggle-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    margin-bottom: 1.625rem;
}
.ps-toggle-label {
    font-size: .8125rem;
    font-weight: 600;
    transition: color .2s;
}
.ps-toggle-btn {
    position: relative;
    width: 42px; height: 24px;
    border-radius: 99px;
    border: none;
    cursor: pointer;
    transition: background .25s;
    flex-shrink: 0;
}
.ps-toggle-knob {
    position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #fff;
    transition: transform .22s cubic-bezier(.34,1.2,.64,1);
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}

/* ── Feature list ───────────────────────────────────────── */
.ps-feat-list { list-style: none; margin: .875rem 0 0; padding: 0; flex: 1; display: flex; flex-direction: column; gap: .45rem; }
.ps-feat-item { display: flex; align-items: center; gap: .5rem; font-size: .75rem; }
.ps-feat-ok   { color: #f1f5f9 }
.ps-feat-no   { color: #475569 }
html:not(.dark) .ps-feat-ok { color: #1e293b }
html:not(.dark) .ps-feat-no { color: #94a3b8 }

/* ── CTA button ─────────────────────────────────────────── */
.ps-btn {
    margin-top: 1.125rem;
    width: 100%;
    border-radius: .625rem;
    padding: .55rem 1rem;
    font-size: .8125rem;
    font-weight: 700;
    letter-spacing: .02em;
    cursor: pointer;
    border: none;
    transition: background .2s, color .2s, box-shadow .2s, transform .15s;
    position: relative;
    z-index: 1;
}
.ps-btn-selected {
    background: var(--sc, #c9a84c);
    color: #0f172a;
    box-shadow: 0 4px 14px rgba(0,0,0,.25);
}
.ps-btn-unselected {
    background: rgba(255,255,255,.08);
    color: #94a3b8;
    border: 1px solid rgba(255,255,255,.1);
}
html:not(.dark) .ps-btn-unselected {
    background: rgba(0,0,0,.04);
    color: #64748b;
    border: 1px solid rgba(0,0,0,.09);
}
.ps-btn:active { transform: scale(.97); }

/* ── Nota footer ────────────────────────────────────────── */
.ps-footer-note {
    text-align: center;
    font-size: .7rem;
    color: #475569;
    margin-top: 1.25rem;
    line-height: 1.5;
}
html:not(.dark) .ps-footer-note { color: #94a3b8; }

/* ── Divider ─────────────────────────────────────────────── */
.ps-rule { display:flex; align-items:center; gap:.75rem; margin-bottom:1rem; }
.ps-rule-line { flex:1; height:1px; background:rgba(255,255,255,.08); }
html:not(.dark) .ps-rule-line { background:#e2e8f0; }
.ps-rule-label { font-size:.6rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#475569; white-space:nowrap; }
html:not(.dark) .ps-rule-label { color:#94a3b8; }

/* ── Trial pill ─────────────────────────────────────────── */
.ps-trial-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .2rem .55rem;
    border-radius: 99px;
    background: rgba(52,211,153,.14);
    color: #34d399;
    border: 1px solid rgba(52,211,153,.25);
    margin-bottom: .35rem;
}
html:not(.dark) .ps-trial-pill {
    background: rgba(5,150,105,.08);
    color: #059669;
    border-color: rgba(5,150,105,.2);
}

/* ── Reduced motion ────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .ps-a1, .ps-a2, .ps-a3 { animation: none; opacity: 1; }
    .ps-card { transition: none; }
    .ps-check { transition: none; }
}
</style>
@endverbatim

<div
    x-data="{
        ciclo: 'mensual',
        plan: 'basico',
        planes: @js($planesData),
        init() {
            this.$watch('plan',  v => $wire.set('data.plan_suscripcion',  v));
            this.$watch('ciclo', v => $wire.set('data.ciclo_facturacion', v));
        },
        fmt(n) { return '$' + n.toLocaleString('es-CO'); },
        precio(key) { return this.ciclo === 'anual' ? this.planes[key].anual : this.planes[key].mensual; },
        periodo()   { return this.ciclo === 'anual' ? 'año' : 'mes'; }
    }"
    x-init="init()"
    style="display:flex;flex-direction:column;"
>
    {{-- ── Toggle Mensual / Anual ─────────────────────────────────── --}}
    <div class="ps-toggle-wrap ps-a1">
        <span
            class="ps-toggle-label ps-t-h"
            :style="ciclo === 'mensual' ? 'opacity:1' : 'opacity:.45'"
        >Mensual</span>

        <button
            type="button"
            @click="ciclo = ciclo === 'mensual' ? 'anual' : 'mensual'"
            class="ps-toggle-btn"
            :style="ciclo === 'anual'
                ? 'background:rgba(201,168,76,.9)'
                : 'background:rgba(255,255,255,.18)'"
            aria-label="Cambiar ciclo de facturación"
        >
            <span
                class="ps-toggle-knob"
                :style="ciclo === 'anual' ? 'transform:translateX(18px)' : 'transform:translateX(0)'"
            ></span>
        </button>

        <span
            class="ps-toggle-label ps-t-h"
            :style="ciclo === 'anual' ? 'opacity:1' : 'opacity:.45'"
        >
            Anual
            <span style="margin-left:.4rem;font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:99px;background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.25);">
                15&nbsp;% dto.
            </span>
        </span>
    </div>

    {{-- ── Grid de planes ─────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.875rem;">

        @foreach ($planes_config as $p)
        <div
            class="ps-card {{ $loop->iteration === 1 ? 'ps-a1' : ($loop->iteration === 2 ? 'ps-a2' : 'ps-a3') }}"
            style="--sc:{{ $p['sc'] }};--ib:{{ $p['ib'] }};--ibc:{{ $p['ibc'] }};"
            :class="plan === '{{ $p['key'] }}' ? 'ps-selected' : ''"
            @click="plan = '{{ $p['key'] }}'"
            id="{{ $uid }}_card_{{ $p['key'] }}"
        >
            {{-- Badge recomendado --}}
            @if($p['badge'])
            <div class="ps-badge-rec" style="--sc:{{ $p['sc'] }}">{{ $p['badge'] }}</div>
            @endif

            {{-- Check seleccionado --}}
            <div class="ps-check">
                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="#0f172a" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            {{-- Contenido (z-index sobre ::after) --}}
            <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;">

                {{-- Icono --}}
                <div class="ps-icon-badge">
                    <lord-icon
                        src="{{ $p['icon'] }}"
                        trigger="loop" delay="2000"
                        colors="{{ $p['icon_colors'] }}"
                        data-pt-dark="{{ $p['icon_colors'] }}"
                        data-pt-light="{{ $p['icon_colors_light'] }}"
                        style="width:28px;height:28px;"
                    ></lord-icon>
                </div>

                {{-- Tag + nombre --}}
                <p class="ps-plan-tag" style="--sc:{{ $p['sc'] }}">{{ $p['tag'] }}</p>
                <p class="ps-t-ct">{{ $p['label'] }}</p>
                @if($p['sublabel'])
                <p class="ps-t-cb" style="margin-bottom:.35rem;">{{ $p['sublabel'] }}</p>
                @endif

                {{-- Precio --}}
                <div style="margin-top:.5rem;min-height:3rem;">
                    @if($p['key'] === 'basico')
                    <div class="ps-trial-pill">
                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        7 días gratis
                    </div>
                    <p class="ps-t-sub">
                        luego <span style="font-weight:700;" x-text="fmt(precio('basico'))"></span> / <span x-text="periodo()"></span>
                    </p>
                    @else
                    <p class="ps-t-price" x-text="fmt(precio('{{ $p['key'] }}'))"></p>
                    <p class="ps-t-sub">
                        por <span x-text="periodo()"></span>
                        <span x-show="ciclo === 'anual'" style="color:#34d399;font-weight:600;">&nbsp;· 15 % dto.</span>
                    </p>
                    @endif
                </div>

                {{-- Features --}}
                <ul class="ps-feat-list">
                    @foreach($p['features'] as $feat)
                    <li class="ps-feat-item {{ $feat['ok'] ? 'ps-feat-ok' : 'ps-feat-no' }}">
                        @if($feat['ok'])
                        <svg width="14" height="14" flex-shrink="0" fill="none" viewBox="0 0 24 24" stroke="{{ $p['sc'] }}" stroke-width="2.5" style="flex-shrink:0;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        @else
                        <svg width="14" height="14" flex-shrink="0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.35;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        @endif
                        {{ $feat['label'] }}
                    </li>
                    @endforeach
                </ul>

                {{-- Botón CTA --}}
                <button
                    type="button"
                    @click.stop="plan = '{{ $p['key'] }}'"
                    class="ps-btn"
                    :class="plan === '{{ $p['key'] }}' ? 'ps-btn-selected' : 'ps-btn-unselected'"
                    :style="plan === '{{ $p['key'] }}' ? '--sc:{{ $p['sc'] }}' : ''"
                >
                    <span x-text="plan === '{{ $p['key'] }}' ? 'Plan seleccionado ✓' : 'Seleccionar plan'"></span>
                </button>

            </div>
        </div>
        @endforeach

    </div>

    {{-- Nota pasarela --}}
    <p class="ps-footer-note">
        Pagos procesados de forma segura a través de
        <strong style="color:#c9a84c;font-weight:600;">PayU Colombia</strong>.
        Acepta PSE, tarjetas débito/crédito y efectivo.
    </p>

</div>

<script>
(function() {
    var UID = '{{ $uid }}';
    var cards = ['basico', 'pro', 'firma'];
    var hasHover = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
    if (!hasHover) return;

    cards.forEach(function(key) {
        var el = document.getElementById(UID + '_card_' + key);
        if (!el) return;

        el.addEventListener('mousemove', function(e) {
            var r = el.getBoundingClientRect();
            var rx = ((e.clientY - r.top  - r.height / 2) / (r.height / 2)) * 7;
            var ry = ((r.width  / 2 - (e.clientX - r.left)) / (r.width  / 2)) * 7;
            var mx = ((e.clientX - r.left) / r.width  * 100).toFixed(1) + '%';
            var my = ((e.clientY - r.top)  / r.height * 100).toFixed(1) + '%';
            el.style.setProperty('--mx', mx);
            el.style.setProperty('--my', my);
            el.style.transition = 'transform .08s ease, box-shadow .08s ease';
            el.style.transform  = 'perspective(900px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) scale(1.03)';
        });

        el.addEventListener('mouseleave', function() {
            el.style.transition = 'transform .5s cubic-bezier(.16,1,.3,1), box-shadow .5s ease';
            el.style.transform  = 'perspective(900px) rotateX(0deg) rotateY(0deg) scale(1)';
        });
    });
})();
</script>
