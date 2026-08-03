<style>
    .cgi-card {
        background: rgba(56,189,248,.06);
        border: 1px solid rgba(56,189,248,.18);
        border-radius: .75rem;
        padding: .875rem 1rem;
        margin-top: .25rem;
    }
    .cgi-title {
        margin: 0 0 .625rem;
        font-size: .8125rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #38bdf8;
    }
    .cgi-row {
        display: flex;
        align-items: flex-start;
        gap: .45rem;
        font-size: .875rem;
        font-weight: 500;
        line-height: 1.6;
        padding: .35rem 0;
        color: #cbd5e1;
        border-bottom: 1px solid rgba(56,189,248,.1);
    }
    .cgi-row:last-child { border-bottom: none; }
    .cgi-ico { width: 15px; height: 15px; flex-shrink: 0; margin-top: 2px; }

    .cgi-top { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    .cgi-badge {
        display: inline-block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        border-radius: .3rem;
        padding: .15rem .5rem;
    }
    .cgi-badge-gravedad {
        background: rgba(56,189,248,.2);
        color: #38bdf8;
        border: 1px solid rgba(56,189,248,.35);
    }
    .cgi-badge-gravedad.cgi-leve      { background: rgba(134,239,172,.2); color: #86efac; border-color: rgba(134,239,172,.35); }
    .cgi-badge-gravedad.cgi-grave     { background: rgba(253,230,138,.2); color: #fde68a; border-color: rgba(253,230,138,.35); }
    .cgi-badge-gravedad.cgi-gravisima { background: rgba(252,165,165,.2); color: #fca5a5; border-color: rgba(252,165,165,.35); }
    .cgi-badge-certeza {
        background: rgba(148,163,184,.15);
        color: #cbd5e1;
        border: 1px solid rgba(148,163,184,.3);
    }
    .cgi-justificacion { color: #cbd5e1; font-size: .875rem; font-weight: 500; line-height: 1.6; margin: .5rem 0 0; }
    .cgi-factores-titulo { font-weight: 700; font-size: .8125rem; margin: .75rem 0 .3rem; color: #e2e8f0; }

    /* Estado: información insuficiente (amarillo) */
    .cgi-card.cgi-warn { background: rgba(253,230,138,.06); border-color: rgba(253,230,138,.2); }
    .cgi-card.cgi-warn .cgi-title { color: #fde68a; }
    .cgi-card.cgi-warn .cgi-row { color: #fde68a; border-bottom-color: rgba(253,230,138,.12); }

    /* Estado: error / no se pudo clasificar (rojo) */
    .cgi-card.cgi-error { background: rgba(252,165,165,.06); border-color: rgba(252,165,165,.2); }
    .cgi-card.cgi-error .cgi-title { color: #fca5a5; }
    .cgi-card.cgi-error .cgi-row { color: #fca5a5; border-bottom-color: rgba(252,165,165,.12); }

    html:not(.dark) .cgi-card              { background: rgba(14,165,233,.04); border-color: rgba(14,165,233,.15); }
    html:not(.dark) .cgi-title             { color: #0284c7; }
    html:not(.dark) .cgi-row               { color: #1f2937; border-bottom-color: rgba(14,165,233,.08); }
    html:not(.dark) .cgi-badge-gravedad    { background: rgba(14,165,233,.1); color: #0284c7; border-color: rgba(14,165,233,.3); }
    html:not(.dark) .cgi-badge-gravedad.cgi-leve      { background: rgba(21,128,61,.1); color: #15803d; border-color: rgba(21,128,61,.3); }
    html:not(.dark) .cgi-badge-gravedad.cgi-grave     { background: rgba(180,83,9,.1); color: #b45309; border-color: rgba(180,83,9,.3); }
    html:not(.dark) .cgi-badge-gravedad.cgi-gravisima { background: rgba(220,38,38,.1); color: #dc2626; border-color: rgba(220,38,38,.3); }
    html:not(.dark) .cgi-badge-certeza     { background: rgba(107,114,128,.1); color: #374151; border-color: rgba(107,114,128,.3); }
    html:not(.dark) .cgi-justificacion     { color: #374151; }
    html:not(.dark) .cgi-factores-titulo   { color: #111827; }
    html:not(.dark) .cgi-card.cgi-warn      { background: rgba(180,83,9,.05); border-color: rgba(180,83,9,.2); }
    html:not(.dark) .cgi-card.cgi-warn .cgi-title { color: #b45309; }
    html:not(.dark) .cgi-card.cgi-warn .cgi-row   { color: #b45309; border-bottom-color: rgba(180,83,9,.1); }
    html:not(.dark) .cgi-card.cgi-error      { background: rgba(220,38,38,.05); border-color: rgba(220,38,38,.2); }
    html:not(.dark) .cgi-card.cgi-error .cgi-title { color: #dc2626; }
    html:not(.dark) .cgi-card.cgi-error .cgi-row   { color: #dc2626; border-bottom-color: rgba(220,38,38,.1); }

    .cgi-rit-box {
        margin-top: .75rem;
        border: 1px solid rgba(253,230,138,.25);
        border-radius: .5rem;
        overflow: hidden;
    }
    .cgi-rit-titulo {
        padding: .5rem .75rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #fde68a;
        background: rgba(253,230,138,.08);
        border-bottom: 1px solid rgba(253,230,138,.2);
    }
    .cgi-rit-texto {
        max-height: 220px;
        overflow-y: auto;
        padding: .75rem;
        font-size: .8125rem;
        line-height: 1.6;
        color: #cbd5e1;
        white-space: pre-line;
    }
    html:not(.dark) .cgi-rit-box     { border-color: rgba(180,83,9,.2); }
    html:not(.dark) .cgi-rit-titulo  { color: #b45309; background: rgba(180,83,9,.05); border-bottom-color: rgba(180,83,9,.15); }
    html:not(.dark) .cgi-rit-texto   { color: #374151; }

    .cgi-hechos-box {
        margin-top: .75rem;
        border: 1px solid rgba(253,230,138,.25);
        border-radius: .5rem;
        overflow: hidden;
    }
    .cgi-hechos-titulo {
        padding: .5rem .75rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #fde68a;
        background: rgba(253,230,138,.08);
        border-bottom: 1px solid rgba(253,230,138,.2);
    }
    .cgi-hechos-texto {
        padding: .75rem;
        font-size: .8125rem;
        line-height: 1.6;
        color: #cbd5e1;
        white-space: pre-line;
    }
    .cgi-highlight {
        background: rgba(253,230,138,.35);
        color: #fde68a;
        border-radius: .2rem;
        padding: 0 .2rem;
        font-weight: 700;
    }
    html:not(.dark) .cgi-hechos-box    { border-color: rgba(180,83,9,.2); }
    html:not(.dark) .cgi-hechos-titulo { color: #b45309; background: rgba(180,83,9,.05); border-bottom-color: rgba(180,83,9,.15); }
    html:not(.dark) .cgi-hechos-texto  { color: #374151; }
    html:not(.dark) .cgi-highlight     { background: rgba(180,83,9,.18); color: #92400e; }
</style>

@php
    $informacionSuficiente = $clasificacion['informacion_suficiente'] ?? null;
    $capituloRit = $capituloRit ?? null;
    $hechosResaltado = $hechosResaltado ?? null;
@endphp

@if($informacionSuficiente === false)
    <div class="cgi-card cgi-warn">
        <p class="cgi-title">Información insuficiente para clasificar la gravedad</p>

        @if(!empty($hechosResaltado))
            <div class="cgi-hechos-box">
                <p class="cgi-hechos-titulo">Lo que escribió (resaltado lo que genera la duda)</p>
                <div class="cgi-hechos-texto">{!! $hechosResaltado !!}</div>
            </div>
        @endif

        @foreach(($clasificacion['elementos_faltantes'] ?? []) as $elemento)
            <div class="cgi-row">
                <svg class="cgi-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <span>{{ $elemento }}</span>
            </div>
        @endforeach

        @if(!empty($capituloRit))
            <div class="cgi-rit-box">
                <p class="cgi-rit-titulo">Capítulo del RIT: régimen disciplinario (referencia)</p>
                <div class="cgi-rit-texto">{{ $capituloRit }}</div>
            </div>
        @endif
    </div>
@elseif($informacionSuficiente === null)
    <div class="cgi-card cgi-error">
        <p class="cgi-title">No se pudo clasificar</p>
        <div class="cgi-row">
            <svg class="cgi-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span>{{ $clasificacion['error'] ?? 'El análisis automático no estuvo disponible.' }}</span>
        </div>
    </div>
@else
    @php
        $gravedad = strtolower($clasificacion['gravedad_estimada'] ?? '');
        $gravedadClase = in_array($gravedad, ['leve', 'grave', 'gravisima']) ? "cgi-{$gravedad}" : '';
        $factores = $clasificacion['factores_riesgo'] ?? [];
    @endphp
    <div class="cgi-card">
        <p class="cgi-title">Clasificación de gravedad</p>
        <div class="cgi-top">
            <span class="cgi-badge cgi-badge-gravedad {{ $gravedadClase }}">{{ strtoupper($clasificacion['gravedad_estimada'] ?? '') }}</span>
            <span class="cgi-badge cgi-badge-certeza">Certeza: {{ $clasificacion['certeza'] ?? '' }}</span>
        </div>
        <div class="cgi-row">
            <svg class="cgi-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Nivel de interrogatorio mínimo: {{ $clasificacion['nivel_interrogatorio_minimo'] ?? '' }}</span>
        </div>
        @if(!empty($clasificacion['justificacion']))
            <p class="cgi-justificacion">{{ $clasificacion['justificacion'] }}</p>
        @endif
        <p class="cgi-factores-titulo">Factores de riesgo</p>
        @forelse($factores as $factor)
            <div class="cgi-row">
                <svg class="cgi-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span>{{ $factor }}</span>
            </div>
        @empty
            <div class="cgi-row">
                <svg class="cgi-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Ninguno identificado</span>
            </div>
        @endforelse
    </div>
@endif
