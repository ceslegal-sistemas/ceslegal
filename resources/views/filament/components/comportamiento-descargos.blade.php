{{--
    Sección de Señales de Comportamiento — Capa 2 + Capa 3 (Autenticidad IA)
    Variable esperada: $diligencia (DiligenciaDescargo|null)
--}}

<style>
/* ── Variables ─────────────────────────────────────────────────────────── */
.cb-wrap {
    --cb-label:   rgba(255,255,255,0.35);
    --cb-text:    rgba(255,255,255,0.65);
    --cb-muted:   rgba(255,255,255,0.40);
    --cb-border:  rgba(255,255,255,0.08);
    --cb-surface: rgba(255,255,255,0.04);
    --cb-row-alt: rgba(255,255,255,0.03);
    --cb-th-bg:   rgba(255,255,255,0.05);
}
html:not(.dark) .cb-wrap {
    --cb-label:   rgba(0,0,0,0.42);
    --cb-text:    rgba(17,24,39,0.72);
    --cb-muted:   rgba(55,65,81,0.60);
    --cb-border:  rgba(0,0,0,0.09);
    --cb-surface: rgba(0,0,0,0.025);
    --cb-row-alt: rgba(0,0,0,0.018);
    --cb-th-bg:   rgba(0,0,0,0.04);
}

/* ── Shared card ────────────────────────────────────────────────────────── */
.cb-card {
    border-radius: 12px;
    border: 1px solid var(--cb-border);
    overflow: hidden;
}

/* ── Label uppercase ────────────────────────────────────────────────────── */
.cb-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: var(--cb-label);
    margin: 0 0 4px;
}

/* ── Alert level badge ──────────────────────────────────────────────────── */
.cb-nivel-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px 5px 8px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 700;
}
.cb-nivel-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Metric box ─────────────────────────────────────────────────────────── */
.cb-metric {
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--cb-surface);
    border: 1px solid var(--cb-border);
}
.cb-metric-val {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
    margin: 2px 0 0;
}

/* ── Table ──────────────────────────────────────────────────────────────── */
.cb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    color: var(--cb-text);
}
.cb-table th {
    text-align: left;
    padding: 8px 12px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--cb-label);
    background: var(--cb-th-bg);
}
.cb-table th:not(:first-child) { text-align: center; }
.cb-table td {
    padding: 7px 12px;
    color: var(--cb-text);
    border-top: 1px solid var(--cb-border);
}
.cb-table td:not(:first-child) { text-align: center; }
.cb-table tr:nth-child(even) td { background: var(--cb-row-alt); }

/* ── Divider ────────────────────────────────────────────────────────────── */
.cb-hr {
    border: none;
    border-top: 1px solid var(--cb-border);
    margin: 14px 0;
}

/* ── Pill tag ───────────────────────────────────────────────────────────── */
.cb-pill {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 100px;
    background: var(--cb-surface);
    border: 1px solid var(--cb-border);
    color: var(--cb-muted);
}

/* ── Footnote ───────────────────────────────────────────────────────────── */
.cb-footnote {
    font-size: 11px;
    color: var(--cb-muted);
    line-height: 1.55;
    margin: 0;
}
</style>

@php
    $resumen  = $diligencia?->resumen_comportamiento;
    $sinDatos = !$diligencia || empty($resumen);

    // Colores de niveles — vivos para que funcionen en dark y light
    $nivelCfg = [
        'alto'  => ['label' => 'Alto',  'color' => '#f87171', 'bg' => 'rgba(239,68,68,0.10)',   'border' => 'rgba(239,68,68,0.28)'],
        'medio' => ['label' => 'Medio', 'color' => '#fbbf24', 'bg' => 'rgba(251,191,36,0.10)',  'border' => 'rgba(251,191,36,0.28)'],
        'bajo'  => ['label' => 'Bajo',  'color' => '#4ade80', 'bg' => 'rgba(74,222,128,0.10)',  'border' => 'rgba(74,222,128,0.28)'],
    ];

    if (!$sinDatos) {
        $nivel       = $resumen['nivel_alerta'] ?? 'bajo';
        $ncfg        = $nivelCfg[$nivel] ?? $nivelCfg['bajo'];
        $tabSwitches = $resumen['total_cambios_pestana'] ?? 0;
        $pegadas     = count($resumen['preguntas_con_pegado'] ?? []);
        $detalle     = $resumen['detalle_por_pregunta'] ?? [];

        // Capa 3
        $auth        = $resumen['analisis_autenticidad'] ?? null;
        if ($auth) {
            $nivelAuth   = $auth['nivel_sospecha'] ?? 'bajo';
            $pct         = $auth['porcentaje_sospecha'] ?? 0;
            $conclusion  = $auth['conclusion'] ?? '';
            $indicadores = $auth['indicadores_detectados'] ?? [];
            $sospechosas = $auth['respuestas_sospechosas'] ?? [];
            $analizadoEn = isset($auth['analizado_en'])
                ? \Carbon\Carbon::parse($auth['analizado_en'])->format('d/m/Y H:i')
                : null;
            $acfg = $nivelCfg[$nivelAuth] ?? $nivelCfg['bajo'];
        }
    }
@endphp

