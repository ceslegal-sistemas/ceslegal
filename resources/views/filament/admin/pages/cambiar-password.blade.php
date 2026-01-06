<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Actualizar Contraseña
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6">
                <x-filament::button type="submit">
                    Guardar Cambios
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
