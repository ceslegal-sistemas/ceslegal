{{--
    Sección de Señales de Comportamiento — Capa 2 + Capa 3 (Autenticidad IA)
    Variable esperada: $diligencia (DiligenciaDescargo|null)
    Usa el sistema pt-card / pt-title / pt-body de pinfo-styles.blade.php
--}}
@include('filament.components.pinfo-styles')

<style>
/* ── Tabla de detalle por pregunta ──────────────────────────────────────── */
.cb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.cb-table th {
    text-align: left;
    padding: 7px 12px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--pt-label, rgba(255,255,255,0.35));
    background: rgba(255,255,255,0.04);
}
html:not(.dark) .cb-table th {
    color: rgba(0,0,0,0.40);
    background: rgba(0,0,0,0.03);
}
.cb-table th:not(:first-child) { text-align: center; }
.cb-table td {
    padding: 7px 12px;
    border-top: 1px solid rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.65);
    font-size: 12px;
}
html:not(.dark) .cb-table td {
    border-top-color: rgba(0,0,0,0.07);
    color: rgba(17,24,39,0.72);
}
.cb-table td:not(:first-child) { text-align: center; }
.cb-table tr:nth-child(even) td { background: rgba(255,255,255,0.025); }
html:not(.dark) .cb-table tr:nth-child(even) td { background: rgba(0,0,0,0.018); }

/* ── Métrica ────────────────────────────────────────────────────────────── */
.cb-metric {
    padding: 11px 13px;
    border-radius: 9px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
}
html:not(.dark) .cb-metric {
    background: rgba(0,0,0,0.025);
    border-color: rgba(0,0,0,0.08);
}
.cb-metric-lbl {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: rgba(255,255,255,0.38);
    margin: 0 0 3px;
}
html:not(.dark) .cb-metric-lbl { color: rgba(0,0,0,0.40); }
.cb-metric-val {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
    margin: 0;
}

/* ── Badge de nivel ─────────────────────────────────────────────────────── */
.cb-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px 4px 7px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 700;
}
.cb-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Pill tag ───────────────────────────────────────────────────────────── */
.cb-pill {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 100px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    color: rgba(255,255,255,0.45);
}
html:not(.dark) .cb-pill {
    background: rgba(0,0,0,0.04);
    border-color: rgba(0,0,0,0.09);
    color: rgba(55,65,81,0.65);
}

