{{--
    Vista custom para EditModificacionContractual (mismo wizard que Crear) -
    mismo patrón que edit-solicitud-contrato.blade.php: oculta el stepper
    nativo de Filament (ces-hide-wizard-steps).
--}}
<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'ces-hide-wizard-steps',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <x-filament-panels::form
        id="form"
        novalidate
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
