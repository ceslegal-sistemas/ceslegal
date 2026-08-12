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

            @include('filament.components.validaciones-v6-resumen', [
                'estado' => $validacionesV6Estado,
                'resultados' => $validacionesV6Resultados,
                'en' => $validacionesV6En,
                'puntosClave' => $validacionesV6PuntosClave,
                'onRiskOpen' => true,
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
                    <span>Debe reconocer los riesgos antes de continuar. Por favor, revise los resultados de las
                        validaciones y confirme que entiende los riesgos.</span>
                @endif
            </div>
        </div>
    @elseif ($paso === 2)
        <div>
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

            <div class="mt-4 flex items-center gap-3">
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
        </div>
    @endif
</div>