<div class="cb-wrap" style="display:grid;gap:14px;">

    @if($sinDatos)
    {{-- Estado vacío ──────────────────────────────────────────────────────── --}}
    <p class="cb-footnote" style="font-style:italic;padding:4px 0;">
        @if(!$diligencia)
            No hay diligencia de descargos registrada para este proceso.
        @else
            Sin datos de comportamiento — el trabajador completó el formulario con
            una versión anterior del sistema que no incluía la detección conductual.
        @endif
    </p>

    @else

    {{-- ── Tarjeta 1: Nivel de alerta + métricas ─────────────────────────── --}}
    <div class="cb-card">
        <div style="padding:14px 16px 12px;">

            {{-- Header nivel --}}
            <p class="cb-label">Nivel de alerta conductual</p>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                <span class="cb-nivel-badge"
                      style="background:{{ $ncfg['bg'] }};border:1px solid {{ $ncfg['border'] }};color:{{ $ncfg['color'] }};">
                    <span class="cb-nivel-dot" style="background:{{ $ncfg['color'] }};"></span>
                    {{ $ncfg['label'] }}
                </span>
                <p class="cb-footnote" style="margin:0;">
                    Criterios: <strong>Alto</strong> ≥3 pegadas o ≥6 tabs ·
                    <strong>Medio</strong> ≥2 pegadas o ≥3 tabs
                </p>
            </div>

            <hr class="cb-hr" style="margin:0 0 12px;">

            {{-- Métricas --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="cb-metric">
                    <p class="cb-label">Cambios de pestaña</p>
                    <p class="cb-metric-val"
                       style="color:{{ $tabSwitches >= 6 ? '#f87171' : ($tabSwitches >= 3 ? '#fbbf24' : '#4ade80') }};">
                        {{ $tabSwitches }}
                    </p>
                </div>
                <div class="cb-metric">
                    <p class="cb-label">Respuestas pegadas (Ctrl+V)</p>
                    <p class="cb-metric-val"
                       style="color:{{ $pegadas >= 3 ? '#f87171' : ($pegadas >= 2 ? '#fbbf24' : '#4ade80') }};">
                        {{ $pegadas }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Tabla por pregunta --}}
        @if(!empty($detalle))
        <div style="border-top:1px solid var(--cb-border);margin-top:2px;">
            <table class="cb-table">
                <thead>
                    <tr>
                        <th>Pregunta</th>
                        <th>Pegada</th>
                        <th>Tiempo (s)</th>
                        <th>Tabs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detalle as $i => $d)
                    @php
                        $esPegada = !empty($d['pegada']);
                        $tiempo   = $d['tiempo_s'] ?? null;
                        $cambios  = $d['cambios_tab'] ?? 0;
                    @endphp
                    <tr style="{{ ($esPegada || $cambios > 0) ? 'border-left:3px solid #f87171;' : '' }}">
                        <td style="font-weight:600;color:var(--cb-muted);">P{{ $i + 1 }}</td>
                        <td>
                            @if($esPegada)
                                <span style="color:#f87171;font-weight:700;">Sí</span>
                            @else
                                <span style="color:var(--cb-muted);">No</span>
                            @endif
                        </td>
                        <td style="color:var(--cb-muted);">
                            {{ $tiempo > 0 ? $tiempo . 's' : '—' }}
                        </td>
                        <td style="color:{{ $cambios > 0 ? '#fbbf24' : 'var(--cb-muted)' }};font-weight:{{ $cambios > 0 ? '700' : '400' }};">
                            {{ $cambios }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Tarjeta 2: Autenticidad IA (Capa 3) ────────────────────────────── --}}
    @if(isset($auth) && $auth)
    <div class="cb-card"
         style="border-left:3px solid {{ $acfg['color'] }};background:linear-gradient(135deg,{{ $acfg['bg'] }} 0%,transparent 100%);">
        <div style="padding:14px 16px;">

            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <div>
                    <p class="cb-label">Análisis de autenticidad — Capa 3 (IA perito)</p>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:3px;">
                        <span class="cb-nivel-badge"
                              style="background:{{ $acfg['bg'] }};border:1px solid {{ $acfg['border'] }};color:{{ $acfg['color'] }};">
                            <span class="cb-nivel-dot" style="background:{{ $acfg['color'] }};"></span>
                            Sospecha {{ $acfg['label'] }}
                        </span>
                        <span style="font-size:13px;font-weight:700;color:{{ $acfg['color'] }};">{{ $pct }}%</span>
                    </div>
                </div>
                @if($analizadoEn)
                <span class="cb-pill">Analizado {{ $analizadoEn }}</span>
                @endif
            </div>

            @if($conclusion)
            <p style="font-size:13px;color:var(--cb-text);line-height:1.6;margin:0 0 12px;">
                {{ $conclusion }}
            </p>
            @endif

            @if(!empty($indicadores))
            <hr class="cb-hr">
            <p class="cb-label">Indicadores detectados</p>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach($indicadores as $ind)
                <li style="font-size:12px;color:var(--cb-text);line-height:1.6;margin-bottom:3px;">
                    {{ $ind }}
                </li>
                @endforeach
            </ul>
            @endif

            @if(!empty($sospechosas))
            <hr class="cb-hr">
            <p class="cb-label">Respuestas que generan sospecha</p>
            <div style="display:grid;gap:6px;margin-top:6px;">
                @foreach($sospechosas as $s)
                <div style="padding:7px 10px;border-radius:8px;background:var(--cb-surface);border:1px solid var(--cb-border);">
                    <span style="font-size:11px;font-weight:700;color:{{ $acfg['color'] }};">
                        {{ $s['pregunta_numero'] ?? '' }}
                    </span>
                    <span style="font-size:11px;color:var(--cb-muted);"> — {{ $s['razon'] ?? '' }}</span>
                </div>
                @endforeach
            </div>
            @endif

            <hr class="cb-hr">
            <p class="cb-footnote">
                Este análisis es orientativo y no constituye prueba definitiva.
                Debe complementarse con el criterio del abogado.
            </p>
        </div>
    </div>

    @else
    <p class="cb-footnote" style="font-style:italic;">
        El análisis de autenticidad (Capa 3) se ejecuta al finalizar el formulario.
        Aún no disponible para este proceso.
    </p>
    @endif

    @endif {{-- /sinDatos --}}

</div>
