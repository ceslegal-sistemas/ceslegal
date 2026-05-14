{{--
    Tarjetas de Análisis IA + Recomendación para el modal "Emitir Sanción"
    Variables esperadas: $analisis (array), $recomendacion (array|null)
--}}
@php
    $gravedad       = $analisis['gravedad'] ?? 'leve';
    $nivel          = $analisis['nivel_gravedad'] ?? null;
    $esReincidencia = $analisis['es_reincidencia'] ?? false;
    $justificacion  = $analisis['justificacion'] ?? '';

    $sancion    = $recomendacion['sancion_sugerida'] ?? $analisis['sancion_recomendada'] ?? null;
    $diasSusp   = $recomendacion['dias_suspension'] ?? null;
    $mensaje    = $recomendacion['mensaje_para_decision'] ?? $analisis['consideraciones_especiales'] ?? '';

    // Configuración visual por gravedad
    $gc = match(true) {
        $gravedad === 'grave' && $nivel === 'alto' => [
            'label'       => 'Falta Grave',
            'sub'         => 'Nivel Alto',
            'accent'      => '#f87171',
            'glow'        => 'rgba(248,113,113,0.12)',
            'border'      => 'rgba(248,113,113,0.35)',
            'badge'       => 'background:rgba(239,68,68,0.18);color:#fca5a5;border:1px solid rgba(248,113,113,0.35)',
            'lord'        => 'hmpomorl.json',
            'lordColors'  => 'primary:#f87171,secondary:#fca5a5',
        ],
        $gravedad === 'grave' => [
            'label'       => 'Falta Grave',
            'sub'         => null,
            'accent'      => '#fbbf24',
            'glow'        => 'rgba(251,191,36,0.10)',
            'border'      => 'rgba(251,191,36,0.35)',
            'badge'       => 'background:rgba(245,158,11,0.18);color:#fde68a;border:1px solid rgba(251,191,36,0.35)',
            'lord'        => 'hmpomorl.json',
            'lordColors'  => 'primary:#fbbf24,secondary:#fde68a',
        ],
        default => [
            'label'       => 'Falta Leve',
            'sub'         => null,
            'accent'      => '#4ade80',
            'glow'        => 'rgba(74,222,128,0.09)',
            'border'      => 'rgba(74,222,128,0.30)',
            'badge'       => 'background:rgba(34,197,94,0.15);color:#86efac;border:1px solid rgba(74,222,128,0.30)',
            'lord'        => 'fikcyfpp.json',
            'lordColors'  => 'primary:#4ade80,secondary:#86efac',
        ],
    };

    // Configuración visual por sanción recomendada
    $sc = match($sancion) {
        'llamado_atencion' => [
            'label'      => 'Llamado de Atención',
            'accent'     => '#60a5fa',
            'glow'       => 'rgba(96,165,250,0.08)',
            'lord'       => 'jdgfsfzr.json',
            'lordColors' => 'primary:#60a5fa,secondary:#bfdbfe',
        ],
        'suspension' => [
            'label'      => 'Suspensión Laboral',
            'accent'     => '#fbbf24',
            'glow'       => 'rgba(251,191,36,0.08)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
        ],
        'terminacion' => [
            'label'      => 'Terminación de Contrato',
            'accent'     => '#f87171',
            'glow'       => 'rgba(248,113,113,0.08)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#f87171,secondary:#fca5a5',
        ],
        default => [
            'label'      => 'Pendiente de análisis',
            'accent'     => '#818cf8',
            'glow'       => 'rgba(129,140,248,0.08)',
            'lord'       => 'edcgvlnw.json',
            'lordColors' => 'primary:#818cf8,secondary:#c7d2fe',
        ],
    };
@endphp

<style>
.esa-card {
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.07);
    overflow: hidden;
    position: relative;
}
.esa-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
}
.esa-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255,255,255,0.35);
    margin: 0 0 6px;
}
.esa-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.07);
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
</style>

<div class="space-y-2">

    {{-- ── Tarjeta: Análisis ────────────────────────────────────────── --}}
    <div class="esa-card"
         style="background: linear-gradient(135deg, {{ $gc['glow'] }} 0%, rgba(255,255,255,0.02) 100%);
                border-left: 3px solid {{ $gc['accent'] }};">
        <div style="padding: 16px 18px;">

            {{-- Header: icono + título gravedad --}}
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
                        @if($gc['sub'])
                            <span style="font-size:12px;color:rgba(255,255,255,0.45);font-weight:500;">
                                {{ $gc['sub'] }}
                            </span>
                        @endif
                        @if($esReincidencia)
                            <span style="padding:2px 10px;border-radius:100px;font-size:11px;font-weight:700;
                                         background:rgba(239,68,68,0.18);color:#fca5a5;
                                         border:1px solid rgba(248,113,113,0.35);">
                                Reincidencia
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Justificación --}}
            @if($justificacion)
                <hr class="esa-divider">
                <p style="font-size:13px;color:rgba(255,255,255,0.60);line-height:1.6;margin:0;">
                    {{ $justificacion }}
                </p>
            @endif
        </div>
    </div>

    {{-- ── Tarjeta: Recomendación ───────────────────────────────────── --}}
    @if($sancion)
    <div class="esa-card"
         style="background: linear-gradient(135deg, {{ $sc['glow'] }} 0%, rgba(255,255,255,0.015) 100%);
                border-left: 3px solid {{ $sc['accent'] }};">
        <div style="padding: 16px 18px;">

            <p class="esa-label">La IA recomienda</p>

            <div style="display:flex;align-items:center;gap:12px;">
                <lord-icon
                    src="https://cdn.lordicon.com/{{ $sc['lord'] }}"
                    trigger="loop" delay="1100" stroke="bold"
                    colors="{{ $sc['lordColors'] }}"
                    style="width:36px;height:36px;flex-shrink:0">
                </lord-icon>

                <div>
                    <p style="font-size:16px;font-weight:800;color:{{ $sc['accent'] }};margin:0;line-height:1.2;">
                        {{ $sc['label'] }}
                        @if($sancion === 'suspension' && $diasSusp)
                            <span style="font-size:13px;color:rgba(255,255,255,0.40);font-weight:400;">
                                &nbsp;·&nbsp;{{ $diasSusp }} día{{ $diasSusp > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            @if($mensaje)
                <hr class="esa-divider">
                <p style="font-size:12px;color:rgba(255,255,255,0.50);line-height:1.6;margin:0;">
                    {{ $mensaje }}
                </p>
            @endif
        </div>
    </div>
    @endif

</div>
