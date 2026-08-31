{{--
    Botón "Completar con IA" del paso "Detalles del Cargo" - llena
    responsabilidades, objeto comercial y manual de funciones a la vez
    (mismo cargo/contexto, coherentes entre sí en una sola llamada).
    wire:click contra completarDetallesConIA() (trait
    CompletaDetallesCargoConIA, compartido por Create y Edit) - mismo
    patrón ya usado en solicitud-contrato-ia-botones.blade.php.

    Rediseñado con el mismo lenguaje visual de marca (.rit-*) que "Mi
    Reglamento Interno" - antes era un botón gris genérico de Filament sin
    ningún indicador de carga mientras Gemini responde (varios segundos).
    wire:loading + wire:target (sin Alpine): mismo patrón ya usado en
    auditar-rit.blade.php para acciones de IA con Livewire.

    UX (pedido explícito del usuario): evitar que el cliente escriba a mano
    en los 3 campos de abajo y LUEGO presione este botón sin saber que va a
    perder lo que ya escribió. El click pasa por Alpine (no wire:click
    directo) para revisar - vía $wire.get('data.<campo>'), mismo patrón ya
    usado en hechos-asistente.blade.php - si alguno de los 3 campos ya tiene
    contenido antes de llamar a completarDetallesConIA(); si es así, pide
    confirmación explícita ANTES de sobrescribir. Con los campos vacíos (el
    caso normal, primera vez) no interrumpe con nada.
--}}
@include('filament.components.lupe-hero-styles')

<div style="border-radius:.75rem;border:1.5px solid rgba(251,113,133,.4);background:rgba(251,113,133,.08);padding:1rem 1.125rem;position:relative">
    <span style="position:absolute;top:-.6rem;left:1rem;background:#fb7185;color:white;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.15rem .55rem;border-radius:999px">Paso recomendado</span>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:.25rem">
        <div style="display:flex;align-items:center;gap:.6rem">
            <lord-icon src="https://cdn.lordicon.com/exymduqj.json" trigger="loop" delay="1000" stroke="bold" colors="primary:#fb7185,secondary:#fb7185" style="width:28px;height:28px;flex-shrink:0"></lord-icon>
            <div>
                <p style="font-size:.825rem;font-weight:600;margin:0" class="text-stone-800 dark:text-stone-100">Complete esto primero con IA</p>
                <p style="font-size:.75rem;margin:0" class="text-stone-600 dark:text-stone-300">Genera responsabilidades, objeto comercial y manual de funciones a la vez. Después puede editar el texto libremente.</p>
            </div>
        </div>

        <button
            type="button"
            x-data
            x-on:click="
                const tieneTexto = (v) => !!(v && v.replace(/<[^>]*>/g, '').trim().length > 0);
                const yaEscribio = tieneTexto($wire.get('data.responsabilidades'))
                    || tieneTexto($wire.get('data.objeto_comercial'))
                    || tieneTexto($wire.get('data.manual_funciones'));
                if (yaEscribio && !confirm('Ya hay contenido escrito en Responsabilidades, Objeto Comercial o Manual de Funciones. Si continúa, la IA lo reemplazará por su propia redacción. ¿Desea continuar?')) {
                    return;
                }
                $wire.call('completarDetallesConIA');
            "
            wire:loading.attr="disabled"
            wire:target="completarDetallesConIA"
            class="rit-btn rit-btn-primary"
        >
            <svg wire:loading.remove wire:target="completarDetallesConIA" style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            <svg wire:loading wire:target="completarDetallesConIA" style="width:15px;height:15px;animation:rit-spin 1s linear infinite" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span wire:loading.remove wire:target="completarDetallesConIA">Completar con IA</span>
            <span wire:loading wire:target="completarDetallesConIA">Generando...</span>
        </button>
    </div>
</div>
