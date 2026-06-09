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
    `rit-hide-wizard-steps`; el CSS se inyecta en el <head> desde un render hook en
    AdminPanelProvider (no como <style> inline, que rompe la raíz única de Livewire).
--}}
<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'rit-hide-wizard-steps',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    {{--
        Barra de progreso propia que reemplaza al stepper nativo (oculto por CSS).
        Lee el estado del wizard (Alpine sobre .fi-fo-wizard: step / getStepIndex /
        getSteps) y los nombres de paso desde el header nativo, que sigue en el DOM
        con display:none. Se sincroniza por sondeo ligero (200 ms) para reflejar la
        navegacion del wizard sin acoplarse a su vista interna.
    --}}
    <div
        x-data="{
            current: 0,
            total: 0,
            percent: 0,
            label: '',
            sync() {
                const w = document.querySelector('.fi-fo-wizard');
                if (! w || ! window.Alpine) return;
                let d;
                try { d = Alpine.$data(w); } catch (e) { return; }
                if (! d || d.step == null || typeof d.getSteps !== 'function') return;
                const steps = d.getSteps();
                const idx = d.getStepIndex(d.step);
                this.total = steps.length;
                this.current = idx + 1;
                this.percent = this.total ? Math.round((this.current / this.total) * 100) : 0;
                const labels = [...w.querySelectorAll('.fi-fo-wizard-header-step-label')].map(e => e.textContent.trim());
                this.label = labels[idx] ?? '';
            },
            init() {
                this.sync();
                this._timer = setInterval(() => this.sync(), 200);
            },
            destroy() {
                clearInterval(this._timer);
            },
        }"
        x-show="total > 0"
        x-cloak
        class="rit-wizard-progress mb-6"
    >
        <div class="mb-1.5 flex items-center justify-between gap-x-3 text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-200">
                Paso <span x-text="current"></span> de <span x-text="total"></span>
                <span x-show="label" class="text-gray-400 dark:text-gray-500">&middot;</span>
                <span x-text="label" class="text-primary-600 dark:text-primary-400"></span>
            </span>
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500" x-text="percent + '%'"></span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
            <div
                class="h-full rounded-full bg-primary-600 transition-all duration-300 ease-out dark:bg-primary-500"
                x-bind:style="`width: ${percent}%`"
            ></div>
        </div>
    </div>

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
