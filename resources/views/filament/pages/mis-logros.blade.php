<x-filament-panels::page>
@include('filament.components.lupe-hero-styles')

<style>
.ml-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem;margin-top:1.5rem}
.ml-card{position:relative;overflow:hidden;border-radius:1.25rem;padding:1.5rem 1.25rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem;background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%);border:1px solid rgba(255,255,255,.08)}
html:not(.dark) .ml-card{background:linear-gradient(150deg,#fff1f2 0%,#fff7ed 55%,#ffffff 100%);border-color:rgba(225,29,72,.14)}
.ml-card.ml-pendiente{opacity:.62;filter:grayscale(.4)}
.ml-medalla{width:76px;height:76px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(225,29,72,.14);border:2px solid rgba(225,29,72,.3)}
.ml-pendiente .ml-medalla{background:rgba(148,163,184,.14);border-color:rgba(148,163,184,.3)}
.ml-nombre{font-size:.9375rem;font-weight:700;color:#f1f5f9;margin:.25rem 0 0}
html:not(.dark) .ml-nombre{color:#1c1917}
.ml-descripcion{font-size:.75rem;color:#94a3b8;margin:0;line-height:1.5}
html:not(.dark) .ml-descripcion{color:#57534e}
.ml-fecha{font-size:.6875rem;font-weight:600;color:#86efac;text-transform:uppercase;letter-spacing:.06em;margin:0}
html:not(.dark) .ml-fecha{color:#166534}
.ml-track{width:100%;height:6px;border-radius:3px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:.25rem}
.ml-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#94a3b8,#cbd5e1)}
.ml-progreso-texto{font-size:.6875rem;color:#94a3b8;margin:0}
html:not(.dark) .ml-progreso-texto{color:#78716c}
.ml-compartir{display:flex;gap:.5rem;margin-top:.5rem}
</style>

<div class="rit-hero" style="padding:1.75rem 2rem;">
    <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-ia">Gamificación</span>
        <h1 class="rit-title">Mis Logros</h1>
        <p class="rit-sub">Estos son los logros de su empresa dentro de LUPE Legal - obtenidos y en camino.</p>
    </div>
</div>

<div class="ml-grid">
    @foreach($logros as $logro)
        <div class="ml-card {{ $logro['completado'] ? '' : 'ml-pendiente' }}">
            <div class="ml-medalla">
                @if($logro['imagen'])
                    <lord-icon
                        src="{{ $logro['imagen'] }}"
                        trigger="{{ $logro['completado'] ? 'loop' : 'hover' }}"
                        delay="1000"
                        stroke="bold"
                        colors="primary:{{ $logro['completado'] ? '#e11d48' : '#94a3b8' }},secondary:{{ $logro['completado'] ? '#e11d48' : '#94a3b8' }}"
                        style="width:40px;height:40px">
                    </lord-icon>
                @endif
            </div>

            <p class="ml-nombre">{{ $logro['nombre'] }}</p>
            <p class="ml-descripcion">{{ $logro['descripcion'] }}</p>

            @if($logro['completado'])
                <p class="ml-fecha">Obtenido el {{ $logro['fecha_obtenido']?->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>

                <div class="ml-compartir" x-data="{ texto: {{ \Illuminate\Support\Js::from('¡Nuestra empresa desbloqueó el logro "' . $logro['nombre'] . '" en LUPE Legal!') }}, copiado: false }">
                    <a class="rit-btn rit-btn-secondary" style="font-size:.75rem;padding:.4rem .75rem"
                       x-bind:href="'https://wa.me/?text=' + encodeURIComponent(texto)" target="_blank" rel="noopener">WhatsApp</a>
                    <button type="button" class="rit-btn rit-btn-secondary" style="font-size:.75rem;padding:.4rem .75rem"
                            x-on:click="navigator.clipboard.writeText(texto); copiado = true; setTimeout(() => copiado = false, 2000)">
                        <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
                    </button>
                </div>
            @else
                <div class="ml-track">
                    <div class="ml-fill" style="width:{{ max(4, $logro['progreso_porcentaje']) }}%"></div>
                </div>
                <p class="ml-progreso-texto">{{ $logro['progreso_texto'] }}</p>
            @endif
        </div>
    @endforeach
</div>
</x-filament-panels::page>
