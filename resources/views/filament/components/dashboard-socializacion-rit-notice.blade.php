{{--
    Tarjeta de progreso "100% aceptó el RIT" + banner de compartir el link,
    combinados en una sola tarjeta (pedido explícito del usuario,
    2026-09-23: "el de socializar rit también poner en el dashboard").
    Solo para rol 'cliente' (ver dashboard.blade.php), mismo criterio que
    la tarjeta de logros de Descargos.
--}}
@include('filament.components.lupe-hero-styles')

@if($estadoSocializacionRit['completo'])
    <div class="rit-hero" style="margin-bottom:1.5rem;padding:1.25rem 1.5rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-sub">
                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0">
                </lord-icon>
                Logro desbloqueado
            </span>
            <h1 class="rit-title">¡Todo tu equipo aceptó el Reglamento Interno!</h1>
            <p class="rit-sub">
                Los {{ $estadoSocializacionRit['total'] }} trabajadores de tu empresa ya conocen y aceptaron
                la versión vigente del Reglamento Interno de Trabajo.
            </p>
        </div>
    </div>
@else
    <div style="margin-bottom:1.5rem">
        @include('filament.components.rit-compartir-banner', ['empresa' => $empresaUsuario, 'posterUrl' => $posterUrlDashboard])

        <div class="rit-hero" style="margin-top:.75rem;padding:1rem 1.5rem;">
            <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <p class="text-sm font-semibold text-gray-900 m-0">
                    {{ $estadoSocializacionRit['aceptados'] }} de {{ $estadoSocializacionRit['total'] }} trabajadores han aceptado el Reglamento Interno vigente
                </p>
                <div style="margin-top:.5rem;height:8px;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;position:relative;z-index:2">
                    <div style="height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#86efac);width:{{ max(4, $estadoSocializacionRit['porcentaje']) }}%"></div>
                </div>
            </div>
        </div>
    </div>
@endif
