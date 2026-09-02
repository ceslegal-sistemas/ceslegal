<x-filament-panels::page>

    @php
        $empresaUsuario = auth()->user()?->empresa;
        $sinRit = $empresaUsuario && !$empresaUsuario->reglamentoInterno;

        // Pedido explícito del usuario: las sugerencias de actualización del
        // RIT (Plan B) dependían solo de la campana de notificaciones - en
        // producción se acumularon 14 sugerencias sin revisar en 14 empresas
        // porque nadie entra a "Mi Reglamento Interno" a buscarlas por su
        // cuenta. Este aviso aparece de una vez al entrar al Dashboard, sin
        // que el cliente tenga que abrir nada. Sin withoutGlobalScopes():
        // ScopedToBufeteOrEmpresa ya filtra exactamente como se necesita acá
        // (cliente -> su empresa; bufete -> su selector o todas las suyas).
        $usuarioDashboard = auth()->user();
        $totalSugerencias = ($usuarioDashboard && in_array($usuarioDashboard->role, ['cliente', 'bufete'], true))
            ? \App\Models\SugerenciaActualizacionRit::where('estado', 'pendiente')->count()
            : 0;
    @endphp

    @if($sinRit)
        @include('filament.components.dashboard-no-rit-notice')
    @endif

    @if($totalSugerencias > 0)
        @include('filament.components.dashboard-sugerencias-rit-notice', ['totalSugerencias' => $totalSugerencias])
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
