{{--
    Campo de solo lectura con botón "Copiar" - mismo patrón ya usado en
    rit-compartir-banner.blade.php (navigator.clipboard.writeText +
    .rit-btn-secondary), reutilizado aquí para la URL del webhook de
    WhatsApp. Requiere $texto.
--}}
@include('filament.components.lupe-hero-styles')

<div x-data="{ copiado: false }" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
    <input type="text" readonly value="{{ $texto }}" onclick="this.select()"
        style="flex:1;min-width:240px;font-family:ui-monospace,monospace;font-size:.8125rem;
               padding:.5rem .75rem;border-radius:.5rem;border:1px solid var(--esa-divider, rgba(255,255,255,.12));
               background:rgba(255,255,255,.04);color:inherit;">
    <button type="button" class="rit-btn rit-btn-secondary"
        x-on:click="navigator.clipboard.writeText('{{ $texto }}'); copiado = true; setTimeout(() => copiado = false, 2000)">
        <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
    </button>
</div>
