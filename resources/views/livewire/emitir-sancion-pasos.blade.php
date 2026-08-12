<div>
    @if ($paso === 1)
        <div>
            @if ($esFallback)
                @include('filament.components.emitir-sancion-ia-error')
            @else
                @include('filament.components.emitir-sancion-analisis', [
                    'analisis' => $analisis,
                    'recomendacion' => $recomendacionFinal,
                    'opcionesSancion' => $opcionesSancion,
                    'iaSancionesRecomendadas' => $iaSancionesRecomendadas,
                    'modoDecision' => false,
                ])
            @endif

            @if ($validacionesV6Estado === 'completado' && !$riskAcknowledged)
                <div class="esa-hint-callout">
                    <svg style="width:18px;height:18px;flex-shrink:0;color:#d97706;margin-top:1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span>Antes de continuar: abra el punto <strong>"Resistencia ante una revisión judicial"</strong> en la lista de abajo para marcarlo como revisado.</span>
                </div>
            @endif

            @include('filament.components.validaciones-v6-resumen', [
                'estado' => $validacionesV6Estado,
                'resultados' => $validacionesV6Resultados,
                'en' => $validacionesV6En,
                'puntosClave' => $validacionesV6PuntosClave,
                'onRiskOpen' => true,
                'riskAcknowledged' => $riskAcknowledged,
            ])

            <div class="mt-4">
                <x-filament::button
                    type="button"
                    wire:click="irAPaso2"
                    color="primary"
                    icon="heroicon-o-arrow-right"
                    icon-position="after"
                    class="w-full justify-center py-3 text-base"
                    :disabled="in_array($validacionesV6Estado, ['pendiente', 'procesando'], true) || ($validacionesV6Estado === 'completado' && !$riskAcknowledged)"
                >
                    Continuar a Decisión
                </x-filament::button>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                @if (in_array($validacionesV6Estado, ['pendiente', 'procesando'], true))
                    <span>Las validaciones de riesgo están en proceso. Por favor, espere a que se completen antes de
                        continuar.</span>
                @elseif ($validacionesV6Estado === 'completado' && !$riskAcknowledged)
                    <span>Debe reconocer los riesgos antes de continuar.</span>
                @endif
            </div>
        </div>
    @elseif ($paso === 2)
        <div>
            @if (!$decision)
                <div class="esa-hint-callout">
                    <svg style="width:18px;height:18px;flex-shrink:0;color:#d97706;margin-top:1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span>Seleccione una de las opciones de sanción abajo (haciendo clic en <strong>"Aplicar esta sanción"</strong> o una de las alternativas) para continuar.</span>
                </div>
            @endif

            @include('filament.components.emitir-sancion-analisis', [
                'analisis' => $analisis,
                'recomendacion' => $recomendacionFinal,
                'opcionesSancion' => $opcionesSancion,
                'iaSancionesRecomendadas' => $iaSancionesRecomendadas,
                'modoDecision' => true,
            ])

            @if (!empty($autoridadRit))
                @include('filament.components.emitir-sancion-potestad', [
                    'autoridadRit' => $autoridadRit,
                    'opcionesSancion' => $opcionesSancion,
                ])
            @endif

            @if ($decision && !empty($iaSancionesRecomendadas) && !in_array($decision, $iaSancionesRecomendadas))
                @include('filament.components.emitir-sancion-exoneracion-aviso', [
                    'tipoSeleccionado' => $decision,
                    'iaRazonesNoRecomendadas' => $iaRazonesNoRecomendadas,
                ])
                <textarea wire:model="razonDivergencia"
                    placeholder="Razón por la cual se elige esta sanción en lugar de las recomendadas por la IA"></textarea>
                <label>
                    <input type="checkbox" wire:model="exoneracionAceptada">
                    Confirmo que entiendo las recomendaciones jurídicas emitidas por la IA, que aun así decido aplicar
                    una sanción diferente, y que asumo completamente la responsabilidad jurídica, laboral y judicial de
                    esta decisión, exonerando a LUPE de cualquier consecuencia derivada de la misma.
                </label>
            @endif

            <div class="mt-6 flex items-center gap-4">
                <x-filament::button
                    type="button"
                    wire:click="irAPaso1"
                    color="gray"
                    icon="heroicon-o-arrow-left"
                    class="py-3"
                >
                    Volver
                </x-filament::button>

                <x-filament::button
                    type="button"
                    wire:click="confirmarDecision"
                    color="primary"
                    icon="heroicon-o-arrow-right"
                    icon-position="after"
                    class="flex-1 justify-center py-3 text-base"
                    :disabled="!$decision ||
                        (!empty($iaSancionesRecomendadas) &&
                            !in_array($decision, $iaSancionesRecomendadas) &&
                            (strlen(trim($razonDivergencia)) < 5 || !$exoneracionAceptada))"
                >
                    Continuar a Autorizar
                </x-filament::button>
            </div>
            <div class="mt-2 text-sm text-gray-500">
                @if (!$decision)
                    <span>Debe seleccionar una sanción antes de continuar.</span>
                @endif
            </div>
        </div>
    @endif

    <style>
    .esa-hint-callout {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0 0 10px;
        padding: 10px 14px;
        border-radius: .5rem;
        border: 1px solid rgba(217,119,6,.35);
        background: rgba(217,119,6,.08);
        font-size: 12.5px;
        line-height: 1.55;
        color: var(--esa-text, rgba(17,24,39,0.78));
    }
    html.dark .esa-hint-callout { background: rgba(217,119,6,.12); border-color: rgba(217,119,6,.4); }
    .esa-hint-callout strong { color: #b45309; }
    html.dark .esa-hint-callout strong { color: #fbbf24; }
    </style>
</div>
