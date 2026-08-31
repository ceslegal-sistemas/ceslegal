@php
    $conteos = $this->getConteos();
    $createUrl = \App\Filament\Admin\Pages\CrearSolicitudContrato::getUrl();
@endphp

@include('filament.components.lupe-hero-styles')

<div class="rit-hero">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-ia">
            <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            Borrador con IA
        </span>

        <h1 class="rit-title">Solicitudes de Contrato</h1>
        <p class="rit-sub">
            {{ $conteos['total'] }} en total
            &nbsp;·&nbsp; {{ $conteos['borrador'] }} en borrador
            &nbsp;·&nbsp; {{ $conteos['aprobado'] }} aprobadas
            &nbsp;·&nbsp; {{ $conteos['rechazado'] }} rechazadas
        </p>

        <div class="rit-actions">
            <a href="{{ $createUrl }}" class="rit-btn rit-btn-primary">
                <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Nueva Solicitud
            </a>
        </div>
    </div>
</div>
