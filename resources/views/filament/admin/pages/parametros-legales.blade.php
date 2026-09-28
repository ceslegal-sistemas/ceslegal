<x-filament-panels::page>

    <form wire:submit="guardar">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Guardar
            </x-filament::button>
        </div>
    </form>

</x-filament-panels::page>
