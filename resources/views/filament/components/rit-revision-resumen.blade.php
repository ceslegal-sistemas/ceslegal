@php
    $fmtHora = function ($t) {
        if (!$t) {
            return null;
        }
        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $fmt) {
            try {
                return \Carbon\Carbon::createFromFormat($fmt, $t)->format('g:i A');
            } catch (\Throwable $e) {
            }
        }
        return $t;
    };
    $formaPagoLabels = [
        'transferencia' => 'Transferencia bancaria',
        'cheque' => 'Cheque',
        'efectivo' => 'Efectivo',
        'mixto' => 'Mixto',
    ];
    $periodicidadLabels = [
        'mensual' => 'Mensual',
        'quincenal' => 'Quincenal',
        'semanal' => 'Semanal',
        'diario' => 'Diario / jornaleros',
        'destajo' => 'Por obra / destajo',
    ];
    $periodicidadTexto = is_array($periodicidad_pago ?? null)
        ? implode(' · ', array_filter(array_map(fn($p) => $periodicidadLabels[$p] ?? $p, $periodicidad_pago)))
        : $periodicidadLabels[$periodicidad_pago ?? ''] ?? ($periodicidad_pago ?: '—');
    $controlLabels = [
        'biometrico' => 'Reloj biométrico',
        'planilla' => 'Planilla manual',
        'app' => 'App móvil / digital',
        'supervision_rondas' => 'Supervisión directa',
        'sin_control' => 'Sin sistema formal',
    ];
    $sgSstLabels = [
        'si' => 'Sí, implementado',
        'en_proceso' => 'En proceso',
        'no' => 'No',
    ];
    $jornadaSabadoLabel = match ($jornada_sabado ?? 'no') {
        'media_jornada' => 'Media jornada',
        'dia_completo' => 'Jornada completa',
        default => 'No',
    };
    $dominicalesLabels = [
        'no' => 'No',
        'ocasionalmente' => 'Ocasionalmente',
        'regularmente' => 'Regularmente',
    ];
    $riesgosLabels = [
        'ergonomico' => 'Ergonómico',
        'psicosocial' => 'Psicosocial',
        'mecanico' => 'Mecánico',
        'electrico' => 'Eléctrico',
        'publico' => 'Público',
        'alturas' => 'Alturas',
        'quimico' => 'Químico',
        'vial' => 'Vial',
        'fisico' => 'Físico',
        'biologico' => 'Biológico',
        'locativo' => 'Locativo',
        'otro' => 'Otro',
    ];
    $sancionesLabels = [
        'llamado_verbal' => 'Llamado verbal',
        'llamado_escrito' => 'Llamado escrito',
        'suspension_1_8' => 'Suspensión 1-8 días',
        'suspension_1_15' => 'Suspensión 1-15 días',
        'suspension_1_30' => 'Suspensión 1-30 días',
        'suspension_1_40' => 'Suspensión 1-40 días',
        'suspension_1_60' => 'Suspensión 1-60 días',
        'terminacion' => 'Terminación justa causa',
    ];
    $tiposContratoLabels = [
        'indefinido' => 'Término indefinido',
        'fijo' => 'Término fijo',
        'obra' => 'Obra o labor',
        'aprendizaje' => 'Aprendizaje SENA',
    ];
    $totalCargos = count($cargos ?? []);

    $modalidadesJornadaLabels = [
        'jornada_fija_diurna' => 'Jornada fija diurna',
        'turnos_rotativos' => 'Turnos rotativos',
        'turno_nocturno_regular' => 'Turno nocturno fijo',
        'operacion_continua_247' => 'Operación 24/7',
        'jornada_flexible' => 'Horario flexible',
        'teletrabajo' => 'Teletrabajo',
        'trabajo_campo_obra' => 'Trabajo en campo/obra',
        'vigilancia_guardias' => 'Vigilancia / guardias',
    ];
    $capitulosRIT = [
        'I' => 'Denominación y Objeto',
        'II' => 'Admisión y Período de Prueba',
        'III' => 'Jornada Ordinaria',
        'IV' => 'Trabajo Suplementario y Festivos',
        'V' => 'Remuneración y Forma de Pago',
        'VI' => 'Vacaciones y Permisos',
        'VII' => 'Licencias Especiales',
        'VIII' => 'Régimen Disciplinario',
        'IX' => 'Escala de Sanciones',
        'X' => 'Reclamos y Procedimientos',
        'XI' => 'Conducta y Comportamiento',
        'XII' => 'Seguridad y Salud (SG-SST)',
        'XIII' => 'Equipos y Bienes',
        'XIV' => 'Comité de Convivencia / Acoso',
        'XV' => 'Protección de Sujetos Especiales',
        'XVI' => 'Disposiciones Finales',
    ];
