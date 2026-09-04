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

        // Contratos a término fijo por vencer (ventana de 45 días, ver
        // PlazoContratoService) sin decisión de renovación tomada -
        // ScopedToBufeteOrEmpresa ya filtra por empresa/bufete del usuario,
        // mismo criterio que $totalSugerencias arriba. Se exige además el
        // permiso real de "Gestión de Contratos" (view_any_solicitud::contrato)
        // para que el banner desaparezca si a un cliente se le quita ese
        // módulo por permisos, en vez de solo depender del rol.
        $totalContratosPorVencer = ($usuarioDashboard
            && in_array($usuarioDashboard->role, ['cliente', 'bufete'], true)
            && $usuarioDashboard->can('view_any_solicitud::contrato'))
            ? \App\Models\SolicitudContrato::where('tipo_contrato', 'Contrato a Término Fijo')
                ->whereNull('decision_no_renovacion_en')
                ->where('requiere_revision_manual_renovacion', false)
                ->whereBetween('fecha_fin_contrato', [now()->startOfDay(), now()->addDays(45)->endOfDay()])
                ->count()
            : 0;

        // Logros de "Plazos de Descargos Cumplidos" - solo 'cliente' (no
        // 'bufete', decisión explícita del usuario: el logro celebra la
        // gestión propia de la empresa, no el trabajo de la firma que
        // gestiona varias empresas).
        $estadoLogros = ($usuarioDashboard && $usuarioDashboard->role === 'cliente' && $empresaUsuario)
            ? app(\App\Services\LogroDescargosService::class)->estadoDashboard($empresaUsuario)
            : null;
    @endphp

    @if($sinRit)
        @include('filament.components.dashboard-no-rit-notice')
    @endif

    @if($totalSugerencias > 0)
        @include('filament.components.dashboard-sugerencias-rit-notice', ['totalSugerencias' => $totalSugerencias])
    @endif

    {{-- Pop-up insistente pedido por el jefe, además del banner de arriba
         (que se conserva sin cambios) - solo 'cliente' (no 'bufete', que
         gestiona el RIT de varias empresas y se vería bombardeado con un
         modal por cada una). No guarda "ya lo cerré" en ningún lado a
         propósito: debe reaparecer en cada carga del Dashboard mientras
         siga pendiente, decisión explícita del usuario ("insistente"). --}}
    @if($totalSugerencias > 0 && $usuarioDashboard && $usuarioDashboard->role === 'cliente')
        @include('filament.components.dashboard-sugerencias-rit-modal', ['totalSugerencias' => $totalSugerencias])
    @endif

    @if($totalContratosPorVencer > 0)
        @include('filament.components.dashboard-contratos-por-vencer-notice', ['totalContratos' => $totalContratosPorVencer])
    @endif

    @if($estadoLogros)
        @include('filament.components.dashboard-logro-descargos-notice', ['estadoLogros' => $estadoLogros])
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
