{{--
    Parametrizado vía @include con: $modelo (propiedad Livewire destino),
    $valorInicial (valor actual de esa propiedad, resuelto en el paso que
    incluye este partial), $placeholder. PRIMER uso de contenteditable en
    el proyecto (0 precedentes, confirmado por grep) - wire:ignore es
    OBLIGATORIO: sin él, cualquier re-render de Livewire reemplaza el HTML
    del div con el valor del servidor, perdiendo cursor/foco/pila de
    deshacer del navegador. La sincronización es de una sola vía: el editor
    empuja su HTML al servidor con debounce; el servidor nunca vuelve a
    escribir en el div mientras se usa (mismo mecanismo ya verificado en
    webcam-autorizador.blade.php).
--}}
<div
    wire:ignore
    x-data="{
        actualizar() {
            $wire.set('{{ $modelo }}', this.$refs.editor.innerHTML, false);
        }
    }"
    class="rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden"
>
    <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-2 py-1">
        <button type="button" x-on:click="document.execCommand('bold'); actualizar()" class="px-2 py-1 text-sm font-bold rounded hover:bg-gray-200 dark:hover:bg-gray-700">B</button>
        <button type="button" x-on:click="document.execCommand('italic'); actualizar()" class="px-2 py-1 text-sm italic rounded hover:bg-gray-200 dark:hover:bg-gray-700">I</button>
        <button type="button" x-on:click="document.execCommand('insertUnorderedList'); actualizar()" class="px-2 py-1 text-sm rounded hover:bg-gray-200 dark:hover:bg-gray-700">&bull; Lista</button>
        <button type="button" x-on:click="document.execCommand('insertOrderedList'); actualizar()" class="px-2 py-1 text-sm rounded hover:bg-gray-200 dark:hover:bg-gray-700">1. Lista</button>
        <button type="button" x-on:click="document.execCommand('undo'); actualizar()" class="px-2 py-1 text-sm rounded hover:bg-gray-200 dark:hover:bg-gray-700">Deshacer</button>
        <button type="button" x-on:click="document.execCommand('redo'); actualizar()" class="px-2 py-1 text-sm rounded hover:bg-gray-200 dark:hover:bg-gray-700">Rehacer</button>
    </div>
    <div
        x-ref="editor"
        contenteditable="true"
        x-on:input.debounce.500ms="actualizar()"
        x-init="$refs.editor.innerHTML = @js($valorInicial ?? '')"
        data-placeholder="{{ $placeholder }}"
        class="min-h-[120px] p-3 text-sm focus:outline-none empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
    ></div>
</div>
