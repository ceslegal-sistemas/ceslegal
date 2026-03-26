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
</style>

<div class="phc-wrap">
    <div class="phc-header">
        <svg style="width:13px;height:13px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        Complete los campos faltantes — haga clic en una opción
    </div>

    @foreach ($sugerencias as $s)
        <div class="phc-field">
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
            </div>
        </div>
    @endforeach
</div>
