<x-filament-panels::page>

    @php
        $empresaUsuario = auth()->user()?->empresa;
        $sinRit = $empresaUsuario && !$empresaUsuario->reglamentoInterno;
    @endphp

    @if($sinRit)
        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-400 dark:border-amber-600 mb-6 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-amber-900 dark:text-amber-100">Su empresa no tiene Reglamento Interno de Trabajo activo</p>
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-0.5">
                        Sin RIT, solo puede aplicar <strong>terminación de contrato</strong> como medida disciplinaria (Art. 105 CST).
                        Con RIT puede aplicar llamados de atención y suspensiones.
                    </p>
                </div>
            </div>
            <a href="{{ route('filament.admin.pages.rit-builder') }}"
               class="flex-shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 text-white font-semibold text-sm hover:bg-amber-500 transition-colors shadow-sm whitespace-nowrap">
                <x-heroicon-o-document-plus class="w-4 h-4" />
                Construir RIT
            </a>
        </div>
    @endif

    <x-filament-widgets::widgets
        :widgets="$this->getVisibleWidgets()"
        :columns="$this->getColumns()"
    />
</x-filament-panels::page>
