<x-filament-panels::page>

    @php
        $empresaUsuario = auth()->user()?->empresa;
        $sinRit = $empresaUsuario && !$empresaUsuario->reglamentoInterno;
    @endphp

    @if($sinRit)
        @include('filament.components.dashboard-no-rit-notice')
    @endif

    <x-filament-widgets::widgets
        :widgets="$this->getVisibleWidgets()"
        :columns="$this->getColumns()"
    />
</x-filament-panels::page>
