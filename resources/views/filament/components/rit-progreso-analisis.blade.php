{{--
    Panel compartido "Progreso del análisis" (auditoría del RIT en proceso) - usado por la
    vista unificada del cliente (rit-auditoria-panel.blade.php) y por la página admin
    (auditar-rit.blade.php), reemplazando el spinner+barra simple que tenía la vista del
    cliente por el mismo diseño rico de la vista admin (lista de secciones con su estado).

    Espera:
      $secciones  : array   - $auditoria->secciones (keyed por clave de sección)
      $numDone    : int     - secciones ya completadas
      $numTotal   : int     - total de secciones
      $titulos    : array   - App\Services\AuditoriaRITService::getTitulosSecciones()
      $pollMethod : string  - método Livewire a invocar cada 2s mientras esta tarjeta esté visible
--}}
@php
    $progreso = $numTotal > 0 ? round($numDone / $numTotal * 100) : 0;
@endphp
<style>
.gsec-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden}
html:not(.dark) .gsec-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.gsec-vh{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .gsec-vh{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.gsec-vl{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.gsec-vb{padding:1.25rem 1.5rem}
.gsec-track{width:100%;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden}
html:not(.dark) .gsec-track{background:rgba(0,0,0,.08)}
.gsec-fill{height:100%;border-radius:3px;transition:width .6s cubic-bezier(.4,0,.2,1)}
.gsec-step{display:flex;align-items:center;gap:.625rem;padding:.3rem 0;border-radius:.5rem;transition:background .2s}
.gsec-step-active{background:rgba(251,113,133,.08);padding-left:.5rem;padding-right:.5rem;margin-left:-.5rem;margin-right:-.5rem}
html:not(.dark) .gsec-step-active{background:rgba(225,29,72,.06)}
.gsec-step-active-label{color:#fb7185;font-weight:600}
html:not(.dark) .gsec-step-active-label{color:#be123c}
.gsec-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.gsec-dot-done{background:#22c55e}
.gsec-dot-pending{background:rgba(255,255,255,.18)}
html:not(.dark) .gsec-dot-pending{background:rgba(0,0,0,.12)}
.gsec-spinner{width:14px;height:14px;flex-shrink:0;animation:gsecspin .8s linear infinite;color:#fb7185}
html:not(.dark) .gsec-spinner{color:#e11d48}
@keyframes gsecspin{to{transform:rotate(360deg)}}
</style>

<div wire:poll.2000ms="{{ $pollMethod }}" class="gsec-viewer"
     x-data="{ elapsed: 0, _t: null }"
     x-init="_t = setInterval(() => elapsed++, 1000)"
     x-destroy="clearInterval(_t)">
    <div class="gsec-vh">
        <span class="gsec-vl">Progreso del análisis</span>
        <span style="display:flex;align-items:center;gap:.75rem">
            <span style="font-size:.75rem;color:#64748b">{{ $numDone }} / {{ $numTotal }} secciones</span>
            <span style="font-size:.7rem;color:#475569;font-variant-numeric:tabular-nums"
                  x-text="Math.floor(elapsed/60).toString().padStart(2,'0') + ':' + (elapsed%60).toString().padStart(2,'0')">
                00:00
            </span>
        </span>
    </div>
    <div class="gsec-vb">

        <div class="gsec-track">
            <div class="gsec-fill" style="width:{{ $progreso }}%;background:linear-gradient(90deg,#f97316,#fb7185)"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 mt-3">
            @foreach($titulos as $clave => $titulo)
                @php
                    $done   = isset($secciones[$clave]) && ($secciones[$clave]['calificacion'] ?? '') !== 'Error';
                    $error  = isset($secciones[$clave]) && ($secciones[$clave]['calificacion'] ?? '') === 'Error';
                    $keys   = array_keys($titulos);
                    $idx    = array_search($clave, $keys);
                    $active = !isset($secciones[$clave]) && $idx === $numDone;
                @endphp
                <div class="gsec-step {{ $active ? 'gsec-step-active' : '' }}">
                    @if($active)
                        <svg class="gsec-spinner" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
                        </svg>
                    @elseif($done)
                        <div class="gsec-dot gsec-dot-done"></div>
                    @elseif($error)
                        <div class="gsec-dot" style="background:#f87171"></div>
                    @else
                        <div class="gsec-dot gsec-dot-pending"></div>
                    @endif
                    <span style="font-size:.8125rem;{{ $active ? '' : ($done ? 'color:#94a3b8' : 'color:#475569') }}"
                          class="{{ $active ? 'gsec-step-active-label' : '' }}">
                        {{ $titulo }}
                        @if($done)   <span style="color:#22c55e;font-size:.7rem;margin-left:.25rem">✓</span>  @endif
                        @if($error)  <span style="color:#f87171;font-size:.7rem;margin-left:.25rem">✗ reintentando</span> @endif
                        @if($active) <span style="font-size:.7rem;color:#fb7185;margin-left:.35rem">analizando…</span> @endif
                    </span>
                </div>
            @endforeach
        </div>

        <p style="font-size:.75rem;color:#64748b;margin-top:1rem;text-align:center">
            Revisando su reglamento contra la normativa vigente colombiana. Por favor espere…
        </p>
    </div>
</div>
