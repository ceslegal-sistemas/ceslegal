{{--
    Vista custom para EditSolicitudContrato (mismo wizard que Crear) - mismo
    patrón que create-solicitud-contrato.blade.php:
    - Oculta el stepper nativo de Filament (ces-hide-wizard-steps) - el
      wizard ya trae su propio step-header de marca (Paso X de 5). Sin esto,
      "Editar" mostraba el stepper nativo DEMÁS del step-header, algo que
      "Crear" ya no tiene desde que se agregó esta misma vista ahí - bug
      real reportado por el usuario con captura.
    - `novalidate` en el <form> por la misma razón que en Crear (Tom Select/
      repeaters en navegadores Mac).
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
