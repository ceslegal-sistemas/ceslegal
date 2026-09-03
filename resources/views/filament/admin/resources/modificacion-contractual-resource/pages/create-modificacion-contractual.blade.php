{{--
    Vista custom para CreateModificacionContractual - mismo patrón que
    create-solicitud-contrato.blade.php: oculta el stepper nativo de Filament
    (ces-hide-wizard-steps) porque el wizard ya trae su propio step-header de
    marca (Paso X de 2). `novalidate` por Tom Select/repeaters en Mac.
--}}
<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'ces-hide-wizard-steps',
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
