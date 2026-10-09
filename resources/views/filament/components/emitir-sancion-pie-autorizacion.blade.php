{{--
    Botones del Paso 3 de "Emitir Sanción" (Verificación del Autorizador).
    - Cancelar: close() de Alpine, la misma función que usan los botones del wizard
      interno (definida en el x-data del modal).
    - Volver a Decisión: método de Livewire de la página (volverADecisionSancion).
    - Continuar: callMountedTableAction (->action() de emitir_sancion, con la validación del formulario);
      deshabilitado hasta que exista la selfie y mientras se envía.
--}}
<div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-4">
    <x-filament::button type="button" color="gray" x-on:click="close()" class="justify-center">
        Cancelar
    </x-filament::button>

    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-left"
        wire:click="volverADecisionSancion" class="justify-center">
        Volver a Decisión
    </x-filament::button>

    <x-filament::button type="button" color="primary" wire:click="callMountedTableAction"
        x-bind:disabled="!$wire.get('mountedTableActionsData.0.foto_autorizador_base64')"
        wire:loading.attr="disabled" wire:target="callMountedTableAction"
        class="justify-center">
        Continuar
    </x-filament::button>
</div>
