{{--
    Aviso de contratos por vencer en el Dashboard - mismo lenguaje visual
    (.rit-hero/.rit-badge/.rit-actions) que los Hero Widgets de Solicitud de
    Contrato/Otrosí, a pedido explícito del usuario ("que se vea mejor como
    un hero"). Reemplaza el .pt-card de texto plano que tenía antes.
--}}
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="margin-bottom:1.5rem">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <span class="rit-badge rit-badge-danger">
            <lord-icon src="https://cdn.lordicon.com/xjovhxra.json" trigger="loop" delay="800" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185" data-pt-icon
                data-pt-dark="primary:#fb7185,secondary:#fb7185"
                data-pt-light="primary:#e11d48,secondary:#e11d48"
                style="width:14px;height:14px;flex-shrink:0">
            </lord-icon>
            {{ $totalContratos === 1 ? 'Contrato por vencer' : "{$totalContratos} contratos por vencer" }}
        </span>

        <h1 class="rit-title">
            {{ $totalContratos === 1
                ? 'Tiene un contrato a término fijo por vencer'
                : "Tiene {$totalContratos} contratos a término fijo por vencer" }}
        </h1>
        <p class="rit-sub">
            {{ $totalContratos === 1 ? 'Está' : 'Están' }} dentro de los próximos 45 días. ¿Desea
            renovarlo{{ $totalContratos === 1 ? '' : 's' }} o generar el preaviso de no renovación?
            Recuerde: la ley exige avisar con al menos 30 días de anticipación si decide no renovar.
        </p>

        <div class="rit-actions">
            <a href="{{ \App\Filament\Admin\Resources\SolicitudContratoResource::getUrl('index') }}" class="rit-btn rit-btn-primary">
                <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                Revisar contratos
            </a>
        </div>
    </div>
</div>
