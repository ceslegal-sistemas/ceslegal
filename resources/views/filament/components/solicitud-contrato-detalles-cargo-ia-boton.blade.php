{{--
    Botón "Completar con IA" del paso "Detalles del Cargo" - llena
    responsabilidades, objeto comercial y manual de funciones a la vez
    (mismo cargo/contexto, coherentes entre sí en una sola llamada).
    wire:click contra completarDetallesConIA() (trait
    CompletaDetallesCargoConIA, compartido por Create y Edit) - mismo
    patrón ya usado en solicitud-contrato-ia-botones.blade.php.
--}}
<div>
    <x-filament::button
        type="button"
        wire:click="completarDetallesConIA"
        icon="heroicon-o-sparkles"
        color="gray"
    >
        Completar con IA (responsabilidades, objeto comercial y manual de funciones)
    </x-filament::button>
</div>
