{{--
    Card de vencimiento de contrato a término fijo - módulo "renovar/no
    renovar con alerta de 30 días" (Art. 46 CST). Solo informa; los botones
    "Sí, renovar"/"No renovar" viven en el header de la página (ver
    ViewSolicitudContrato::getHeaderActions()) para reusar las Actions de
    Filament tal cual, sin duplicar wiring en blade.
--}}
@include('filament.components.pinfo-styles')

@php
    $plazoService = app(\App\Services\PlazoContratoService::class);
    $dias = $plazoService->diasHastaVencimiento($record);
@endphp

@if ($record->renovado_automaticamente_en)
    <div class="pt-card" style="border-left-color:#f97316;">
        <p class="pt-title">Este contrato se renovó automáticamente</p>
        <p class="pt-body">
            No se gestionó a tiempo y, conforme al Art. 46 CST, la ley lo renovó sola.
            Nueva fecha de vencimiento: <strong>{{ $record->fecha_fin_contrato?->format('d/m/Y') }}</strong>.
        </p>
    </div>
@elseif ($record->requiere_revision_manual_renovacion)
    <div class="pt-card" style="border-left-color:#e11d48;">
        <p class="pt-title">Este contrato necesita su revisión urgente</p>
        <p class="pt-body">
            No se pudo renovar automáticamente porque superaría el límite legal de 4 años
            de un contrato a término fijo. Revíselo cuanto antes con su abogado.
        </p>
    </div>
@elseif ($record->decision_no_renovacion_en)
    <div class="pt-card" style="border-left-color:#64748b;">
        <p class="pt-title">Ya decidió no renovar este contrato</p>
        <p class="pt-body">
            Preaviso generado el {{ $record->decision_no_renovacion_en->format('d/m/Y') }}.
            @if ($record->ruta_preaviso)
                <a href="{{ route('solicitud-contrato.descargar-preaviso', $record) }}" target="_blank">Descargar Preaviso</a>
            @endif
        </p>
    </div>
@elseif ($plazoService->estaEnVentanaDeAlerta($record))
    <div class="pt-card" style="border-left-color:#e11d48;">
        <p class="pt-title">Este contrato está por vencer</p>
        <p class="pt-body">
            Vence el <strong>{{ $record->fecha_fin_contrato?->format('d/m/Y') }}</strong>
            (faltan {{ $dias }} día{{ $dias === 1 ? '' : 's' }}). ¿Desea renovarlo? Use los botones
            "Sí, renovar" o "No renovar" en la parte superior de esta página.
        </p>
    </div>
@endif
