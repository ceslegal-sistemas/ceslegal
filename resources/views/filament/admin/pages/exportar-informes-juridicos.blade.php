<x-filament-panels::page>
    <form wire:submit.prevent class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-4">
            <x-filament::button
                type="button"
                wire:click="exportarPDF"
                wire:loading.attr="disabled"
                color="danger"
                icon="heroicon-o-document-text"
            >
                <span wire:loading.remove wire:target="exportarPDF">
                    Exportar PDF
                </span>
                <span wire:loading wire:target="exportarPDF">
                    Generando PDF...
                </span>
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="exportarExcel"
                wire:loading.attr="disabled"
                color="success"
                icon="heroicon-o-table-cells"
            >
                <span wire:loading.remove wire:target="exportarExcel">
                    Exportar Excel
                </span>
                <span wire:loading wire:target="exportarExcel">
                    Generando Excel...
                </span>
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                Instrucciones
            </div>
        </x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <ul class="space-y-2">
                <li><strong>Empresa:</strong> Seleccione la empresa para la cual desea generar el informe.</li>
                <li><strong>Año:</strong> Escoja el año del periodo a reportar.</li>
                <li><strong>Mes:</strong> Puede seleccionar un mes específico o "Todos los meses" para un informe anual completo.</li>
            </ul>
            <p class="mt-4 text-gray-500 dark:text-gray-400">
                El informe incluirá un resumen por área de práctica, tipo de gestión, estado y el detalle de cada gestión registrada.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
