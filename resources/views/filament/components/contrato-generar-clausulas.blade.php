{{--
    Botón que dispara CreateContratoLaboral::generarClausulas() (método
    público real del componente, no una Filament Action embebida en el
    schema) - el patrón de Action anidada en un Wizard Step no es testeable
    de forma simple con Livewire::test() y no tiene precedente en este
    proyecto; este botón sí, es el mismo usado en mi-reglamento-interno.blade.php
    y EmitirSancionPasos (wire:click contra un método público directo).
--}}
<div>
    <x-filament::button
        type="button"
        wire:click="generarClausulas"
        icon="heroicon-o-sparkles"
    >
        {{ $contratoBorradorId ? 'Volver a generar' : 'Generar cláusulas con IA' }}
    </x-filament::button>
</div>
