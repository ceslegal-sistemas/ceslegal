@php
    $formaPagoLabels = [
        'transferencia' => 'Transferencia bancaria',
        'cheque'        => 'Cheque',
        'efectivo'      => 'Efectivo',
        'mixto'         => 'Mixto',
    ];
    $periodicidadLabels = [
        'mensual'   => 'Mensual',
        'quincenal' => 'Quincenal',
        'semanal'   => 'Semanal',
    ];
    $controlLabels = [
        'biometrico'  => 'Biométrico',
        'planilla'    => 'Planilla manual',
        'app'         => 'App móvil',
        'sin_control' => 'Sin control formal',
    ];
    $sgSstLabels = [
        'si'         => 'Sí, implementado',
        'en_proceso' => 'En proceso',
        'no'         => 'No',
    ];
    $jornadaSabadoLabel = match ($jornada_sabado ?? 'no') {
        'media_jornada' => 'Media jornada',
        'dia_completo'  => 'Jornada completa',
        default         => 'No',
    };
    $dominicalesLabels = [
        'no'            => 'No',
        'ocasionalmente' => 'Ocasionalmente',
        'regularmente'  => 'Regularmente',
    ];
    $riesgosLabels = [
        'ergonomico'  => 'Ergonómico',
        'psicosocial' => 'Psicosocial',
        'mecanico'    => 'Mecánico',
        'electrico'   => 'Eléctrico',
        'publico'     => 'Público',
        'otro'        => 'Otro',
    ];
    $sancionesLabels = [
        'llamado_verbal'   => 'Llamado verbal',
        'llamado_escrito'  => 'Llamado escrito',
        'suspension_1_3'   => 'Suspensión 1-3 días',
        'suspension_4_8'   => 'Suspensión 4-8 días',
        'terminacion'      => 'Terminación justa causa',
    ];
    $tiposContratoLabels = [
        'indefinido'  => 'Término indefinido',
        'fijo'        => 'Término fijo',
        'obra'        => 'Obra o labor',
        'aprendizaje' => 'Aprendizaje SENA',
    ];
    $totalCargos     = count($cargos ?? []);
    $totalFaltas     = count($faltas_leves ?? []) + count($faltas_graves ?? []) + count($faltas_muy_graves ?? []);
    $totalSanciones  = count($sanciones ?? []);
    $totalRiesgos    = count($riesgos_principales ?? []);
@endphp

@verbatim
<style>
/* ── Keyframes ─────────────────────────────── */
@keyframes rr-up   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes rr-pop  { from{opacity:0;transform:scale(.6)}        to{opacity:1;transform:scale(1)}      }
@keyframes rr-fb   { 0%,100%{transform:translate(0,0)} 40%{transform:translate(-16px,12px)} 70%{transform:translate(10px,-9px)} }
@keyframes rr-fg   { 0%,100%{transform:translate(0,0)} 35%{transform:translate(13px,-15px)} 65%{transform:translate(-9px,7px)} }

.rr-a1 { animation:rr-up .55s cubic-bezier(.16,1,.3,1) both }
.rr-a2 { animation:rr-up .55s .1s cubic-bezier(.16,1,.3,1) both }
.rr-a3 { animation:rr-up .55s .2s cubic-bezier(.16,1,.3,1) both }
.rr-a4 { animation:rr-up .55s .3s cubic-bezier(.16,1,.3,1) both }
.rr-icon-pop { animation:rr-pop .6s .05s cubic-bezier(.34,1.56,.64,1) both }
.rr-orb-b { animation:rr-fb 13s ease-in-out infinite }
.rr-orb-g { animation:rr-fg 16s ease-in-out infinite }

@media(prefers-reduced-motion:reduce){
  .rr-a1,.rr-a2,.rr-a3,.rr-a4,.rr-icon-pop,.rr-orb-b,.rr-orb-g{animation:none;opacity:.7;transform:none}
}

