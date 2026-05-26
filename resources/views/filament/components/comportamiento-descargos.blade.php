{{--
    Señales de Comportamiento — registro conductual durante el formulario
    Completamente auto-contenido con inline styles garantizados.
    Variable: $diligencia (DiligenciaDescargo|null)
--}}
@once
<style>
@keyframes cb-pulse {
    0%,100%{opacity:1;transform:scale(1)}
    50%{opacity:.5;transform:scale(1.4)}
}
</style>
@endonce

@php
    $resumen  = $diligencia?->resumen_comportamiento;
    $sinDatos = !$diligencia || empty($resumen);

    $nivelCfg = [
        'alto'  => ['label'=>'Alto',  'color'=>'#ef4444','bg'=>'rgba(239,68,68,.12)','border'=>'rgba(239,68,68,.30)'],
        'medio' => ['label'=>'Medio', 'color'=>'#f59e0b','bg'=>'rgba(245,158,11,.12)','border'=>'rgba(245,158,11,.30)'],
        'bajo'  => ['label'=>'Bajo',  'color'=>'#22c55e','bg'=>'rgba(34,197,94,.12)','border'=>'rgba(34,197,94,.30)'],
    ];

    if (!$sinDatos) {
        $nivel       = $resumen['nivel_alerta'] ?? 'bajo';
        $ncfg        = $nivelCfg[$nivel] ?? $nivelCfg['bajo'];
        $tabSwitches = $resumen['total_cambios_pestana'] ?? 0;
        $pegadas     = count($resumen['preguntas_con_pegado'] ?? []);
        $detalle     = $resumen['detalle_por_pregunta'] ?? [];

        // Texto descriptivo según nivel
        $resumenTexto = match($nivel) {
            'alto'  => 'Se registró actividad conductual que requiere revisión cuidadosa.',
            'medio' => 'Se detectaron algunas señales que pueden ameritar revisión.',
            default => 'No se detectaron señales de alerta relevantes.',
        };

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

{{-- ── Estado vacío ─────────────────────────────────────────────────────────── --}}
@if($sinDatos)
<p style="font-size:.8125rem;color:rgba(100,116,139,.7);font-style:italic;margin:0;">
    @if(!$diligencia)
        No hay diligencia de descargos registrada para este proceso.
    @else
        Sin datos de comportamiento — el trabajador completó el formulario con
        una versión anterior del sistema que no incluía la detección conductual.
    @endif
</p>

@else

{{-- ── Wrapper principal ───────────────────────────────────────────────────── --}}
<div style="display:flex;flex-direction:column;gap:16px;">

    {{-- ── 1. Encabezado: nivel de alerta ─────────────────────────────────── --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
                padding:14px 16px;border-radius:10px;
                background:{{ $ncfg['bg'] }};border:1px solid {{ $ncfg['border'] }};">
        <div style="display:flex;align-items:center;gap:10px;">
            {{-- Dot pulsante --}}
            <span style="width:10px;height:10px;border-radius:50%;background:{{ $ncfg['color'] }};flex-shrink:0;
                         animation:cb-pulse 2s ease-in-out infinite;display:inline-block;"></span>
            <div>
                <p style="margin:0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
                           color:{{ $ncfg['color'] }};opacity:.8;">Nivel de alerta</p>
                <p style="margin:0;font-size:1.1rem;font-weight:800;color:{{ $ncfg['color'] }};">
                    {{ $ncfg['label'] }}
                </p>
            </div>
        </div>
        <p style="margin:0;font-size:.8125rem;color:{{ $ncfg['color'] }};opacity:.75;
                  max-width:380px;line-height:1.5;">
            {{ $resumenTexto }}
        </p>
    </div>

    {{-- ── 2. Métricas ─────────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">

        {{-- Cambios de pestaña --}}
        @php $colorTabs = $tabSwitches >= 6 ? '#ef4444' : ($tabSwitches >= 3 ? '#f59e0b' : '#22c55e'); @endphp
        <div style="padding:14px 16px;border-radius:10px;
                    background:rgba(255,255,255,.03);border:1px solid rgba(128,128,128,.15);">
            <div style="display:flex;align-items:center;gap:7px;margin-bottom:6px;">
                {{-- Ícono SVG: ventanas/pantallas --}}
                <svg style="width:16px;height:16px;color:{{ $colorTabs }};flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>
                    <path d="M9 21V9"/>
                </svg>
                <p style="margin:0;font-size:.7rem;font-weight:700;text-transform:uppercase;
                           letter-spacing:.08em;color:rgba(100,116,139,.8);">Cambios de pestaña</p>
            </div>
            <p style="margin:0 0 4px;font-size:2rem;font-weight:900;line-height:1;color:{{ $colorTabs }};">
                {{ $tabSwitches }}
            </p>
            <p style="margin:0;font-size:.7rem;color:rgba(100,116,139,.65);">
                Umbral alto: ≥6 · Umbral medio: ≥3
            </p>
        </div>

        {{-- Respuestas pegadas --}}
        @php $colorPeg = $pegadas >= 3 ? '#ef4444' : ($pegadas >= 2 ? '#f59e0b' : '#22c55e'); @endphp
        <div style="padding:14px 16px;border-radius:10px;
                    background:rgba(255,255,255,.03);border:1px solid rgba(128,128,128,.15);">
            <div style="display:flex;align-items:center;gap:7px;margin-bottom:6px;">
                {{-- Ícono SVG: portapapeles --}}
                <svg style="width:16px;height:16px;color:{{ $colorPeg }};flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                    <rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>
                </svg>
                <p style="margin:0;font-size:.7rem;font-weight:700;text-transform:uppercase;
                           letter-spacing:.08em;color:rgba(100,116,139,.8);">Respuestas pegadas (Ctrl+V)</p>
            </div>
            <p style="margin:0 0 4px;font-size:2rem;font-weight:900;line-height:1;color:{{ $colorPeg }};">
                {{ $pegadas }}
            </p>
            <p style="margin:0;font-size:.7rem;color:rgba(100,116,139,.65);">
                Umbral alto: ≥3 · Umbral medio: ≥2
            </p>
        </div>
    </div>

    {{-- ── 3. Tabla de detalle ──────────────────────────────────────────────── --}}
    @if(!empty($detalle))
    <div style="border-radius:10px;border:1px solid rgba(128,128,128,.15);overflow:hidden;">
        <div style="padding:9px 14px;border-bottom:1px solid rgba(128,128,128,.12);">
            <p style="margin:0;font-size:.7rem;font-weight:700;text-transform:uppercase;
                       letter-spacing:.08em;color:rgba(100,116,139,.7);">Detalle por pregunta</p>
        </div>
        <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <colgroup>
                <col style="width:12%;">
                <col style="width:22%;">
                <col style="width:22%;">
                <col style="width:44%;">
            </colgroup>
            <thead>
                <tr style="background:rgba(128,128,128,.06);">
                    <th style="padding:7px 12px;text-align:left;font-size:.65rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:.07em;color:rgba(100,116,139,.7);">#</th>
                    <th style="padding:7px 12px;text-align:center;font-size:.65rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:.07em;color:rgba(100,116,139,.7);">Pegada</th>
                    <th style="padding:7px 12px;text-align:center;font-size:.65rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:.07em;color:rgba(100,116,139,.7);">Tiempo</th>
                    <th style="padding:7px 12px;text-align:center;font-size:.65rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:.07em;color:rgba(100,116,139,.7);">Cambios de pestaña</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalle as $i => $d)
                @php
                    $esPegada = !empty($d['pegada']);
                    $tiempo   = $d['tiempo_s'] ?? null;
                    $cambios  = $d['cambios_tab'] ?? 0;
                    $bgFila   = ($i % 2 === 1) ? 'background:rgba(128,128,128,.03);' : '';
                    $alertFila = ($esPegada || $cambios > 0);
                @endphp
                <tr style="{{ $bgFila }}{{ $alertFila ? 'border-left:3px solid #ef4444;' : 'border-left:3px solid transparent;' }}">
                    <td style="padding:6px 12px;font-size:.8rem;font-weight:600;color:rgba(100,116,139,.8);">P{{ $i + 1 }}</td>
                    <td style="padding:6px 12px;text-align:center;font-size:.8rem;">
                        @if($esPegada)
                            <span style="font-weight:700;color:#ef4444;">Sí</span>
                        @else
                            <span style="color:rgba(100,116,139,.4);">—</span>
                        @endif
                    </td>
                    <td style="padding:6px 12px;text-align:center;font-size:.8rem;color:rgba(100,116,139,.6);">
                        {{ $tiempo > 0 ? $tiempo.'s' : '—' }}
                    </td>
                    <td style="padding:6px 12px;text-align:center;font-size:.8rem;">
                        @if($cambios > 0)
                            <span style="font-weight:700;color:{{ $cambios >= 3 ? '#ef4444' : '#f59e0b' }};">
                                {{ $cambios }}
                            </span>
                        @else
                            <span style="color:rgba(100,116,139,.4);">0</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ── 4. Análisis de autenticidad ─────────────────────────────────────── --}}
    @if(isset($auth) && $auth)
    <div style="border-radius:10px;border:1px solid {{ $acfg['border'] }};overflow:hidden;">

        {{-- Header IA --}}
        <div style="padding:12px 16px;background:{{ $acfg['bg'] }};
                    border-bottom:1px solid {{ $acfg['border'] }};
                    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;align-items:center;gap:10px;">
                {{-- Ícono IA / cerebro --}}
                <svg style="width:18px;height:18px;color:{{ $acfg['color'] }};flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <div>
                    <p style="margin:0;font-size:.7rem;font-weight:700;text-transform:uppercase;
                               letter-spacing:.08em;color:{{ $acfg['color'] }};opacity:.8;">Análisis de autenticidad por IA</p>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 10px 2px 6px;
                                      border-radius:100px;font-size:.75rem;font-weight:700;
                                      background:{{ $acfg['bg'] }};border:1px solid {{ $acfg['border'] }};
                                      color:{{ $acfg['color'] }};">
                            <span style="width:6px;height:6px;border-radius:50%;background:{{ $acfg['color'] }};"></span>
                            Sospecha {{ $acfg['label'] }}
                        </span>
                        <span style="font-size:1rem;font-weight:900;color:{{ $acfg['color'] }};">{{ $pct }}%</span>
                    </div>
                </div>
            </div>
            @if($analizadoEn)
            <span style="font-size:.7rem;padding:2px 9px;border-radius:100px;
                          background:rgba(128,128,128,.08);border:1px solid rgba(128,128,128,.15);
                          color:rgba(100,116,139,.7);">
                {{ $analizadoEn }}
            </span>
            @endif
        </div>

        <div style="padding:14px 16px;display:flex;flex-direction:column;gap:10px;">

            @if($conclusion)
            <p style="margin:0;font-size:.8125rem;line-height:1.65;color:rgba(71,85,105,.9);">
                {{ $conclusion }}
            </p>
            @endif

            @if(!empty($indicadores))
            <div>
                <p style="margin:0 0 6px;font-size:.7rem;font-weight:700;text-transform:uppercase;
                           letter-spacing:.08em;color:rgba(100,116,139,.7);">Indicadores detectados</p>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    @foreach($indicadores as $ind)
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <svg style="width:14px;height:14px;flex-shrink:0;margin-top:2px;color:{{ $acfg['color'] }};" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                        <span style="font-size:.8rem;line-height:1.55;color:rgba(71,85,105,.85);">{{ $ind }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($sospechosas))
            <div>
                <p style="margin:0 0 6px;font-size:.7rem;font-weight:700;text-transform:uppercase;
                           letter-spacing:.08em;color:rgba(100,116,139,.7);">Respuestas que generan sospecha</p>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    @foreach($sospechosas as $s)
                    <div style="padding:7px 11px;border-radius:7px;
                                background:rgba(128,128,128,.05);border:1px solid rgba(128,128,128,.12);
                                display:flex;align-items:baseline;gap:6px;">
                        <span style="font-size:.75rem;font-weight:700;color:{{ $acfg['color'] }};flex-shrink:0;">
                            {{ $s['pregunta_numero'] ?? '' }}
                        </span>
                        <span style="font-size:.75rem;color:rgba(100,116,139,.7);">
                            {{ $s['razon'] ?? '' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <p style="margin:0;font-size:.7rem;color:rgba(100,116,139,.55);line-height:1.55;
                       border-top:1px solid rgba(128,128,128,.1);padding-top:10px;">
                Este análisis es orientativo y no constituye prueba definitiva.
                Debe complementarse con el criterio del abogado.
            </p>
        </div>
    </div>

    @else
    <div style="padding:11px 14px;border-radius:8px;
                background:rgba(128,128,128,.04);border:1px solid rgba(128,128,128,.12);
                display:flex;align-items:center;gap:9px;">
        <svg style="width:16px;height:16px;flex-shrink:0;color:rgba(100,116,139,.4);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <p style="margin:0;font-size:.8rem;color:rgba(100,116,139,.6);font-style:italic;">
            El análisis de autenticidad por IA se ejecuta al finalizar el formulario.
            Aún no disponible para este proceso.
        </p>
    </div>
    @endif

</div>
@endif
