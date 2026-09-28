{{--
    Banner "Paso recomendado" para "¿Qué ocurrió?" (Crear Citación de
    Descargos) - mismo lenguaje visual que
    solicitud-contrato-detalles-cargo-ia-boton.blade.php (pedido explícito
    del usuario, 2026-09-28: "así como este campo de Paso recomendado es
    que quiero en qué ocurrió"). Reemplaza el hint action pequeño
    "Generar redacción con IA" que existía junto al label del Textarea -
    mismo método Livewire (generarRedaccion(), ya valida por su cuenta si
    el borrador es muy corto), solo cambia la presentación visual.
--}}
@include('filament.components.lupe-hero-styles')

<div style="border-radius:.75rem;border:1.5px solid rgba(251,113,133,.4);background:rgba(251,113,133,.08);padding:1rem 1.125rem;position:relative;margin-bottom:1rem">
    <span style="position:absolute;top:-.6rem;left:1rem;background:#fb7185;color:white;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.15rem .55rem;border-radius:999px">Paso recomendado</span>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:.25rem">
        <div style="display:flex;align-items:center;gap:.6rem">
            <lord-icon src="https://cdn.lordicon.com/exymduqj.json" trigger="loop" delay="1000" stroke="bold" colors="primary:#fb7185,secondary:#fb7185" style="width:28px;height:28px;flex-shrink:0"></lord-icon>
            <div>
                <p style="font-size:.825rem;font-weight:600;margin:0" class="text-stone-800 dark:text-stone-100">Escriba un texto breve de lo ocurrido y genere la redacción con IA</p>
                <p style="font-size:.75rem;margin:0" class="text-stone-600 dark:text-stone-300">La IA corrige el lenguaje, agrega presuntivo donde haga falta y redacta el texto profesional completo. Después puede editar el texto libremente.</p>
            </div>
        </div>

        <button
            type="button"
            wire:click="generarRedaccion"
            wire:loading.attr="disabled"
            wire:target="generarRedaccion"
            class="rit-btn rit-btn-primary"
        >
            <svg wire:loading.remove wire:target="generarRedaccion" style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            <svg wire:loading wire:target="generarRedaccion" style="width:15px;height:15px;animation:rit-spin 1s linear infinite" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span wire:loading.remove wire:target="generarRedaccion">Generar redacción con IA</span>
            <span wire:loading wire:target="generarRedaccion">Generando...</span>
        </button>
    </div>
</div>
