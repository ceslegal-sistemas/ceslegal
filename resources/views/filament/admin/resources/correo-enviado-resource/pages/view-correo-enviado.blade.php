<x-filament-panels::page>
@php
    $estado       = $correo->estado;
    $fueLeido     = $estado === 'leido';
    $fueEntregado = $estado === 'entregado';
    $esUrgente    = $correo->prioridad === 'urgente';
    $esAlta       = $correo->prioridad === 'alta';

    // Lordicon por estado (CDN oficial, free tier)
    $lordSrc = match ($estado) {
        'leido'     => 'https://cdn.lordicon.com/dicvhxpz.json',  // ojo
        'entregado' => 'https://cdn.lordicon.com/wlpxtupd.json',  // sobre abierto
        default     => 'https://cdn.lordicon.com/abgtphux.json',  // reloj / espera
    };
    $lordColors = match ($estado) {
        'leido'     => 'primary:#22c55e,secondary:#86efac',
        'entregado' => 'primary:#f59e0b,secondary:#fcd34d',
        default     => 'primary:#64748b,secondary:#94a3b8',
    };
    $estadoBadgeCls = match ($estado) {
        'leido'     => 'ce-badge-ok',
        'entregado' => 'ce-badge-warn',
        default     => 'ce-badge-none',
    };
@endphp

<script src="https://cdn.lordicon.com/lordicon.js"></script>

<style>
/* ── Base — hereda patrón rit-hero / rit-viewer ─────────────────────────── */
.ce-hero{position:relative;overflow:hidden;border-radius:1.25rem;padding:2rem 1.75rem;background:linear-gradient(150deg,#060f22 0%,#0d1f3c 55%,#060e20 100%)}
html:not(.dark) .ce-hero{background:#fff;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 28px rgba(0,0,0,.08)}
.ce-orb-b{position:absolute;width:260px;height:260px;top:-70px;right:-50px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.4),transparent 70%);filter:blur(28px);pointer-events:none;animation:ce-fb 14s ease-in-out infinite}
.ce-orb-g{position:absolute;width:180px;height:180px;bottom:-55px;left:-35px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.18),transparent 70%);filter:blur(24px);pointer-events:none;animation:ce-fg 18s ease-in-out infinite}
@keyframes ce-fb{0%,100%{transform:translate(0,0)}40%{transform:translate(-16px,12px)}70%{transform:translate(10px,-8px)}}
@keyframes ce-fg{0%,100%{transform:translate(0,0)}35%{transform:translate(12px,-14px)}65%{transform:translate(-8px,7px)}}
html:not(.dark) .ce-orb-b{background:radial-gradient(circle,rgba(99,102,241,.14),transparent 70%)!important}
html:not(.dark) .ce-orb-g{background:radial-gradient(circle,rgba(201,168,76,.16),transparent 70%)!important}
.ce-overlay{position:absolute;inset:0;pointer-events:none;z-index:1;background:radial-gradient(ellipse 80% 90% at 50% 50%,rgba(3,8,20,.72) 0%,rgba(3,8,20,.35) 55%,transparent 100%)}
html:not(.dark) .ce-overlay{background:radial-gradient(ellipse 75% 85% at 50% 40%,rgba(255,255,255,.72) 0%,rgba(255,255,255,.32) 55%,transparent 100%)}

