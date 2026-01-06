<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" style="width:150px; margin-top: 1.5rem" wire:loading.attr="disabled">
            <x-filament::loading-indicator wire:loading class="h-5 w-5" /> Submit
        </x-filament::button>
    </form>
</x-filament-panels::page>
