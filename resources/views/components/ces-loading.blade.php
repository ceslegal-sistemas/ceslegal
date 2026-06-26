@props([
    'text' => 'Cargando',
    'size' => 150,
])

{{-- Loader de marca con Lordicon en loop + texto "Cargando", para páginas custom
     (no-resources) y estados async. El CSS (.ces-pl*) se inyecta globalmente desde
     AdminPanelProvider; lordicon.js ya está cargado en el <head> del panel.
     Uso típico con Livewire:
       <div wire:loading.flex wire:target="auditar"><x-ces-loading text="Analizando RIT" /></div> --}}
<div {{ $attributes->merge(['class' => 'ces-pl']) }} aria-busy="true" aria-live="polite">
    <lord-icon
        src="https://cdn.lordicon.com/fikcyfpp.json"
        trigger="loop"
        delay="500"
        colors="primary:#e11d48,secondary:#f97316"
        style="width:{{ (int) $size }}px;height:{{ (int) $size }}px">
    </lord-icon>
    <div class="ces-pl-txt">{{ $text }}</div>
</div>
