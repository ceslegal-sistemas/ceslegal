<x-filament-panels::page>
@php
    $tieneAuditoria  = $auditoria && $auditoria->estado !== 'pendiente';
    $secciones       = $auditoria?->secciones ?? [];
    $numCompletadas  = count($secciones);
    $numTotal        = $this->getNumSecciones();
    $progreso        = $numTotal > 0 ? round($numCompletadas / $numTotal * 100) : 0;
    $titulos         = \App\Services\AuditoriaRITService::getTitulosSecciones();
    $score           = $auditoria?->score;
    $colorScore      = $auditoria?->color_score ?? 'danger';
    $scoreColor      = match($colorScore) {
        'success' => '#22c55e',
        'warning' => '#f59e0b',
        default   => '#ef4444',
    };
    $estadoMejora    = $auditoria?->estado_mejora ?? 'no_aplica';
    $mejorando       = $estadoMejora === 'procesando';
    $mejoraLista     = $estadoMejora === 'completado' && $ritMejorado;
    $mejoraFallo     = $estadoMejora === 'fallido';
    $numCorregidas   = $mejoraLista ? collect($secciones)->filter(fn($s) => ($s['score'] ?? 100) < 100)->count() : 0;

    // GAP
    $gapGenerando    = $gapReporte?->estaGenerando() ?? false;
    $gapListo        = $gapReporte?->estaListo() ?? false;
    $gapFallo        = $gapReporte?->falloGeneracion() ?? false;
    $mostrarBtnGap   = ($auditoria?->estado === 'completado')
                       && !$gapGenerando && !$gapListo;
@endphp