/* ── Badges ─────────────────────────────────────────────────────────────── */
.ce-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.35rem .9rem;border-radius:2rem;border:1px solid}
.ce-badge-ok{background:rgba(34,197,94,.11);border-color:rgba(34,197,94,.28);color:#86efac}
html:not(.dark) .ce-badge-ok{background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22);color:#166534}
.ce-badge-warn{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#fcd34d}
html:not(.dark) .ce-badge-warn{background:rgba(217,119,6,.08);border-color:rgba(217,119,6,.22);color:#92400e}
.ce-badge-none{background:rgba(100,116,139,.1);border-color:rgba(100,116,139,.25);color:#94a3b8}
html:not(.dark) .ce-badge-none{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.18);color:#475569}
.ce-badge-danger{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:#fca5a5}
html:not(.dark) .ce-badge-danger{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:#991b1b}
.ce-badge-warning{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:#fcd34d}
html:not(.dark) .ce-badge-warning{background:rgba(217,119,6,.08);border-color:rgba(217,119,6,.22);color:#92400e}
.ce-badge-indigo{background:rgba(99,102,241,.12);border-color:rgba(99,102,241,.28);color:#a5b4fc}
html:not(.dark) .ce-badge-indigo{background:rgba(79,70,229,.08);border-color:rgba(79,70,229,.22);color:#4338ca}

/* ── Títulos hero ────────────────────────────────────────────────────────── */
.ce-title{font-size:1.2rem;font-weight:700;color:#f1f5f9;margin:.5rem 0 .2rem;letter-spacing:-.015em}
html:not(.dark) .ce-title{color:#0f172a}
.ce-sub{font-size:.8rem;color:#94a3b8;margin:0}
html:not(.dark) .ce-sub{color:#475569}

/* ── Viewer (card) ───────────────────────────────────────────────────────── */
.ce-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden}
html:not(.dark) .ce-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.ce-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .ce-viewer-header{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.ce-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.ce-viewer-body{padding:1.375rem 1.5rem;background:rgba(0,0,0,.12)}
html:not(.dark) .ce-viewer-body{background:#fafafa}

/* ── Bloque estado principal ─────────────────────────────────────────────── */
.ce-acuse-hero{display:flex;align-items:center;gap:1.25rem;padding:1.25rem 1.5rem;border-radius:.875rem;margin-bottom:.875rem;border:1px solid}
.ce-acuse-leido{background:rgba(34,197,94,.07);border-color:rgba(34,197,94,.22)}
html:not(.dark) .ce-acuse-leido{background:rgba(22,163,74,.05);border-color:rgba(22,163,74,.18)}
.ce-acuse-entregado{background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.22)}
html:not(.dark) .ce-acuse-entregado{background:rgba(217,119,6,.05);border-color:rgba(217,119,6,.18)}
.ce-acuse-pendiente{background:rgba(100,116,139,.07);border-color:rgba(100,116,139,.18)}
html:not(.dark) .ce-acuse-pendiente{background:rgba(100,116,139,.04);border-color:rgba(100,116,139,.14)}
.ce-acuse-estado{font-size:1.0625rem;font-weight:700;color:#f1f5f9;margin:0 0 .15rem}
html:not(.dark) .ce-acuse-estado{color:#0f172a}
.ce-acuse-sub{font-size:.8rem;color:#64748b;margin:0;line-height:1.55}

/* ── Grid de metadatos ───────────────────────────────────────────────────── */
.ce-meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.625rem;margin-top:.75rem}
.ce-meta-item{padding:.75rem 1rem;border-radius:.625rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)}
html:not(.dark) .ce-meta-item{background:#fff;border-color:rgba(0,0,0,.07);box-shadow:0 1px 4px rgba(0,0,0,.04)}
.ce-meta-label{font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin-bottom:.3rem}
.ce-meta-value{font-size:.8125rem;font-weight:600;color:#e2e8f0}
html:not(.dark) .ce-meta-value{color:#1e293b}
.ce-meta-sub{font-size:.75rem;color:#94a3b8;word-break:break-all;margin-top:.1rem}
html:not(.dark) .ce-meta-sub{color:#475569}

/* ── Cuerpo del correo ───────────────────────────────────────────────────── */
.ce-content-body{font-size:.875rem;line-height:1.75;color:#cbd5e1}
html:not(.dark) .ce-content-body{color:#334155}
.ce-content-body h2{font-size:1rem;font-weight:700;color:#f1f5f9;margin:.875rem 0 .375rem}
html:not(.dark) .ce-content-body h2{color:#0f172a}
.ce-content-body h3{font-size:.9375rem;font-weight:600;color:#e2e8f0;margin:.75rem 0 .3rem}
html:not(.dark) .ce-content-body h3{color:#1e293b}
.ce-content-body p{margin-bottom:.75rem}
.ce-content-body ul,.ce-content-body ol{padding-left:1.25rem;margin-bottom:.75rem}
.ce-content-body li{margin-bottom:.25rem}
.ce-content-body a{color:#818cf8;text-decoration:underline}
html:not(.dark) .ce-content-body a{color:#4338ca}
.ce-content-body strong{color:#f1f5f9;font-weight:600}
html:not(.dark) .ce-content-body strong{color:#0f172a}
.ce-content-body blockquote{border-left:3px solid rgba(99,102,241,.5);padding:.5rem 1rem;color:#94a3b8;background:rgba(99,102,241,.06);border-radius:0 .375rem .375rem 0;margin:.75rem 0}

/* ── Adjunto item ────────────────────────────────────────────────────────── */
.ce-adjunto{display:flex;align-items:center;gap:.625rem;padding:.625rem .875rem;border-radius:.5rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);font-size:.8125rem;color:#cbd5e1}
html:not(.dark) .ce-adjunto{background:#fff;border-color:rgba(0,0,0,.08);color:#334155}
</style>

<div style="display:flex;flex-direction:column;gap:1.25rem;max-width:900px;margin:0 auto">

  {{-- ── HERO ── --}}
  <div class="ce-hero">
    <div class="ce-orb-b"></div>
    <div class="ce-orb-g"></div>
    <div class="ce-overlay"></div>
    <div style="position:relative;z-index:2">

      {{-- Badges de estado, prioridad y proceso --}}
      <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.625rem">
        <span class="ce-badge {{ $estadoBadgeCls }}">
          {{ $correo->getLabelEstado() }}
        </span>
        @if($esUrgente)
          <span class="ce-badge ce-badge-danger">Urgente</span>
        @elseif($esAlta)
          <span class="ce-badge ce-badge-warning">Alta prioridad</span>
        @endif
        @if($correo->proceso)
          <span class="ce-badge ce-badge-indigo">
            Exp. {{ $correo->proceso->codigo ?? 'N/A' }}
          </span>
        @endif
      </div>

      <h1 class="ce-title">{{ $correo->asunto }}</h1>
      <p class="ce-sub">
        Para: {{ $correo->destinatario_nombre }}
        &nbsp;&middot;&nbsp;
        {{ $correo->email_destinatario }}
        @if($correo->enviado_en)
          &nbsp;&middot;&nbsp; Enviado {{ $correo->enviado_en->format('d/m/Y H:i') }}
        @endif
      </p>

    </div>
  </div>

  {{-- ── ACUSE DE RECIBO ── --}}
  <div class="ce-viewer">
    <div class="ce-viewer-header">
      <span class="ce-viewer-label">Acuse de recibo</span>
      @if($correo->abierto_en)
        <span style="font-size:.75rem;color:#64748b">Abierto {{ $correo->abierto_en->format('d/m/Y H:i') }}</span>
      @endif
    </div>
    <div class="ce-viewer-body">

      {{-- Estado principal con Lordicon animado en loop ─────────────── --}}
      <div class="ce-acuse-hero {{ $estado === 'leido' ? 'ce-acuse-leido' : ($estado === 'entregado' ? 'ce-acuse-entregado' : 'ce-acuse-pendiente') }}">
        <lord-icon
          src="{{ $lordSrc }}"
          trigger="loop"
          delay="1500"
          colors="{{ $lordColors }}"
          style="width:52px;height:52px;flex-shrink:0;">
        </lord-icon>
        <div>
          <p class="ce-acuse-estado">
            @if($estado === 'leido')
              Correo leído por el destinatario
            @elseif($estado === 'entregado')
              Correo entregado al servidor de correo
            @else
              Esperando confirmación de entrega
            @endif
          </p>
          <p class="ce-acuse-sub">
            @if($estado === 'leido')
              El trabajador abrió el correo {{ $correo->vecesLeidoReal() }} {{ $correo->vecesLeidoReal() === 1 ? 'vez' : 'veces' }}.
              @if($correo->tiempo_hasta_apertura)
                Tiempo hasta apertura: {{ $correo->tiempo_hasta_apertura }}.
              @endif
            @elseif($estado === 'entregado')
              El correo llegó al servidor de correo del destinatario. Aún no se ha confirmado que haya sido abierto.
            @else
              El correo fue programado para envío.
              @if(!$correo->enviado_en) El servidor SMTP aún no lo ha procesado. @endif
            @endif
          </p>
        </div>
      </div>

      {{-- Grid de metadatos ──────────────────────────────────────────── --}}
      <div class="ce-meta-grid">

        <div class="ce-meta-item">
          <p class="ce-meta-label">Enviado en</p>
          <p class="ce-meta-value">{{ $correo->enviado_en?->format('d/m/Y') ?? '—' }}</p>
          <p class="ce-meta-sub">{{ $correo->enviado_en?->format('H:i:s') ?? 'Pendiente de envío' }}</p>
        </div>

        <div class="ce-meta-item">
          <p class="ce-meta-label">Primera apertura</p>
          <p class="ce-meta-value">{{ $correo->abierto_en?->format('d/m/Y') ?? '—' }}</p>
          <p class="ce-meta-sub">{{ $correo->abierto_en?->format('H:i:s') ?? 'Sin apertura registrada' }}</p>
        </div>

        <div class="ce-meta-item">
          <p class="ce-meta-label">Veces abierto</p>
          <p class="ce-meta-value">{{ $correo->vecesLeidoReal() }}</p>
          <p class="ce-meta-sub">{{ $correo->veces_abierto }} pings totales (incl. precarga)</p>
        </div>

        @if($correo->ip_apertura)
        <div class="ce-meta-item">
          <p class="ce-meta-label">IP de apertura</p>
          <p class="ce-meta-value">{{ $correo->ip_apertura }}</p>
          <p class="ce-meta-sub">Última apertura registrada</p>
        </div>
        @endif

        @if($correo->user_agent)
        <div class="ce-meta-item">
          <p class="ce-meta-label">Cliente de correo</p>
          <p class="ce-meta-value">{{ $uaInfo['cliente'] }}</p>
          <p class="ce-meta-sub">{{ $uaInfo['os'] }} &middot; {{ $uaInfo['dispositivo'] }}</p>
        </div>
        @endif

        <div class="ce-meta-item">
          <p class="ce-meta-label">Remitente</p>
          <p class="ce-meta-value">{{ $correo->enviador?->name ?? '—' }}</p>
          <p class="ce-meta-sub">{{ $correo->created_at->format('d/m/Y H:i') }}</p>
        </div>

      </div>

      {{-- Con copia (CC) ─────────────────────────────────────────────── --}}
      @if(!empty($correo->email_cc))
      <div style="margin-top:.875rem;padding:.75rem 1rem;border-radius:.625rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07)">
        <p class="ce-meta-label" style="margin-bottom:.4rem">Con copia (CC)</p>
        <div style="display:flex;flex-wrap:wrap;gap:.375rem">
          @foreach($correo->email_cc as $cc)
            <span style="font-size:.775rem;padding:.2rem .65rem;border-radius:2rem;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#a5b4fc">{{ $cc }}</span>
          @endforeach
        </div>
      </div>
      @endif

    </div>
  </div>

  {{-- ── CONTENIDO DEL CORREO ── --}}
  <div class="ce-viewer">
    <div class="ce-viewer-header">
      <span class="ce-viewer-label">Contenido del correo</span>
      <span style="font-size:.75rem;color:#64748b">{{ Str::limit($correo->asunto, 60) }}</span>
    </div>
    <div class="ce-viewer-body">
      <div class="ce-content-body">
        {!! $correo->cuerpo !!}
      </div>
    </div>
  </div>

  {{-- ── ADJUNTOS ── --}}
  @if(!empty($adjuntos))
  <div class="ce-viewer">
    <div class="ce-viewer-header">
      <span class="ce-viewer-label">Archivos adjuntos</span>
      <span style="font-size:.75rem;color:#64748b">{{ count($adjuntos) }} archivo{{ count($adjuntos) === 1 ? '' : 's' }}</span>
    </div>
    <div class="ce-viewer-body">
      <div style="display:flex;flex-direction:column;gap:.5rem">
        @foreach($adjuntos as $nombre)
          <div class="ce-adjunto">
            <svg style="width:16px;height:16px;color:#64748b;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
            </svg>
            <span>{{ $nombre }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

</div>
</x-filament-panels::page>
