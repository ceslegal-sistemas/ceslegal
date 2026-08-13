{{--
    Botón para volver del Paso 3 (Verificación del Autorizador) al wizard de
    Decisión. Solo cambia mountedTableActionsData.0.paso_actual - el
    View::make('livewire.emitir-sancion-pasos-wrapper') vuelve a ser visible
    (->visible() en ProcesoDisciplinarioResource.php) y EmitirSancionPasos se
    remonta abriendo directo en el Paso 2 con la decisión ya elegida (ver
    'decision' en el viewData de ese View::make y EmitirSancionPasos::mount()).
--}}
<div x-data class="-mt-2 mb-2">
    <x-filament::button
        type="button"
        color="gray"
        icon="heroicon-o-arrow-left"
        size="sm"
        x-on:click="$wire.$set('mountedTableActionsData.0.paso_actual', 1)"
    >
        Volver a Decisión
    </x-filament::button>
</div>