<style>
/* ── Heredados de mi-reglamento-interno ── */
.rit-hero{position:relative;overflow:hidden;border-radius:1.25rem;padding:2rem 1.75rem;background:linear-gradient(150deg,#060f22 0%,#0d1f3c 55%,#060e20 100%)}
html:not(.dark) .rit-hero{background:#fff;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 28px rgba(0,0,0,.08)}
.rit-orb-b{position:absolute;width:280px;height:280px;top:-80px;right:-60px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.45),transparent 70%);filter:blur(28px);pointer-events:none;animation:rit-fb 14s ease-in-out infinite}
.rit-orb-g{position:absolute;width:200px;height:200px;bottom:-60px;left:-40px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.2),transparent 70%);filter:blur(24px);pointer-events:none;animation:rit-fg 18s ease-in-out infinite}
@keyframes rit-fb{0%,100%{transform:translate(0,0)}40%{transform:translate(-18px,14px)}70%{transform:translate(12px,-10px)}}
@keyframes rit-fg{0%,100%{transform:translate(0,0)}35%{transform:translate(14px,-16px)}65%{transform:translate(-10px,8px)}}
html:not(.dark) .rit-orb-b{background:radial-gradient(circle,rgba(99,102,241,.15),transparent 70%)!important}
html:not(.dark) .rit-orb-g{background:radial-gradient(circle,rgba(201,168,76,.18),transparent 70%)!important}
.rit-overlay{position:absolute;inset:0;pointer-events:none;z-index:1;background:radial-gradient(ellipse 80% 90% at 50% 50%,rgba(3,8,20,.75) 0%,rgba(3,8,20,.4) 55%,transparent 100%)}
html:not(.dark) .rit-overlay{background:radial-gradient(ellipse 75% 85% at 50% 40%,rgba(255,255,255,.75) 0%,rgba(255,255,255,.35) 55%,transparent 100%)}
.rit-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.35rem .9rem;border-radius:2rem;border:1px solid}
.rit-badge-ia{background:rgba(99,102,241,.13);border-color:rgba(99,102,241,.3);color:#a5b4fc}
html:not(.dark) .rit-badge-ia{background:rgba(79,70,229,.08);border-color:rgba(79,70,229,.2);color:#4338ca}
.rit-badge-ok{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-badge-ok{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-badge-warn{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#fcd34d}
html:not(.dark) .rit-badge-warn{background:rgba(217,119,6,.08);border-color:rgba(217,119,6,.22);color:#92400e}
.rit-badge-none{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:#fca5a5}
html:not(.dark) .rit-badge-none{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:#991b1b}
.rit-title{font-size:1.25rem;font-weight:700;color:#f1f5f9;margin:.5rem 0 .25rem;letter-spacing:-.015em}
html:not(.dark) .rit-title{color:#0f172a}
.rit-sub{font-size:.8125rem;color:#94a3b8;margin:0}
html:not(.dark) .rit-sub{color:#475569}
.rit-actions{display:flex;flex-wrap:wrap;gap:.625rem;margin-top:1.25rem;position:relative;z-index:2}
.rit-btn{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.55rem 1.125rem;border-radius:.625rem;border:1px solid;cursor:pointer;text-decoration:none;transition:opacity .15s}
.rit-btn:hover{opacity:.85}
.rit-btn-primary{background:rgba(99,102,241,.18);border-color:rgba(99,102,241,.35);color:#c7d2fe}
html:not(.dark) .rit-btn-primary{background:rgba(79,70,229,.1);border-color:rgba(79,70,229,.25);color:#4338ca}
.rit-btn-secondary{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);color:#e2e8f0}
html:not(.dark) .rit-btn-secondary{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:#374151}
.rit-btn-success{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-btn-success{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden}
html:not(.dark) .rit-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.rit-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .rit-viewer-header{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.rit-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.rit-viewer-body{padding:1.5rem 1.75rem;background:rgba(0,0,0,.15)}
html:not(.dark) .rit-viewer-body{background:#fafafa}
.rit-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3.5rem 2rem;text-align:center}
.rit-empty-icon{width:56px;height:56px;border-radius:50%;background:rgba(99,102,241,.12);border:1.5px solid rgba(99,102,241,.25);display:flex;align-items:center;justify-content:center;margin-bottom:1rem}
.rit-empty-title{font-size:1.0625rem;font-weight:700;color:#f1f5f9;margin:0 0 .4rem}
html:not(.dark) .rit-empty-title{color:#0f172a}
.rit-empty-sub{font-size:.825rem;color:#64748b;margin:0 0 1.5rem;max-width:380px}

/* ── Específicos de auditoría ── */
.audit-progress-track{width:100%;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden;margin:.75rem 0}
html:not(.dark) .audit-progress-track{background:rgba(0,0,0,.08)}
.audit-progress-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#6366f1,#818cf8);transition:width .6s cubic-bezier(.4,0,.2,1)}
.audit-step{display:flex;align-items:center;gap:.625rem;padding:.3rem 0;border-radius:.5rem;transition:background .2s}
.audit-step-active{background:rgba(99,102,241,.08);padding-left:.5rem;padding-right:.5rem;margin-left:-.5rem;margin-right:-.5rem}
html:not(.dark) .audit-step-active{background:rgba(79,70,229,.06)}
.audit-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.audit-dot-done{background:#22c55e}
.audit-dot-pending{background:rgba(255,255,255,.18)}
html:not(.dark) .audit-dot-pending{background:rgba(0,0,0,.12)}
/* Spinner SVG para la sección activa */
.audit-spinner{width:14px;height:14px;flex-shrink:0;animation:aspin .8s linear infinite;color:#818cf8}
html:not(.dark) .audit-spinner{color:#4f46e5}
@keyframes aspin{to{transform:rotate(360deg)}}
.audit-step-active-label{color:#a5b4fc;font-weight:600}
html:not(.dark) .audit-step-active-label{color:#4338ca}
.audit-sec{border-radius:.875rem;padding:1.125rem 1.25rem;border-left:3px solid;margin-bottom:.625rem;background:rgba(255,255,255,.03)}
html:not(.dark) .audit-sec{background:#fff}
.audit-sec-ok{border-color:#22c55e}
.audit-sec-warn{border-color:#f59e0b}
.audit-sec-danger{border-color:#ef4444}
.audit-sec-title{font-size:.875rem;font-weight:600;color:#f1f5f9}
html:not(.dark) .audit-sec-title{color:#0f172a}
.audit-tag{display:inline-flex;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.2rem .6rem;border-radius:.375rem}
.audit-tag-ok{background:rgba(34,197,94,.13);color:#86efac}
html:not(.dark) .audit-tag-ok{background:rgba(22,163,74,.1);color:#166534}
.audit-tag-warn{background:rgba(245,158,11,.13);color:#fcd34d}
html:not(.dark) .audit-tag-warn{background:rgba(217,119,6,.1);color:#92400e}
.audit-tag-danger{background:rgba(239,68,68,.13);color:#fca5a5}
html:not(.dark) .audit-tag-danger{background:rgba(220,38,38,.1);color:#991b1b}
.audit-list-item{display:flex;gap:.5rem;font-size:.8rem;color:#94a3b8;line-height:1.5;margin:.2rem 0}
html:not(.dark) .audit-list-item{color:#475569}
.audit-art{font-size:.7rem;font-family:ui-monospace,monospace;padding:.15rem .5rem;border-radius:.3rem;background:rgba(99,102,241,.1);color:#a5b4fc;display:inline-block;margin:.125rem}
html:not(.dark) .audit-art{background:rgba(79,70,229,.08);color:#4338ca}
.audit-score-ring{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:800;border:5px solid;flex-shrink:0}
.audit-sub-label{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin-bottom:.375rem}
.audit-result-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin:0 0 .25rem}
html:not(.dark) .audit-result-title{color:#0f172a}

/* ── RIT Mejorado ── */
.mejora-shimmer{border-radius:1rem;overflow:hidden;border:1px solid rgba(99,102,241,.25);background:linear-gradient(135deg,rgba(99,102,241,.06) 0%,rgba(129,140,248,.04) 50%,rgba(99,102,241,.06) 100%);background-size:200% 200%;animation:mejora-shine 2.4s ease-in-out infinite}
html:not(.dark) .mejora-shimmer{border-color:rgba(79,70,229,.18);background:linear-gradient(135deg,rgba(79,70,229,.06) 0%,rgba(99,102,241,.03) 50%,rgba(79,70,229,.06) 100%);background-size:200% 200%}
@keyframes mejora-shine{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
.mejora-card{border-radius:1rem;overflow:hidden;border:1.5px solid rgba(99,102,241,.3);background:rgba(255,255,255,.03)}
html:not(.dark) .mejora-card{background:#fff;border-color:rgba(79,70,229,.2);box-shadow:0 2px 16px rgba(79,70,229,.08)}
.mejora-header{padding:1rem 1.25rem;background:linear-gradient(135deg,rgba(99,102,241,.12) 0%,rgba(79,70,229,.06) 100%);border-bottom:1px solid rgba(99,102,241,.15);display:flex;align-items:center;gap:.75rem}
html:not(.dark) .mejora-header{background:linear-gradient(135deg,rgba(79,70,229,.07) 0%,rgba(99,102,241,.03) 100%);border-bottom-color:rgba(79,70,229,.12)}
.mejora-badge{display:inline-flex;align-items:center;gap:.35rem;font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;padding:.3rem .75rem;border-radius:2rem}
.mejora-badge-version{background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.35);color:#a5b4fc}
html:not(.dark) .mejora-badge-version{background:rgba(79,70,229,.1);border-color:rgba(79,70,229,.25);color:#4338ca}
.mejora-badge-ok{background:rgba(34,197,94,.13);border:1px solid rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .mejora-badge-ok{background:rgba(22,163,74,.09);border-color:rgba(22,163,74,.22);color:#166534}
.mejora-download-btn{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.25rem;border-radius:.625rem;border:none;cursor:pointer;text-decoration:none;transition:all .2s;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;box-shadow:0 2px 8px rgba(99,102,241,.35)}
.mejora-download-btn:hover{opacity:.9;box-shadow:0 4px 12px rgba(99,102,241,.45);transform:translateY(-1px)}

/* ── Reporte GAP ── */
.gap-shimmer{border-radius:1rem;overflow:hidden;border:1px solid rgba(185,28,28,.22);background:linear-gradient(135deg,rgba(185,28,28,.06) 0%,rgba(239,68,68,.04) 50%,rgba(185,28,28,.06) 100%);background-size:200% 200%;animation:mejora-shine 2.4s ease-in-out infinite}
html:not(.dark) .gap-shimmer{border-color:rgba(185,28,28,.18);background:linear-gradient(135deg,rgba(185,28,28,.06) 0%,rgba(239,68,68,.03) 50%,rgba(185,28,28,.06) 100%);background-size:200% 200%}
.gap-card{border-radius:1rem;overflow:hidden;border:1.5px solid rgba(185,28,28,.28);background:rgba(255,255,255,.03)}
html:not(.dark) .gap-card{background:#fff;border-color:rgba(185,28,28,.2);box-shadow:0 2px 16px rgba(185,28,28,.07)}
.gap-header{padding:1rem 1.25rem;background:linear-gradient(135deg,rgba(185,28,28,.1) 0%,rgba(127,29,29,.06) 100%);border-bottom:1px solid rgba(185,28,28,.15);display:flex;align-items:center;gap:.75rem}
html:not(.dark) .gap-header{background:linear-gradient(135deg,rgba(185,28,28,.06) 0%,rgba(185,28,28,.03) 100%);border-bottom-color:rgba(185,28,28,.12)}
.gap-btn-gen{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.25rem;border-radius:.625rem;border:1px solid rgba(185,28,28,.3);cursor:pointer;text-decoration:none;transition:all .2s;background:rgba(185,28,28,.1);color:#fca5a5}
html:not(.dark) .gap-btn-gen{background:rgba(185,28,28,.07);border-color:rgba(185,28,28,.22);color:#b91c1c}
.gap-btn-gen:hover{opacity:.85}
.gap-btn-exec{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.25rem;border-radius:.625rem;border:none;cursor:pointer;text-decoration:none;transition:all .2s;background:linear-gradient(135deg,#b91c1c,#dc2626);color:#fff;box-shadow:0 2px 8px rgba(185,28,28,.3)}
.gap-btn-exec:hover{opacity:.9;box-shadow:0 4px 12px rgba(185,28,28,.4);transform:translateY(-1px)}
.gap-btn-tech{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.25rem;border-radius:.625rem;border:1px solid rgba(185,28,28,.3);cursor:pointer;text-decoration:none;transition:all .2s;background:rgba(185,28,28,.08);color:#fca5a5}
html:not(.dark) .gap-btn-tech{background:rgba(185,28,28,.06);border-color:rgba(185,28,28,.2);color:#b91c1c}
.gap-btn-tech:hover{opacity:.85}
</style>

<div style="display:flex;flex-direction:column;gap:1.25rem;max-width:900px;margin:0 auto">

  {{-- ── HERO ── --}}
  <div class="rit-hero">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

      {{-- Badge estado --}}
      @if($auditoria && $auditoria->estado === 'completado')
        @if(($score ?? 0) >= 80)
          <span class="rit-badge rit-badge-ok">
            <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Aprobado
          </span>
        @elseif(($score ?? 0) >= 65)
          <span class="rit-badge rit-badge-ok" style="opacity:.85">
            <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Aprobado con sugerencias
          </span>
        @elseif(($score ?? 0) >= 50)
          <span class="rit-badge rit-badge-warn">
            <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
            Con observaciones
          </span>
        @else
          <span class="rit-badge rit-badge-none">
            <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            Requiere revisión
          </span>
        @endif
      @elseif($procesando)
        <span class="rit-badge rit-badge-ia">
          <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
          Analizando con IA
        </span>
      @else
        <span class="rit-badge rit-badge-ia">
          <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
          Servicio de auditoría
        </span>
      @endif

      <h1 class="rit-title">Auditoría Legal del Reglamento Interno</h1>
      <p class="rit-sub">
        @if($empresa){{ $empresa->razon_social }} &nbsp;·&nbsp; @endif
        Revisión contra el CST, Ley 1010/2006, Ley 2365/2024 y la biblioteca jurídica actualizada
      </p>

      {{-- Acciones hero --}}
      <div class="rit-actions">
        @if($auditoria && $auditoria->estado === 'completado')
          <button wire:click="nuevaAuditoria" class="rit-btn rit-btn-primary">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            Nueva auditoría
          </button>
          <a href="{{ route('filament.admin.pages.mi-reglamento-interno') }}" class="rit-btn rit-btn-secondary">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
            Ver mi RIT
          </a>
        @elseif(!$procesando)
          @if(!$soloExternoPermitido)
            <button wire:click="iniciarAuditoria" wire:loading.attr="disabled" class="rit-btn rit-btn-primary">
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803M10.5 7.5v6m3-3h-6"/></svg>
              Auditar RIT
            </button>
          @else
            <button wire:click="iniciarAuditoria" wire:loading.attr="disabled" class="rit-btn rit-btn-primary">
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
              Auditar mi RIT
            </button>
          @endif
        @endif
      </div>

    </div>
  </div>

  {{-- ── EN PROCESO ── --}}
  @if($procesando && $auditoria)
  <div wire:poll.2000ms="refrescarEstado" class="rit-viewer"
       x-data="{ elapsed: 0, _t: null }"
       x-init="_t = setInterval(() => elapsed++, 1000)"
       x-destroy="clearInterval(_t)">
    <div class="rit-viewer-header">
      <span class="rit-viewer-label">Progreso del análisis</span>
      <span style="display:flex;align-items:center;gap:.75rem">
        <span style="font-size:.75rem;color:#64748b">{{ $numCompletadas }} / {{ $numTotal }} secciones</span>
        {{-- Tiempo transcurrido (Alpine) --}}
        <span style="font-size:.7rem;color:#475569;font-variant-numeric:tabular-nums"
              x-text="Math.floor(elapsed/60).toString().padStart(2,'0') + ':' + (elapsed%60).toString().padStart(2,'0')">
          00:00
        </span>
      </span>
    </div>
    <div class="rit-viewer-body">

      <div class="audit-progress-track">
        <div class="audit-progress-fill" style="width:{{ $progreso }}%"></div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 mt-3">
        @foreach($titulos as $clave => $titulo)
          @php
            $done   = isset($secciones[$clave]) && ($secciones[$clave]['calificacion'] ?? '') !== 'Error';
            $error  = isset($secciones[$clave]) && ($secciones[$clave]['calificacion'] ?? '') === 'Error';
            $keys   = array_keys($titulos);
            $idx    = array_search($clave, $keys);
            $active = !isset($secciones[$clave]) && $idx === $numCompletadas;
          @endphp
          <div class="audit-step {{ $active ? 'audit-step-active' : '' }}">
            @if($active)
              {{-- Spinner SVG para la sección que se está procesando ahora --}}
              <svg class="audit-spinner" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
              </svg>
            @elseif($done)
              <div class="audit-dot audit-dot-done"></div>
            @elseif($error)
              <div class="audit-dot" style="background:#f87171"></div>
            @else
              <div class="audit-dot audit-dot-pending"></div>
            @endif
            <span style="font-size:.8125rem;{{ $active ? '' : ($done ? 'color:#94a3b8' : 'color:#475569') }}"
                  class="{{ $active ? 'audit-step-active-label' : '' }}">
              {{ $titulo }}
              @if($done)   <span style="color:#22c55e;font-size:.7rem;margin-left:.25rem">✓</span>  @endif
              @if($error)  <span style="color:#f87171;font-size:.7rem;margin-left:.25rem">✗ reintentando</span> @endif
              @if($active) <span style="font-size:.7rem;color:#818cf8;margin-left:.35rem">analizando…</span> @endif
            </span>
          </div>
        @endforeach
      </div>

      <p style="font-size:.75rem;color:#64748b;margin-top:1rem;text-align:center">
        Revisando su reglamento contra la normativa vigente colombiana. Por favor espere…
      </p>
    </div>
  </div>
  @endif

  {{-- ── RESULTADO COMPLETADO ── --}}
  @if($auditoria && $auditoria->estado === 'completado')

    {{-- Score + resumen --}}
    <div class="rit-viewer">
      <div class="rit-viewer-header">
        <span class="rit-viewer-label">Resultado general</span>
        <span style="font-size:.75rem;color:#64748b">{{ $auditoria->updated_at->format('d/m/Y g:i A') }}</span>
      </div>
      <div class="rit-viewer-body">
        <div style="display:flex;align-items:flex-start;gap:1.5rem">
          <div class="audit-score-ring" style="border-color:{{ $scoreColor }};color:{{ $scoreColor }}">
            {{ $score }}
          </div>
          <div style="flex:1;min-width:0">
            <p class="audit-result-title">
              @if($score >= 80) Reglamento jurídicamente actualizado
              @elseif($score >= 65) Reglamento aprobado — con sugerencias de mejora
              @elseif($score >= 50) Reglamento con observaciones
              @else Reglamento requiere revisión urgente
              @endif
            </p>
            @if($auditoria->resumen_general)
              <p style="font-size:.8125rem;color:#64748b;line-height:1.7;white-space:pre-line">{{ $auditoria->resumen_general }}</p>
            @endif
            <p style="font-size:.75rem;color:#94a3b8;margin-top:.5rem">
              Fuente: {{ $auditoria->fuente === 'externo' ? 'Documento externo adjunto' : 'RIT generado en el sistema' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- Detalle por sección --}}
    <div class="rit-viewer">
      <div class="rit-viewer-header">
        <span class="rit-viewer-label">Detalle por sección</span>
        <span style="font-size:.75rem;color:#64748b">{{ $numCompletadas }} secciones revisadas</span>
      </div>
      <div class="rit-viewer-body">
        @foreach($secciones as $clave => $sec)
          @php
            $calif   = $sec['calificacion'] ?? 'Ausente';
            $secCls  = match($calif) { 'Completo' => 'audit-sec-ok', 'Parcial' => 'audit-sec-warn', default => 'audit-sec-danger' };
            $tagCls  = match($calif) { 'Completo' => 'audit-tag-ok', 'Parcial' => 'audit-tag-warn', default => 'audit-tag-danger' };
          @endphp
          <div class="audit-sec {{ $secCls }}">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">
              <span class="audit-sec-title">{{ $sec['titulo'] ?? $clave }}</span>
              <div style="display:flex;align-items:center;gap:.5rem">
                @if(!($sec['seccion_encontrada'] ?? true))
                  <span style="font-size:.7rem;color:#94a3b8;font-style:italic">No encontrado en el RIT</span>
                @endif
                <span style="font-size:.75rem;font-weight:700;color:{{ match($calif){ 'Completo'=>'#22c55e','Parcial'=>'#f59e0b',default=>'#ef4444' } }}">{{ $sec['score'] ?? 0 }}/100</span>
                <span class="audit-tag {{ $tagCls }}">{{ $calif }}</span>
              </div>
            </div>

            @if(!empty($sec['hallazgos']))
              <p class="audit-sub-label">Hallazgos</p>
              @foreach($sec['hallazgos'] as $h)
                <div class="audit-list-item"><span style="flex-shrink:0;color:#6366f1">›</span>{{ $h }}</div>
              @endforeach
            @endif

            @if(!empty($sec['recomendaciones']))
              <p class="audit-sub-label" style="margin-top:.625rem">Recomendaciones</p>
              @foreach($sec['recomendaciones'] as $r)
                <div class="audit-list-item"><span style="flex-shrink:0;color:#22c55e">→</span>{{ $r }}</div>
              @endforeach
            @endif

            @if(!empty($sec['articulos_referencia']))
              <div style="margin-top:.625rem">
                @foreach($sec['articulos_referencia'] as $art)
                  <span class="audit-art">{{ $art }}</span>
                @endforeach
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>

    {{-- ── RIT MEJORADO: EN PROCESO ── --}}
    @if($mejorando)
    <div wire:poll.2000ms="refrescarEstado" class="mejora-shimmer" style="padding:1.5rem 1.75rem">
      <div style="display:flex;align-items:center;gap:1rem">
        <div style="width:40px;height:40px;border-radius:50%;background:rgba(99,102,241,.15);border:1.5px solid rgba(99,102,241,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;animation:adot 1.4s ease-in-out infinite">
          <svg style="width:20px;height:20px;color:#a5b4fc" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
        </div>
        <div>
          <p style="font-size:.875rem;font-weight:700;color:#a5b4fc;margin:0 0 .2rem">
            Generando RIT Mejorado con IA...
          </p>
          <p style="font-size:.775rem;color:#64748b;margin:0;line-height:1.5">
            La IA está aplicando las correcciones de la auditoría y consultando la biblioteca jurídica para generar la versión mejorada del Reglamento Interno.
          </p>
        </div>
      </div>
    </div>
    @endif

    {{-- ── RIT MEJORADO: COMPLETADO ── --}}
    @if($mejoraLista)
    <div class="mejora-card">
      <div class="mejora-header">
        <div style="width:36px;height:36px;border-radius:.5rem;background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg style="width:18px;height:18px;color:#a5b4fc" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
        </div>
        <div style="flex:1;min-width:0">
          <p style="font-size:.875rem;font-weight:700;color:#f1f5f9;margin:0 0 .2rem">RIT Mejorado Generado</p>
          <p style="font-size:.75rem;color:#64748b;margin:0">Versión {{ $ritMejorado->version }} · {{ $ritMejorado->created_at->format('d/m/Y g:i A') }}</p>
        </div>
        <span class="mejora-badge mejora-badge-version">v{{ $ritMejorado->version }}</span>
      </div>
      <div style="padding:1.25rem 1.5rem">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
          @if($numCorregidas > 0)
            <span class="mejora-badge mejora-badge-ok">
              <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              {{ $numCorregidas }} sección{{ $numCorregidas === 1 ? '' : 'es' }} corregida{{ $numCorregidas === 1 ? '' : 's' }}
            </span>
          @endif
          <span class="mejora-badge mejora-badge-ok">
            <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            Correcciones aplicadas
          </span>
          <span class="mejora-badge mejora-badge-ok">
            <svg style="width:10px;height:10px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            Biblioteca jurídica consultada
          </span>
        </div>
        <p style="font-size:.8125rem;color:#64748b;line-height:1.6;margin-bottom:1.125rem">
          La IA aplicó las correcciones de la auditoría y consultó la biblioteca jurídica para generar
          esta versión mejorada del Reglamento Interno. Revise el documento antes de activarlo
          como versión vigente en el sistema.
        </p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          @if($ritMejorado->ruta_pdf)
            <button wire:click="downloadPDFMejorado" class="mejora-download-btn">
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
              Descargar PDF v{{ $ritMejorado->version }}
            </button>
          @else
            <span style="font-size:.8rem;color:#94a3b8;font-style:italic">PDF en generación...</span>
          @endif
        </div>
      </div>
    </div>
    @endif

    {{-- ── RIT MEJORADO: FALLÓ ── --}}
    @if($mejoraFallo)
    <div style="padding:1rem 1.25rem;border-radius:.875rem;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.06)">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
          <p style="font-size:.8125rem;font-weight:600;color:#f87171;margin:0 0 .3rem">No se pudo generar el RIT Mejorado</p>
          <p style="font-size:.775rem;color:#94a3b8;margin:0">El servicio de inteligencia artificial no está disponible en este momento. Por favor, reintenta en unos minutos.</p>
        </div>
        <button wire:click="reintentarMejora" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.775rem;font-weight:600;padding:.45rem .9rem;border-radius:.5rem;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.1);color:#f87171;cursor:pointer;white-space:nowrap;flex-shrink:0">
          <svg style="width:13px;height:13px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
          Reintentar mejora
        </button>
      </div>
    </div>
    @endif

    {{-- ── REPORTE GAP: BOTÓN GENERAR ── --}}
    @if($mostrarBtnGap)
    <div style="padding:1rem 1.25rem;border-radius:.875rem;border:1px solid rgba(185,28,28,.18);background:rgba(185,28,28,.05);display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
      <div style="flex:1;min-width:0">
        <p style="font-size:.875rem;font-weight:600;color:#fca5a5;margin:0 0 .2rem">
          @if($gapFallo) Reintentar generación de Reportes GAP @else Generar Reportes de Análisis GAP @endif
        </p>
        <p style="font-size:.775rem;color:#94a3b8;margin:0;line-height:1.5">
          @if($gapFallo)
            Ocurrió un error al generar los reportes. {{ $gapReporte?->mensaje_error ? 'Error: '.$gapReporte->mensaje_error : '' }}
          @else
            Genere los reportes ejecutivo y técnico de cumplimiento normativo en PDF, basados en los hallazgos de esta auditoría.
          @endif
        </p>
      </div>
      <button wire:click="generarReporteGAP" class="gap-btn-gen">
        <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        {{ $gapFallo ? 'Reintentar' : 'Generar Reportes GAP' }}
      </button>
    </div>
    @endif

    {{-- ── REPORTE GAP: EN PROCESO ── --}}
    @if($gapGenerando)
    <div wire:poll.2000ms="refrescarEstado" class="gap-shimmer" style="padding:1.5rem 1.75rem">
      <div style="display:flex;align-items:center;gap:1rem">
        <div style="width:40px;height:40px;border-radius:50%;background:rgba(185,28,28,.12);border:1.5px solid rgba(185,28,28,.28);display:flex;align-items:center;justify-content:center;flex-shrink:0;animation:adot 1.4s ease-in-out infinite">
          <svg style="width:20px;height:20px;color:#fca5a5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </div>
        <div>
          <p style="font-size:.875rem;font-weight:700;color:#fca5a5;margin:0 0 .2rem">Generando Reportes GAP...</p>
          <p style="font-size:.775rem;color:#64748b;margin:0;line-height:1.5">
            Los reportes ejecutivo y técnico de cumplimiento normativo están siendo generados.
          </p>
        </div>
      </div>
    </div>
    @endif

    {{-- ── REPORTE GAP: LISTO ── --}}
    @if($gapListo)
    <div class="gap-card">
      <div class="gap-header">
        <div style="width:36px;height:36px;border-radius:.5rem;background:rgba(185,28,28,.15);border:1px solid rgba(185,28,28,.28);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg style="width:18px;height:18px;color:#fca5a5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
        </div>
        <div style="flex:1;min-width:0">
          <p style="font-size:.875rem;font-weight:700;color:#f1f5f9;margin:0 0 .2rem">Reportes GAP Generados</p>
          <p style="font-size:.75rem;color:#64748b;margin:0">Score {{ $gapReporte->score_snapshot }}/100 · {{ $gapReporte->updated_at?->format('d/m/Y g:i A') }}</p>
        </div>
      </div>
      <div style="padding:1.25rem 1.5rem">
        <p style="font-size:.8125rem;color:#64748b;line-height:1.6;margin-bottom:1.125rem">
          Los reportes de análisis GAP están listos. El reporte ejecutivo resume las brechas y el plan de acciones.
          El técnico incluye además los hallazgos detallados con trazabilidad normativa para el equipo legal.
        </p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          <button wire:click="downloadGapEjecutivo" class="gap-btn-exec">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            PDF Ejecutivo
          </button>
          <button wire:click="downloadGapTecnico" class="gap-btn-tech">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            PDF Técnico
          </button>
        </div>
      </div>
    </div>
    @endif

  {{-- ── ERROR ── --}}
  @elseif($auditoria && $auditoria->estado === 'error')
  <div class="rit-viewer">
    <div class="rit-viewer-body">
      <div class="rit-empty">
        <div class="rit-empty-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.25)">
          <svg style="width:26px;height:26px;color:#f87171" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        </div>
        <p class="rit-empty-title">Error en la auditoría</p>
        <p class="rit-empty-sub">{{ $auditoria->mensaje_error }}</p>
        <button wire:click="nuevaAuditoria" class="rit-btn rit-btn-primary" style="font-size:.875rem;padding:.65rem 1.375rem">
          Intentar nuevamente
        </button>
      </div>
    </div>
  </div>

  {{-- ── FORMULARIO INICIAL ── --}}
  @elseif(!$procesando)
  <div class="rit-viewer">
    <div class="rit-viewer-header">
      <span class="rit-viewer-label">
        @if($soloExternoPermitido) Auditar documento externo
        @elseif($rit && !empty($rit->texto_completo)) Auditar RIT existente
        @else Subir documento
        @endif
      </span>
      @if($rit && !$soloExternoPermitido)
        <span style="font-size:.75rem;color:#64748b">Último RIT: {{ $rit->updated_at->format('d/m/Y') }}</span>
      @endif
    </div>
    <div class="rit-viewer-body">

      @if($soloExternoPermitido)
        {{-- Panel informativo: RIT fue construido por CES Legal --}}
        <div style="padding:1rem 1.25rem;border-radius:.875rem;border:1px solid rgba(245,158,11,.22);background:rgba(245,158,11,.06);margin-bottom:1.25rem">
          <div style="display:flex;align-items:flex-start;gap:.875rem">
            <div style="width:36px;height:36px;border-radius:50%;background:rgba(245,158,11,.12);border:1.5px solid rgba(245,158,11,.28);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:.125rem">
              <svg style="width:18px;height:18px;color:#fcd34d" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
              </svg>
            </div>
            <div style="flex:1;min-width:0">
              <p style="font-size:.875rem;font-weight:700;color:#fcd34d;margin:0 0 .375rem;line-height:1.4">
                Su Reglamento Interno fue elaborado por CES Legal
              </p>
              <p style="font-size:.8rem;color:#94a3b8;margin:0;line-height:1.65">
                El módulo de auditoría está diseñado para verificar <strong style="color:#cbd5e1">documentos propios de la empresa</strong>
                frente a la normativa colombiana vigente. Como su RIT fue construido directamente por CES Legal aplicando
                todos los estándares jurídicos, no es posible auditarlo a través de este módulo.<br><br>
                Si cuenta con un <strong style="color:#cbd5e1">Reglamento Interno previo o de otra fuente</strong> que desee
                verificar, adjúntelo a continuación y lo revisaremos sección por sección.
              </p>
            </div>
          </div>
        </div>
        <p style="font-size:.8125rem;color:#64748b;margin-bottom:1rem;line-height:1.6">
          Adjunte el documento a auditar (PDF o Word) y verificaremos su cumplimiento frente al CST,
          Ley 1010/2006, Ley 2365/2024 y la jurisprudencia de la biblioteca legal.
        </p>
      @elseif($rit && !empty($rit->texto_completo))
        <p style="font-size:.8125rem;color:#64748b;margin-bottom:1rem;line-height:1.6">
          El sistema auditará el Reglamento Interno almacenado y lo comparará sección por sección
          contra el Código Sustantivo del Trabajo, Ley 1010/2006, Ley 2365/2024 y la jurisprudencia
          de la biblioteca legal. También puede adjuntar un documento externo para auditarlo.
        </p>
      @else
        <p style="font-size:.8125rem;color:#64748b;margin-bottom:1rem;line-height:1.6">
          No hay un RIT en el sistema. Adjunte el Reglamento Interno de su empresa en formato
          PDF o Word y lo auditaremos contra la normativa colombiana vigente.
        </p>
      @endif

      <form wire:submit="iniciarAuditoria">
        {{ $this->form }}
        <div style="margin-top:1.25rem">
          <button type="submit" wire:loading.attr="disabled" class="rit-btn rit-btn-primary" style="font-size:.875rem;padding:.65rem 1.375rem">
            @if($soloExternoPermitido)
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
              <span wire:loading.remove>Auditar documento adjunto</span>
            @else
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803M10.5 7.5v6m3-3h-6"/></svg>
              <span wire:loading.remove>Iniciar Auditoría Legal</span>
            @endif
            <span wire:loading>Analizando RIT... puede tardar varios minutos</span>
          </button>
        </div>
      </form>

    </div>
  </div>
  @endif

</div>
</x-filament-panels::page>
