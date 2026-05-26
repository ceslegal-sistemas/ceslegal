<x-filament-panels::page>
@php
    $estaGenerando = $reglamento && $reglamento->estaGenerando();
    $tieneError    = $reglamento && $reglamento->tieneErrorGeneracion();
    $tiene = $reglamento && !empty($reglamento->texto_completo) && !$estaGenerando && !$tieneError;
    $eIA   = in_array($reglamento?->fuente, ['construido_ia', 'mejora_ia']);
    $fecha = $reglamento?->updated_at?->format('d/m/Y \a \l\a\s g:i A');
    $wizardUrl  = route('filament.admin.resources.reglamento-internos.create');
    $esAdmin = auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('abogado');
    $descargaUrl = $tiene && $eIA
        ? ($esAdmin && $empresa ? route('rit.descargar.admin', $empresa) : route('rit.descargar'))
        : null;
@endphp

{{-- Auto-refresh cada 15s mientras se está generando --}}
@if($estaGenerando)
<meta http-equiv="refresh" content="15">
@endif

<style>
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
.rit-badge-sub{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .rit-badge-sub{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.rit-badge-none{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.25);color:#94a3b8}
html:not(.dark) .rit-badge-none{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.2);color:#475569}
.rit-badge-gen{background:rgba(99,102,241,.13);border-color:rgba(99,102,241,.3);color:#a5b4fc}
html:not(.dark) .rit-badge-gen{background:rgba(79,70,229,.08);border-color:rgba(79,70,229,.2);color:#4338ca}
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
.rit-btn-danger{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:#fca5a5}
html:not(.dark) .rit-btn-danger{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:#b91c1c}
.rit-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden;margin-top:1.25rem}
html:not(.dark) .rit-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.rit-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .rit-viewer-header{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.rit-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.rit-viewer-body{max-height:65vh;overflow-y:auto;padding:1.5rem 1.75rem;background:rgba(0,0,0,.15)}
html:not(.dark) .rit-viewer-body{background:#fafafa}
.rit-text{white-space:normal;font-family:'Georgia','Times New Roman',serif;font-size:.875rem;line-height:1.9;color:#cbd5e1;word-break:break-word}
html:not(.dark) .rit-text{color:#1e293b}
.rit-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3.5rem 2rem;text-align:center}
.rit-empty-icon{width:56px;height:56px;border-radius:50%;background:rgba(99,102,241,.12);border:1.5px solid rgba(99,102,241,.25);display:flex;align-items:center;justify-content:center;margin-bottom:1rem}
.rit-empty-title{font-size:1.0625rem;font-weight:700;color:#f1f5f9;margin:0 0 .4rem}
html:not(.dark) .rit-empty-title{color:#0f172a}
.rit-empty-sub{font-size:.825rem;color:#64748b;margin:0 0 1.5rem;max-width:380px}
/* Shimmer generando */
@keyframes rit-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.rit-shimmer-line{border-radius:6px;background:linear-gradient(90deg,rgba(99,102,241,.08) 25%,rgba(99,102,241,.18) 50%,rgba(99,102,241,.08) 75%);background-size:200% 100%;animation:rit-shimmer 2.2s ease-in-out infinite}
html:not(.dark) .rit-shimmer-line{background:linear-gradient(90deg,rgba(99,102,241,.06) 25%,rgba(99,102,241,.14) 50%,rgba(99,102,241,.06) 75%);background-size:200% 100%;animation:rit-shimmer 2.2s ease-in-out infinite}
@keyframes rit-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
</style>

<div style="display:flex;flex-direction:column;gap:1.25rem;max-width:900px;margin:0 auto">

  {{-- ── HERO ── --}}
  <div class="rit-hero">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

      {{-- Badge fuente --}}
      @if($estaGenerando)
        <span class="rit-badge rit-badge-gen">
          <svg style="width:11px;height:11px;animation:rit-spin 1.2s linear infinite" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
          Generando con IA...
        </span>
      @elseif($tieneError)
        <span class="rit-badge" style="background:rgba(239,68,68,.13);border-color:rgba(239,68,68,.3);color:#fca5a5">
          <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
          Error de generación
        </span>
      @elseif($tiene && $eIA)
        <span class="rit-badge rit-badge-ia">
          <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
          Generado con IA
        </span>
      @elseif($tiene)
        <span class="rit-badge rit-badge-sub">
          <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
          Cargado manualmente
        </span>
      @else
        <span class="rit-badge rit-badge-none">Sin reglamento</span>
      @endif

      <h1 class="rit-title">
        {{ $empresa?->razon_social ?? 'Mi empresa' }}
      </h1>
      <p class="rit-sub">
        @if($estaGenerando)
          La IA está redactando su Reglamento Interno. Esta página se actualizará automáticamente.
        @elseif($tieneError)
          Ocurrió un error al generar el Reglamento Interno. Sus datos están guardados.
        @elseif($tiene)
          Reglamento Interno de Trabajo
          @if($fecha) &nbsp;·&nbsp; Actualizado el {{ $fecha }} @endif
        @else
          No tiene un Reglamento Interno de Trabajo activo.
        @endif
      </p>

      {{-- Acciones --}}
      <div class="rit-actions">
        @if($tieneError)
          <button wire:click="reintentarGeneracion" class="rit-btn rit-btn-primary">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            Reintentar generación
          </button>
          <a href="{{ $wizardUrl }}" class="rit-btn rit-btn-secondary">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
            Editar respuestas
          </a>
        @elseif(!$estaGenerando)
          @if($descargaUrl)
            <a href="{{ $descargaUrl }}" class="rit-btn rit-btn-success">
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
              Descargar PDF
            </a>
          @endif
          @if($tiene)
            <a href="{{ $wizardUrl }}" class="rit-btn rit-btn-primary">
              <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              Actualizar RIT con IA
            </a>
          @endif
        @endif
      </div>

    </div>
  </div>

  {{-- ── GENERANDO: shimmer ── --}}
  @if($estaGenerando)
    <div class="rit-viewer">
      <div class="rit-viewer-header">
        <span class="rit-viewer-label">Redactando documento</span>
        <span style="font-size:.75rem;color:#64748b;display:flex;align-items:center;gap:6px">
          <svg style="width:13px;height:13px;animation:rit-spin 1.2s linear infinite;color:#818cf8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
          En progreso...
        </span>
      </div>
      <div class="rit-viewer-body" style="padding:1.75rem 1.75rem">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="rit-shimmer-line" style="height:14px;width:65%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:100%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:95%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:88%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:100%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:72%"></div>
          <div style="height:12px"></div>
          <div class="rit-shimmer-line" style="height:14px;width:55%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:100%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:97%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:83%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:100%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:91%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:78%"></div>
          <div style="height:12px"></div>
          <div class="rit-shimmer-line" style="height:14px;width:60%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:100%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:94%"></div>
          <div class="rit-shimmer-line" style="height:10px;width:87%"></div>
        </div>
        <p style="margin-top:1.5rem;font-size:.8rem;color:#64748b;text-align:center">
          La IA está redactando su Reglamento Interno. Esta página se actualizará automáticamente cada 15 segundos.
        </p>
      </div>
    </div>

  {{-- ── ERROR ── --}}
  @elseif($tieneError)
    <div class="rit-viewer">
      <div class="rit-empty" style="padding:2.5rem 2rem">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,.12);border:1.5px solid rgba(239,68,68,.3);display:flex;align-items:center;justify-content:center;margin-bottom:1rem">
          <svg style="width:26px;height:26px;color:#f87171" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
          </svg>
        </div>
        <p class="rit-empty-title" style="color:#f87171">No se pudo generar el Reglamento</p>
        <p class="rit-empty-sub">
          Sus respuestas están guardadas. Puede reintentar la generación y la IA volverá a redactar su RIT.
          Si el error persiste, contacte a soporte.
        </p>
        <button wire:click="reintentarGeneracion"
                class="rit-btn rit-btn-primary"
                style="font-size:.875rem;padding:.65rem 1.375rem">
          <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
          Reintentar generación
        </button>
      </div>
    </div>

  {{-- ── VIEWER / EMPTY STATE ── --}}
  @elseif($tiene)
    <div class="rit-viewer">
      <div class="rit-viewer-header">
        <span class="rit-viewer-label">Texto del reglamento</span>
        <span style="font-size:.75rem;color:#64748b">
          {{ number_format(strlen($reglamento->texto_completo)) }} caracteres
        </span>
      </div>
      <div class="rit-viewer-body">
        <div class="rit-text">{!! preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<strong>$1</strong>', nl2br(e($reglamento->texto_completo))) !!}</div>
      </div>
    </div>
  @else
    <div class="rit-viewer">
      <div class="rit-empty">
        <div class="rit-empty-icon">
          <svg style="width:26px;height:26px;color:#818cf8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
        </div>
        <p class="rit-empty-title">Aún no tiene un Reglamento Interno</p>
        <p class="rit-empty-sub">
          Construya su RIT con IA en minutos. El sistema lo redactará con cumplimiento del
          Artículo 105 del CST y la Ley 2365/2024 de prevención de acoso sexual.
        </p>
        <a href="{{ $wizardUrl }}" class="rit-btn rit-btn-primary" style="font-size:.875rem;padding:.65rem 1.375rem">
          <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
          Construir Reglamento Interno con IA
        </a>
      </div>
    </div>
  @endif

</div>
</x-filament-panels::page>
