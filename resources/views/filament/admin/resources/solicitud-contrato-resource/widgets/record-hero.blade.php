@php
    $record = $this->record;

    $estadoBadge = match ($record?->estado) {
        'aprobado' => ['class' => 'rit-badge-sub', 'label' => 'Aprobado'],
        'rechazado' => ['class' => 'rit-badge-danger', 'label' => 'Rechazado'],
        'borrador' => ['class' => 'rit-badge-ia', 'label' => 'Borrador con IA'],
        default => null,
    };

    $nombreTrabajador = $record
        ? trim("{$record->trabajador_nombres} {$record->trabajador_apellidos}")
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
                Asistente con IA
            </span>
        @endif

        <h1 class="rit-title">
            {{ $nombreTrabajador ?: 'Nueva Solicitud de Contrato' }}
        </h1>
        <p class="rit-sub">
            @if($record)
                {{ $record->cargo_contrato }} &nbsp;·&nbsp; {{ $record->tipo_contrato }} &nbsp;·&nbsp; {{ $record->empresa?->razon_social }}
            @else
                Complete los pasos del asistente. Al guardar, la IA redacta el objeto jurídico y genera
                el borrador del contrato automáticamente - no necesita ningún paso adicional.
            @endif
        </p>

        @if($record?->ruta_contrato)
            <div class="rit-actions">
                <a href="{{ route('solicitud-contrato.descargar', $record) }}" target="_blank" class="rit-btn rit-btn-success">
                    <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Descargar Contrato
                </a>
            </div>
        @endif
    </div>
</div>
