{{--
    Tarjetas de Análisis IA + Recomendaciones para el modal "Emitir Sanción"
    Variables esperadas: $analisis (array), $recomendacion (array|null)
--}}
@php
    $gravedad       = $analisis['gravedad'] ?? 'leve';
    $esReincidencia = $analisis['es_reincidencia'] ?? false;
    $justificacion  = $analisis['justificacion'] ?? '';

    // Multi-recommendation: sanciones_sugeridas[] + sancion_principal
    $sancionPrincipal = $recomendacion['sancion_principal']
        ?? $recomendacion['sancion_sugerida']
        ?? $analisis['sancion_recomendada']
        ?? null;
    $sancionesValidas = $recomendacion['sanciones_sugeridas']
        ?? ($sancionPrincipal ? [$sancionPrincipal] : []);
    $diasSusp         = $recomendacion['dias_suspension'] ?? null;
    $mensaje          = $recomendacion['mensaje_para_decision'] ?? $analisis['consideraciones_especiales'] ?? '';

    // Asegurar que sancion_principal va primero
    if ($sancionPrincipal && ($pos = array_search($sancionPrincipal, $sancionesValidas)) !== false && $pos > 0) {
        array_splice($sancionesValidas, $pos, 1);
        array_unshift($sancionesValidas, $sancionPrincipal);
    }

    // ── Configuración visual por gravedad ─────────────────────────────────────
    $gc = match(true) {
        $gravedad === 'grave' => [
            'label'      => 'Falta Grave',
            'accent'     => '#fbbf24',
            'glow'       => 'rgba(251,191,36,0.10)',
            'border'     => 'rgba(251,191,36,0.35)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
        ],
        default => [
            'label'      => 'Falta Leve',
            'accent'     => '#4ade80',
            'glow'       => 'rgba(74,222,128,0.09)',
            'border'     => 'rgba(74,222,128,0.30)',
            'lord'       => 'fikcyfpp.json',
            'lordColors' => 'primary:#4ade80,secondary:#86efac',
        ],
    };

    // ── Configuración visual por tipo de sanción ──────────────────────────────
    $scMap = [
        'llamado_atencion' => [
            'label'      => 'Llamado de Atención',
            'accent'     => '#60a5fa',
            'glow'       => 'rgba(96,165,250,0.09)',
            'border'     => 'rgba(96,165,250,0.28)',
            'lord'       => 'jdgfsfzr.json',
            'lordColors' => 'primary:#60a5fa,secondary:#bfdbfe',
        ],
        'suspension' => [
            'label'      => 'Suspensión Laboral',
            'accent'     => '#fbbf24',
            'glow'       => 'rgba(251,191,36,0.09)',
            'border'     => 'rgba(251,191,36,0.28)',
            'lord'       => 'uphbloed.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
        ],
        'terminacion' => [
            'label'      => 'Terminación de Contrato',
            'accent'     => '#f87171',
            'glow'       => 'rgba(248,113,113,0.09)',
            'border'     => 'rgba(248,113,113,0.28)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#f87171,secondary:#fca5a5',
        ],
    ];
@endphp

<style>
:root {
    --esa-label:   rgba(0,0,0,0.45);
    --esa-sub:     rgba(55,65,81,0.65);
    --esa-text:    rgba(17,24,39,0.78);
    --esa-muted:   rgba(17,24,39,0.55);
    --esa-days:    rgba(55,65,81,0.55);
    --esa-divider: rgba(0,0,0,0.08);
    --esa-shimmer: rgba(0,0,0,0.05);
    --esa-border:  rgba(0,0,0,0.08);
    --esa-row-alt: rgba(0,0,0,0.025);
}
html.dark {
    --esa-label:   rgba(255,255,255,0.35);
    --esa-sub:     rgba(255,255,255,0.45);
    --esa-text:    rgba(255,255,255,0.60);
    --esa-muted:   rgba(255,255,255,0.50);
    --esa-days:    rgba(255,255,255,0.40);
    --esa-divider: rgba(255,255,255,0.07);
    --esa-shimmer: rgba(255,255,255,0.12);
    --esa-border:  rgba(255,255,255,0.07);
    --esa-row-alt: rgba(255,255,255,0.03);
}
.esa-card {
    border-radius: 14px;
    border: 1px solid var(--esa-border);
    overflow: hidden;
    position: relative;
}
.esa-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--esa-shimmer), transparent);
}
.esa-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--esa-label);
    margin: 0 0 6px;
}
.esa-divider {
    border: none;
    border-top: 1px solid var(--esa-divider);
    margin: 14px 0;
}
@keyframes esa-glow-pulse {
    0%, 100% { opacity: 0.6; }
    50%       { opacity: 1;   }
}
.esa-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block;
    animation: esa-glow-pulse 2.2s ease-in-out infinite;
    flex-shrink: 0;
}
.esa-badge-reincidencia {
    padding: 2px 10px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(239,68,68,0.12);
    color: #b91c1c;
    border: 1px solid rgba(239,68,68,0.35);
}
html.dark .esa-badge-reincidencia {
    background: rgba(239,68,68,0.18);
    color: #fca5a5;
    border: 1px solid rgba(248,113,113,0.35);
}
.esa-badge-principal {
    padding: 2px 9px;
    border-radius: 100px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .04em;
}
.esa-badge-valido {
    padding: 2px 9px;
    border-radius: 100px;
    font-size: 10px;
    font-weight: 600;
    background: var(--esa-row-alt);
    border: 1px solid var(--esa-border);
    color: var(--esa-sub);
}
</style>

