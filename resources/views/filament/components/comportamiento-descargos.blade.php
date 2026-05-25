{{--
    Señales de Comportamiento — Capa 2 + Capa 3
    Completamente auto-contenido con inline styles (funciona dentro de Filament Infolist Livewire).
    Variable: $diligencia (DiligenciaDescargo|null)
--}}

{{-- Lordicon script — carga una sola vez por página --}}
@once
<script src="https://cdn.lordicon.com/lordicon.js"></script>
<script>
(function(){
    function applyLordColors(){
        var dark = document.documentElement.classList.contains('dark');
        document.querySelectorAll('lord-icon[data-cb-icon]').forEach(function(el){
            el.setAttribute('colors', dark ? el.dataset.cbDark : el.dataset.cbLight);
        });
    }
    applyLordColors();
    setTimeout(applyLordColors,80);
    new MutationObserver(applyLordColors).observe(document.documentElement,{attributes:true,attributeFilter:['class']});
    new MutationObserver(function(ms){if(ms.some(function(m){return m.addedNodes.length}))applyLordColors();}).observe(document.body,{childList:true,subtree:true});
})();
</script>
@endonce

@php
    $resumen  = $diligencia?->resumen_comportamiento;
    $sinDatos = !$diligencia || empty($resumen);

    $nivelCfg = [
        'alto'  => ['label'=>'Alto',  'color'=>'#f87171','bg'=>'rgba(239,68,68,.12)', 'border'=>'rgba(239,68,68,.30)'],
        'medio' => ['label'=>'Medio', 'color'=>'#fbbf24','bg'=>'rgba(251,191,36,.12)','border'=>'rgba(251,191,36,.30)'],
        'bajo'  => ['label'=>'Bajo',  'color'=>'#4ade80','bg'=>'rgba(74,222,128,.12)','border'=>'rgba(74,222,128,.30)'],
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

    // Estilos reutilizables como variables PHP
    $cardBase  = 'border-radius:10px;border:1px solid rgba(255,255,255,.10);overflow:hidden;background:rgba(255,255,255,.03);';
    $cardLight = ''; // se aplica con data-theme en JS o se usa border fijo
    $labelSt   = 'font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.38);margin:0 0 4px;';
    $mutedSt   = 'font-size:12px;color:rgba(255,255,255,.50);';
    $thSt      = 'text-align:left;padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.35);background:rgba(255,255,255,.04);white-space:nowrap;';
    $tdSt      = 'padding:7px 12px;font-size:12px;color:rgba(255,255,255,.62);border-top:1px solid rgba(255,255,255,.07);';
    $metricSt  = 'padding:12px 14px;border-radius:8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);';
@endphp

{{-- Wrapper con clase para dark/light targeting via CSS inyectado inline --}}
<style>
.cb2-wrap .cb2-card   { border-color: rgba(255,255,255,.12) !important; background: rgba(255,255,255,.03) !important; }
.cb2-wrap .cb2-th     { color: rgba(255,255,255,.35) !important; background: rgba(255,255,255,.05) !important; }
.cb2-wrap .cb2-td     { color: rgba(255,255,255,.62) !important; border-color: rgba(255,255,255,.07) !important; }
.cb2-wrap .cb2-metric { background: rgba(255,255,255,.04) !important; border-color: rgba(255,255,255,.09) !important; }
.cb2-wrap .cb2-lbl    { color: rgba(255,255,255,.38) !important; }
.cb2-wrap .cb2-muted  { color: rgba(255,255,255,.50) !important; }
.cb2-wrap .cb2-body   { color: rgba(255,255,255,.68) !important; }
.cb2-wrap .cb2-pill   { background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.11);color:rgba(255,255,255,.42); }
html:not(.dark) .cb2-wrap .cb2-card   { border-color: rgba(0,0,0,.10) !important; background: rgba(0,0,0,.025) !important; }
html:not(.dark) .cb2-wrap .cb2-th     { color: rgba(0,0,0,.40) !important; background: rgba(0,0,0,.04) !important; }
html:not(.dark) .cb2-wrap .cb2-td     { color: rgba(17,24,39,.72) !important; border-color: rgba(0,0,0,.07) !important; }
html:not(.dark) .cb2-wrap .cb2-metric { background: rgba(0,0,0,.025) !important; border-color: rgba(0,0,0,.08) !important; }
html:not(.dark) .cb2-wrap .cb2-lbl    { color: rgba(0,0,0,.42) !important; }
html:not(.dark) .cb2-wrap .cb2-muted  { color: rgba(55,65,81,.60) !important; }
html:not(.dark) .cb2-wrap .cb2-body   { color: rgba(17,24,39,.78) !important; }
html:not(.dark) .cb2-wrap .cb2-pill   { background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.09);color:rgba(55,65,81,.60); }
.cb2-wrap .cb2-card { border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,.12); }
.cb2-wrap .cb2-tr-alt td { background:rgba(255,255,255,.02) !important; }
html:not(.dark) .cb2-wrap .cb2-tr-alt td { background:rgba(0,0,0,.018) !important; }
.cb2-wrap table { border-collapse:collapse;width:100%; }
.cb2-wrap .cb2-th:not(:first-child), .cb2-wrap .cb2-td:not(:first-child) { text-align:center; }
</style>

