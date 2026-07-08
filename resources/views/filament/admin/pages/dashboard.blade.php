<x-filament-panels::page>

    @php
        $empresaUsuario = auth()->user()?->empresa;
        $sinRit = $empresaUsuario && !$empresaUsuario->reglamentoInterno;
    @endphp

    @if($sinRit)
        @include('filament.components.dashboard-no-rit-notice')
    @endif

    {{-- Guía "Tu proceso" a todo el ancho, fuera del grid de widgets (garantiza full-width). --}}
    @php $guiaUser = auth()->user(); @endphp
    @if($guiaUser && in_array($guiaUser->role, ['cliente', 'bufete'], true))
        <div style="margin-bottom:1.5rem">
            @livewire(\App\Filament\Admin\Widgets\ProcesoGuiaWidget::class)
        </div>
    @endif

    <x-filament-widgets::widgets
        :widgets="$this->getVisibleWidgets()"
        :columns="$this->getColumns()"
    />
</x-filament-panels::page>
