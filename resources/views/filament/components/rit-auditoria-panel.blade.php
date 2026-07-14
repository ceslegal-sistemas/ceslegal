@php
    /**
     * Panel compacto de auditoría del RIT para la vista unificada del cliente.
     * Requiere que la página exponga $this->auditoria y los métodos refrescarAuditoria,
     * iniciarAuditoriaManual, mantenerRITActual y la acción aceptarSugerenciasRITAction.
     */
    $a          = $auditoria ?? null;
    $secciones  = $a?->secciones ?? [];
    $numTotal   = \App\Services\AuditoriaRITService::getNumSecciones();
    $numDone    = count($secciones);
    $enProceso  = $a && in_array($a->estado, ['pendiente', 'procesando'], true);
    $completada = $a && $a->estado === 'completado';
    $errorAud   = $a && $a->estado === 'error';
    $score      = $a?->score;
    $scoreColor = match (true) {
        ($score ?? 0) >= 80 => '#22c55e',
        ($score ?? 0) >= 50 => '#f59e0b',
        default             => '#ef4444',
    };
    $estadoMejora    = $a?->estado_mejora;
    $decision        = $a?->decision_mejora;
    $mejorando       = $estadoMejora === 'procesando';
    $mejoraLista     = $estadoMejora === 'completado' && $a?->reglamento_mejorado_id;
    $mejoraPendiente = $mejoraLista && ! in_array($decision, ['adoptado', 'rechazado'], true);

    // Recomendaciones agregadas de las secciones que no cumplen al 100%.
    $sugerencias = collect($secciones)
        ->filter(fn ($s) => ($s['score'] ?? 100) < 100)
        ->flatMap(fn ($s) => $s['recomendaciones'] ?? [])
        ->filter()
        ->take(6)
        ->values();
@endphp