/* ── Hero ──────────────────────────────────── */
.rr-hero {
  position:relative;overflow:hidden;border-radius:1.25rem;
  padding:1.75rem 1.5rem 1.625rem;
  background:linear-gradient(150deg,#060f22 0%,#0d1f3c 55%,#060e20 100%);
}
@media(min-width:540px){ .rr-hero{ padding:2.25rem 2rem 2rem } }
html:not(.dark) .rr-hero { background:#fff;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 28px rgba(0,0,0,.07) }
html:not(.dark) .rr-orb-b { background:radial-gradient(circle,rgba(215,75,10,.22),transparent 70%)!important }
html:not(.dark) .rr-orb-g { background:radial-gradient(circle,rgba(190,130,10,.26),transparent 70%)!important }
.rr-overlay {
  position:absolute;inset:0;pointer-events:none;z-index:1;
  background:radial-gradient(ellipse 80% 90% at 50% 50%,rgba(3,8,20,.80) 0%,rgba(3,8,20,.45) 55%,transparent 100%);
}
html:not(.dark) .rr-overlay {
  background:radial-gradient(ellipse 75% 85% at 50% 40%,rgba(255,255,255,.72) 0%,rgba(255,255,255,.38) 55%,transparent 100%);
}

/* ── Hero icon ring ────────────────────────── */
.rr-icon-ring {
  display:inline-flex;align-items:center;justify-content:center;
  width:52px;height:52px;border-radius:50%;margin-bottom:.875rem;
  background:rgba(201,168,76,.13);border:1.5px solid rgba(201,168,76,.38);
}

/* ── Hero typography ───────────────────────── */
.rr-hero-label { font-size:.65rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin:0 0 .35rem;color:#c9a84c;text-shadow:0 0 16px rgba(201,168,76,.6) }
html:not(.dark) .rr-hero-label { color:#92710d;text-shadow:none }
.rr-hero-title { font-size:1.1875rem;font-weight:700;letter-spacing:-.015em;line-height:1.25;margin:0 0 .5rem;color:#f1f5f9;text-shadow:0 2px 20px rgba(0,0,0,.8) }
@media(min-width:540px){ .rr-hero-title { font-size:1.375rem } }
html:not(.dark) .rr-hero-title { color:#0f172a;text-shadow:none }
.rr-hero-sub { font-size:.8125rem;line-height:1.6;color:#94a3b8;margin:0 0 1.25rem }
html:not(.dark) .rr-hero-sub { color:#475569 }

/* ── Bullets ───────────────────────────────── */
.rr-bullets { display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.25rem;text-align:left }
.rr-bullet {
  display:flex;align-items:flex-start;gap:.625rem;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
  border-radius:.625rem;padding:.6rem .875rem;font-size:.8rem;color:#cbd5e1;line-height:1.5;
}
html:not(.dark) .rr-bullet { background:rgba(79,70,229,.04);border-color:rgba(79,70,229,.12);color:#374151 }
.rr-bullet strong { color:#e2e8f0;font-weight:600 }
html:not(.dark) .rr-bullet strong { color:#111827 }

/* ── Stats strip ───────────────────────────── */
.rr-stats {
  display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;
  max-width:480px;margin:0 auto .875rem;
}
.rr-stat {
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);
  border-radius:.75rem;padding:.6rem .5rem;text-align:center;
}
html:not(.dark) .rr-stat { background:rgba(79,70,229,.05);border-color:rgba(79,70,229,.14) }
.rr-stat-num { font-size:1.3rem;font-weight:800;color:#f1f5f9;line-height:1 }
html:not(.dark) .rr-stat-num { color:#0f172a }
.rr-stat-lbl { font-size:.58rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-top:.25rem }

/* ── Section divider ───────────────────────── */
.rr-rule { display:flex;align-items:center;gap:.75rem;margin-bottom:1rem }
.rr-rule-line { flex:1;height:1px;background:rgba(255,255,255,.08) }
html:not(.dark) .rr-rule-line { background:#e5e7eb }
.rr-rule-txt { font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;white-space:nowrap;color:#475569 }
html:not(.dark) .rr-rule-txt { color:#9ca3af }

/* ── Doc card ──────────────────────────────── */
.rr-doc { border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden }
html:not(.dark) .rr-doc { border-color:rgba(0,0,0,.08);box-shadow:0 1px 6px rgba(0,0,0,.05) }
.rr-section { padding:1rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.07) }
.rr-section:last-child { border-bottom:none }
html:not(.dark) .rr-section { border-bottom-color:rgba(0,0,0,.06) }
.rr-sec-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 }
.rr-sec-grid .rr-section { border-bottom:none;border-right:1px solid rgba(255,255,255,.07) }
.rr-sec-grid .rr-section:last-child { border-right:none }
html:not(.dark) .rr-sec-grid .rr-section { border-right-color:rgba(0,0,0,.06) }
@media(max-width:500px){
  .rr-sec-grid { grid-template-columns:1fr }
  .rr-sec-grid .rr-section { border-right:none;border-bottom:1px solid rgba(255,255,255,.07) }
  .rr-sec-grid .rr-section:last-child { border-bottom:none }
}
.rr-section[data-color] { border-left:3px solid var(--sc,rgba(99,102,241,.5)) }
.rr-sec-header { display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem }
.rr-sec-ico { width:13px;height:13px;flex-shrink:0;opacity:.6 }
.rr-sec-label { font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin:0 }
html:not(.dark) .rr-sec-label { color:#9ca3af }
.rr-sec-val { font-size:.9rem;font-weight:500;color:#e2e8f0;margin:0;line-height:1.55 }
.rr-sec-val.empty { color:#475569;font-style:italic;font-weight:400 }
html:not(.dark) .rr-sec-val { color:#111827 }
html:not(.dark) .rr-sec-val.empty { color:#9ca3af }
.rr-sec-sub { font-size:.775rem;color:#64748b;margin:.25rem 0 0;line-height:1.4 }
html:not(.dark) .rr-sec-sub { color:#6b7280 }
.rr-tag {
  display:inline-block;font-size:.7rem;font-weight:500;border-radius:.3rem;
  padding:.1rem .45rem;margin:.15rem .15rem 0 0;
  background:rgba(99,102,241,.14);color:#a5b4fc;
}
html:not(.dark) .rr-tag { background:rgba(79,70,229,.09);color:#4338ca }
.rr-tag.leve { background:rgba(250,204,21,.12);color:#fde047 }
html:not(.dark) .rr-tag.leve { background:rgba(234,179,8,.09);color:#854d0e }
.rr-tag.grave { background:rgba(249,115,22,.13);color:#fb923c }
html:not(.dark) .rr-tag.grave { background:rgba(234,88,12,.09);color:#9a3412 }
.rr-tag.muy-grave { background:rgba(239,68,68,.13);color:#f87171 }
html:not(.dark) .rr-tag.muy-grave { background:rgba(220,38,38,.09);color:#991b1b }
</style>
@endverbatim

<div style="display:flex;flex-direction:column;gap:1.125rem;padding:.25rem 0;">

  {{-- ══ HERO ══ --}}
  <div class="rr-hero rr-a1" style="text-align:center;">
    <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
      <div class="rr-orb-b" style="position:absolute;width:240px;height:240px;top:-60px;right:-40px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.5),transparent 70%);filter:blur(24px);"></div>
      <div class="rr-orb-g" style="position:absolute;width:180px;height:180px;bottom:-45px;left:-35px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.22),transparent 70%);filter:blur(22px);"></div>
    </div>
    <div class="rr-overlay"></div>
    <div style="position:relative;z-index:2;">

      <div class="rr-icon-ring rr-icon-pop">
        <svg style="width:26px;height:26px;color:#c9a84c" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
      </div>

      <p class="rr-hero-label">Paso final</p>
      <h2 class="rr-hero-title">Confirme y construya su RIT</h2>
      <p class="rr-hero-sub">
        Revise el resumen de sus respuestas. Al hacer clic en <strong style="color:#f1f5f9">"Crear"</strong>
        la IA redactará el Reglamento Interno completo con cumplimiento del Art. 105 CST
        y la Ley 2365/2024 (prevención de acoso sexual).
      </p>

      <div class="rr-bullets rr-a2" style="max-width:500px;margin-left:auto;margin-right:auto;">
        <div class="rr-bullet">
          <svg style="width:16px;height:16px;flex-shrink:0;margin-top:1px;color:#86efac" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span>Verifique que el resumen sea <strong>correcto y completo</strong> antes de continuar.</span>
        </div>
        <div class="rr-bullet">
          <svg style="width:16px;height:16px;flex-shrink:0;margin-top:1px;color:#fde68a" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
          </svg>
          <span>El proceso puede tardar hasta <strong>60 segundos</strong> — no cierre ni recargue la ventana.</span>
        </div>
        <div class="rr-bullet">
          <svg style="width:16px;height:16px;flex-shrink:0;margin-top:1px;color:#93c5fd" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
          </svg>
          <span>El documento debe ser <strong>revisado por un abogado</strong> antes de presentarlo al Ministerio del Trabajo.</span>
        </div>
      </div>

      {{-- Stats --}}
      <div class="rr-stats rr-a3">
        <div class="rr-stat">
          <div class="rr-stat-num">{{ $num_trabajadores ?: '?' }}</div>
          <div class="rr-stat-lbl">Trabajadores</div>
        </div>
        <div class="rr-stat">
          <div class="rr-stat-num">{{ $totalCargos }}</div>
          <div class="rr-stat-lbl">Cargos</div>
        </div>
        <div class="rr-stat">
          <div class="rr-stat-num">{{ $totalFaltas }}</div>
          <div class="rr-stat-lbl">Faltas</div>
        </div>
        <div class="rr-stat">
          <div class="rr-stat-num">{{ $totalSanciones }}</div>
          <div class="rr-stat-lbl">Sanciones</div>
        </div>
      </div>

    </div>
  </div>

  {{-- ══ RESUMEN ══ --}}
  <div class="rr-a4">
    <div class="rr-rule">
      <div class="rr-rule-line"></div>
      <span class="rr-rule-txt">Resumen de sus respuestas</span>
      <div class="rr-rule-line"></div>
    </div>

    <div class="rr-doc">

      {{-- Empresa --}}
      <div class="rr-section" data-color style="--sc:#60a5fa">
        <div class="rr-sec-header">
          <svg class="rr-sec-ico" style="color:#60a5fa" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
          </svg>
          <p class="rr-sec-label">Empresa</p>
        </div>
        <p class="rr-sec-val">{{ $empresa?->razon_social ?? '—' }}</p>
        <p class="rr-sec-sub">
          NIT {{ $empresa?->nit ?? '—' }}
          @if($empresa?->ciudad) &nbsp;·&nbsp; {{ $empresa->ciudad }}@if($empresa->departamento), {{ $empresa->departamento }}@endif @endif
        </p>
        @if($tiene_sucursales === 'si')
          <p class="rr-sec-sub" style="margin-top:.35rem">{{ count($sucursales ?? []) }} sucursal(es) registrada(s)</p>
        @endif
      </div>

      {{-- Jornada --}}
      <div class="rr-sec-grid" style="border-bottom:1px solid rgba(255,255,255,.07)">
        <div class="rr-section" data-color style="--sc:#34d399">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#34d399" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="rr-sec-label">Jornada</p>
          </div>
          <p class="rr-sec-val {{ !$horario_entrada ? 'empty' : '' }}">
            {{ $horario_entrada ?: '—' }} → {{ $horario_salida ?: '—' }}
          </p>
          <p class="rr-sec-sub">
            Sáb: {{ $jornadaSabadoLabel }}
            &nbsp;·&nbsp; Dom: {{ $dominicalesLabels[$trabaja_dominicales ?? 'no'] ?? 'No' }}
          </p>
        </div>

        <div class="rr-section" data-color style="--sc:#f59e0b">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#f59e0b" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
            </svg>
            <p class="rr-sec-label">Pago</p>
          </div>
          <p class="rr-sec-val {{ !$forma_pago ? 'empty' : '' }}">
            {{ $formaPagoLabels[$forma_pago ?? ''] ?? ($forma_pago ?: '—') }}
          </p>
          <p class="rr-sec-sub">{{ $periodicidadLabels[$periodicidad_pago ?? ''] ?? ($periodicidad_pago ?: '') }}</p>
        </div>
      </div>

      {{-- Contratos + Control asistencia --}}
      <div class="rr-sec-grid" style="border-bottom:1px solid rgba(255,255,255,.07)">
        <div class="rr-section" data-color style="--sc:#a78bfa">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#a78bfa" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <p class="rr-sec-label">Tipos de contrato</p>
          </div>
          @if(!empty($tipos_contrato))
            @foreach($tipos_contrato as $tc)
              <span class="rr-tag">{{ $tiposContratoLabels[$tc] ?? $tc }}</span>
            @endforeach
          @else
            <p class="rr-sec-val empty">No indicado</p>
          @endif
        </div>

        <div class="rr-section" data-color style="--sc:#67e8f9">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#67e8f9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33"/>
            </svg>
            <p class="rr-sec-label">Control asistencia</p>
          </div>
          <p class="rr-sec-val {{ !$control_asistencia ? 'empty' : '' }}">
            {{ $controlLabels[$control_asistencia ?? ''] ?? ($control_asistencia ?: '—') }}
          </p>
        </div>
      </div>

      {{-- Faltas --}}
      <div class="rr-section" data-color style="--sc:#f87171;background:rgba(239,68,68,.03)">
        <div class="rr-sec-header">
          <svg class="rr-sec-ico" style="color:#f87171" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
          </svg>
          <p class="rr-sec-label">Régimen disciplinario</p>
        </div>
        <div>
          @if(!empty($faltas_leves))
            <p class="rr-sec-sub" style="margin-bottom:.25rem;color:#fbbf24">Leves</p>
            @foreach($faltas_leves as $f)<span class="rr-tag leve">{{ $f }}</span>@endforeach
          @endif
          @if(!empty($faltas_graves))
            <p class="rr-sec-sub" style="margin:.5rem 0 .25rem;color:#fb923c">Graves</p>
            @foreach($faltas_graves as $f)<span class="rr-tag grave">{{ $f }}</span>@endforeach
          @endif
          @if(!empty($faltas_muy_graves))
            <p class="rr-sec-sub" style="margin:.5rem 0 .25rem;color:#f87171">Muy graves</p>
            @foreach($faltas_muy_graves as $f)<span class="rr-tag muy-grave">{{ $f }}</span>@endforeach
          @endif
          @if(empty($faltas_leves) && empty($faltas_graves) && empty($faltas_muy_graves))
            <p class="rr-sec-val empty">Sin faltas registradas</p>
          @endif
        </div>
        @if(!empty($sanciones))
          <p class="rr-sec-sub" style="margin-top:.75rem;margin-bottom:.25rem">Sanciones habilitadas:</p>
          @foreach($sanciones as $s)<span class="rr-tag">{{ $sancionesLabels[$s] ?? $s }}</span>@endforeach
        @endif
      </div>

      {{-- SST --}}
      <div class="rr-sec-grid">
        <div class="rr-section" data-color style="--sc:#4ade80">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#4ade80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
            </svg>
            <p class="rr-sec-label">SG-SST</p>
          </div>
          <p class="rr-sec-val {{ !$tiene_sg_sst ? 'empty' : '' }}">
            {{ $sgSstLabels[$tiene_sg_sst ?? ''] ?? ($tiene_sg_sst ?: '—') }}
          </p>
        </div>

        <div class="rr-section" data-color style="--sc:#fb7185">
          <div class="rr-sec-header">
            <svg class="rr-sec-ico" style="color:#fb7185" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <p class="rr-sec-label">Riesgos principales</p>
          </div>
          @if(!empty($riesgos_principales))
            @foreach($riesgos_principales as $r)
              <span class="rr-tag">{{ $riesgosLabels[$r] ?? $r }}</span>
            @endforeach
          @else
            <p class="rr-sec-val empty">No indicados</p>
          @endif
        </div>
      </div>

    </div>{{-- /rr-doc --}}
  </div>

</div>
