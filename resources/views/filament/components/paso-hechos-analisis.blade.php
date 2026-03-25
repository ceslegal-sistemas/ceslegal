<style>
    .pha-card {
        background: rgba(99, 102, 241, .06);
        border: 1px solid rgba(99, 102, 241, .18);
        border-radius: .75rem;
        padding: .875rem 1rem;
        height: 100%;
        box-sizing: border-box;
    }
    .pha-title {
        margin: 0 0 .625rem;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #a5b4fc;
    }
    .pha-row {
        display: flex;
        align-items: flex-start;
        gap: .4rem;
        font-size: .8rem;
        line-height: 1.5;
        padding: .3rem 0;
        color: #94a3b8;
        border-bottom: 1px solid rgba(99,102,241,.1);
    }
    .pha-row:last-child { border-bottom: none; }
    .pha-row.pha-ok    { color: #86efac; }
    .pha-row.pha-warn  { color: #fde68a; }
    .pha-ico { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }

    /* Fila de categoría: columna para poder poner el badge debajo */
    .pha-cat-row { flex-direction: column; align-items: flex-start; gap: .2rem; }
    .pha-cat-top { display: flex; align-items: center; gap: .4rem; }
    .pha-badge {
        display: inline-block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        background: rgba(99,102,241,.2);
        color: #a5b4fc;
        border: 1px solid rgba(99,102,241,.35);
        border-radius: .3rem;
        padding: .1rem .45rem;
        margin-top: .15rem;
    }

    /* Estado vacío */
    .pha-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 140px;
        text-align: center;
        color: #64748b;
        font-size: .8125rem;
        line-height: 1.6;
        padding: 1rem;
        border: 1px dashed rgba(99,102,241,.2);
        border-radius: .75rem;
    }

    html:not(.dark) .pha-card        { background: rgba(99,102,241,.04); border-color: rgba(99,102,241,.15); }
    html:not(.dark) .pha-title       { color: #4f46e5; }
    html:not(.dark) .pha-row         { color: #6b7280; border-bottom-color: rgba(99,102,241,.08); }
    html:not(.dark) .pha-row.pha-ok  { color: #15803d; }
    html:not(.dark) .pha-row.pha-warn{ color: #b45309; }
    html:not(.dark) .pha-badge       { background: rgba(99,102,241,.1); color: #4f46e5; border-color: rgba(99,102,241,.3); }
    html:not(.dark) .pha-empty       { color: #9ca3af; border-color: rgba(99,102,241,.15); }

    /* Feedback de voz IA */
    .pha-feedback {
        margin-top: .5rem;
        padding: .5rem .625rem;
        background: rgba(139,92,246,.1);
        border: 1px solid rgba(139,92,246,.25);
        border-radius: .5rem;
        font-size: .775rem;
        color: #c4b5fd;
        line-height: 1.55;
        display: flex;
        gap: .375rem;
        align-items: flex-start;
    }
    .pha-feedback svg { width: 13px; height: 13px; flex-shrink: 0; margin-top: 1px; color: #a78bfa; }
    html:not(.dark) .pha-feedback       { background: rgba(139,92,246,.07); border-color: rgba(139,92,246,.2); color: #6d28d9; }
    html:not(.dark) .pha-feedback svg   { color: #7c3aed; }
</style>

@php $feedbackVoz = $feedbackVoz ?? ''; @endphp

@if(count($items) > 0)
<div class="pha-card">
    <p class="pha-title">Análisis previo</p>

    @foreach($items as $item)
        @if(($item['tipo'] ?? '') === 'categoria')
        {{-- Fila categoría: muestra badge si hay tipo detectado --}}
        <div class="pha-row {{ $item['ok'] ? 'pha-ok' : 'pha-warn' }} pha-cat-row">
            <div class="pha-cat-top">
                @if($item['ok'])
                <svg class="pha-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Conducta identificada</span>
                @else
                <svg class="pha-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <span>{{ $item['texto'] }}</span>
                @endif
            </div>
            @if(!empty($item['badge']))
                <span class="pha-badge">{{ $item['badge'] }}</span>
            @endif
        </div>

        @else
        {{-- Filas normales --}}
        <div class="pha-row {{ $item['ok'] ? 'pha-ok' : 'pha-warn' }}">
            @if($item['ok'])
            <svg class="pha-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            @else
            <svg class="pha-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            @endif
            <span>{{ $item['texto'] }}</span>
        </div>
        @endif
    @endforeach
    @if($feedbackVoz)
    <div class="pha-feedback">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        <span>{{ $feedbackVoz }}</span>
    </div>
    @endif
</div>

@else
<div class="pha-empty">
    <lord-icon src="https://cdn.lordicon.com/vgwutnhw.json" trigger="loop" delay="500" stroke="bold"
        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0" data-pt-icon
        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
        style="width:50px;height:50px;flex-shrink:0;margin-top:1px">
    </lord-icon>
    <span>Empiece a escribir<br>para ver el análisis.</span>
</div>
@endif