/* ── Indicador sospechoso ───────────────────────────────────────────────── */
.cb-suspicion-row {
    padding: 7px 10px;
    border-radius: 8px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
html:not(.dark) .cb-suspicion-row {
    background: rgba(0,0,0,0.025);
    border-color: rgba(0,0,0,0.07);
}
</style>

@php
    $resumen  = $diligencia?->resumen_comportamiento;
    $sinDatos = !$diligencia || empty($resumen);

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

        $auth = $resumen['analisis_autenticidad'] ?? null;
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

<div style="display:grid;gap:10px;">

    {{-- ── Estado vacío ────────────────────────────────────────────────────── --}}
    @if($sinDatos)
        <p class="pt-body" style="font-style:italic;margin:0;">
            @if(!$diligencia)
                No hay diligencia de descargos registrada para este proceso.
            @else
                Sin datos de comportamiento — el trabajador completó el formulario con
                una versión anterior del sistema que no incluía la detección conductual.
            @endif
        </p>

    @else

    {{-- ── Tarjeta 1: Nivel de alerta + métricas ──────────────────────────── --}}
    <div class="pt-card" style="border-left-color:{{ $ncfg['color'] }};padding:.875rem 1.125rem;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <p class="pt-title" style="margin:0;">Nivel de alerta conductual</p>
                <span class="cb-badge"
                      style="background:{{ $ncfg['bg'] }};border:1px solid {{ $ncfg['border'] }};color:{{ $ncfg['color'] }};">
                    <span class="cb-dot" style="background:{{ $ncfg['color'] }};"></span>
                    {{ $ncfg['label'] }}
                </span>
            </div>
            <p class="pt-footer" style="margin:0;border:none;padding:0;font-size:.7rem;">
                Alto ≥3 pegadas o ≥6 tabs · Medio ≥2 pegadas o ≥3 tabs
            </p>
        </div>

        {{-- Métricas --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:{{ !empty($detalle) ? '14px' : '0' }};">
            <div class="cb-metric">
                <p class="cb-metric-lbl">Cambios de pestaña</p>
                <p class="cb-metric-val"
                   style="color:{{ $tabSwitches >= 6 ? '#f87171' : ($tabSwitches >= 3 ? '#fbbf24' : '#4ade80') }};">
                    {{ $tabSwitches }}
                </p>
            </div>
            <div class="cb-metric">
                <p class="cb-metric-lbl">Respuestas pegadas (Ctrl+V)</p>
                <p class="cb-metric-val"
                   style="color:{{ $pegadas >= 3 ? '#f87171' : ($pegadas >= 2 ? '#fbbf24' : '#4ade80') }};">
                    {{ $pegadas }}
                </p>
            </div>
        </div>

        {{-- Tabla por pregunta --}}
        @if(!empty($detalle))
        <div style="border-top:1px solid rgba(255,255,255,0.07);margin:0 -1.125rem -0.875rem;overflow:hidden;border-radius:0 0 .625rem .625rem;">
            <style>html:not(.dark) .cb-tbl-border-fix { border-top-color: rgba(0,0,0,0.07) !important; }</style>
            <table class="cb-table cb-tbl-border-fix" style="border-top:1px solid rgba(255,255,255,0.07);">
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
                        <td style="font-weight:600;opacity:.6;">P{{ $i + 1 }}</td>
                        <td>
                            @if($esPegada)
                                <span style="color:#f87171;font-weight:700;">Sí</span>
                            @else
                                <span style="opacity:.45;">No</span>
                            @endif
                        </td>
                        <td style="opacity:.55;">{{ $tiempo > 0 ? $tiempo.'s' : '—' }}</td>
                        <td style="color:{{ $cambios > 0 ? '#fbbf24' : 'inherit' }};font-weight:{{ $cambios > 0 ? '700' : '400' }};opacity:{{ $cambios > 0 ? '1' : '.45' }};">
                            {{ $cambios }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Tarjeta 2: Autenticidad IA (Capa 3) ─────────────────────────────── --}}
    @if(isset($auth) && $auth)
    <div class="pt-card" style="border-left-color:{{ $acfg['color'] }};padding:.875rem 1.125rem;">

        {{-- Header --}}
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
            <div>
                <p class="pt-title" style="margin:0 0 5px;">Análisis de autenticidad — Capa 3 (IA perito)</p>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="cb-badge"
                          style="background:{{ $acfg['bg'] }};border:1px solid {{ $acfg['border'] }};color:{{ $acfg['color'] }};">
                        <span class="cb-dot" style="background:{{ $acfg['color'] }};"></span>
                        Sospecha {{ $acfg['label'] }}
                    </span>
                    <span style="font-size:13px;font-weight:800;color:{{ $acfg['color'] }};">{{ $pct }}%</span>
                </div>
            </div>
            @if($analizadoEn)
                <span class="cb-pill">Analizado {{ $analizadoEn }}</span>
            @endif
        </div>

        @if($conclusion)
            <p class="pt-body" style="margin:0 0 10px;">{{ $conclusion }}</p>
        @endif

        @if(!empty($indicadores))
            <p class="pt-title" style="margin:10px 0 6px;font-size:.7rem;">Indicadores detectados</p>
            @foreach($indicadores as $ind)
                <div class="pt-bullet" style="margin-bottom:3px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                         style="width:12px;height:12px;color:{{ $acfg['color'] }};flex-shrink:0;margin-top:1px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    {{ $ind }}
                </div>
            @endforeach
        @endif

        @if(!empty($sospechosas))
            <p class="pt-title" style="margin:10px 0 6px;font-size:.7rem;">Respuestas que generan sospecha</p>
            <div style="display:grid;gap:5px;">
                @foreach($sospechosas as $s)
                <div class="cb-suspicion-row">
                    <span style="font-size:11px;font-weight:700;color:{{ $acfg['color'] }};">
                        {{ $s['pregunta_numero'] ?? '' }}
                    </span>
                    <span style="font-size:11px;opacity:.6;"> — {{ $s['razon'] ?? '' }}</span>
                </div>
                @endforeach
            </div>
        @endif

        <p class="pt-footer" style="margin-top:12px;">
            Este análisis es orientativo y no constituye prueba definitiva.
            Debe complementarse con el criterio del abogado.
        </p>
    </div>

    @else
        <p class="pt-body" style="font-style:italic;margin:0;opacity:.6;">
            El análisis de autenticidad (Capa 3) se ejecuta al finalizar el formulario.
            Aún no disponible para este proceso.
        </p>
    @endif

    @endif {{-- /sinDatos --}}

</div>
