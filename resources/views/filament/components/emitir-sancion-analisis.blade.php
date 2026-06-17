{{--
    Tarjetas de Análisis IA + Recomendaciones para el modal "Emitir Sanción"
    Variables esperadas: $analisis (array), $recomendacion (array|null)
--}}
@php
    $analisis       = $analisis ?? [];
    $recomendacion  = $recomendacion ?? [];
    $gravedad       = $analisis['gravedad'] ?? 'leve';
    $esReincidencia = $analisis['es_reincidencia'] ?? false;
    $justificacion  = $analisis['justificacion'] ?? '';

    // Fase 1 — garantías y fuero
    $alertaFuero   = is_array($analisis['alerta_fuero'] ?? null) ? $analisis['alerta_fuero'] : [];
    $reqVerifFuero = (bool) ($alertaFuero['requiere_verificacion'] ?? false);
    $garantias     = is_array($analisis['verificacion_garantias'] ?? null) ? $analisis['verificacion_garantias'] : [];
    $noSancionTxt  = $analisis['razones_no_recomendadas']['no_sancion'] ?? '';
    $estadoRec     = $recomendacion['estado_recomendacion'] ?? ($analisis['recomendacion_final']['estado_recomendacion'] ?? null);
    $esCondicional = in_array($estadoRec, ['condicionada', 'esperar_pruebas'], true);
    $analisisPruebas = trim((string) ($analisis['analisis_pruebas'] ?? ''));

    // Multi-recommendation: sanciones_sugeridas[] + sancion_principal
    $sancionPrincipal = $recomendacion['sancion_principal']
        ?? $recomendacion['sancion_sugerida']
        ?? $analisis['sancion_recomendada']
        ?? null;
    $sancionesValidas = $recomendacion['sanciones_sugeridas']
        ?? ($sancionPrincipal ? [$sancionPrincipal] : []);
    $diasSusp         = $recomendacion['dias_suspension'] ?? null;
    $mensaje          = $recomendacion['mensaje_para_decision'] ?? $analisis['consideraciones_especiales'] ?? '';
    $basesJuridicas   = $recomendacion['bases_juridicas'] ?? [];

    // Asegurar que sancion_principal va primero
    if ($sancionPrincipal && ($pos = array_search($sancionPrincipal, $sancionesValidas)) !== false && $pos > 0) {
        array_splice($sancionesValidas, $pos, 1);
        array_unshift($sancionesValidas, $sancionPrincipal);
    }

    // ── Configuración visual por gravedad ─────────────────────────────────────
    $gc = match(true) {
        $gravedad === 'muy_grave' => [
            'label'      => 'Falta Muy Grave',
            'accent'     => '#f87171',
            'accentLight'=> '#b91c1c',
            'glow'       => 'rgba(248,113,113,0.10)',
            'border'     => 'rgba(248,113,113,0.35)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#f87171,secondary:#fca5a5',
        ],
        $gravedad === 'grave' => [
            'label'      => 'Falta Grave',
            'accent'     => '#fbbf24',
            'accentLight'=> '#b45309',
            'glow'       => 'rgba(251,191,36,0.10)',
            'border'     => 'rgba(251,191,36,0.35)',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
        ],
        default => [
            'label'      => 'Falta Leve',
            'accent'     => '#4ade80',
            'accentLight'=> '#15803d',
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
            'accentLight'=> '#1d4ed8',
            'glow'       => 'rgba(96,165,250,0.09)',
            'border'     => 'rgba(96,165,250,0.28)',
            'lord'       => 'jdgfsfzr.json',
            'lordColors' => 'primary:#60a5fa,secondary:#bfdbfe',
        ],
        'suspension' => [
            'label'      => 'Suspensión Laboral',
            'accent'     => '#fbbf24',
            'accentLight'=> '#b45309',
            'glow'       => 'rgba(251,191,36,0.09)',
            'border'     => 'rgba(251,191,36,0.28)',
            'lord'       => 'uphbloed.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
        ],
        'multa' => [
            'label'      => 'Multa',
            'accent'     => '#a78bfa',
            'accentLight'=> '#6d28d9',
            'glow'       => 'rgba(167,139,250,0.09)',
            'border'     => 'rgba(167,139,250,0.28)',
            'lord'       => 'lupugrca.json',
            'lordColors' => 'primary:#a78bfa,secondary:#ddd6fe',
        ],
        'terminacion' => [
            'label'      => 'Terminación de Contrato',
            'accent'     => '#f87171',
            'accentLight'=> '#b91c1c',
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
.esa-base-juridica summary::-webkit-details-marker { display: none; }
.esa-base-juridica[open] .esa-chevron { transform: rotate(90deg); }
/* Texto con acento: tono oscuro/saturado en modo claro, pastel en modo oscuro.
   Cada elemento define --a-light y --a-dark inline según su sanción/gravedad. */
.esa-accent-text { color: var(--a-light); }
html.dark .esa-accent-text { color: var(--a-dark); }
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
                        <span class="esa-accent-text"
                              style="--a-light:{{ $gc['accentLight'] }};--a-dark:{{ $gc['accent'] }};
                                     font-size:17px;font-weight:800;line-height:1.2;">
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

    {{-- ── Alerta de fuero / estabilidad reforzada (Fase 1) ────────────── --}}
    @if($reqVerifFuero)
    <div class="esa-card"
         style="background: linear-gradient(135deg, rgba(251,146,60,0.13) 0%, transparent 100%);
                border-left: 3px solid #fb923c;">
        <div style="padding:14px 18px;">
            <div style="display:flex;align-items:flex-start;gap:11px;">
                <lord-icon
                    src="https://cdn.lordicon.com/lltgvngb.json"
                    trigger="loop"
                    delay="500"
                    colors="primary:#ea7317,secondary:#fb923c"
                    style="width:40px;height:40px;flex-shrink:0;margin-top:-3px;">
                </lord-icon>
                <div style="flex:1;min-width:0;">
                    <p class="esa-label" style="color:#b45309;">Verificar fuero / estabilidad laboral reforzada</p>
                    @if(!empty($alertaFuero['indicios']))
                        <p style="font-size:12.5px;color:var(--esa-text);line-height:1.6;margin:0 0 5px;">
                            <strong>Indicios:</strong> {{ $alertaFuero['indicios'] }}
                        </p>
                    @endif
                    @if(!empty($alertaFuero['recomendacion']))
                        <p style="font-size:12.5px;color:var(--esa-text);line-height:1.6;margin:0;">
                            {{ $alertaFuero['recomendacion'] }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Análisis de pruebas aportadas por el trabajador (multimodal) ──── --}}
    @if($analisisPruebas !== '')
    <div class="esa-card"
         style="background: linear-gradient(135deg, rgba(96,165,250,0.10) 0%, transparent 100%);
                border-left: 3px solid #60a5fa;">
        <div style="padding:14px 18px;">
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     style="width:22px;height:22px;color:#2563eb;flex-shrink:0;margin-top:1px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <div style="flex:1;min-width:0;">
                    <p class="esa-label" style="color:#1d4ed8;">Análisis de las pruebas del trabajador (lectura IA)</p>
                    <p style="font-size:12.5px;color:var(--esa-text);line-height:1.6;margin:0 0 6px;white-space:pre-line;">{{ $analisisPruebas }}</p>
                    <p style="font-size:11.5px;color:var(--esa-muted);line-height:1.5;margin:0;font-style:italic;">
                        La IA lee y resume las pruebas, pero no verifica su autenticidad: confirme con la fuente (EPS, tránsito, aseguradora). La decisión es del funcionario.
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Aviso condicional: el rango aplica solo si se verifican las pruebas ── --}}
    @if($esCondicional && !empty($sancionesValidas))
    <div class="esa-card"
         style="background: linear-gradient(135deg, rgba(251,146,60,0.13) 0%, transparent 100%);
                border-left: 3px solid #fb923c;">
        <div style="padding:13px 18px;">
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     style="width:22px;height:22px;color:#ea7317;flex-shrink:0;margin-top:1px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <div style="flex:1;min-width:0;">
                    <p class="esa-label" style="color:#b45309;">Opciones sujetas a verificación</p>
                    <p style="font-size:12.5px;color:var(--esa-text);line-height:1.6;margin:0 0 7px;">
                        {{ $mensaje ?: 'Las opciones de abajo aplican solo si, tras verificar las pruebas del trabajador, la falta se mantiene. Si la justificación se confirma, no procede sanción.' }}
                    </p>
                    {{-- <p style="font-size:12px;line-height:1.55;margin:0;padding:7px 10px;border-radius:8px;
                              background:rgba(74,222,128,0.10);border:1px solid rgba(74,222,128,0.30);color:var(--esa-text);">
                        <strong style="color:#15803d;">Cómo proceder:</strong>
                        verifique primero las pruebas. Si la justificación es válida, en
                        <strong>“Decisión de Sanción”</strong> marque
                        <strong style="color:#15803d;">“No Aplicar Sanción”</strong>.
                        Si la falta se mantiene, elija una de las opciones de arriba.
                    </p> --}}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Tarjeta 2: Recomendaciones (una por cada opción válida) ──────── --}}
    @if(!empty($sancionesValidas))
    <div class="esa-card">
        <div style="padding:14px 18px 4px;">
            <p class="esa-label">
                {{ $esCondicional ? 'Opciones si la falta se confirma' : 'La IA recomienda' }}
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
                        'accentLight'=> '#4338ca',
                        'glow'       => 'rgba(129,140,248,0.08)',
                        'border'     => 'rgba(129,140,248,0.28)',
                        'lord'       => 'edcgvlnw.json',
                        'lordColors' => 'primary:#818cf8,secondary:#c7d2fe',
                    ];
                    $esPrincipal  = ($s === $sancionPrincipal);
                    $delay        = 1000 + ($loop->index * 250);
                    $iconSize     = $esPrincipal ? '32px' : '26px';
                    $baseJuridica = $basesJuridicas[$s] ?? null;
                @endphp

                {{-- Contenedor de cada sanción: encabezado + desplegable base jurídica --}}
                <div style="border-top:{{ $loop->first ? 'none' : '1px solid var(--esa-divider)' }};
                            background:{{ $esPrincipal ? $sc['glow'] : 'transparent' }};
                            border-left:3px solid {{ $esPrincipal ? $sc['accent'] : 'transparent' }};">

                    {{-- Fila principal --}}
                    <div style="display:flex;align-items:center;gap:12px;
                                padding:{{ $esPrincipal ? '12px' : '9px' }} 18px;">

                        <lord-icon
                            src="https://cdn.lordicon.com/{{ $sc['lord'] }}"
                            trigger="loop" delay="{{ $delay }}" stroke="bold"
                            colors="{{ $sc['lordColors'] }}"
                            style="width:{{ $iconSize }};height:{{ $iconSize }};flex-shrink:0;">
                        </lord-icon>

                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
                                <span class="esa-accent-text"
                                      style="--a-light:{{ $sc['accentLight'] }};--a-dark:{{ $sc['accent'] }};
                                             font-size:{{ $esPrincipal ? '15px' : '13px' }};
                                             font-weight:{{ $esPrincipal ? '800' : '600' }};
                                             line-height:1.2;">
                                    {{ $sc['label'] }}
                                    @if($s === 'suspension' && $diasSusp && $esPrincipal)
                                        <span style="font-size:12px;font-weight:400;color:var(--esa-days);">
                                            &nbsp;·&nbsp;{{ $diasSusp }} día{{ $diasSusp > 1 ? 's' : '' }}
                                        </span>
                                    @endif
                                </span>

                                @if($esPrincipal)
                                    <span class="esa-badge-principal esa-accent-text"
                                          style="--a-light:{{ $sc['accentLight'] }};--a-dark:{{ $sc['accent'] }};
                                                 background:{{ $sc['glow'] }};
                                                 border:1px solid {{ $sc['border'] }};">
                                        ★ Principal
                                    </span>
                                @else
                                    <span class="esa-badge-valido">✓ También válido</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Desplegable: base jurídica argumentada --}}
                    @if($baseJuridica)
                        <details class="esa-base-juridica"
                                 style="margin:0 18px 10px; padding:0;">
                            <summary class="esa-accent-text"
                                     style="--a-light:{{ $sc['accentLight'] }};--a-dark:{{ $sc['accent'] }};
                                           font-size:11px;font-weight:700;
                                           list-style:none;display:flex;align-items:center;
                                           gap:5px;cursor:pointer;user-select:none;
                                           opacity:0.85;width:fit-content;">
                                <svg class="esa-chevron" xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 20 20" fill="currentColor"
                                     style="width:12px;height:12px;transition:transform .2s;flex-shrink:0;">
                                    <path fill-rule="evenodd"
                                          d="M7.293 4.707a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 10 7.293 6.707a1 1 0 010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                                Base jurídica argumentada
                            </summary>
                            <div style="margin-top:6px;padding:10px 12px;border-radius:8px;
                                        background:rgba(0,0,0,0.04);border:1px solid {{ $sc['border'] }};">
                                <p style="font-size:12.5px;color:var(--esa-text);
                                          line-height:1.65;margin:0;">
                                    {{ $baseJuridica }}
                                </p>
                            </div>
                        </details>
                    @endif

                </div>
            @endforeach
        </div>

        @if($mensaje && !$esCondicional)
            <div style="padding:0 18px 14px;">
                <hr class="esa-divider" style="margin-top:4px;">
                <p style="font-size:12px;color:var(--esa-muted);line-height:1.6;margin:0;">
                    {{ $mensaje }}
                </p>
            </div>
        @endif
    </div>
    @endif

    {{-- ── No procede sanción (exoneración clara, sin opciones) ──────────── --}}
    @if(empty($sancionesValidas))
    <div class="esa-card"
         style="background: linear-gradient(135deg, rgba(74,222,128,0.11) 0%, transparent 100%);
                border-left: 3px solid #4ade80;">
        <div style="padding:16px 18px;">
            <div style="display:flex;align-items:flex-start;gap:11px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     style="width:26px;height:26px;color:#16a34a;flex-shrink:0;margin-top:1px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div style="flex:1;min-width:0;">
                    <p class="esa-label" style="color:#15803d;">La IA no recomienda sanción</p>
                    @if($mensaje)
                        <p style="font-size:13px;color:var(--esa-text);line-height:1.6;margin:0 0 6px;">
                            {{ $mensaje }}
                        </p>
                    @endif
                    @if($noSancionTxt)
                        <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:0;">
                            {{ $noSancionTxt }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Verificación de garantías (debido proceso) — Fase 1 ──────────── --}}
    @if(!empty($garantias))
    @php
        $gEtiquetas = ['tipicidad' => '¿Es una falta del reglamento?', 'debido_proceso' => '¿Se respetó el debido proceso?', 'inmediatez' => '¿Se actuó a tiempo?', 'non_bis_in_idem' => '¿Ya fue sancionado por lo mismo?', 'proporcionalidad' => '¿La sanción es proporcional?', 'suficiencia_probatoria' => '¿Hay pruebas suficientes?'];
        // Solo se muestran las garantías que NO se cumplen (riesgo / por verificar).
        $gRiesgos = collect($gEtiquetas)->filter(fn($lbl, $k) => isset($garantias[$k]) && ($garantias[$k]['estado'] ?? '') !== 'cumple');
    @endphp
    <div class="esa-card">
        <div style="padding:14px 18px;">
            <p class="esa-label">¿La sanción se sostiene? — puntos a revisar</p>
            @if($gRiesgos->isEmpty())
                <div style="display:flex;align-items:center;gap:9px;margin-top:9px;">
                    <span class="esa-accent-text"
                          style="--a-light:#15803d;--a-dark:#4ade80;flex-shrink:0;font-size:10px;font-weight:700;
                                 padding:2px 8px;border-radius:100px;background:rgba(74,222,128,0.12);
                                 border:1px solid rgba(74,222,128,0.30);">OK</span>
                    <span style="font-size:12.5px;color:var(--esa-text);">Todas las garantías se cumplen; sin puntos de riesgo.</span>
                </div>
            @else
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:9px;">
                @foreach($gRiesgos as $gk => $glabel)
                    @php
                        $g      = $garantias[$gk];
                        $estado = $g['estado'] ?? 'no_determinable';
                        $nota   = $g['nota'] ?? '';
                        $cfg = match($estado) {
                            'riesgo'    => ['#b45309', '#fbbf24', 'rgba(251,191,36,0.13)', 'rgba(251,191,36,0.32)', 'Riesgo'],
                            'no_cumple' => ['#b91c1c', '#f87171', 'rgba(248,113,113,0.13)', 'rgba(248,113,113,0.32)', 'No cumple'],
                            default     => ['#6b7280', '#9ca3af', 'rgba(120,120,120,0.10)', 'var(--esa-border)', 'Por verificar'],
                        };
                    @endphp
                    <div style="display:flex;align-items:flex-start;gap:9px;">
                        <span class="esa-accent-text"
                              style="--a-light:{{ $cfg[0] }};--a-dark:{{ $cfg[1] }};
                                     flex-shrink:0;font-size:10px;font-weight:700;
                                     padding:2px 8px;border-radius:100px;
                                     background:{{ $cfg[2] }};border:1px solid {{ $cfg[3] }};
                                     min-width:80px;text-align:center;">
                            {{ $cfg[4] }}
                        </span>
                        <div style="flex:1;min-width:0;">
                            <span style="font-size:12.5px;font-weight:600;color:var(--esa-text);">{{ $glabel }}</span>
                            @if($nota)
                                <span style="font-size:12px;color:var(--esa-muted);"> — {{ $nota }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
