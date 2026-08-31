{{--
    Parametrizado vía @include con: $modeloBusqueda (nombre de la propiedad
    wire:model del texto de búsqueda), $metodoBuscar (método Livewire a
    llamar), $resultados (array de ['id' => .., 'label' => ..]), $metodoSeleccionar
    (método Livewire a llamar con el id elegido), $placeholder, $valorActualLabel
    (texto a mostrar cuando ya hay algo seleccionado), $deshabilitado.
--}}
<div class="relative" x-data="{ abierto: false }">
    @if ($valorActualLabel)
        <div class="flex items-center justify-between rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-800">
            <span class="text-sm">{{ $valorActualLabel }}</span>
            @unless($deshabilitado ?? false)
                <button type="button" x-on:click="abierto = true; $wire.set('{{ $modeloBusqueda }}', '')" class="text-xs text-primary-600 hover:underline">Cambiar</button>
            @endunless
        </div>
    @endif

    <div x-show="abierto || !@js((bool) $valorActualLabel)" x-cloak>
        <input
            type="text"
            wire:model.live.debounce.300ms="{{ $modeloBusqueda }}"
            wire:keydown.escape="$set('{{ $modeloBusqueda }}', '')"
            placeholder="{{ $placeholder }}"
            class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm"
            autocomplete="off"
        />

        @if (count($resultados))
            <div class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg bg-white dark:bg-gray-800">
                @foreach ($resultados as $item)
                    <button
                        type="button"
                        wire:click="{{ $metodoSeleccionar }}({{ is_int($item['id']) ? $item['id'] : "'" . $item['id'] . "'" }})"
                        x-on:click="abierto = false"
                        class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