@endphp

@verbatim
<style>
    @keyframes rg-up   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
    @keyframes rg-pop  { from{opacity:0;transform:scale(.6)}       to{opacity:1;transform:none} }
    @keyframes rg-fa   { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-16px,12px)} }
    @keyframes rg-fb   { 0%,100%{transform:translate(0,0)} 50%{transform:translate(14px,-14px)} }

    /* Tipografía: Space Grotesk para display, Inter (heredado del panel) para cuerpo */
    .rg-wrap{ position:relative; max-width:560px; margin:0 auto; display:flex; flex-direction:column; gap:14px; padding:.25rem 0; }
    .rg-disp{ font-family:'Space Grotesk', ui-sans-serif, system-ui, sans-serif; letter-spacing:-.02em; }

    /* Fondo con wash de marca (da profundidad al vidrio) */
    .rg-bg{ position:absolute; inset:-60px -20px; z-index:0; pointer-events:none; overflow:hidden; }
    .rg-orb{ position:absolute; border-radius:50%; filter:blur(46px); }
    .rg-orb.a{ width:300px;height:300px; top:-40px; right:-30px; background:radial-gradient(circle, rgba(225,29,72,.20), transparent 70%); animation:rg-fa 16s ease-in-out infinite; }
    .rg-orb.b{ width:260px;height:260px; top:38%; left:-50px; background:radial-gradient(circle, rgba(249,115,22,.18), transparent 70%); animation:rg-fb 19s ease-in-out infinite; }
    .rg-orb.c{ width:240px;height:240px; bottom:-40px; right:0; background:radial-gradient(circle, rgba(225,29,72,.12), transparent 70%); animation:rg-fa 22s ease-in-out infinite; }
    @media(prefers-reduced-motion:reduce){ .rg-orb{ animation:none } }

    /* ── Liquid glass card ── */
    .rg-glass{
        position:relative; z-index:1;
        background:rgba(255,255,255,.60);
        -webkit-backdrop-filter:blur(20px) saturate(150%);
        backdrop-filter:blur(20px) saturate(150%);
        border:1px solid rgba(255,255,255,.72);
        border-radius:22px;
        box-shadow:0 10px 30px rgba(28,25,23,.08), inset 0 1px 0 rgba(255,255,255,.6);
    }
    html.dark .rg-glass{
        background:rgba(38,34,32,.52);
        border-color:rgba(255,255,255,.09);
        box-shadow:0 12px 34px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    /* ── Hero ── */
    .rg-hero{ padding:26px 22px; text-align:center; }
    .rg-ring{ width:58px;height:58px;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;
        background:linear-gradient(135deg,#e11d48,#f97316); box-shadow:0 10px 24px rgba(225,29,72,.35); margin-bottom:12px; animation:rg-pop .6s .05s cubic-bezier(.34,1.56,.64,1) both; }
    .rg-ring svg{ width:29px;height:29px;color:#fff }
    .rg-kicker{ font-size:.66rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#be123c;margin:0 0 6px }
    html.dark .rg-kicker{ color:#fb7185 }
    .rg-title{ font-size:1.35rem;font-weight:700;line-height:1.2;margin:0 0 8px;color:#1c1917 }
    html.dark .rg-title{ color:#f5f5f4 }
    .rg-lead{ font-size:.85rem;line-height:1.55;color:#78716c;margin:0 auto;max-width:36ch }
    html.dark .rg-lead{ color:#a8a29e }
    .rg-em{ color:#be123c;font-weight:600 }
    html.dark .rg-em{ color:#fda4af }

    .rg-stats{ display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:16px }
    .rg-stat{ background:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.6);border-radius:14px;padding:10px 4px;text-align:center }
    html.dark .rg-stat{ background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08) }
    .rg-stat b{ display:block;font-family:'Space Grotesk';font-size:1.25rem;font-weight:700;line-height:1;color:#1c1917 }
    html.dark .rg-stat b{ color:#f5f5f4 }
    .rg-stat span{ font-size:.55rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#a8a29e;margin-top:4px;display:block }

    .rg-notes{ display:flex;flex-direction:column;gap:8px;margin-top:16px;text-align:left }
    .rg-note{ display:flex;gap:10px;align-items:flex-start;background:rgba(255,255,255,.42);border:1px solid rgba(255,255,255,.55);
        border-radius:13px;padding:10px 12px;font-size:.78rem;line-height:1.45;color:#57534e }
    html.dark .rg-note{ background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.07);color:#d6d3d1 }
    .rg-note b{ color:#1c1917 } html.dark .rg-note b{ color:#f5f5f4 }
    .rg-note .ic{ width:18px;height:18px;flex-shrink:0;margin-top:1px }

    /* Section label divider */
    .rg-seclabel{ font-size:.66rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:#a8a29e;
        margin:8px 4px 0;display:flex;align-items:center;gap:10px }
    .rg-seclabel::after{ content:"";flex:1;height:1px;background:rgba(28,25,23,.1) }
    html.dark .rg-seclabel::after{ background:rgba(255,255,255,.1) }

    /* Data card */
    .rg-card{ padding:15px 17px;overflow:hidden }
    .rg-card::before{ content:"";position:absolute;left:0;top:14px;bottom:14px;width:3px;border-radius:0 3px 3px 0;background:linear-gradient(180deg,#e11d48,#f97316) }
    .rg-lbl{ font-size:.61rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#a8a29e;margin:0 0 5px }
    .rg-val{ font-size:1rem;font-weight:600;margin:0;line-height:1.35;color:#1c1917 }
    html.dark .rg-val{ color:#f5f5f4 }
    .rg-val.empty{ color:#a8a29e;font-weight:400;font-style:italic;font-size:.9rem }
    .rg-sub{ font-size:.8rem;color:#78716c;margin:4px 0 0;line-height:1.45 }
    html.dark .rg-sub{ color:#a8a29e }
    .rg-grid2{ display:grid;grid-template-columns:1fr 1fr;gap:14px }
    @media(max-width:460px){ .rg-grid2{ grid-template-columns:1fr } }

    /* Pills */
    .rg-pills{ display:flex;flex-wrap:wrap;gap:6px;margin-top:8px }
    .rg-pill{ font-size:.72rem;font-weight:600;border-radius:999px;padding:.24rem .7rem;background:rgba(225,29,72,.1);color:#be123c }
    html.dark .rg-pill{ background:rgba(251,113,133,.16);color:#fda4af }
    .rg-pill.amber{ background:rgba(234,179,8,.15);color:#854d0e } html.dark .rg-pill.amber{ background:rgba(250,204,21,.13);color:#fde047 }
    .rg-pill.orange{ background:rgba(234,88,12,.13);color:#9a3412 } html.dark .rg-pill.orange{ background:rgba(249,115,22,.15);color:#fdba74 }
    .rg-pill.red{ background:rgba(220,38,38,.12);color:#991b1b } html.dark .rg-pill.red{ background:rgba(239,68,68,.15);color:#fca5a5 }
    .rg-pill-h{ font-size:.7rem;font-weight:700;letter-spacing:.04em;margin:.5rem 0 .1rem }

    /* Capítulos disclosure */
    details.rg-caps{ padding:0;overflow:hidden }
    details.rg-caps>summary{ list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;
        padding:15px 18px;font-weight:600;color:#1c1917 }
    html.dark details.rg-caps>summary{ color:#f5f5f4 }
    details.rg-caps>summary::-webkit-details-marker{ display:none }
    .rg-caps-count{ font-size:.7rem;font-weight:700;color:#be123c;background:rgba(225,29,72,.1);border-radius:999px;padding:.16rem .6rem;margin-left:.4rem }
    html.dark .rg-caps-count{ color:#fda4af;background:rgba(251,113,133,.16) }
    .rg-chev{ transition:transform .25s;color:#a8a29e;flex-shrink:0 }
    details.rg-caps[open] .rg-chev{ transform:rotate(180deg) }
    .rg-caps-body{ padding:0 13px 12px;display:grid;grid-template-columns:1fr 1fr;gap:8px }
    @media(max-width:460px){ .rg-caps-body{ grid-template-columns:1fr } }
    .rg-cap{ display:flex;align-items:center;gap:9px;font-size:.75rem;color:#44403c;
        background:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.6);border-radius:12px;padding:9px 11px }
    html.dark .rg-cap{ color:#d6d3d1;background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.07) }
    .rg-cap svg{ width:14px;height:14px;color:#e11d48;flex-shrink:0 }
    .rg-cap b{ font-weight:700 }
    .rg-foot{ font-size:.72rem;color:#a8a29e;text-align:center;line-height:1.5;padding:2px 18px 14px }

    .rg-a1{ animation:rg-up .5s cubic-bezier(.16,1,.3,1) both }
    .rg-a2{ animation:rg-up .5s .08s cubic-bezier(.16,1,.3,1) both }
    .rg-a3{ animation:rg-up .5s .16s cubic-bezier(.16,1,.3,1) both }
    @media(prefers-reduced-motion:reduce){ .rg-a1,.rg-a2,.rg-a3{ animation:none } }
</style>
@endverbatim

<div class="rg-wrap">
    <div class="rg-bg"><div class="rg-orb a"></div><div class="rg-orb b"></div><div class="rg-orb c"></div></div>

    {{-- ══ HERO ══ --}}
    <div class="rg-glass rg-hero rg-a1">
        <div class="rg-ring">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        </div>
        <p class="rg-kicker">Resumen de su información</p>
        <h2 class="rg-title rg-disp">Reglamento Interno de Trabajo</h2>
        <p class="rg-lead">
            Verifique que todo sea correcto. Use <span class="rg-em">Anterior</span> para cambiar algo;
            al pulsar <span class="rg-em">Crear</span>, la IA redactará los 16 capítulos conforme al Art. 105 CST.
        </p>

        <div class="rg-stats">
            <div class="rg-stat"><b>{{ $num_trabajadores ?: '?' }}</b><span>Trabaj.</span></div>
            <div class="rg-stat"><b>{{ $totalCargos ?: '—' }}</b><span>Cargos</span></div>
            <div class="rg-stat"><b>16</b><span>Capít.</span></div>
            <div class="rg-stat"><b>~80</b><span>Artíc.</span></div>
        </div>

        <div class="rg-notes">
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Verifique que el resumen sea <b>correcto y completo</b> antes de continuar.</span>
            </div>
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                <span>Puede tardar hasta <b>60 segundos</b> — no cierre ni recargue la ventana.</span>
            </div>
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#ea580c" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                <span>Revíselo con un <b>abogado</b> antes de presentarlo al Ministerio del Trabajo.</span>
            </div>
        </div>
    </div>

    <div class="rg-seclabel rg-a2">Resumen de sus respuestas</div>

    {{-- Empresa --}}
    <div class="rg-glass rg-card rg-a2">
        <p class="rg-lbl">Empresa</p>
        <p class="rg-val">{{ $empresa?->razon_social ?? '—' }}</p>
        <p class="rg-sub">
            NIT {{ $empresa?->nit ?? '—' }}@if ($empresa?->ciudad) &nbsp;·&nbsp; {{ $empresa->ciudad }}@if ($empresa->departamento), {{ $empresa->departamento }}@endif @endif
        </p>
        @if (!empty($actividad_economica))
            <p class="rg-sub" style="margin-top:.3rem"><span style="opacity:.75">Actividad:</span> {{ $actividad_economica }}</p>
        @endif
        @if (($tiene_sucursales ?? null) === 'si')
            <p class="rg-sub" style="margin-top:.3rem">{{ count($sucursales ?? []) }} sucursal(es) registrada(s)</p>
        @endif
    </div>

    {{-- Jornada + Pago --}}
    <div class="rg-grid2 rg-a2">
        <div class="rg-glass rg-card">
            <p class="rg-lbl">Jornada</p>
            <p class="rg-val {{ !$horario_entrada ? 'empty' : '' }}">{{ $horario_entrada ?? '—' }} → {{ $horario_salida ?? '—' }}</p>
            <p class="rg-sub">Sáb: {{ $jornadaSabadoLabel }} &nbsp;·&nbsp; Dom: {{ $dominicalesLabels[$trabaja_dominicales ?? 'no'] ?? 'No' }}</p>
            @if (!empty($modalidades_jornada))
                <div class="rg-pills">@foreach ($modalidades_jornada as $mj)<span class="rg-pill">{{ $modalidadesJornadaLabels[$mj] ?? $mj }}</span>@endforeach</div>
            @endif
        </div>
        <div class="rg-glass rg-card">
            <p class="rg-lbl">Pago</p>
            <p class="rg-val {{ !$forma_pago ? 'empty' : '' }}">{{ $formaPagoLabels[$forma_pago ?? ''] ?? ($forma_pago ?: '—') }}</p>
            <p class="rg-sub">{{ $periodicidadTexto }}</p>
        </div>
    </div>

    {{-- Contratos + Control --}}
    <div class="rg-grid2 rg-a3">
        <div class="rg-glass rg-card">
            <p class="rg-lbl">Tipos de contrato</p>
            @if (is_array($tipos_contrato) && !empty($tipos_contrato))
                <div class="rg-pills">@foreach ($tipos_contrato as $tc)<span class="rg-pill">{{ $tiposContratoLabels[$tc] ?? $tc }}</span>@endforeach</div>
            @else
                <p class="rg-val empty">No indicado</p>
            @endif
        </div>
        <div class="rg-glass rg-card">
            <p class="rg-lbl">Control asistencia</p>
            <p class="rg-val {{ !$control_asistencia ? 'empty' : '' }}" style="font-size:.95rem">{{ $controlLabels[$control_asistencia ?? ''] ?? ($control_asistencia ?: '—') }}</p>
        </div>
    </div>

    {{-- Faltas (full) --}}
    <div class="rg-glass rg-card rg-a3">
        <p class="rg-lbl">Régimen disciplinario</p>
        @if (!empty($faltas_leves))
            <p class="rg-pill-h" style="color:#854d0e">Leves</p>
            <div class="rg-pills">@foreach ($faltas_leves as $f)<span class="rg-pill amber">{{ $f }}</span>@endforeach</div>
        @endif
        @if (!empty($faltas_graves))
            <p class="rg-pill-h" style="color:#9a3412">Graves</p>
            <div class="rg-pills">@foreach ($faltas_graves as $f)<span class="rg-pill orange">{{ $f }}</span>@endforeach</div>
        @endif
        @if (empty($faltas_leves) && empty($faltas_graves))
            <p class="rg-val empty">Sin faltas registradas</p>
        @endif
        @if (!empty($sanciones))
            <p class="rg-pill-h">Sanciones habilitadas</p>
            <div class="rg-pills">@foreach ($sanciones as $s)<span class="rg-pill">{{ $sancionesLabels[$s] ?? $s }}</span>@endforeach</div>
        @endif
    </div>

    {{-- SST + Riesgos --}}
    <div class="rg-grid2 rg-a3">
        <div class="rg-glass rg-card">
            <p class="rg-lbl">SG-SST</p>
            <p class="rg-val {{ !$tiene_sg_sst ? 'empty' : '' }}" style="font-size:.95rem">{{ $sgSstLabels[$tiene_sg_sst ?? ''] ?? ($tiene_sg_sst ?: '—') }}</p>
        </div>
        <div class="rg-glass rg-card">
            <p class="rg-lbl">Riesgos principales</p>
            @if (!empty($riesgos_principales))
                <div class="rg-pills">@foreach ($riesgos_principales as $r)<span class="rg-pill">{{ $riesgosLabels[$r] ?? $r }}</span>@endforeach</div>
            @else
                <p class="rg-val empty">No indicados</p>
            @endif
        </div>
    </div>

    {{-- Capítulos (colapsable) --}}
    <details class="rg-glass rg-caps rg-a3">
        <summary>
            <span class="rg-disp">Capítulos que generará la IA <span class="rg-caps-count">16</span></span>
            <svg class="rg-chev" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </summary>
        <div class="rg-caps-body">
            @foreach ($capitulosRIT as $num => $titulo)
                <div class="rg-cap">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    <span><b>{{ $num }}</b>&nbsp;· {{ $titulo }}</span>
                </div>
            @endforeach
        </div>
        <p class="rg-foot">Todos obligatorios según el Art. 105 CST. La IA los redacta artículo por artículo con la información que usted proporcionó.</p>
    </details>

</div>
