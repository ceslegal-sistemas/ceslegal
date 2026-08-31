{{--
    Parametrizado vía @include con: $modelo (propiedad WithFileUploads),
    $label, $ayuda, $tiposAceptados (string para el atributo accept).
--}}
<div x-data="{ arrastrando: false }">
    <label class="text-sm font-medium">{{ $label }}</label>

    @if ($this->{$modelo})
        <div class="flex items-center justify-between rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 mt-1">
            <span class="text-sm truncate">{{ $this->{$modelo}->getClientOriginalName() }}</span>
            <button type="button" wire:click="$set('{{ $modelo }}', null)" class="text-xs text-danger-600 hover:underline">Quitar</button>
        </div>
    @else
        <div
            x-on:dragover.prevent="arrastrando = true"
            x-on:dragleave.prevent="arrastrando = false"
            x-on:drop.prevent="arrastrando = false; $refs.input_{{ $modelo }}.files = $event.dataTransfer.files; $refs.input_{{ $modelo }}.dispatchEvent(new Event('change'))"
            x-on:click="$refs.input_{{ $modelo }}.click()"
            :class="arrastrando ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/10' : 'border-gray-300 dark:border-gray-600'"
            class="mt-1 flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 cursor-pointer text-center"
        >
            <span class="text-sm text-gray-500">Arrastre un archivo aquí o haga clic para seleccionarlo</span>
            <input type="file" x-ref="input_{{ $modelo }}" wire:model="{{ $modelo }}" accept="{{ $tiposAceptados }}" class="hidden" />
        </div>
    @endif

    <p class="text-xs text-gray-500 mt-1">{{ $ayuda }}</p>
    @error($modelo) <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
</div>
