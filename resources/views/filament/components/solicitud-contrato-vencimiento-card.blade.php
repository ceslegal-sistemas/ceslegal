{{--
    Card de vencimiento de contrato a término fijo - módulo "renovar/no
    renovar con alerta de 45 días" (Art. 46 CST). Mismo lenguaje visual
    (.rit-hero) que los Hero Widgets de Solicitud de Contrato/Otrosí, a
    pedido explícito del usuario ("que se vea mejor como un hero").

    Los botones "Sí, renovar"/"No renovar" siguen siendo las Actions reales
    registradas en ViewSolicitudContrato::getHeaderActions() (mismo wiring,
    sin duplicar lógica) - acá solo se disparan con
    wire:click="mountAction(...)", que apunta al mismo componente Livewire
    porque este partial se incluye inline (no dentro de un @livewire()
    anidado).
--}}
@include('filament.components.lupe-hero-styles')

@php
    $plazoService = app(\App\Services\PlazoContratoService::class);
    $dias = $plazoService->diasHastaVencimiento($record);
@endphp

@if ($record->renovado_automaticamente_en)
    <div class="rit-hero" style="margin-bottom:1.5rem">
        <div class="rit-orb-b"></div>
        <div class="rit-orb-g"></div>
        <div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge" style="background:rgba(249,115,22,.13);border-color:rgba(249,115,22,.3);color:#fdba74">
                Renovado automáticamente
            </span>
            <h1 class="rit-title">Este contrato se renovó automáticamente</h1>
            <p class="rit-sub">
                No se gestionó a tiempo y, conforme al Art. 46 CST, la ley lo renovó sola.
                Nueva fecha de vencimiento: <strong>{{ $record->fecha_fin_contrato?->format('d/m/Y') }}</strong>.
            </p>
        </div>
    </div>
@elseif ($record->requiere_revision_manual_renovacion)
    <div class="rit-hero" style="margin-bottom:1.5rem">
        <div class="rit-orb-b"></div>
        <div class="rit-orb-g"></div>
        <div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-danger">Revisión urgente</span>
            <h1 class="rit-title">Este contrato necesita su revisión urgente</h1>
            <p class="rit-sub">
                No se pudo renovar automáticamente porque superaría el límite legal de 4 años
                de un contrato a término fijo. Revíselo cuanto antes con su abogado.
            </p>
        </div>
    </div>
@elseif ($record->decision_no_renovacion_en)
    <div class="rit-hero" style="margin-bottom:1.5rem">
        <div class="rit-orb-b"></div>
        <div class="rit-orb-g"></div>
        <div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-none">Decisión tomada</span>
            <h1 class="rit-title">Ya decidió no renovar este contrato</h1>
            <p class="rit-sub">Preaviso generado el {{ $record->decision_no_renovacion_en->format('d/m/Y') }}.</p>

            @if ($record->ruta_preaviso)
                <div class="rit-actions">
                    <a href="{{ route('solicitud-contrato.descargar-preaviso', $record) }}" target="_blank" class="rit-btn rit-btn-secondary">
                        <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Descargar Preaviso
                    </a>
                </div>
            @endif
        </div>
    </div>
@elseif ($plazoService->estaEnVentanaDeAlerta($record))
    <div class="rit-hero" style="margin-bottom:1.5rem">
        <div class="rit-orb-b"></div>
        <div class="rit-orb-g"></div>
        <div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-ia">Por vencer</span>
            <h1 class="rit-title">Este contrato está por vencer</h1>
            <p class="rit-sub">
                Vence el <strong>{{ $record->fecha_fin_contrato?->format('d/m/Y') }}</strong>
                (faltan {{ $dias }} día{{ $dias === 1 ? '' : 's' }}). ¿Desea renovarlo o generar el preaviso de no renovación?
            </p>

            <div class="rit-actions">
                <button type="button" wire:click="mountAction('solicitarCambio')" class="rit-btn rit-btn-success">
                    <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    Sí, renovar
                </button>
                <button type="button" wire:click="mountAction('noRenovarContrato')" class="rit-btn rit-btn-danger">
                    <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    No renovar
                </button>
            </div>
        </div>
    </div>
@endif
