{{--
    Botones "Redactar Otrosí con IA" y "Generar Otrosí (PDF)" -
    wire:click contra métodos reales de EditModificacionContractual,
    mismo patrón ya usado en solicitud-contrato-ia-botones.blade.php.
--}}
<div class="flex flex-wrap gap-3">
    <x-filament::button
        type="button"
        wire:click="redactarOtrosiConIA"
        icon="heroicon-o-sparkles"
        color="gray"
    >
        Redactar Otrosí con IA
    </x-filament::button>

    <x-filament::button
        type="button"
        wire:click="generarOtrosiAction"
        icon="heroicon-o-document-arrow-down"
    >
        Generar Otrosí (PDF)
    </x-filament::button>
</div>
