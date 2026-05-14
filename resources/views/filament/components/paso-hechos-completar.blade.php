@php $sugerenciasJson = \Illuminate\Support\Js::from($sugerencias); @endphp

@once
{{-- NOTA: Alpine.data('completarIA') se registra en hechos-asistente.blade.php --}}
<style>
    .phc-wrap {
        border-radius: .75rem;
        background: rgba(99,102,241,.05);
        border: 1px solid rgba(99,102,241,.18);
        margin-top: .375rem;
        overflow: hidden;
    }
    html:not(.dark) .phc-wrap {
        background: rgba(79,70,229,.04);
        border-color: rgba(79,70,229,.15);
    }

    /* Header */
    .phc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .5rem .875rem;
        border-bottom: 1px solid rgba(99,102,241,.12);
        gap: .5rem;
    }
    .phc-header-left {
        display: flex;
        align-items: center;
        gap: .35rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #818cf8;
    }
    html:not(.dark) .phc-header-left { color: #4f46e5; }
    .phc-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        padding: 0 5px;
        font-size: .65rem;
        font-weight: 700;
        background: rgba(99,102,241,.18);
        color: #a5b4fc;
    }
    html:not(.dark) .phc-badge { background: rgba(79,70,229,.12); color: #4338ca; }

    /* Nav buttons */
    .phc-nav { display: flex; align-items: center; gap: .25rem; }
    .phc-nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px; height: 22px;
        border-radius: .35rem;
        border: 1px solid rgba(99,102,241,.25);
        background: transparent;
        color: #818cf8;
        cursor: pointer;
        transition: background .12s;
    }
    .phc-nav-btn:disabled { opacity: .3; cursor: default; }
    .phc-nav-btn:not(:disabled):hover { background: rgba(99,102,241,.14); }
    html:not(.dark) .phc-nav-btn { color: #4f46e5; border-color: rgba(79,70,229,.2); }
    .phc-nav-pos { font-size: .7rem; color: #64748b; min-width: 28px; text-align: center; }

    /* Body */
    .phc-body { padding: .75rem .875rem .875rem; }
    .phc-label {
        font-size: .72rem;
        color: #94a3b8;
        font-weight: 600;
        margin-bottom: .4rem;
        display: flex;
        align-items: center;
        gap: .3rem;
    }
    html:not(.dark) .phc-label { color: #475569; }

    /* Chips */
    .phc-chips { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
    .phc-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .3rem .7rem;
        border-radius: 2rem;
        font-size: .78rem; font-weight: 500;
        cursor: pointer;
        border: 1px solid rgba(99,102,241,.3);
        background: rgba(99,102,241,.08);
        color: #a5b4fc;
        transition: background .12s, border-color .12s, color .12s, transform .08s;
        user-select: none;
    }
    .phc-chip:hover { background: rgba(99,102,241,.18); border-color: rgba(99,102,241,.55); color: #c7d2fe; transform: translateY(-1px); }
    .phc-chip:active { transform: scale(.96); }
    html:not(.dark) .phc-chip { border-color: rgba(79,70,229,.25); background: rgba(79,70,229,.07); color: #4338ca; }
    html:not(.dark) .phc-chip:hover { background: rgba(79,70,229,.14); border-color: rgba(79,70,229,.5); color: #3730a3; }

    /* Otra opción */
    .phc-btn-otra {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .3rem .65rem; border-radius: 2rem;
        font-size: .72rem; font-weight: 500; cursor: pointer;
        border: 1px dashed rgba(148,163,184,.35);
        background: transparent; color: #64748b;
        transition: border-color .12s, color .12s;
        user-select: none;
    }
    .phc-btn-otra:hover { border-color: rgba(148,163,184,.65); color: #94a3b8; }
    html:not(.dark) .phc-btn-otra { color: #9ca3af; border-color: rgba(0,0,0,.15); }

    /* Custom input */
    .phc-custom-row { display: flex; gap: .375rem; align-items: center; margin-top: .375rem; flex-wrap: wrap; }
    .phc-custom-input {
        flex: 1; min-width: 150px;
        padding: .3rem .65rem; border-radius: .4rem;
        font-size: .8125rem;
        border: 1px solid rgba(99,102,241,.35);
        background: rgba(99,102,241,.06); color: #e2e8f0;
        outline: none; transition: border-color .12s;
    }
    .phc-custom-input:focus { border-color: rgba(99,102,241,.7); }
    html:not(.dark) .phc-custom-input { background: #fff; border-color: rgba(79,70,229,.3); color: #0f172a; }
    .phc-btn-aplicar {
        display: inline-flex; align-items: center; gap: .25rem;
        padding: .3rem .7rem; border-radius: .4rem;
        font-size: .75rem; font-weight: 600; cursor: pointer;
        border: 1px solid rgba(99,102,241,.5);
        background: rgba(99,102,241,.15); color: #a5b4fc;
        transition: background .12s; white-space: nowrap;
    }
    .phc-btn-aplicar:hover { background: rgba(99,102,241,.28); }
    html:not(.dark) .phc-btn-aplicar { background: rgba(79,70,229,.1); color: #4338ca; border-color: rgba(79,70,229,.4); }

    [x-cloak] { display: none !important; }
</style>
@endonce

<div x-data="completarIA({{ $sugerenciasJson }})"
     x-show="total > 0"
     x-cloak
     class="phc-wrap">

    {{-- Header con contador y navegación --}}
    <div class="phc-header">
        <div class="phc-header-left">
            <svg style="width:12px;height:12px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
            </svg>
            Completar con IA
            <span class="phc-badge" x-text="total"></span>
        </div>

        <div class="phc-nav" x-show="total > 1">
            <button type="button" class="phc-nav-btn" @click="prev()" :disabled="idx === 0">
                <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <span class="phc-nav-pos" x-text="(idx + 1) + ' / ' + total"></span>
            <button type="button" class="phc-nav-btn" @click="next()" :disabled="idx >= total - 1">
                <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Sugerencia actual (sin x-data anidado para evitar conflicto de scope) --}}
    <div class="phc-body" x-show="current">

        <div class="phc-label">
            <svg style="width:11px;height:11px;opacity:.6;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
            </svg>
            <span x-text="current ? current.label : ''"></span>
        </div>

        <div class="phc-chips">
            <template x-if="current">
                <template x-for="op in current.opciones" :key="op">
                    <button type="button" class="phc-chip"
                        @click="aplicar(current.marker, op)">
                        <svg style="width:9px;height:9px;opacity:.65;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span x-text="op"></span>
                    </button>
                </template>
            </template>

            <button type="button" class="phc-btn-otra"
                x-show="!mostrarPersonalizado"
                @click="mostrarPersonalizado = true; $nextTick(() => $refs.customInput && $refs.customInput.focus())">
                <svg style="width:9px;height:9px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                </svg>
                Otra opción
            </button>
        </div>

        <div class="phc-custom-row" x-show="mostrarPersonalizado">
            <input type="text"
                class="phc-custom-input"
                x-ref="customInput"
                x-model="valorPersonalizado"
                placeholder="Escribe tu propia respuesta…"
                @keydown.enter.prevent="if (valorPersonalizado.trim() && current) aplicar(current.marker, valorPersonalizado.trim())"
                @keydown.escape="mostrarPersonalizado = false; valorPersonalizado = ''">
            <button type="button" class="phc-btn-aplicar"
                @click="if (valorPersonalizado.trim() && current) aplicar(current.marker, valorPersonalizado.trim())">
                <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
                Aplicar
            </button>
            <button type="button" class="phc-btn-otra"
                @click="mostrarPersonalizado = false; valorPersonalizado = ''">
                Cancelar
            </button>
        </div>

    </div>

</div>
