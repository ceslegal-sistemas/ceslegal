{{--
    Botones "Redactar con IA" y "Generar Contrato (PDF)" de la sección
    Análisis Jurídico de SolicitudContrato - wire:click contra métodos
    públicos reales de EditSolicitudContrato (redactarObjetoConIA(),
    generarContratoAction()), mismo patrón ya usado en el resto del
    proyecto (mi-reglamento-interno.blade.php, EmitirSancionPasos), no una
    Filament Action anidada en el schema del form (no es testeable de
    forma simple con Livewire::test()).
--}}
<div class="flex flex-wrap gap-3">
    <x-filament::button
        type="button"
        wire:click="redactarObjetoConIA"
        icon="heroicon-o-sparkles"
        color="gray"
    >
        Redactar objeto jurídico con IA
    </x-filament::button>

    <x-filament::button
        type="button"
        wire:click="generarContratoAction"
        icon="heroicon-o-document-arrow-down"
    >
        Generar Contrato (PDF)
    </x-filament::button>
</div>
