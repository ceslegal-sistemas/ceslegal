{{--
    Custom view for CreateReglamentoInterno.
    Identical to filament-panels::resources.pages.create-record but adds `novalidate`
    to the <form> element so Mac browsers never trigger native HTML5 validation,
    which throws "An invalid form control with name='' is not focusable" for hidden
    inputs created by Tom Select and conditional repeaters.
--}}
{{--
    Oculta el stepper nativo de Filament SOLO en este wizard (el RIT usa su propio
    encabezado de paso con barra de progreso). El alcance lo limita la clase
    `rit-hide-wizard-steps`, por lo que no afecta a otros wizards (p. ej. Procesos).
--}}
<style>
    .rit-hide-wizard-steps .fi-fo-wizard-header {
        display: none;
    }
</style>

<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'rit-hide-wizard-steps',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <x-filament-panels::form
        id="form"
        novalidate
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="create"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
