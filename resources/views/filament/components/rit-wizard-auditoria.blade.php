@php
    /**
     * Paso "Auditoría del RIT" del wizard de registro de empresa.
     * La página CreateEmpresa expone $this->auditoria y los métodos
     * refrescarAuditoriaWizard, mantenerRITWizard y la acción aceptarMejoraWizardAction.
     */
    $opcion     = data_get($this->data, 'rit_opcion');
    $subioRit   = $opcion === 'tiene';
    $a          = $this->auditoria ?? null;
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
    $decision   = $a?->decision_mejora;
    $yaDecidio  = in_array($decision, ['adoptado', 'rechazado'], true);
    $gate       = data_get($this->data, 'auditoria_confirmada');
    $sugerencias = collect($secciones)
        ->filter(fn ($s) => ($s['score'] ?? 100) < 100)
        ->flatMap(fn ($s) => $s['recomendaciones'] ?? [])
        ->filter()->take(6)->values();
@endphp

<div wire:key="rit-wizard-auditoria"
     @if($enProceso) wire:poll.2000ms="refrescarAuditoriaWizard" @endif
     style="border-radius:1rem;padding:1.5rem;border:1px solid rgba(251,113,133,.2);background:rgba(251,113,133,.05)">

    {{-- No subió RIT: nada que auditar --}}
    @if(! $subioRit)
        <div style="display:flex;align-items:center;gap:.75rem">
            <svg style="width:22px;height:22px;color:#22c55e;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p style="font-weight:700;margin:0;color:#f1f5f9">No se requiere auditoría</p>
                <p style="font-size:.82rem;color:#94a3b8;margin:.15rem 0 0">No subió un Reglamento Interno en este registro. Puede crear la empresa.</p>
            </div>
        </div>

    {{-- Auditoría no iniciada aún (fail-safe permitió continuar) --}}
    @elseif(! $a)
        <div style="display:flex;align-items:center;gap:.75rem">
            <svg style="width:22px;height:22px;color:#f59e0b;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <div>
                <p style="font-weight:700;margin:0;color:#f1f5f9">Auditoría no disponible</p>
                <p style="font-size:.82rem;color:#94a3b8;margin:.15rem 0 0">No se pudo auditar el documento ahora. Puede crear la empresa y auditarlo luego desde "Auditar RIT".</p>
            </div>
        </div>

    {{-- En proceso --}}
    @elseif($enProceso)
        <div style="display:flex;align-items:center;gap:.7rem">
            <svg style="width:20px;height:20px;color:#fb7185;animation:wspin .8s linear infinite" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
            <span style="font-size:.9rem;color:#fb7185;font-weight:600">Auditando su RIT… {{ $numDone }} / {{ $numTotal }} secciones</span>
        </div>
        <p style="font-size:.8rem;color:#94a3b8;margin:.75rem 0 0">Revisamos el reglamento contra el CST y la normativa vigente. No cierre esta ventana.</p>
        <style>@keyframes wspin{to{transform:rotate(360deg)}}</style>

    {{-- Error --}}
    @elseif($errorAud)
        <p style="font-weight:700;margin:0 0 .3rem;color:#f87171">No se pudo completar la auditoría</p>
        <p style="font-size:.82rem;color:#94a3b8;margin:0 0 1rem">Puede crear la empresa de todos modos y auditar el RIT luego.</p>
        <button type="button" wire:click="mantenerRITWizard" style="font-size:.82rem;font-weight:600;padding:.55rem 1.1rem;border-radius:.55rem;border:1px solid rgba(148,163,184,.3);background:rgba(148,163,184,.12);color:#cbd5e1;cursor:pointer">Continuar sin auditar</button>

    {{-- Completada --}}
    @elseif($completada)
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
            <div style="width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;border:5px solid {{ $scoreColor }};color:{{ $scoreColor }};flex-shrink:0">{{ $score }}</div>
            <div>
                <p style="font-weight:700;margin:0;color:#f1f5f9">Auditoría completada</p>
                <p style="font-size:.82rem;color:#94a3b8;margin:.15rem 0 0">{{ $numDone }} secciones revisadas contra la normativa vigente.</p>
            </div>
        </div>

        @foreach($secciones as $sec)
            @php
                $sScore = $sec['score'] ?? 0;
                $dot    = ($sec['calificacion'] ?? '') !== 'Error' && $sScore >= 80 ? '#22c55e' : ($sScore >= 50 ? '#f59e0b' : '#ef4444');
            @endphp
            <div style="display:flex;align-items:center;gap:.6rem;padding:.4rem 0;border-top:1px solid rgba(148,163,184,.14);font-size:.8125rem;color:#cbd5e1">
                <span style="width:9px;height:9px;border-radius:50%;background:{{ $dot }};flex-shrink:0"></span>
                <span>{{ $sec['titulo'] ?? '—' }}</span>
                <span style="margin-left:auto;font-weight:700;color:{{ $dot }}">{{ $sScore }}</span>
            </div>
        @endforeach

        @if($sugerencias->isNotEmpty())
            <p style="font-size:.78rem;font-weight:700;color:#fb7185;text-transform:uppercase;letter-spacing:.05em;margin:.9rem 0 .35rem">Sugerencias de mejora</p>
            @foreach($sugerencias as $s)
                <div style="display:flex;gap:.5rem;font-size:.8rem;color:#94a3b8;line-height:1.5;padding:.2rem 0"><span style="color:#22c55e;flex-shrink:0">→</span><span>{{ $s }}</span></div>
            @endforeach
        @endif

        <p style="font-size:.8rem;color:#cbd5e1;margin:1rem 0 0;padding-top:.75rem;border-top:1px dashed rgba(148,163,184,.2)">
            Revise el resultado y, debajo, indique si desea actualizar su RIT con las sugerencias o mantenerlo como está.
        </p>
    @endif
</div>