<div class="cb2-wrap" style="display:grid;gap:12px;">

    {{-- ── Estado vacío ─────────────────────────────────────────────────────── --}}
    @if($sinDatos)
    <p style="font-size:.8125rem;font-style:italic;color:rgba(148,163,184,.7);margin:0;">
        @if(!$diligencia)
            No hay diligencia de descargos registrada para este proceso.
        @else
            Sin datos de comportamiento — el trabajador completó el formulario con
            una versión anterior del sistema que no incluía la detección conductual.
        @endif
    </p>
    @else

    {{-- ── Tarjeta 1: Nivel de alerta ───────────────────────────────────────── --}}
    <div class="cb2-card" style="border-left:3px solid {{ $ncfg['color'] }};">

        {{-- Header --}}
        <div style="padding:13px 16px 12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <lord-icon src="https://cdn.lordicon.com/hpivxauj.json"
                    trigger="loop" delay="1200" stroke="bold"
                    data-cb-icon
                    data-cb-dark="primary:#94a3b8,secondary:#cbd5e1"
                    data-cb-light="primary:#475569,secondary:#64748b"
                    style="width:22px;height:22px;flex-shrink:0;">
                </lord-icon>
                <div>
                    <p class="cb2-lbl" style="margin:0 0 3px;">Nivel de alerta conductual</p>
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 11px 3px 7px;border-radius:100px;font-size:12px;font-weight:700;background:{{ $ncfg['bg'] }};border:1px solid {{ $ncfg['border'] }};color:{{ $ncfg['color'] }};">
                        <span style="width:7px;height:7px;border-radius:50%;background:{{ $ncfg['color'] }};flex-shrink:0;"></span>
                        {{ $ncfg['label'] }}
                    </span>
                </div>
            </div>
            <p class="cb2-muted" style="font-size:11px;margin:0;text-align:right;">
                Alto ≥3 pegadas o ≥6 tabs<br>
                Medio ≥2 pegadas o ≥3 tabs
            </p>
        </div>

        {{-- Métricas --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:0 12px 12px;">
            <div class="cb2-metric" style="padding:11px 13px;border-radius:8px;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <lord-icon src="https://cdn.lordicon.com/jnzhohhs.json"
                        trigger="loop" delay="2000" stroke="bold"
                        data-cb-icon
                        data-cb-dark="primary:#94a3b8,secondary:#cbd5e1"
                        data-cb-light="primary:#475569,secondary:#64748b"
                        style="width:16px;height:16px;flex-shrink:0;">
                    </lord-icon>
                    <p class="cb2-lbl" style="margin:0;">Cambios de pestaña</p>
                </div>
                <p style="font-size:26px;font-weight:800;line-height:1;margin:0;color:{{ $tabSwitches >= 6 ? '#f87171' : ($tabSwitches >= 3 ? '#fbbf24' : '#4ade80') }};">
                    {{ $tabSwitches }}
                </p>
            </div>
            <div class="cb2-metric" style="padding:11px 13px;border-radius:8px;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <lord-icon src="https://cdn.lordicon.com/vspbtnep.json"
                        trigger="loop" delay="2500" stroke="bold"
                        data-cb-icon
                        data-cb-dark="primary:#94a3b8,secondary:#cbd5e1"
                        data-cb-light="primary:#475569,secondary:#64748b"
                        style="width:16px;height:16px;flex-shrink:0;">
                    </lord-icon>
                    <p class="cb2-lbl" style="margin:0;">Pegadas (Ctrl+V)</p>
                </div>
                <p style="font-size:26px;font-weight:800;line-height:1;margin:0;color:{{ $pegadas >= 3 ? '#f87171' : ($pegadas >= 2 ? '#fbbf24' : '#4ade80') }};">
                    {{ $pegadas }}
                </p>
            </div>
        </div>

        {{-- Tabla detalle --}}
        @if(!empty($detalle))
        <div style="border-top:1px solid rgba(128,128,128,.12);">
            <table>
                <thead>
                    <tr>
                        <th class="cb2-th" style="padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">Pregunta</th>
                        <th class="cb2-th" style="padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;text-align:center;">Pegada</th>
                        <th class="cb2-th" style="padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;text-align:center;">Tiempo</th>
                        <th class="cb2-th" style="padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;text-align:center;">Tabs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detalle as $i => $d)
                    @php
                        $esPegada = !empty($d['pegada']);
                        $tiempo   = $d['tiempo_s'] ?? null;
                        $cambios  = $d['cambios_tab'] ?? 0;
                        $fila     = ($esPegada || $cambios > 0) ? 'border-left:3px solid #f87171;' : 'border-left:3px solid transparent;';
                    @endphp
                    <tr class="{{ ($i % 2 === 1) ? 'cb2-tr-alt' : '' }}">
                        <td class="cb2-td" style="padding:6px 12px;font-size:12px;font-weight:600;{{ $fila }}">P{{ $i + 1 }}</td>
                        <td class="cb2-td" style="padding:6px 12px;font-size:12px;text-align:center;">
                            @if($esPegada)
                                <span style="color:#f87171;font-weight:700;">Sí</span>
                            @else
                                <span style="opacity:.35;">—</span>
                            @endif
                        </td>
                        <td class="cb2-td" style="padding:6px 12px;font-size:12px;text-align:center;opacity:.5;">{{ $tiempo > 0 ? $tiempo.'s' : '—' }}</td>
                        <td class="cb2-td" style="padding:6px 12px;font-size:12px;text-align:center;color:{{ $cambios > 0 ? '#fbbf24' : 'inherit' }};font-weight:{{ $cambios > 0 ? '700' : '400' }};opacity:{{ $cambios > 0 ? '1' : '.35' }};">{{ $cambios }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Tarjeta 2: Autenticidad IA (Capa 3) ──────────────────────────────── --}}
    @if(isset($auth) && $auth)
    <div class="cb2-card" style="border-left:3px solid {{ $acfg['color'] }};">
        <div style="padding:13px 16px 14px;">

            {{-- Header --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <lord-icon src="https://cdn.lordicon.com/lupuorrc.json"
                        trigger="loop" delay="1500" stroke="bold"
                        data-cb-icon
                        data-cb-dark="primary:{{ $acfg['color'] }},secondary:{{ $acfg['color'] }}"
                        data-cb-light="primary:{{ $acfg['color'] }},secondary:{{ $acfg['color'] }}"
                        style="width:24px;height:24px;flex-shrink:0;">
                    </lord-icon>
                    <div>
                        <p class="cb2-lbl" style="margin:0 0 4px;">Análisis de autenticidad — IA Perito (Capa 3)</p>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 11px 3px 7px;border-radius:100px;font-size:12px;font-weight:700;background:{{ $acfg['bg'] }};border:1px solid {{ $acfg['border'] }};color:{{ $acfg['color'] }};">
                                <span style="width:7px;height:7px;border-radius:50%;background:{{ $acfg['color'] }};flex-shrink:0;"></span>
                                Sospecha {{ $acfg['label'] }}
                            </span>
                            <span style="font-size:15px;font-weight:800;color:{{ $acfg['color'] }};">{{ $pct }}%</span>
                        </div>
                    </div>
                </div>
                @if($analizadoEn)
                <span class="cb2-pill" style="font-size:11px;font-weight:600;padding:2px 9px;border-radius:100px;">
                    Analizado {{ $analizadoEn }}
                </span>
                @endif
            </div>

            @if($conclusion)
            <p class="cb2-body" style="font-size:.8125rem;line-height:1.6;margin:0 0 10px;">{{ $conclusion }}</p>
            @endif

            @if(!empty($indicadores))
            <p class="cb2-lbl" style="margin:10px 0 5px;">Indicadores detectados</p>
            <div style="display:grid;gap:4px;">
                @foreach($indicadores as $ind)
                <div style="display:flex;align-items:flex-start;gap:7px;">
                    <span style="color:{{ $acfg['color'] }};font-size:12px;flex-shrink:0;margin-top:1px;">▸</span>
                    <span class="cb2-body" style="font-size:.8rem;line-height:1.55;">{{ $ind }}</span>
                </div>
                @endforeach
            </div>
            @endif

            @if(!empty($sospechosas))
            <p class="cb2-lbl" style="margin:10px 0 5px;">Respuestas que generan sospecha</p>
            <div style="display:grid;gap:5px;">
                @foreach($sospechosas as $s)
                <div class="cb2-metric" style="padding:7px 10px;border-radius:7px;">
                    <span style="font-size:11px;font-weight:700;color:{{ $acfg['color'] }};">{{ $s['pregunta_numero'] ?? '' }}</span>
                    <span class="cb2-muted" style="font-size:11px;"> — {{ $s['razon'] ?? '' }}</span>
                </div>
                @endforeach
            </div>
            @endif

            <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(128,128,128,.12);">
                <p class="cb2-muted" style="font-size:11px;margin:0;line-height:1.55;">
                    Este análisis es orientativo y no constituye prueba definitiva.
                    Debe complementarse con el criterio del abogado.
                </p>
            </div>
        </div>
    </div>

    @else
    <div class="cb2-card" style="padding:11px 14px;border-left:3px solid rgba(148,163,184,.25);">
        <div style="display:flex;align-items:center;gap:9px;">
            <lord-icon src="https://cdn.lordicon.com/lupuorrc.json"
                trigger="loop" delay="3000" stroke="bold"
                data-cb-icon
                data-cb-dark="primary:#64748b,secondary:#94a3b8"
                data-cb-light="primary:#94a3b8,secondary:#64748b"
                style="width:18px;height:18px;flex-shrink:0;opacity:.5;">
            </lord-icon>
            <p class="cb2-muted" style="font-size:.8rem;margin:0;font-style:italic;">
                El análisis de autenticidad (Capa 3) se ejecuta al finalizar el formulario.
                Aún no disponible para este proceso.
            </p>
        </div>
    </div>
    @endif

    @endif {{-- /sinDatos --}}
</div>