<div class="space-y-2">

    {{-- ── Tarjeta 1: Gravedad de la falta ────────────────────────────── --}}
    <div class="esa-card"
         style="background: linear-gradient(135deg, {{ $gc['glow'] }} 0%, transparent 100%);
                border-left: 3px solid {{ $gc['accent'] }};">
        <div style="padding: 16px 18px;">

            <div style="display:flex; align-items:flex-start; gap:12px;">
                <lord-icon
                    src="https://cdn.lordicon.com/{{ $gc['lord'] }}"
                    trigger="loop" delay="900" stroke="bold"
                    colors="{{ $gc['lordColors'] }}"
                    style="width:40px;height:40px;flex-shrink:0;margin-top:-2px">
                </lord-icon>

                <div style="flex:1;min-width:0;">
                    <p class="esa-label">Análisis IA — Gravedad de la falta</p>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:17px;font-weight:800;color:{{ $gc['accent'] }};line-height:1.2;">
                            {{ $gc['label'] }}
                        </span>
                        @if($esReincidencia)
                            <span class="esa-badge-reincidencia">Reincidencia</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($justificacion)
                <hr class="esa-divider">
                <p style="font-size:13px;color:var(--esa-text);line-height:1.6;margin:0;">
                    {{ $justificacion }}
                </p>
            @endif
        </div>
    </div>

    {{-- ── Tarjeta 2: Recomendaciones (una por cada opción válida) ──────── --}}
    @if(!empty($sancionesValidas))
    <div class="esa-card">
        <div style="padding:14px 18px 4px;">
            <p class="esa-label">
                La IA recomienda
                @if(count($sancionesValidas) > 1)
                    — {{ count($sancionesValidas) }} opciones jurídicamente válidas
                @endif
            </p>
        </div>

        <div style="display:flex;flex-direction:column;gap:0;">
            @foreach($sancionesValidas as $s)
                @php
                    $sc = $scMap[$s] ?? [
                        'label'      => ucfirst(str_replace('_', ' ', $s)),
                        'accent'     => '#818cf8',
                        'glow'       => 'rgba(129,140,248,0.08)',
                        'border'     => 'rgba(129,140,248,0.28)',
                        'lord'       => 'edcgvlnw.json',
                        'lordColors' => 'primary:#818cf8,secondary:#c7d2fe',
                    ];
                    $esPrincipal = ($s === $sancionPrincipal);
                    $delay = 1000 + ($loop->index * 250);
                    $iconSize = $esPrincipal ? '32px' : '26px';
                @endphp

                <div style="display:flex;align-items:center;gap:12px;
                            padding:{{ $esPrincipal ? '12px' : '9px' }} 18px;
                            background:{{ $esPrincipal ? $sc['glow'] : 'transparent' }};
                            border-top:{{ $loop->first ? 'none' : '1px solid var(--esa-divider)' }};
                            border-left:3px solid {{ $esPrincipal ? $sc['accent'] : 'transparent' }};">

                    <lord-icon
                        src="https://cdn.lordicon.com/{{ $sc['lord'] }}"
                        trigger="loop" delay="{{ $delay }}" stroke="bold"
                        colors="{{ $sc['lordColors'] }}"
                        style="width:{{ $iconSize }};height:{{ $iconSize }};flex-shrink:0;">
                    </lord-icon>

                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
                            <span style="font-size:{{ $esPrincipal ? '15px' : '13px' }};
                                         font-weight:{{ $esPrincipal ? '800' : '600' }};
                                         color:{{ $sc['accent'] }};
                                         line-height:1.2;">
                                {{ $sc['label'] }}
                                @if($s === 'suspension' && $diasSusp && $esPrincipal)
                                    <span style="font-size:12px;font-weight:400;color:var(--esa-days);">
                                        &nbsp;·&nbsp;{{ $diasSusp }} día{{ $diasSusp > 1 ? 's' : '' }}
                                    </span>
                                @endif
                            </span>

                            @if($esPrincipal)
                                <span class="esa-badge-principal"
                                      style="background:{{ $sc['glow'] }};
                                             border:1px solid {{ $sc['border'] }};
                                             color:{{ $sc['accent'] }};">
                                    ★ Principal
                                </span>
                            @else
                                <span class="esa-badge-valido">✓ También válido</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($mensaje)
            <div style="padding:0 18px 14px;">
                <hr class="esa-divider" style="margin-top:4px;">
                <p style="font-size:12px;color:var(--esa-muted);line-height:1.6;margin:0;">
                    {{ $mensaje }}
                </p>
            </div>
        @endif
    </div>
    @endif

</div>
