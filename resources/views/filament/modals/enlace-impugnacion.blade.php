@php
    $limiteTxt = $limite?->copy()->timezone('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
@endphp
@include('filament.components.lupe-hero-styles')
<div class="space-y-4" x-data="{ copiado: false }">
    <div class="rit-hero" style="padding:1.1rem 1.25rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-ia">Portal del trabajador</span>
            <h1 class="rit-title">{{ $trabajador }} impugna desde este enlace</h1>
            <p class="rit-sub">
                El trabajador verifica su identidad con un código a su correo ({{ $correo ?? 'sin correo registrado' }}),
                escribe sus motivos y adjunta pruebas. La impugnación queda registrada sola y usted recibe el aviso.
                @if ($limiteTxt) Plazo: hasta el <strong>{{ $limiteTxt }}</strong>. @endif
            </p>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <input type="text" readonly value="{{ $url }}" class="rit-link-input" x-ref="enlace" x-on:focus="$event.target.select()">
        <button type="button" class="rit-btn rit-btn-primary"
            x-on:click="navigator.clipboard.writeText($refs.enlace.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })">
            <span x-show="!copiado">Copiar enlace</span>
            <span x-show="copiado" style="display:none">¡Copiado!</span>
        </button>
    </div>

    <p style="font-size:.8rem;color:#6b7280;">
        Este mismo enlace va en el correo de la sanción (botón y enlace de respaldo). Use «Reenviar al trabajador» si no lo recibió o lo perdió.
        Si el trabajador impugna por carta física o de forma verbal, use «Registrar impugnación (otro medio)».
    </p>
</div>
