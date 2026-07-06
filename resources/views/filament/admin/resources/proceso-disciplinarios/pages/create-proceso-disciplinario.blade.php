{{--
    Vista custom para CreateProcesoDisciplinario (wizard de descargos).
    - Añade `novalidate` al <form> para que los navegadores Mac no disparen la
      validación HTML5 nativa sobre inputs ocultos (Tom Select, repeaters).
    - Oculta el stepper nativo de Filament SOLO en este wizard mediante la clase
      `ces-hide-wizard-steps` (el wizard usa su propio encabezado de paso con
      barra de progreso/%). El CSS se inyecta en el <head> desde un render hook en
      AdminPanelProvider (no como <style> inline, que rompe la raíz única Livewire).
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
