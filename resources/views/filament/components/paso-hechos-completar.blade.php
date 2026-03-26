<style>
    .phc-wrap {
        display: flex;
        flex-direction: column;
        gap: .625rem;
        padding: .875rem 1rem;
        border-radius: .75rem;
        background: rgba(99,102,241,.05);
        border: 1px solid rgba(99,102,241,.18);
        margin-top: .25rem;
    }
    .phc-header {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #818cf8;
    }
    html:not(.dark) .phc-header { color: #4f46e5; }
    .phc-field {
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }
    .phc-label {
        font-size: .75rem;
        color: #94a3b8;
        font-weight: 500;
    }
    html:not(.dark) .phc-label { color: #475569; }
    .phc-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .375rem;
        align-items: center;
    }
    .phc-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .3rem .7rem;
        border-radius: 2rem;
        font-size: .75rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid rgba(99,102,241,.3);
        background: rgba(99,102,241,.08);
        color: #a5b4fc;
        transition: background .15s, border-color .15s, color .15s, transform .1s;
        user-select: none;
    }
    .phc-chip:hover {
        background: rgba(99,102,241,.18);
        border-color: rgba(99,102,241,.55);
        color: #c7d2fe;
        transform: translateY(-1px);
    }
    .phc-chip:active { transform: scale(.96); }
    html:not(.dark) .phc-chip {
        border-color: rgba(79,70,229,.25);
        background: rgba(79,70,229,.07);
        color: #4338ca;
    }
    html:not(.dark) .phc-chip:hover {
        background: rgba(79,70,229,.14);
        border-color: rgba(79,70,229,.5);
        color: #3730a3;
    }
    /* Botón "otra opción" */
    .phc-btn-otra {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .3rem .65rem;
        border-radius: 2rem;
        font-size: .72rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px dashed rgba(148,163,184,.35);
        background: transparent;
        color: #64748b;
        transition: border-color .15s, color .15s;
        user-select: none;
    }
    .phc-btn-otra:hover { border-color: rgba(148,163,184,.65); color: #94a3b8; }
    html:not(.dark) .phc-btn-otra { color: #9ca3af; border-color: rgba(0,0,0,.18); }
    html:not(.dark) .phc-btn-otra:hover { color: #6b7280; border-color: rgba(0,0,0,.35); }
    /* Input personalizado */
    .phc-custom-row {
        display: flex;
        gap: .4rem;
        align-items: center;
        margin-top: .25rem;
        flex-wrap: wrap;
    }
    .phc-custom-input {
        flex: 1;
        min-width: 160px;
        padding: .3rem .65rem;
        border-radius: .4rem;
        font-size: .8125rem;
        border: 1px solid rgba(99,102,241,.35);
        background: rgba(99,102,241,.06);
        color: #e2e8f0;
        outline: none;
        transition: border-color .15s;
    }
    .phc-custom-input:focus { border-color: rgba(99,102,241,.7); }
    html:not(.dark) .phc-custom-input {
        background: #fff;
        border-color: rgba(79,70,229,.3);
        color: #0f172a;
    }
    .phc-btn-aplicar {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .3rem .75rem;
        border-radius: .4rem;
        font-size: .75rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid rgba(99,102,241,.5);
        background: rgba(99,102,241,.15);
        color: #a5b4fc;
        transition: background .15s;
        white-space: nowrap;
    }
    .phc-btn-aplicar:hover { background: rgba(99,102,241,.28); }
    html:not(.dark) .phc-btn-aplicar { background: rgba(79,70,229,.1); color: #4338ca; border-color: rgba(79,70,229,.4); }
    html:not(.dark) .phc-btn-aplicar:hover { background: rgba(79,70,229,.2); }
</style>

<div class="phc-wrap">
    <div class="phc-header">
        <svg style="width:13px;height:13px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        Complete los campos faltantes — haga clic en una opción
    </div>

    @foreach ($sugerencias as $idx => $s)
        <div class="phc-field"
             x-data="{ mostrarPersonalizado: false, valorPersonalizado: '' }"
             x-id="['phc-inp']">

            <span class="phc-label">{{ $s['label'] }}</span>

            <div class="phc-chips">
                @foreach ($s['opciones'] as $op)
                    <button type="button"
                        class="phc-chip"
                        onclick="$wire.aplicarSugerencia({{ Js::from($s['marker']) }}, {{ Js::from($op) }})">
                        <svg style="width:10px;height:10px;opacity:.7;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        {{ $op }}
                    </button>
                @endforeach

                {{-- Botón "otra opción" --}}
                <button type="button"
                    class="phc-btn-otra"
                    x-show="!mostrarPersonalizado"
                    @click="mostrarPersonalizado = true; $nextTick(() => $refs.inp_{{ $idx }}.focus())">
                    <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                    </svg>
                    Otra opción
                </button>
            </div>

            {{-- Input personalizado --}}
            <div class="phc-custom-row" x-show="mostrarPersonalizado" x-cloak>
                <input
                    type="text"
                    class="phc-custom-input"
                    x-ref="inp_{{ $idx }}"
                    x-model="valorPersonalizado"
                    placeholder="Escribe tu propia respuesta…"
                    @keydown.enter.prevent="
                        if (valorPersonalizado.trim()) {
                            $wire.aplicarSugerencia({{ Js::from($s['marker']) }}, valorPersonalizado.trim());
                        }
                    "
                    @keydown.escape="mostrarPersonalizado = false; valorPersonalizado = ''">
                <button type="button"
                    class="phc-btn-aplicar"
                    @click="if (valorPersonalizado.trim()) { $wire.aplicarSugerencia({{ Js::from($s['marker']) }}, valorPersonalizado.trim()); }">
                    <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Aplicar
                </button>
                <button type="button"
                    class="phc-btn-otra"
                    @click="mostrarPersonalizado = false; valorPersonalizado = ''">
                    Cancelar
                </button>
            </div>

        </div>
    @endforeach
</div>
