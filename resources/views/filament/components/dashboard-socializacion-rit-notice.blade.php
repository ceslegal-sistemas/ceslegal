{{--
    Tarjeta de progreso "100% aceptó el RIT" + banner de compartir el link,
    combinados en una sola tarjeta (pedido explícito del usuario,
    2026-09-23: "el de socializar rit también poner en el dashboard").
    Solo para rol 'cliente' (ver dashboard.blade.php), mismo criterio que
    la tarjeta de logros de Descargos.

    Layout en 2 columnas (pedido del usuario, 2026-09-30: "en dos columnas,
    en una 'Socializa el RIT' y la otra los detalles como en mi reglamento
    interno") - mismo patrón .rit-two-col y mismo desplegable "Ver
    detalle"/trabajador ya usado en mi-reglamento-interno.blade.php, para
    que ambas pantallas se vean consistentes. $detalleSocializacionRit lo
    calcula dashboard.blade.php vía LogroSocializacionRitService::detallePorTrabajador().
--}}
@include('filament.components.lupe-hero-styles')
<style>
.rit-two-col{display:grid;grid-template-columns:1fr;gap:1.25rem}
@media(min-width:768px){.rit-two-col{grid-template-columns:1fr 1fr}}
.rit-two-col .rit-hero{height:100%}
</style>

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
    <div style="margin-bottom:1.5rem" class="rit-two-col">
        @include('filament.components.rit-compartir-banner', [
            'empresa' => $empresaUsuario,
            'posterUrl' => $posterUrlDashboard,
            'reglamento' => $empresaUsuario->reglamentoInterno,
            'declararFechaUrl' => \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(panel: 'empresa'),
        ])

        <div class="rit-hero" style="padding:1rem 1.5rem;">
            <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <p class="text-sm font-semibold text-gray-900 m-0">
                    {{ $estadoSocializacionRit['aceptados'] }} de {{ $estadoSocializacionRit['total'] }} trabajadores han aceptado el Reglamento Interno vigente
                </p>
                <div style="margin-top:.5rem;height:8px;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;position:relative;z-index:2">
                    <div style="height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#86efac);width:{{ max(4, $estadoSocializacionRit['porcentaje']) }}%"></div>
                </div>

                @if(!empty($detalleSocializacionRit))
                    <div x-data="{ abierto: false }" style="margin-top:.65rem">
                        <button type="button" x-on:click="abierto = !abierto" style="display:flex;align-items:center;gap:.35rem;font-size:.75rem;font-weight:600;color:#57534e;background:none;border:none;cursor:pointer;padding:0">
                            <span x-text="abierto ? 'Ocultar detalle' : 'Ver detalle'"></span>
                            <svg x-bind:style="{ transform: abierto ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform .15s' }" style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="abierto" x-cloak style="margin-top:.5rem;display:flex;flex-direction:column;gap:.4rem;max-height:220px;overflow-y:auto">
                            @foreach(array_slice($detalleSocializacionRit, 0, 5) as $fila)
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.75rem">
                                    <span style="color:#44403c">{{ $fila['nombre'] }}</span>
                                    @if($fila['acepto'])
                                        <span style="font-weight:600;color:#166534">Aceptó</span>
                                    @else
                                        <span style="font-weight:600;color:#854d0e">Pendiente</span>
                                    @endif
                                </div>
                            @endforeach
                            <a href="{{ \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(panel: 'empresa') }}" style="text-align:left;font-size:.75rem;font-weight:600;color:#be123c;text-decoration:none;padding:.25rem 0">
                                Ver reporte completo →
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
