@php
    $record = $this->record;

    $estadoBadge = match ($record?->estado) {
        'otrosi_generado' => ['class' => 'rit-badge-sub', 'label' => 'Otrosí Generado'],
        'borrador' => ['class' => 'rit-badge-ia', 'label' => 'Borrador'],
        default => null,
    };

    $tipoLabel = $record ? (\App\Models\ModificacionContractual::TIPOS[$record->tipo_modificacion] ?? $record->tipo_modificacion) : null;

    $nombreTrabajador = $record?->solicitudContrato
        ? trim("{$record->solicitudContrato->trabajador_nombres} {$record->solicitudContrato->trabajador_apellidos}")
        : null;
@endphp

@include('filament.components.lupe-hero-styles')

<div class="rit-hero">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        @if($estadoBadge)
            <span class="rit-badge {{ $estadoBadge['class'] }}">
                <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                {{ $estadoBadge['label'] }}
            </span>
        @else
            <span class="rit-badge rit-badge-ia">
                <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                Nuevo Otrosí
            </span>
        @endif

        <h1 class="rit-title">
            {{ $tipoLabel ? "Cambio de {$tipoLabel}" : 'Nuevo Otrosí de Contrato' }}
        </h1>
        <p class="rit-sub">
            @if($record)
                {{ $nombreTrabajador }} &nbsp;·&nbsp; {{ $record->solicitudContrato?->codigo }} &nbsp;·&nbsp; {{ $record->empresa?->razon_social }}
            @else
                Seleccione el contrato y el cambio a aplicar. Al guardar, se genera el documento del otrosí
                y queda registrado en el historial de cambios del contrato.
            @endif
        </p>

        @if($record?->ruta_otrosi)
            <div class="rit-actions">
                <a href="{{ route('modificacion-contractual.descargar', $record) }}" target="_blank" class="rit-btn rit-btn-success">
                    <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Descargar Otrosí
                </a>
            </div>
        @endif
    </div>
</div>
