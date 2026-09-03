{{--
    Vista custom de "Ver Otrosí" - mismo lenguaje visual (.rit-info-card/
    .rit-viewer) que Ver Solicitud de Contrato, en vez del formulario
    deshabilitado por defecto (que mostraba el Wizard crudo).
--}}
@php
    $tipoLabel = \App\Models\ModificacionContractual::TIPOS[$record->tipo_modificacion] ?? $record->tipo_modificacion;
    $estadoLabel = \App\Models\ModificacionContractual::ESTADOS[$record->estado] ?? $record->estado;
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.components.documento-viewer-styles')

    @include('filament.components.rit-info-card', [
        'icon' => 'https://cdn.lordicon.com/moedrfvp.json',
        'title' => 'Contrato',
        'rows' => [
            ['label' => 'Código', 'value' => $record->solicitudContrato?->codigo],
            ['label' => 'Trabajador', 'value' => trim("{$record->solicitudContrato?->trabajador_nombres} {$record->solicitudContrato?->trabajador_apellidos}")],
            ['label' => 'Empresa', 'value' => $record->empresa?->razon_social, 'full' => true],
        ],
    ])

    @include('filament.components.rit-info-card', [
        'icon' => 'https://cdn.lordicon.com/edcgvlnw.json',
        'title' => 'Cambio Propuesto',
        'rows' => [
            ['label' => 'Tipo de Modificación', 'value' => $tipoLabel],
            ['label' => 'Estado', 'value' => $estadoLabel],
            ['label' => 'Valor Anterior', 'value' => $record->valor_anterior],
            ['label' => 'Valor Nuevo', 'value' => $record->valor_nuevo],
            ['label' => 'Fecha Efectiva', 'value' => $record->fecha_efectiva?->format('d/m/Y')],
            ['label' => 'Justificación', 'value' => $record->justificacion, 'full' => true],
        ],
    ])

    <div class="rit-viewer">
        <div class="rit-viewer-header">
            <span class="rit-viewer-label">Texto del Otrosí</span>
            @if($record->texto_otrosi_redactado)
                <span style="font-size:.75rem;color:#64748b">{{ number_format(strlen(strip_tags($record->texto_otrosi_redactado))) }} caracteres</span>
            @endif
        </div>
        <div class="rit-viewer-body">
            @if($record->texto_otrosi_redactado)
                <div class="rit-text">{!! $record->texto_otrosi_redactado !!}</div>
            @else
                <div class="rit-empty">
                    <div class="rit-empty-icon">
                        <svg style="width:26px;height:26px;color:#fb7185" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <p class="rit-empty-title">Aún sin redactar</p>
                    <p class="rit-empty-sub">Use "Editar" para redactarlo y generar el documento del otrosí.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