<div class="ra-wrap" @if($enProceso || $mejorando) wire:poll.2000ms="refrescarAuditoria" @endif>
    <style>
        .ra-wrap{border-radius:1rem;padding:1.5rem 1.5rem;border:1px solid rgba(251,113,133,.2);background:rgba(251,113,133,.05)}
        html:not(.dark) .ra-wrap{background:#fff;border-color:rgba(0,0,0,.07);box-shadow:0 2px 16px rgba(0,0,0,.05)}
        .ra-h{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem}
        .ra-h-t{font-size:1rem;font-weight:700;color:#f1f5f9;margin:0}
        html:not(.dark) .ra-h-t{color:#1c1917}
        .ra-h-s{font-size:.8rem;color:#94a3b8;margin:.1rem 0 0}
        html:not(.dark) .ra-h-s{color:#64748b}
        .ra-ring{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.15rem;font-weight:800;border:5px solid;flex-shrink:0}
        .ra-sec{display:flex;align-items:center;gap:.6rem;padding:.5rem 0;border-top:1px solid rgba(148,163,184,.14);font-size:.8125rem;color:#cbd5e1}
        html:not(.dark) .ra-sec{color:#334155}
        .ra-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
        .ra-badge{margin-left:auto;font-size:.72rem;font-weight:700}
        .ra-sug{display:flex;gap:.5rem;font-size:.8rem;color:#94a3b8;line-height:1.5;padding:.25rem 0}
        html:not(.dark) .ra-sug{color:#475569}
        .ra-btn{display:inline-flex;align-items:center;gap:.45rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.15rem;border-radius:.6rem;border:none;cursor:pointer;transition:all .2s}
        .ra-btn-p{background:linear-gradient(135deg,#e11d48,#f97316);color:#fff;box-shadow:0 2px 8px rgba(251,113,133,.35)}
        .ra-btn-p:hover{opacity:.92;transform:translateY(-1px)}
        .ra-btn-g{background:rgba(148,163,184,.15);color:#cbd5e1;border:1px solid rgba(148,163,184,.3)}
        html:not(.dark) .ra-btn-g{color:#475569}
        .ra-spin{width:16px;height:16px;animation:raspin .8s linear infinite;color:#fb7185}
        @keyframes raspin{to{transform:rotate(360deg)}}
    </style>

    {{-- Encabezado --}}
    <div class="ra-h">
        <lord-icon src="https://cdn.lordicon.com/lecprnjb.json" trigger="loop" delay="1500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185" data-pt-dark="primary:#fb7185,secondary:#fb7185"
            data-pt-light="primary:#e11d48,secondary:#f97316" style="width:34px;height:34px;flex-shrink:0"></lord-icon>
        <div style="flex:1;min-width:0">
            <p class="ra-h-t">Salud legal del Reglamento Interno</p>
            <p class="ra-h-s">Revisión contra el CST, Ley 1010/2006, Ley 2365/2024 y la biblioteca jurídica.</p>
        </div>
        @if($completada && $score !== null)
            <div class="ra-ring" style="border-color:{{ $scoreColor }};color:{{ $scoreColor }}">{{ $score }}</div>
        @endif
    </div>

    {{-- Sin auditoría aún --}}
    @if(! $a)
        <p class="ra-h-s" style="margin:0 0 1rem">Aún no se ha auditado este Reglamento Interno.</p>
        <button wire:click="iniciarAuditoriaManual" class="ra-btn ra-btn-p">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2m1.7-4.05a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z"/></svg>
            Auditar ahora
        </button>

    {{-- En proceso --}}
    @elseif($enProceso)
        <div style="display:flex;align-items:center;gap:.6rem">
            <svg class="ra-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
            <span style="font-size:.85rem;color:#fb7185;font-weight:600">Auditando… {{ $numDone }} / {{ $numTotal }} secciones</span>
        </div>

    {{-- Error --}}
    @elseif($errorAud)
        <p class="ra-h-s" style="margin:0 0 1rem">No se pudo completar la auditoría. Puede reintentarla.</p>
        <button wire:click="iniciarAuditoriaManual" class="ra-btn ra-btn-p">Reintentar auditoría</button>

    {{-- Completada --}}
    @elseif($completada)
        <div style="margin-bottom:1rem">
            @foreach($secciones as $sec)
                @php
                    $sScore = $sec['score'] ?? 0;
                    $cumple = ($sec['calificacion'] ?? '') !== 'Error' && $sScore >= 80;
                    $dot    = $cumple ? '#22c55e' : ($sScore >= 50 ? '#f59e0b' : '#ef4444');
                @endphp
                <div class="ra-sec">
                    <span class="ra-dot" style="background:{{ $dot }}"></span>
                    <span>{{ $sec['titulo'] ?? '—' }}</span>
                    <span class="ra-badge" style="color:{{ $dot }}">{{ $sScore }}</span>
                </div>
            @endforeach
        </div>

        @if($sugerencias->isNotEmpty())
            <p style="font-size:.78rem;font-weight:700;color:#fb7185;text-transform:uppercase;letter-spacing:.05em;margin:.5rem 0 .35rem">Sugerencias de mejora</p>
            @foreach($sugerencias as $s)
                <div class="ra-sug"><span style="color:#22c55e;flex-shrink:0">→</span><span>{{ $s }}</span></div>
            @endforeach
        @endif

        {{-- Estado de la mejora / decisión --}}
        @if($mejorando)
            <div wire:poll.2000ms="refrescarAuditoria" style="display:flex;align-items:center;gap:.6rem;margin-top:1rem">
                <svg class="ra-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
                <span style="font-size:.82rem;color:#fb7185;font-weight:600">{{ $a->progreso_mejora ?: 'Generando su RIT mejorado…' }}</span>
            </div>
        @elseif($decision === 'adoptado')
            <div style="display:flex;align-items:center;gap:.5rem;margin-top:1rem;padding:.65rem 1rem;border-radius:.6rem;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.22)">
                <svg style="width:16px;height:16px;color:#22c55e;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size:.8rem;color:#86efac">Se adoptó la versión mejorada como su Reglamento Interno vigente.</span>
            </div>
        @elseif($mejoraPendiente || $decision === 'rechazado')
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:1rem">
                {{ $this->aceptarSugerenciasRITAction }}
                @if($mejoraPendiente)
                    <button wire:click="mantenerRITActual" class="ra-btn ra-btn-g">Mantener mi RIT actual</button>
                @endif
            </div>
        @endif
    @endif
</div>
