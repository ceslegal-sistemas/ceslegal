@php
    $p = $this->getProgreso();
@endphp

@if($p)
<div wire:poll.2000ms>
<style>
.pcs-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden;margin-bottom:1rem}
html:not(.dark) .pcs-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.pcs-vh{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .pcs-vh{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.pcs-vl{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.pcs-vb{padding:1.125rem 1.5rem}
.pcs-track{width:100%;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden}
html:not(.dark) .pcs-track{background:rgba(0,0,0,.08)}
.pcs-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#f97316,#fb7185);transition:width .6s cubic-bezier(.4,0,.2,1)}
.pcs-muted{font-size:.8rem;color:#94a3b8}
html:not(.dark) .pcs-muted{color:#475569}
.pcs-spin{width:14px;height:14px;flex-shrink:0;animation:pcsspin .8s linear infinite;color:#fb7185}
html:not(.dark) .pcs-spin{color:#e11d48}
@keyframes pcsspin{to{transform:rotate(360deg)}}
</style>

    @if($p['estado'] === 'procesando')
        <div class="pcs-viewer">
            <div class="pcs-vh">
                <span class="pcs-vl">Actualizando artículos legales</span>
                <span style="font-size:.75rem;color:#64748b">{{ $p['actual'] ?? 0 }} / {{ $p['total'] ?? '?' }}</span>
            </div>
            <div class="pcs-vb">
                <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem">
                    <svg class="pcs-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
                    <span class="pcs-muted" style="color:#fb7185;font-weight:600">{{ $p['mensaje'] ?? 'Procesando...' }}</span>
                </div>
                <div class="pcs-track">
                    <div class="pcs-fill" style="width:{{ ($p['total'] ?? 0) > 0 ? round(($p['actual'] ?? 0) / $p['total'] * 100) : 0 }}%"></div>
                </div>
            </div>
        </div>
    @elseif($p['estado'] === 'completado')
        <div class="pcs-viewer">
            <div class="pcs-vh"><span class="pcs-vl">Actualización de artículos legales</span></div>
            <div class="pcs-vb">
                <p class="pcs-muted" style="margin:0">
                    <span style="color:#22c55e;font-weight:600">Completada.</span>
                    {{ $p['ok'] ?? 0 }} importados, {{ $p['skip'] ?? 0 }} omitidos, {{ $p['errores'] ?? 0 }} errores.
                </p>
            </div>
        </div>
    @elseif($p['estado'] === 'error')
        <div class="pcs-viewer">
            <div class="pcs-vh"><span class="pcs-vl">Actualización de artículos legales</span></div>
            <div class="pcs-vb">
                <p class="pcs-muted" style="margin:0;color:#f87171">Error: {{ $p['mensaje'] ?? 'ocurrió un problema.' }}</p>
            </div>
        </div>
    @endif
</div>
@endif
